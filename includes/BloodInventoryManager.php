<?php
/**
 * Blood Inventory Management System
 * Philippine Red Cross Compliant
 * Data Privacy Act 2012 Compliant
 */

class BloodInventoryManager {
    private $pdo;
    private $adminId;
    private $adminName;
    private $adminRole;
    
    public function __construct($pdo, $adminId = null, $adminName = null, $adminRole = null) {
        $this->pdo = $pdo;
        $this->adminId = $adminId;
        $this->adminName = $adminName;
        $this->adminRole = $adminRole;
    }
    
    /**
     * Check if admin has permission for specific action
     */
    private function hasPermission($action) {
        $permissions = [
            'super_admin' => ['view', 'create', 'edit', 'delete', 'view_donor_info', 'update_test_results'],
            'inventory_manager' => ['view', 'create', 'edit', 'view_donor_info', 'update_test_results'],
            'medical_staff' => ['view', 'update_test_results', 'view_donor_info'],
            'viewer' => ['view']
        ];
        
        return isset($permissions[$this->adminRole]) && 
               in_array($action, $permissions[$this->adminRole]);
    }
    
    /**
     * Log audit trail for all actions
     */
    private function logAudit($unitId, $action, $oldValues = null, $newValues = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_inventory_audit 
                (unit_id, action, old_values, new_values, admin_id, admin_name, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $unitId,
                $action,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $this->adminId,
                $this->adminName,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate unique unit ID
     */
    private function generateUnitId($bloodType, $collectionDate) {
        // Get count for this blood type and date
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM blood_inventory 
            WHERE blood_type = ? AND DATE(collection_date) = ?
        ");
        $stmt->execute([$bloodType, $collectionDate]);
        $count = $stmt->fetchColumn() + 1;
        
        // Format: PRC-[BloodType]-[YYYYMMDD]-[001]
        $bloodTypeCode = str_replace(['+', '-'], ['P', 'N'], $bloodType);
        $dateCode = date('Ymd', strtotime($collectionDate));
        
        return sprintf('PRC-%s-%s-%03d', $bloodTypeCode, $dateCode, $count);
    }
    
    /**
     * Create new blood unit - ONLY for real registered donors
     */
    public function createBloodUnit($data) {
        if (!$this->hasPermission('create')) {
            throw new Exception('Insufficient permissions to create blood units');
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // CRITICAL: Validate that donor exists and is a real registration
            $stmt = $this->pdo->prepare("
                SELECT id, first_name, last_name, email, blood_type, status, created_at 
                FROM donors_new 
                WHERE id = ? 
                AND email NOT LIKE 'test_%' 
                AND email NOT LIKE '%@example.com'
                AND first_name != 'Test'
                AND last_name != 'User'
                AND reference_code NOT LIKE 'TEST-%'
            ");
            $stmt->execute([$data['donor_id']]);
            $donor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$donor) {
                throw new Exception('Invalid donor ID. Blood units can only be created for real registered donors.');
            }
            
            // Validate donor status
            if ($donor['status'] !== 'approved' && $donor['status'] !== 'served') {
                throw new Exception('Blood units can only be created for approved donors. Current status: ' . $donor['status']);
            }
            
            // Validate blood type matches donor's blood type
            if ($data['blood_type'] !== 'Unknown' && $donor['blood_type'] !== 'Unknown') {
                if ($data['blood_type'] !== $donor['blood_type']) {
                    throw new Exception("Blood type mismatch. Donor's blood type is {$donor['blood_type']}, but trying to create unit with {$data['blood_type']}");
                }
            }
            
            // Generate unique unit ID
            $unitId = $this->generateUnitId($data['blood_type'], $data['collection_date']);
            
            // Policy update: whole blood expires in 25 days from collection
            $expiryDate = date('Y-m-d', strtotime($data['collection_date'] . ' + 25 days'));
            
            // Validate collection date (cannot be in the future, cannot be too old)
            $collectionDate = strtotime($data['collection_date']);
            $today = strtotime(date('Y-m-d'));
            $maxPastDays = strtotime('-30 days');
            
            if ($collectionDate > $today) {
                throw new Exception('Collection date cannot be in the future');
            }
            
            if ($collectionDate < $maxPastDays) {
                throw new Exception('Collection date cannot be more than 30 days in the past');
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_inventory 
                (unit_id, donor_id, blood_type, collection_date, expiry_date, 
                 volume_ml, collection_site, storage_location, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $unitId,
                $data['donor_id'],
                $data['blood_type'],
                $data['collection_date'],
                $expiryDate,
                $data['volume_ml'] ?? 450,
                $data['collection_site'] ?? 'Philippine Red Cross',
                $data['storage_location'] ?? null,
                $data['notes'] ?? null,
                $this->adminId
            ]);
            
            // Log audit with donor information
            $auditData = array_merge($data, [
                'donor_name' => $donor['first_name'] . ' ' . $donor['last_name'],
                'donor_email' => $donor['email'],
                'unit_id' => $unitId
            ]);
            $this->logAudit($unitId, 'created', null, $auditData);
            
            $this->pdo->commit();
            return $unitId;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Failed to create blood unit: ' . $e->getMessage());
        }
    }
    
    /**
     * Get blood inventory with filtering and pagination
     */
    public function getInventory($filters = [], $page = 1, $limit = 50) {
        if (!$this->hasPermission('view')) {
            throw new Exception('Insufficient permissions to view inventory');
        }
        
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];
        
        // Apply filters
        if (!empty($filters['blood_type'])) {
            $where[] = "bi.blood_type = ?";
            $params[] = $filters['blood_type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "bi.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['collection_date_from'])) {
            $where[] = "bi.collection_date >= ?";
            $params[] = $filters['collection_date_from'];
        }
        
        if (!empty($filters['collection_date_to'])) {
            $where[] = "bi.collection_date <= ?";
            $params[] = $filters['collection_date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(bi.unit_id LIKE ? OR d.reference_code LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Check if admin can view donor info
        $donorFields = $this->hasPermission('view_donor_info') ? 
            "d.first_name, d.last_name, d.reference_code," : 
            "'***' as first_name, '***' as last_name, d.reference_code,";
        
        // CRITICAL: Only show blood units linked to real registered donors
        $where[] = "d.id IS NOT NULL";  // Ensure donor exists
        
        // Handle test data filter
        if (isset($filters['show_test']) && $filters['show_test'] == '0') {
            // Exclude test data when show_test = 0
            $where[] = "d.email NOT LIKE 'test_%'";  // Exclude test emails
            $where[] = "d.email NOT LIKE '%@example.com'";  // Exclude example emails
            $where[] = "d.first_name != 'Test'";  // Exclude test names
            $where[] = "d.last_name != 'User'";  // Exclude test names
            $where[] = "(d.reference_code NOT LIKE 'TEST-%' OR d.reference_code IS NULL)";  // Exclude test references
            
            // Also exclude by seed_flag if column exists
            try {
                $checkSeedFlag = $this->pdo->query("SHOW COLUMNS FROM donors_new LIKE 'seed_flag'");
                if ($checkSeedFlag->fetch()) {
                    $where[] = "(d.seed_flag = 0 OR d.seed_flag IS NULL)";
                }
            } catch (Exception $e) {
                // Column doesn't exist, continue without filter
            }
        }
        // If show_test = 1 or not set, show all data (including test data)
        
        $sql = "
            SELECT bi.*, 
                   {$donorFields}
                   d.blood_type as donor_blood_type,
                   d.status as donor_status,
                   DATEDIFF(bi.expiry_date, CURDATE()) as days_to_expire,
                   CASE 
                       WHEN bi.expiry_date < CURDATE() THEN 'expired'
                       WHEN DATEDIFF(bi.expiry_date, CURDATE()) <= 5 THEN 'expiring_soon'
                       ELSE 'normal'
                   END as urgency_status
            FROM blood_inventory bi
            INNER JOIN donors_new d ON bi.donor_id = d.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY 
                CASE bi.status 
                    WHEN 'available' THEN 1 
                    WHEN 'quarantined' THEN 2 
                    WHEN 'used' THEN 3 
                    WHEN 'expired' THEN 4 
                END,
                bi.collection_date ASC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get blood unit details
     */
    public function getBloodUnit($unitId) {
        if (!$this->hasPermission('view')) {
            throw new Exception('Insufficient permissions to view blood unit');
        }
        
        $donorFields = $this->hasPermission('view_donor_info') ? 
            "d.first_name, d.last_name, d.email, d.phone," : 
            "'***' as first_name, '***' as last_name, '***' as email, '***' as phone,";
        
        $stmt = $this->pdo->prepare("
            SELECT bi.*, 
                   {$donorFields}
                   d.reference_code,
                   d.blood_type as donor_blood_type,
                   DATEDIFF(bi.expiry_date, CURDATE()) as days_to_expire
            FROM blood_inventory bi
            LEFT JOIN donors_new d ON bi.donor_id = d.id
            WHERE bi.unit_id = ?
        ");
        
        $stmt->execute([$unitId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update blood unit
     */
    public function updateBloodUnit($unitId, $data) {
        if (!$this->hasPermission('edit')) {
            throw new Exception('Insufficient permissions to update blood unit');
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Get current values for audit
            $currentUnit = $this->getBloodUnit($unitId);
            if (!$currentUnit) {
                throw new Exception('Blood unit not found');
            }
            
            $updateFields = [];
            $params = [];
            
            // Only allow certain fields to be updated
            $allowedFields = ['blood_type', 'status', 'notes', 'storage_location', 'test_results', 'screening_status'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('No valid fields to update');
            }
            
            $updateFields[] = "updated_by = ?";
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $this->adminId;
            $params[] = $unitId;
            
            $sql = "UPDATE blood_inventory SET " . implode(', ', $updateFields) . " WHERE unit_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Log audit
            $this->logAudit($unitId, 'updated', $currentUnit, $data);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Failed to update blood unit: ' . $e->getMessage());
        }
    }
    
    /**
     * Issue blood unit (FIFO)
     */
    public function issueBloodUnit($bloodType, $requestData) {
        if (!$this->hasPermission('edit')) {
            throw new Exception('Insufficient permissions to issue blood');
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Find oldest available unit of requested blood type (FIFO)
            $stmt = $this->pdo->prepare("
                SELECT unit_id FROM blood_inventory 
                WHERE blood_type = ? AND status = 'available' 
                ORDER BY collection_date ASC 
                LIMIT 1
            ");
            $stmt->execute([$bloodType]);
            $unitId = $stmt->fetchColumn();
            
            if (!$unitId) {
                throw new Exception("No available blood units of type {$bloodType}");
            }
            
            // Update status to used
            $stmt = $this->pdo->prepare("
                UPDATE blood_inventory 
                SET status = 'used', updated_by = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE unit_id = ?
            ");
            $stmt->execute([$this->adminId, $unitId]);
            
            // Record the issuance
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_requests_inventory 
                (request_id, unit_id, issued_by, recipient_hospital, recipient_patient, notes) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $requestData['request_id'] ?? null,
                $unitId,
                $this->adminId,
                $requestData['recipient_hospital'] ?? null,
                $requestData['recipient_patient'] ?? null,
                $requestData['notes'] ?? null
            ]);
            
            // Log audit
            $this->logAudit($unitId, 'issued', ['status' => 'available'], ['status' => 'used']);
            
            $this->pdo->commit();
            return $unitId;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Failed to issue blood unit: ' . $e->getMessage());
        }
    }
    
    /**
     * Get dashboard summary
     */
    public function getDashboardSummary() {
        if (!$this->hasPermission('view')) {
            throw new Exception('Insufficient permissions to view dashboard');
        }
        
        // Get inventory summary
        $stmt = $this->pdo->query("SELECT * FROM blood_inventory_summary");
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get expiring units
        $stmt = $this->pdo->query("SELECT * FROM expiring_blood_units");
        $expiringUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get low stock alerts (< 5 units)
        $stmt = $this->pdo->query("
            SELECT blood_type, available_units 
            FROM blood_inventory_summary 
            WHERE available_units < 5
        ");
        $lowStockAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'summary' => $summary,
            'expiring_units' => $expiringUnits,
            'low_stock_alerts' => $lowStockAlerts
        ];
    }
    
    /**
     * Get real registered donors only (for blood unit creation)
     */
    public function getRealDonors($limit = 100) {
        if (!$this->hasPermission('view')) {
            throw new Exception('Insufficient permissions to view donors');
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, first_name, last_name, email, blood_type, reference_code, 
                   status, created_at, phone
            FROM donors_new 
            WHERE email NOT LIKE 'test_%' 
              AND email NOT LIKE '%@example.com'
              AND first_name != 'Test'
              AND last_name != 'User'
              AND (reference_code NOT LIKE 'TEST-%' OR reference_code IS NULL)
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get approved donors eligible for blood collection
     */
    public function getApprovedDonors($limit = 100) {
        if (!$this->hasPermission('view')) {
            throw new Exception('Insufficient permissions to view donors');
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, first_name, last_name, email, blood_type, reference_code, 
                   status, created_at, phone, last_donation_date
            FROM donors_new 
            WHERE email NOT LIKE 'test_%' 
              AND email NOT LIKE '%@example.com'
              AND first_name != 'Test'
              AND last_name != 'User'
              AND (reference_code NOT LIKE 'TEST-%' OR reference_code IS NULL)
              AND status IN ('approved', 'served')
            ORDER BY 
                CASE WHEN last_donation_date IS NULL THEN 1 ELSE 0 END,
                last_donation_date ASC,
                created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get audit log
     */
    public function getAuditLog($unitId = null, $limit = 100) {
        if (!$this->hasPermission('view')) {
            throw new Exception('Insufficient permissions to view audit log');
        }
        
        $sql = "SELECT * FROM blood_inventory_audit";
        $params = [];
        
        if ($unitId) {
            $sql .= " WHERE unit_id = ?";
            $params[] = $unitId;
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update test results (medical staff only)
     */
    public function updateTestResults($unitId, $testResults, $screeningStatus) {
        if (!$this->hasPermission('update_test_results')) {
            throw new Exception('Insufficient permissions to update test results');
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                UPDATE blood_inventory 
                SET test_results = ?, screening_status = ?, 
                    status = CASE WHEN ? = 'failed' THEN 'quarantined' ELSE status END,
                    updated_by = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE unit_id = ?
            ");
            
            $stmt->execute([
                json_encode($testResults),
                $screeningStatus,
                $screeningStatus,
                $this->adminId,
                $unitId
            ]);
            
            // Log audit
            $this->logAudit($unitId, 'test_results_updated', null, [
                'test_results' => $testResults,
                'screening_status' => $screeningStatus
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Failed to update test results: ' . $e->getMessage());
        }
    }
    
    /**
     * Get inventory data with pagination and sorting
     */
    public function getInventoryPaginated($filters = [], $page = 1, $perPage = 25, $sortBy = 'created_at', $sortOrder = 'DESC') {
        try {
            $whereConditions = [];
            $params = [];
            
            // Blood type filter
            if (!empty($filters['blood_type'])) {
                $whereConditions[] = 'bi.blood_type = ?';
                $params[] = $filters['blood_type'];
            }
            
            // Status filter
            if (!empty($filters['status'])) {
                $whereConditions[] = 'bi.status = ?';
                $params[] = $filters['status'];
            }
            
            // Collection date range filter
            if (!empty($filters['collection_date_from'])) {
                $whereConditions[] = 'bi.collection_date >= ?';
                $params[] = $filters['collection_date_from'];
            }
            
            if (!empty($filters['collection_date_to'])) {
                $whereConditions[] = 'bi.collection_date <= ?';
                $params[] = $filters['collection_date_to'];
            }
            
            // Search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $whereConditions[] = '(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            // CRITICAL: Only show blood units linked to real registered donors
            $whereConditions[] = "d.id IS NOT NULL";  // Ensure donor exists
            $whereConditions[] = "d.email NOT LIKE 'test_%'";  // Exclude test emails
            $whereConditions[] = "d.email NOT LIKE '%@example.com'";  // Exclude example emails
            $whereConditions[] = "d.first_name != 'Test'";  // Exclude test names
            $whereConditions[] = "d.last_name != 'User'";  // Exclude test names
            $whereConditions[] = "(d.reference_code NOT LIKE 'TEST-%' OR d.reference_code IS NULL)";  // Exclude test references
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Validate sort field
            $allowedSortFields = ['created_at', 'unit_id', 'blood_type', 'collection_date', 'expiry_date', 'status'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            
            // Validate sort order
            $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
            
            // Calculate offset
            $offset = ($page - 1) * $perPage;
            
            // Check if admin can view donor info
            $donorFields = $this->hasPermission('view_donor_info') ? 
                "d.first_name as donor_first_name, d.last_name as donor_last_name, d.reference_code as donor_reference," : 
                "'***' as donor_first_name, '***' as donor_last_name, d.reference_code as donor_reference,";
            
            // Build query with donor information
            $query = "
                SELECT bi.*, 
                       {$donorFields}
                       d.blood_type as donor_blood_type,
                       d.status as donor_status,
                       CASE 
                           WHEN bi.expiry_date < CURDATE() THEN 'expired'
                           WHEN bi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expiring_soon'
                           ELSE 'good'
                       END as urgency_status,
                       DATEDIFF(bi.expiry_date, CURDATE()) as days_to_expiry
                FROM blood_inventory bi
                INNER JOIN donors_new d ON bi.donor_id = d.id
                $whereClause
                ORDER BY bi.$sortBy $sortOrder
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $perPage;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting paginated inventory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of inventory items with filters
     */
    public function getInventoryCount($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Blood type filter
            if (!empty($filters['blood_type'])) {
                $whereConditions[] = 'bi.blood_type = ?';
                $params[] = $filters['blood_type'];
            }
            
            // Status filter
            if (!empty($filters['status'])) {
                $whereConditions[] = 'bi.status = ?';
                $params[] = $filters['status'];
            }
            
            // Collection date range filter
            if (!empty($filters['collection_date_from'])) {
                $whereConditions[] = 'bi.collection_date >= ?';
                $params[] = $filters['collection_date_from'];
            }
            
            if (!empty($filters['collection_date_to'])) {
                $whereConditions[] = 'bi.collection_date <= ?';
                $params[] = $filters['collection_date_to'];
            }
            
            // Search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $whereConditions[] = '(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            // CRITICAL: Only count blood units linked to real registered donors
            $whereConditions[] = "d.id IS NOT NULL";  // Ensure donor exists
            
            // Handle test data filter
            if (isset($filters['show_test']) && $filters['show_test'] == '0') {
                // Exclude test data when show_test = 0
                $whereConditions[] = "d.email NOT LIKE 'test_%'";  // Exclude test emails
                $whereConditions[] = "d.email NOT LIKE '%@example.com'";  // Exclude example emails
                $whereConditions[] = "d.first_name != 'Test'";  // Exclude test names
                $whereConditions[] = "d.last_name != 'User'";  // Exclude test names
                $whereConditions[] = "(d.reference_code NOT LIKE 'TEST-%' OR d.reference_code IS NULL)";  // Exclude test references
                
                // Also exclude by seed_flag if column exists
                try {
                    $checkSeedFlag = $this->pdo->query("SHOW COLUMNS FROM donors_new LIKE 'seed_flag'");
                    if ($checkSeedFlag->fetch()) {
                        $whereConditions[] = "(d.seed_flag = 0 OR d.seed_flag IS NULL)";
                    }
                } catch (Exception $e) {
                    // Column doesn't exist, continue without filter
                }
            }
            // If show_test = 1 or not set, show all data (including test data)
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            $query = "
                SELECT COUNT(*) as total
                FROM blood_inventory bi
                INNER JOIN donors_new d ON bi.donor_id = d.id
                $whereClause
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
            
        } catch (Exception $e) {
            error_log("Error getting inventory count: " . $e->getMessage());
            return 0;
        }
    }
}
?>
