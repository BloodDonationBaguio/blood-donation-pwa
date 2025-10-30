<?php
/**
 * Robust Blood Inventory Manager
 * Alternative approach that handles edge cases and provides fallbacks
 */

class BloodInventoryManagerRobust {
    private $pdo;
    private $debug = false;

    public function __construct($pdo, $debug = false) {
        $this->pdo = $pdo;
        $this->debug = $debug;
    }

    /**
     * Get dashboard summary with multiple fallback strategies
     */
    public function getDashboardSummary() {
        try {
            // Primary approach: Direct blood_inventory query
            $stmt = $this->pdo->query("
                SELECT 
                    COALESCE(COUNT(*), 0) AS total_units,
                    COALESCE(SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END), 0) AS available_units,
                    COALESCE(SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END), 0) AS expired_units,
                    COALESCE(SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END), 0) AS used_units,
                    COALESCE(SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END), 0) AS reserved_units
                FROM blood_inventory
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total_units'] > 0) {
                return $result;
            }

            // Fallback 1: Check if blood_inventory is empty but donors exist
            $donorCount = $this->getServedDonorCount();
            if ($donorCount > 0) {
                // Generate virtual inventory from served donors
                return [
                    'total_units' => $donorCount,
                    'available_units' => $donorCount,
                    'expired_units' => 0,
                    'used_units' => 0,
                    'reserved_units' => 0,
                    'virtual_from_donors' => true
                ];
            }

            // Fallback 2: Return zeros if no data
            return [
                'total_units' => 0,
                'available_units' => 0,
                'expired_units' => 0,
                'used_units' => 0,
                'reserved_units' => 0
            ];

        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Dashboard summary error: " . $e->getMessage());
            }
            return [
                'total_units' => 0,
                'available_units' => 0,
                'expired_units' => 0,
                'used_units' => 0,
                'reserved_units' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory with robust fallback strategies
     */
    public function getInventory($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;

            // Decide source based on the same count logic used elsewhere
            $count = (int)$this->getInventoryCount($filters);
            if ($count > 0) {
                $result = $this->getInventoryFromTable($filters, $page, $limit);
                // Ensure totals/pagination align exactly with count logic
                $result['total'] = $count;
                $result['total_pages'] = (int)ceil($count / $limit);
                return $result;
            }

            // Fallback: Generate from donors if blood_inventory is empty or filtered count is zero
            return $this->getInventoryFromDonors($filters, $page, $limit);

        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Inventory error: " . $e->getMessage());
            }
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory directly from blood_inventory table
     */
    private function getInventoryFromTable($filters, $page, $limit) {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        // Build filters
        if (!empty($filters['blood_type'])) {
            $whereConditions[] = "blood_type = ?";
            $params[] = $filters['blood_type'];
        }

        if (!empty($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereConditions[] = "(unit_id LIKE ? OR donor_id LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm]);
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Get count
        $countSql = "SELECT COUNT(*) as total FROM blood_inventory $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get data
        $sql = "
            SELECT 
                *,
                CASE 
                    WHEN expiry_date <= DATE_ADD(NOW(), INTERVAL 5 DAY) AND status = 'available' THEN 1 
                    ELSE 0 
                END as expiring_soon,
                'blood_inventory' as data_source
            FROM blood_inventory 
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrich with donor data if available
        foreach ($data as &$unit) {
            if ($unit['donor_id']) {
                $donorInfo = $this->getDonorInfo($unit['donor_id']);
                $unit = array_merge($unit, $donorInfo);
            }
        }

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
            'source' => 'blood_inventory'
        ];
    }

    /**
     * Generate inventory from donors (fallback)
     */
    private function getInventoryFromDonors($filters, $page, $limit) {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        // Determine donor table
        $donorTable = $this->getDonorTable();
        
        // Base condition for served/completed donors (support both tables uniformly)
        $statusCondition = "status IN ('served', 'completed')";
        
        $whereConditions[] = $statusCondition;

        // If a unit status filter is provided and it is NOT 'available', donor fallback cannot satisfy it
        if (!empty($filters['status']) && strtolower($filters['status']) !== 'available') {
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => 0,
                'source' => 'virtual_from_donors'
            ];
        }

        // Apply filters
        if (!empty($filters['blood_type'])) {
            $whereConditions[] = "blood_type = ?";
            $params[] = $filters['blood_type'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR reference_code LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        // Get count
        $countSql = "SELECT COUNT(*) as total FROM $donorTable $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get data
        $sql = "
            SELECT 
                id as donor_id,
                CONCAT('VIRT-', id) as unit_id,
                blood_type,
                'available' as status,
                first_name,
                last_name,
                reference_code,
                CONCAT(first_name, ' ', last_name) as donor_name,
                created_at,
                DATE_ADD(created_at, INTERVAL 35 DAY) as expiry_date,
                0 as expiring_soon,
                'virtual_from_donors' as data_source
            FROM $donorTable 
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
            'source' => 'virtual_from_donors'
        ];
    }

    /**
     * Get donor information
     */
    private function getDonorInfo($donorId) {
        $donorTable = $this->getDonorTable();
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT first_name, last_name, reference_code, blood_type as donor_blood_type,
                       CONCAT(first_name, ' ', last_name) as donor_name
                FROM $donorTable 
                WHERE id = ?
            ");
            $stmt->execute([$donorId]);
            $donor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $donor ?: [
                'first_name' => 'Unknown',
                'last_name' => 'Donor',
                'reference_code' => 'N/A',
                'donor_blood_type' => 'Unknown',
                'donor_name' => 'Unknown Donor'
            ];
        } catch (Exception $e) {
            return [
                'first_name' => 'Error',
                'last_name' => 'Loading',
                'reference_code' => 'ERR',
                'donor_blood_type' => 'Unknown',
                'donor_name' => 'Error Loading'
            ];
        }
    }

    /**
     * Get count of served donors
     */
    private function getServedDonorCount() {
        $donorTable = $this->getDonorTable();
        $statusCondition = ($donorTable === 'donors_new') 
            ? "status IN ('served', 'completed')" 
            : "status = 'served'";

        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM $donorTable WHERE $statusCondition");
            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Determine which donor table to use
     */
    private function getDonorTable() {
        try {
            // Check if donors_new exists and has data
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM donors_new");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($count > 0) {
                return 'donors_new';
            }
        } catch (Exception $e) {
            // donors_new doesn't exist, fall back to donors
        }
        
        return 'donors';
    }

    /**
     * Get inventory count
     */
    public function getInventoryCount($filters = []) {
        try {
            // Try blood_inventory first
            $whereConditions = [];
            $params = [];

            if (!empty($filters['blood_type'])) {
                $whereConditions[] = "blood_type = ?";
                $params[] = $filters['blood_type'];
            }

            if (!empty($filters['status'])) {
                $whereConditions[] = "status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $whereConditions[] = "(unit_id LIKE ? OR donor_id LIKE ?)";
                $params = array_merge($params, [$searchTerm, $searchTerm]);
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM blood_inventory $whereClause");
            $stmt->execute($params);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($count > 0) {
                return $count;
            }

            // Fallback to donor count with same filter semantics as donor-derived inventory
            $donorTable = $this->getDonorTable();

            // Donor-based units are treated as 'available' only. If user filters another status, return 0
            if (!empty($filters['status']) && strtolower($filters['status']) !== 'available') {
                return 0;
            }

            $dWhere = ["status IN ('served','completed')"];
            $dParams = [];

            if (!empty($filters['blood_type'])) {
                $dWhere[] = 'blood_type = ?';
                $dParams[] = $filters['blood_type'];
            }

            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $dWhere[] = "(first_name LIKE ? OR last_name LIKE ? OR reference_code LIKE ?)";
                $dParams = array_merge($dParams, [$searchTerm, $searchTerm, $searchTerm]);
            }

            $dWhereClause = 'WHERE ' . implode(' AND ', $dWhere);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM $donorTable $dWhereClause");
            $stmt->execute($dParams);
            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        } catch (Exception $e) {
            if ($this->debug) {
                error_log("Count error: " . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * Get alerts
     */
    public function getAlerts() {
        try {
            $alerts = [];
            
            // Check for expiring units
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count 
                FROM blood_inventory 
                WHERE expiry_date <= DATE_ADD(NOW(), INTERVAL 5 DAY) 
                AND status = 'available'
            ");
            $expiring = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($expiring > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "$expiring blood units expiring within 5 days",
                    'count' => $expiring
                ];
            }

            // Check for low stock by blood type
            $stmt = $this->pdo->query("
                SELECT blood_type, COUNT(*) as count 
                FROM blood_inventory 
                WHERE status = 'available' 
                GROUP BY blood_type 
                HAVING COUNT(*) < 5
            ");
            $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($lowStock as $stock) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "Low stock for {$stock['blood_type']}: {$stock['count']} units",
                    'blood_type' => $stock['blood_type'],
                    'count' => $stock['count']
                ];
            }

            return $alerts;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get eligible donors
     */
    public function getEligibleDonors() {
        $donorTable = $this->getDonorTable();
        
        try {
            $stmt = $this->pdo->query("
                SELECT id, first_name, last_name, blood_type, reference_code,
                       CONCAT(first_name, ' ', last_name) as full_name
                FROM $donorTable 
                WHERE status IN ('served', 'completed')
                ORDER BY created_at DESC 
                LIMIT 100
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
?>