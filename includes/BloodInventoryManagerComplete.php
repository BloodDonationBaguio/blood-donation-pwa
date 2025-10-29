<?php
/**
 * Complete Blood Inventory Manager
 * Full admin capabilities with security and compliance
 */

class BloodInventoryManagerComplete {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get dashboard summary with alerts
     */
    public function getDashboardSummary() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COALESCE(COUNT(*), 0) AS total_units,
                    COALESCE(SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END), 0) AS available_units,
                    COALESCE(SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END), 0) AS expired_units,
                    COALESCE(SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END), 0) AS used_units
                FROM blood_inventory
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !isset($row['total_units'])) {
                // Fallback: schema-qualified for PostgreSQL if search_path differs
                $stmt = $this->pdo->query("
                    SELECT 
                        COALESCE(COUNT(*), 0) AS total_units,
                        COALESCE(SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END), 0) AS available_units,
                        COALESCE(SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END), 0) AS expired_units,
                        COALESCE(SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END), 0) AS used_units
                    FROM public.blood_inventory
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            // Fallback: if inventory is empty, infer counts from donors_new served/completed
            try {
                $tu = isset($row['total_units']) ? (int)$row['total_units'] : 0;
                if ($tu === 0) {
                    // Some deployments use 'completed' instead of 'served' in donors_new
                    $servedStmt = $this->pdo->query("SELECT COUNT(*) AS cnt FROM donors_new WHERE status IN ('served','completed')");
                    $served = (int)($servedStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
                    if ($served > 0) {
                        $row = [
                            'total_units' => $served,
                            'available_units' => 0,
                            'expired_units' => 0,
                            'used_units' => $served
                        ];
                    }
                }
            } catch (Exception $fe) {
                error_log('Fallback donors_new served aggregation failed: ' . $fe->getMessage());
            }
            return $row ?: ['total_units' => 0, 'available_units' => 0, 'expired_units' => 0, 'used_units' => 0];
        } catch (Exception $e) {
            error_log("Error getting dashboard summary: " . $e->getMessage());
            return ['total_units' => 0, 'available_units' => 0, 'expired_units' => 0, 'used_units' => 0];
        }
    }

    /**
     * Get system alerts
     */
    public function getAlerts() {
        $alerts = [];
        
        try {
            // Check for expiring units
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count FROM blood_inventory 
                WHERE expiry_date <= DATE_ADD(NOW(), INTERVAL 5 DAY) 
                AND status = 'available'
            ");
            $expiring = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($expiring > 0) {
                $alerts[] = "{$expiring} blood units expiring within 5 days";
            }

            // Check for low stock
            $stmt = $this->pdo->query("
                SELECT blood_type, COUNT(*) as count 
                FROM blood_inventory 
                WHERE status = 'available' 
                GROUP BY blood_type 
                HAVING count < 5
            ");
            $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($lowStock as $stock) {
                $alerts[] = "Low stock alert: {$stock['blood_type']} has only {$stock['count']} units available";
            }

        } catch (Exception $e) {
            error_log("Error getting alerts: " . $e->getMessage());
        }
        
        return $alerts;
    }

    /**
     * Get inventory with filters
     */
    public function getInventory($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $whereConditions = [];
            $params = [];

            // Determine donor table dynamically (support deployments using donors_new)
            $donorTable = 'donors';
            try {
                if (function_exists('tableExists') && tableExists($this->pdo, 'donors_new')) {
                    $donorTable = 'donors_new';
                }
            } catch (Exception $e) {
                // Default to legacy donors table if detection fails
                error_log('Table detection failed in getInventory, defaulting to donors: ' . $e->getMessage());
            }

            if (!empty($filters['blood_type'])) {
                $whereConditions[] = "bi.blood_type = ?";
                $params[] = $filters['blood_type'];
            }

            if (!empty($filters['status'])) {
                $whereConditions[] = "bi.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $whereConditions[] = "(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }

            // Only show blood units for donors with eligible status
            if ($donorTable === 'donors_new') {
                $whereConditions[] = "(d.status = 'served' OR d.status = 'completed')";
            } else {
                $whereConditions[] = "d.status = 'served'";
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Get total count
            $countSql = "
                SELECT COUNT(*) as total
                FROM blood_inventory bi
                LEFT JOIN {$donorTable} d ON bi.donor_id = d.id
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
                    CONCAT(d.first_name, ' ', d.last_name) as donor_name,
                    CASE 
                        WHEN bi.expiry_date <= DATE_ADD(NOW(), INTERVAL 5 DAY) AND bi.status = 'available' THEN 1 
                        ELSE 0 
                    END as expiring_soon
                FROM blood_inventory bi
                LEFT JOIN {$donorTable} d ON bi.donor_id = d.id
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
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }
    }

    /**
     * Get unit details with PII masking
     */
    public function getUnitDetails($unitId, $canViewPII = true) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    bi.*,
                    d.first_name,
                    d.last_name,
                    d.reference_code,
                    d.blood_type as donor_blood_type,
                    d.email,
                    d.phone,
                    CONCAT(d.first_name, ' ', d.last_name) as donor_name
                FROM blood_inventory bi
                LEFT JOIN donors d ON bi.donor_id = d.id
                WHERE bi.unit_id = ?
            ");
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return ['success' => false, 'message' => 'Unit not found'];
            }

            // PII always visible - no masking

            // REPLACED BLOCK START: robust audit retrieval
            $unit['audit_log'] = [];
            try {
                if (isset($unit['id']) && $unit['id'] !== null && $unit['id'] !== '') {
                    $auditStmt = $this->pdo->prepare("
                        SELECT * FROM blood_inventory_audit
                        WHERE unit_id = ?
                        ORDER BY timestamp DESC
                    ");
                    $auditStmt->execute([$unit['id']]);
                    $unit['audit_log'] = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                if (empty($unit['audit_log'])) {
                    $auditStmt2 = $this->pdo->prepare("
                        SELECT * FROM blood_inventory_audit
                        WHERE unit_id = ? OR new_values LIKE ? OR old_values LIKE ?
                        ORDER BY timestamp DESC
                    ");
                    $like = '%"' . $unit['unit_id'] . '"%';
                    $auditStmt2->execute([$unit['unit_id'], $like, $like]);
                    $unit['audit_log'] = $auditStmt2->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log('Audit fetch failed in getUnitDetails: ' . $e->getMessage());
                $unit['audit_log'] = [];
            }
            // REPLACED BLOCK END

            return ['success' => true, 'unit' => $unit];
        } catch (Exception $e) {
            error_log("Error getting unit details: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error retrieving unit details'];
        }
    }

    /**
     * Add new blood unit
     */
    public function addBloodUnit($data) {
        $transactionStarted = false;
        try {
            // Start transaction only if not already in one
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transactionStarted = true;
            }

            // Validate donor (support both donors and donors_new)
            $donorTable = 'donors';
            try {
                if (function_exists('tableExists') && tableExists($this->pdo, 'donors_new')) {
                    $donorTable = 'donors_new';
                }
            } catch (Exception $e) {
                // If table detection fails, default to original 'donors'
                error_log('Table detection failed in addBloodUnit, defaulting to donors: ' . $e->getMessage());
            }

            $donorStmt = $this->pdo->prepare(
                "SELECT id, first_name, last_name, blood_type, status FROM {$donorTable} WHERE id = ? AND status = 'served'"
            );
            $donorStmt->execute([$data['donor_id']]);
            $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

            if (!$donor) {
                throw new Exception('Donor not found or not eligible for blood donation');
            }

            // Generate unit ID
            $unitId = $this->generateUnitId($donor['blood_type']);
            // UPDATED: expiry is 25 days after collection
            $expiryDate = date('Y-m-d', strtotime($data['collection_date'] . ' +25 days'));

            // Insert blood unit
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_inventory (
                    unit_id, donor_id, blood_type, collection_date, expiry_date,
                    status, collection_site, storage_location, created_at
                ) VALUES (?, ?, ?, ?, ?, 'available', ?, ?, NOW())
            ");
            $stmt->execute([
                $unitId,
                $data['donor_id'],
                $donor['blood_type'],
                $data['collection_date'],
                $expiryDate,
                $data['collection_site'] ?? 'Main Center',
                $data['storage_location'] ?? 'Storage A'
            ]);

            $unitDbId = $this->pdo->lastInsertId();

            // Log audit
            $this->logAudit($unitDbId, 'unit_created', 'Blood unit created', [
                'unit_id' => $unitId,
                'donor_name' => $donor['first_name'] . ' ' . $donor['last_name'],
                'blood_type' => $donor['blood_type']
            ]);

            // Commit only if we started the transaction
            if ($transactionStarted) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => 'Blood unit created successfully', 'unit_id' => $unitId];

        } catch (Exception $e) {
            // Rollback only if we started the transaction
            if ($transactionStarted && $this->pdo->inTransaction()) {
                try {
                    $this->pdo->rollBack();
                } catch (Exception $rollbackEx) {
                    error_log("Error rolling back transaction: " . $rollbackEx->getMessage());
                }
            }
            error_log("Error adding blood unit: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update unit status
     */
    public function updateUnitStatus($unitId, $newStatus, $reason = '') {
        $transactionStarted = false;
        try {
            // Start transaction only if not already in one
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transactionStarted = true;
            }

            // Get current unit
            $stmt = $this->pdo->prepare("SELECT * FROM blood_inventory WHERE unit_id = ?");
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                throw new Exception('Unit not found');
            }

            $oldStatus = $unit['status'];

            // Append reason to notes if provided
            $newNotes = $unit['notes'] ?? '';
            $noteLine = null;
            if (is_string($reason) && trim($reason) !== '') {
                $adminName = $_SESSION['admin_username'] ?? 'admin';
                $noteLine = '[' . date('Y-m-d H:i') . "] {$adminName}: Status changed from {$oldStatus} to {$newStatus} — Reason: " . trim($reason);
                $newNotes = trim(rtrim($newNotes)) . (empty($newNotes) ? '' : "\n") . $noteLine;
            }

            // Update status (and notes)
            $updateStmt = $this->pdo->prepare("
                UPDATE blood_inventory 
                SET status = ?, notes = ?, updated_at = NOW() 
                WHERE unit_id = ?
            ");
            $updateStmt->execute([$newStatus, $newNotes, $unitId]);

            // Log audit
            $this->logAudit($unit['id'], 'status_updated', "Status changed from $oldStatus to $newStatus", [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'notes_appended' => $noteLine
            ]);

            // Commit only if we started the transaction
            if ($transactionStarted) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => 'Status updated successfully'];

        } catch (Exception $e) {
            // Rollback only if we started the transaction
            if ($transactionStarted && $this->pdo->inTransaction()) {
                try {
                    $this->pdo->rollBack();
                } catch (Exception $rollbackEx) {
                    error_log("Error rolling back transaction: " . $rollbackEx->getMessage());
                }
            }
            error_log("Error updating status: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update blood type
     */
    public function updateBloodType($unitId, $bloodType) {
        $transactionStarted = false;
        try {
            // Start transaction only if not already in one
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transactionStarted = true;
            }

            $stmt = $this->pdo->prepare("SELECT * FROM blood_inventory WHERE unit_id = ?");
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                throw new Exception('Unit not found');
            }

            $oldType = $unit['blood_type'];

            $updateStmt = $this->pdo->prepare("
                UPDATE blood_inventory 
                SET blood_type = ?, updated_at = NOW() 
                WHERE unit_id = ?
            ");
            $updateStmt->execute([$bloodType, $unitId]);

            // Log audit
            $this->logAudit($unit['id'], 'blood_type_updated', "Blood type changed from $oldType to $bloodType", [
                'old_blood_type' => $oldType,
                'new_blood_type' => $bloodType
            ]);

            // Commit only if we started the transaction
            if ($transactionStarted) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => 'Blood type updated successfully'];

        } catch (Exception $e) {
            // Rollback only if we started the transaction
            if ($transactionStarted && $this->pdo->inTransaction()) {
                try {
                    $this->pdo->rollBack();
                } catch (Exception $rollbackEx) {
                    error_log("Error rolling back transaction: " . $rollbackEx->getMessage());
                }
            }
            error_log("Error updating blood type: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete unit
     */
    public function deleteUnit($unitId, $reason = '') {
        $transactionStarted = false;
        try {
            // Validate unit_id
            if (empty($unitId)) {
                throw new Exception('Invalid unit ID');
            }

            // Start transaction only if not already in one
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transactionStarted = true;
            }

            // Get unit details
            $stmt = $this->pdo->prepare("SELECT * FROM blood_inventory WHERE unit_id = ?");
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                throw new Exception('Blood unit not found in inventory');
            }

            // Log audit before deletion
            try {
                $this->logAudit($unit['id'], 'unit_deleted', "Blood unit deleted", [
                    'unit_id' => $unitId,
                    'blood_type' => $unit['blood_type'],
                    'status' => $unit['status'],
                    'reason' => $reason
                ]);
            } catch (Exception $auditEx) {
                error_log("Audit logging failed: " . $auditEx->getMessage());
                // Continue with deletion even if audit fails
            }

            // Delete unit
            $deleteStmt = $this->pdo->prepare("DELETE FROM blood_inventory WHERE unit_id = ?");
            $deleteStmt->execute([$unitId]);

            // Verify deletion
            if ($deleteStmt->rowCount() === 0) {
                throw new Exception('Failed to delete blood unit');
            }

            // Commit only if we started the transaction
            if ($transactionStarted) {
                $this->pdo->commit();
            }
            
            return ['success' => true, 'message' => 'Blood unit deleted successfully'];

        } catch (Exception $e) {
            // Rollback only if we started the transaction
            if ($transactionStarted && $this->pdo->inTransaction()) {
                try {
                    $this->pdo->rollBack();
                } catch (Exception $rollbackEx) {
                    error_log("Error rolling back transaction: " . $rollbackEx->getMessage());
                }
            }
            error_log("Error deleting unit $unitId: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get eligible donors
     */
    public function getEligibleDonors() {
        try {
            // Determine donor table dynamically
            $donorTable = 'donors';
            try {
                if (function_exists('tableExists') && tableExists($this->pdo, 'donors_new')) {
                    $donorTable = 'donors_new';
                }
            } catch (Exception $e) {
                error_log('Table detection failed in getEligibleDonors, defaulting to donors: ' . $e->getMessage());
            }

            $statusFilter = $donorTable === 'donors_new' ? "(status = 'served' OR status = 'completed')" : "status = 'served'";

            $stmt = $this->pdo->prepare("SELECT id, first_name, last_name, reference_code, blood_type, status FROM {$donorTable} WHERE {$statusFilter} ORDER BY last_name, first_name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting eligible donors: " . $e->getMessage());
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
        $inTransaction = false;
        
        // Log to blood_inventory_audit table
        try {
            // Check if we're in a transaction - if so, don't start a new one
            $inTransaction = $this->pdo->inTransaction();
            
            // Ensure table exists
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS blood_inventory_audit (
                id INT AUTO_INCREMENT PRIMARY KEY,
                unit_id VARCHAR(100),
                action VARCHAR(100),
                old_values TEXT,
                new_values TEXT,
                admin_name VARCHAR(255),
                ip_address VARCHAR(64),
                user_agent TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_inventory_audit (
                    unit_id, action, old_values, new_values, 
                    admin_name, ip_address, user_agent, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'system';
            
            $result = $stmt->execute([
                $unitId,
                $action,
                json_encode($details),
                json_encode($details),
                $adminUsername,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            if ($result) {
                error_log("✓ Blood inventory audit logged: $action for unit $unitId by $adminUsername");
            } else {
                error_log("✗ Blood inventory audit failed to log: $action for unit $unitId");
            }
        } catch (Exception $e) {
            error_log("✗ Error logging to blood_inventory_audit: " . $e->getMessage());
        }

        // Mirror into admin_audit_log for the Admin > Audit Log tab
        try {
            // Ensure table exists
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                admin_username VARCHAR(255) NULL,
                action_type VARCHAR(255) NOT NULL,
                table_name VARCHAR(255) NULL,
                record_id VARCHAR(255) NULL,
                description TEXT NULL,
                ip_address VARCHAR(64) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'system';
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $tableName = 'blood_inventory';
            $recordId = is_scalar($unitId) ? (string)$unitId : null;

            $stmt2 = $this->pdo->prepare("INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description, ip_address)
                                          VALUES (?, ?, ?, ?, ?, ?)");
            $result2 = $stmt2->execute([$adminUsername, $action, $tableName, $recordId, $description, $ip]);
            
            if ($result2) {
                error_log("✓ Admin audit log inserted: $action for unit $unitId by $adminUsername");
            } else {
                error_log("✗ Admin audit log failed: $action for unit $unitId");
            }
        } catch (Exception $e) {
            error_log('✗ Admin audit log insert failed: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Export to CSV
     */
    public function exportToCSV($filters = []) {
        try {
            $inventory = $this->getInventory($filters, 1, 10000);
            
            $filename = 'blood_inventory_' . date('Y-m-d_H-i-s') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($output, [
                'Unit ID', 'Blood Type', 'Donor Name', 'Reference Code', 'Collection Date',
                'Expiry Date', 'Status', 'Collection Site', 'Storage Location'
            ]);
            
            // CSV data
            foreach ($inventory['data'] as $unit) {
                fputcsv($output, [
                    $unit['unit_id'],
                    $unit['blood_type'],
                    $unit['donor_name'],
                    $unit['reference_code'],
                    $unit['collection_date'],
                    $unit['expiry_date'],
                    $unit['status'],
                    $unit['collection_site'],
                    $unit['storage_location']
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
     * Get total count of inventory items with filters
     */
    public function getInventoryCount($filters = []) {
        try {
            $whereConditions = [];
            $params = [];

            // Determine donor table dynamically
            $donorTable = 'donors';
            try {
                if (function_exists('tableExists') && tableExists($this->pdo, 'donors_new')) {
                    $donorTable = 'donors_new';
                }
            } catch (Exception $e) {
                error_log('Table detection failed in getInventoryCount, defaulting to donors: ' . $e->getMessage());
            }
            
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
            
            // Search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $whereConditions[] = '(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            // Only show blood units for donors with eligible status
            if ($donorTable === 'donors_new') {
                $whereConditions[] = "(d.status = 'served' OR d.status = 'completed')";
            } else {
                $whereConditions[] = "d.status = 'served'";
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $query = "
                SELECT COUNT(*) as total
                FROM blood_inventory bi
                LEFT JOIN {$donorTable} d ON bi.donor_id = d.id
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
