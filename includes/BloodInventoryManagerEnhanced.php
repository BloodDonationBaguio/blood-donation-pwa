<?php
/**
 * Enhanced Blood Inventory Manager
 * Comprehensive blood unit tracking and management system
 */

class BloodInventoryManagerEnhanced {
    private $pdo;
    private $userRole;
    private $userId;

    public function __construct($pdo, $userRole = 'viewer', $userId = null) {
        $this->pdo = $pdo;
        $this->userRole = $userRole;
        $this->userId = $userId;
    }

    /**
     * Get comprehensive dashboard summary
     */
    public function getDashboardSummary() {
        try {
            // Check if blood_inventory table exists
            $tableCheck = $this->pdo->query("
                SELECT COUNT(*) as table_exists 
                FROM information_schema.tables 
                WHERE table_name = 'blood_inventory'
            ");
            $tableExists = $tableCheck->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;
            
            if (!$tableExists) {
                error_log("Blood inventory table does not exist");
                return [
                    'total_units' => 0,
                    'available_units' => 0,
                    'expired_units' => 0,
                    'by_blood_type' => [],
                    'low_stock_types' => []
                ];
            }

            // Get total units by blood type (without schema prefix for better compatibility)
            $stmt = $this->pdo->query("
                SELECT 
                    blood_type,
                    COALESCE(COUNT(*), 0) as count,
                    COALESCE(SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END), 0) as available,
                    COALESCE(SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END), 0) as used,
                    COALESCE(SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END), 0) as expired,
                    COALESCE(SUM(CASE WHEN status = 'quarantined' THEN 1 ELSE 0 END), 0) as quarantined
                FROM blood_inventory 
                GROUP BY blood_type
            ");
            $byBloodType = [];
            $totalUnits = 0;
            $availableUnits = 0;
            $expiredUnits = 0;

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $byBloodType[$row['blood_type']] = $row['count'];
                $totalUnits += $row['count'];
                $availableUnits += $row['available'];
                $expiredUnits += $row['expired'];
            }

            // Get low stock alerts (less than 5 units)
            $lowStockTypes = [];
            foreach ($byBloodType as $type => $count) {
                if ($count < 5) {
                    $lowStockTypes[] = $type;
                }
            }

            return [
                'total_units' => $totalUnits,
                'available_units' => $availableUnits,
                'expired_units' => $expiredUnits,
                'by_blood_type' => $byBloodType,
                'low_stock_types' => $lowStockTypes
            ];
        } catch (Exception $e) {
            error_log("Error getting dashboard summary: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Try a simple count query to test basic connectivity
            try {
                $testStmt = $this->pdo->query("SELECT COUNT(*) as total FROM blood_inventory");
                $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
                error_log("Simple count query result: " . json_encode($testResult));
            } catch (Exception $testE) {
                error_log("Even simple count query failed: " . $testE->getMessage());
            }
            
            return [
                'total_units' => 0,
                'available_units' => 0,
                'expired_units' => 0,
                'by_blood_type' => [],
                'low_stock_types' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get units expiring within specified days
     */
    public function getExpiringUnits($days = 5) {
        try {
            $endDate = date('Y-m-d', strtotime('+' . (int)$days . ' days'));
            $stmt = $this->pdo->prepare("
                SELECT bi.*, d.first_name, d.last_name, d.reference_code
                FROM blood_inventory bi
                LEFT JOIN donors_new d ON bi.donor_id = d.id
                WHERE bi.status = 'available' 
                AND bi.expiry_date <= ?
                AND bi.expiry_date > CURRENT_DATE
                ORDER BY bi.expiry_date ASC
            ");
            $stmt->execute([$endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting expiring units: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get inventory with filters and pagination
     */
    public function getInventory($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $whereConditions = [];
            $params = [];

            // Build WHERE conditions
            if (!empty($filters['blood_type'])) {
                $whereConditions[] = "bi.blood_type = ?";
                $params[] = $filters['blood_type'];
            }

            if (!empty($filters['status'])) {
                $whereConditions[] = "bi.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "bi.collection_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "bi.collection_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $whereConditions[] = "(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Get total count
            $countSql = "
                SELECT COUNT(*) as total
                FROM blood_inventory bi
                LEFT JOIN donors_new d ON bi.donor_id = d.id
                $whereClause
            ";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get data
            $sql = "
                SELECT 
                    bi.*,
                    d.first_name,
                    d.last_name,
                    d.reference_code,
                    d.blood_type as donor_blood_type,
                    d.status as donor_status,
                    COALESCE(d.first_name, '') || ' ' || COALESCE(d.last_name, '') as donor_name
                FROM blood_inventory bi
                LEFT JOIN donors_new d ON bi.donor_id = d.id
                $whereClause
                ORDER BY bi.created_at DESC
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
                'total_pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Error getting inventory: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'limit' => $limit,
                'total_pages' => 0
            ];
        }
    }

    /**
     * Get detailed blood unit information
     */
    public function getBloodUnit($unitId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    bi.*,
                    d.first_name,
                    d.last_name,
                    d.reference_code,
                    d.blood_type as donor_blood_type,
                    d.status as donor_status,
                    d.email,
                    d.phone,
                    CONCAT(d.first_name, ' ', d.last_name) as donor_name
                FROM blood_inventory bi
                LEFT JOIN donors_new d ON bi.donor_id = d.id
                WHERE bi.id = ?
            ");
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return ['success' => false, 'message' => 'Unit not found'];
            }

            // Get audit log
            $auditStmt = $this->pdo->prepare("
                SELECT * FROM blood_inventory_audit 
                WHERE unit_id = ? 
                ORDER BY created_at DESC
            ");
            $auditStmt->execute([$unitId]);
            $unit['audit_log'] = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'data' => $unit];
        } catch (Exception $e) {
            error_log("Error getting blood unit: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error retrieving unit details'];
        }
    }

    /**
     * Create new blood unit
     */
    public function createBloodUnit($data) {
        try {
            // Validate required fields
            $required = ['donor_id', 'blood_type', 'collection_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Missing required field: $field"];
                }
            }

            // Validate donor exists and is served
            $donorStmt = $this->pdo->prepare("
                SELECT id, first_name, last_name, blood_type, status 
                FROM donors_new 
                WHERE id = ? AND status = 'served'
            ");
            $donorStmt->execute([$data['donor_id']]);
            $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

            if (!$donor) {
                return ['success' => false, 'message' => 'Donor not found or not eligible for blood collection'];
            }

            // Generate unique unit ID
            $unitId = $this->generateUnitId($data['blood_type']);

            // Calculate expiry date (25 days from collection)
            $expiryDate = date('Y-m-d', strtotime($data['collection_date'] . ' +25 days'));

            $this->pdo->beginTransaction();

            // Insert blood unit
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_inventory (
                    unit_id, donor_id, blood_type, collection_date, expiry_date,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'available', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $unitId,
                $data['donor_id'],
                $data['blood_type'],
                $data['collection_date'],
                $expiryDate
            ]);

            $unitId = $this->pdo->lastInsertId();

            // Log audit
            $this->logAudit($unitId, 'unit_created', 'Blood unit created', [
                'unit_id' => $unitId,
                'donor_id' => $data['donor_id'],
                'blood_type' => $data['blood_type']
            ]);

            $this->pdo->commit();

            return ['success' => true, 'message' => 'Blood unit created successfully', 'unit_id' => $unitId];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error creating blood unit: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating blood unit: ' . $e->getMessage()];
        }
    }

    /**
     * Update blood unit status
     */
    public function updateBloodUnitStatus($unitId, $newStatus, $notes = '') {
        try {
            $validStatuses = ['available', 'used', 'expired', 'quarantined'];
            if (!in_array($newStatus, $validStatuses)) {
                return ['success' => false, 'message' => 'Invalid status'];
            }

            $this->pdo->beginTransaction();

            // Get current unit
            $stmt = $this->pdo->prepare("SELECT * FROM blood_inventory WHERE id = ?");
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return ['success' => false, 'message' => 'Unit not found'];
            }

            $oldStatus = $unit['status'];

            // Update status
            $updateStmt = $this->pdo->prepare("
                UPDATE blood_inventory 
                SET status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $updateStmt->execute([$newStatus, $unitId]);

            // Log audit
            $this->logAudit($unitId, 'status_updated', "Status changed from $oldStatus to $newStatus", [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'notes' => $notes
            ]);

            $this->pdo->commit();

            return ['success' => true, 'message' => 'Status updated successfully'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error updating blood unit status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
        }
    }

    /**
     * Update blood type (for Unknown units after lab screening)
     */
    public function updateBloodType($unitId, $bloodType) {
        try {
            $validTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
            if (!in_array($bloodType, $validTypes)) {
                return ['success' => false, 'message' => 'Invalid blood type'];
            }

            $this->pdo->beginTransaction();

            // Get current unit
            $stmt = $this->pdo->prepare("SELECT * FROM blood_inventory WHERE id = ?");
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return ['success' => false, 'message' => 'Unit not found'];
            }

            $oldType = $unit['blood_type'];

            // Update blood type
            $updateStmt = $this->pdo->prepare("
                UPDATE blood_inventory 
                SET blood_type = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $updateStmt->execute([$bloodType, $unitId]);

            // Log audit
            $this->logAudit($unitId, 'blood_type_updated', "Blood type changed from $oldType to $bloodType", [
                'old_blood_type' => $oldType,
                'new_blood_type' => $bloodType
            ]);

            $this->pdo->commit();

            return ['success' => true, 'message' => 'Blood type updated successfully'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error updating blood type: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating blood type: ' . $e->getMessage()];
        }
    }

    /**
     * Get approved donors for blood unit creation
     */
    public function getApprovedDonors($limit = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, first_name, last_name, reference_code, blood_type, status
                FROM donors_new 
                WHERE status = 'served'
                ORDER BY last_donation_date DESC, created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting approved donors: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate unique unit ID
     */
    private function generateUnitId($bloodType) {
        $prefix = 'PRC';
        $date = date('Ymd');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . $date . '-' . $random;
    }

    /**
     * Log audit trail
     */
    private function logAudit($unitId, $action, $description, $details = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_inventory_audit (
                    unit_id, action_type, description, details, 
                    admin_username, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $unitId,
                $action,
                $description,
                json_encode($details),
                $this->userId ?? 'system',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Error logging audit: " . $e->getMessage());
        }
    }

    /**
     * Export inventory to CSV
     */
    public function exportToCSV($filters = []) {
        try {
            $inventory = $this->getInventory($filters, 1, 10000); // Get all records
            
            $filename = 'blood_inventory_' . date('Y-m-d_H-i-s') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($output, [
                'Unit ID', 'Blood Type', 'Donor Name', 'Donor ID', 'Collection Date',
                'Expiry Date', 'Status', 'Collection Center', 'Collection Staff'
            ]);
            
            // CSV data
            foreach ($inventory['data'] as $unit) {
                fputcsv($output, [
                    $unit['unit_id'],
                    $unit['blood_type'],
                    $unit['donor_name'],
                    $unit['donor_id'],
                    $unit['collection_date'],
                    $unit['expiry_date'],
                    $unit['status'],
                    $unit['collection_center'],
                    $unit['collection_staff']
                ]);
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            error_log("Error exporting CSV: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error exporting data'];
        }
    }

    /**
     * Check permissions
     */
    private function hasPermission($action) {
        $permissions = [
            'view' => ['super_admin', 'inventory_manager', 'medical_staff', 'viewer'],
            'edit' => ['super_admin', 'inventory_manager', 'medical_staff'],
            'add' => ['super_admin', 'inventory_manager', 'medical_staff'],
            'delete' => ['super_admin', 'inventory_manager']
        ];
        
        return in_array($this->userRole, $permissions[$action] ?? []);
    }
}
?>
