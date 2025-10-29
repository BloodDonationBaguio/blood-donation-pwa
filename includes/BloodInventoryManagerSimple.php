<?php
/**
 * Simple Blood Inventory Manager
 * Works with the existing database structure
 */

class BloodInventoryManagerSimple {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get comprehensive dashboard summary
     */
    public function getDashboardSummary() {
        try {
            // Get total units by blood type
            $stmt = $this->pdo->query("
                SELECT 
                    blood_type,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN status = 'quarantined' THEN 1 ELSE 0 END) as quarantined
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

            return [
                'total_units' => $totalUnits,
                'available_units' => $availableUnits,
                'expired_units' => $expiredUnits,
                'by_blood_type' => $byBloodType,
                'low_stock_types' => array_keys(array_filter($byBloodType, function($count) { return $count < 5; }))
            ];
        } catch (Exception $e) {
            error_log("Error getting dashboard summary: " . $e->getMessage());
            return [
                'total_units' => 0,
                'available_units' => 0,
                'expired_units' => 0,
                'by_blood_type' => [],
                'low_stock_types' => []
            ];
        }
    }

    /**
     * Get units expiring within specified days
     */
    public function getExpiringUnits($days = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT bi.*, d.first_name, d.last_name, d.reference_code
                FROM blood_inventory bi
                LEFT JOIN donors_new d ON bi.donor_id = d.id
                WHERE bi.status = 'available' 
                AND bi.expiry_date <= DATE_ADD(NOW(), INTERVAL ? DAY)
                AND bi.expiry_date > NOW()
                ORDER BY bi.expiry_date ASC
            ");
            $stmt->execute([$days]);
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
                    CONCAT(d.first_name, ' ', d.last_name) as donor_name
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
            // Check if unitId is numeric (database ID) or string (unit_id)
            if (is_numeric($unitId)) {
                // Search by database ID
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
            } else {
                // Search by unit_id (string like PRC-20250930-0364)
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
                    LEFT JOIN donors d ON bi.donor_id = d.id
                    WHERE bi.unit_id = ?
                ");
            }
            
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return ['success' => false, 'message' => 'Unit not found'];
            }

            // Get audit log using the database ID
            $auditStmt = $this->pdo->prepare("
                SELECT * FROM blood_inventory_audit 
                WHERE unit_id = ? 
                ORDER BY timestamp DESC
            ");
            $auditStmt->execute([$unit['id']]); // Use the database ID for audit log
            $unit['audit_log'] = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'data' => $unit];
        } catch (Exception $e) {
            error_log("Error getting blood unit: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Error retrieving unit details: ' . $e->getMessage()];
        }
    }

    /**
     * Create new blood unit
     */
    public function createBloodUnit($data) {
        try {
            // Validate required fields
            $required = ['donor_id', 'collection_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Missing required field: $field"];
                }
            }

            // Validate donor exists and is approved/served
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

            // Use the donor's actual blood type, not the submitted one
            $actualBloodType = $donor['blood_type'];
            
            // Validate that the submitted blood type matches the donor's actual blood type
            if (isset($data['blood_type']) && $data['blood_type'] !== $actualBloodType) {
                return ['success' => false, 'message' => "Blood type mismatch. Donor {$donor['first_name']} {$donor['last_name']} has blood type {$actualBloodType}, not {$data['blood_type']}"];
            }

            // Generate unique unit ID using the donor's actual blood type
            $unitId = $this->generateUnitId($actualBloodType);

            // Calculate expiry date (42 days from collection)
            $expiryDate = date('Y-m-d', strtotime($data['collection_date'] . ' +42 days'));

            $this->pdo->beginTransaction();

            // Insert blood unit using the donor's actual blood type
            $stmt = $this->pdo->prepare("
                INSERT INTO blood_inventory (
                    unit_id, donor_id, blood_type, collection_date, expiry_date,
                    status, collection_site, storage_location, created_at
                ) VALUES (?, ?, ?, ?, ?, 'available', ?, ?, NOW())
            ");
            $stmt->execute([
                $unitId,
                $data['donor_id'],
                $actualBloodType, // Use donor's actual blood type
                $data['collection_date'],
                $expiryDate,
                $data['collection_center'] ?? 'Main Center',
                $data['storage_location'] ?? 'Storage A'
            ]);

            $unitId = $this->pdo->lastInsertId();

            // Log audit
            $this->logAudit($unitId, 'unit_created', 'Blood unit created', [
                'unit_id' => $unitId,
                'donor_id' => $data['donor_id'],
                'blood_type' => $actualBloodType,
                'donor_name' => $donor['first_name'] . ' ' . $donor['last_name']
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
                SET status = ?, updated_at = NOW() 
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
     * Get approved donors for blood unit creation
     */
    public function getApprovedDonors($limit = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, first_name, last_name, reference_code, blood_type, status
                FROM donors_new 
                WHERE status = 'served'
                ORDER BY created_at DESC
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $unitId,
                $action,
                $description,
                json_encode($details),
                $_SESSION['admin_username'] ?? 'system',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Error logging audit: " . $e->getMessage());
        }
    }
}
?>
