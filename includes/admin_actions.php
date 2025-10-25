<?php
/**
 * Admin Actions Logging System
 * Tracks all admin actions for audit trail
 */

/**
 * Ensure audit log table exists
 * Call this BEFORE starting any transaction to avoid auto-commit issues
 */
function ensureAuditLogTableExists($pdo) {
    try {
        // Check if table exists first to avoid unnecessary DDL
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_audit_log'");
        if ($stmt->rowCount() == 0) {
            // Table doesn't exist, create it
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                admin_username VARCHAR(255) NULL,
                action_type VARCHAR(255) NOT NULL,
                table_name VARCHAR(255) NULL,
                record_id VARCHAR(255) NULL,
                description TEXT NULL,
                ip_address VARCHAR(64) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (PDOException $e) {
        error_log("Failed to ensure audit log table exists: " . $e->getMessage());
    }
}

function logAdminAction($pdo, $actionType, $tableName, $recordId, $actionDetails = null, $adminId = null) {
    try {
        // DO NOT create table here - it auto-commits and breaks transactions
        // Call ensureAuditLogTableExists() before starting any transaction instead
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log 
            (admin_username, action_type, table_name, record_id, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $adminUsername = $adminId ?? ($_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'system');
        
        $result = $stmt->execute([
            $adminUsername,
            $actionType,
            $tableName,
            $recordId,
            $actionDetails,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        if ($result) {
            error_log("✓ Audit logged: $actionType on $tableName (ID: $recordId) by $adminUsername");
        } else {
            error_log("✗ Audit log failed for: $actionType on $tableName (ID: $recordId)");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("✗ Failed to log admin action: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function getAdminActionLog($pdo, $filters = []) {
    $sql = "SELECT aal.*, 
            CASE 
                WHEN aal.table_name = 'donors_new' THEN CONCAT(d.first_name, ' ', d.last_name)
                ELSE 'Unknown'
            END as record_name
            FROM admin_audit_log aal
            LEFT JOIN donors_new d ON aal.table_name = 'donors_new' AND aal.record_id = d.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['action_type'])) {
        $sql .= " AND aal.action_type = ?";
        $params[] = $filters['action_type'];
    }
    
    if (!empty($filters['table_name'])) {
        $sql .= " AND aal.table_name = ?";
        $params[] = $filters['table_name'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(aal.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(aal.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $sql .= " ORDER BY aal.created_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . (int)$filters['limit'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}




function generateDonorReport($pdo, $filters = []) {
    $sql = "SELECT 
            d.*,
            COUNT(DISTINCT dm.id) as total_matches,
            COUNT(DISTINCT CASE WHEN dm.status = 'confirmed' THEN dm.id END) as confirmed_matches
            FROM donors d
            LEFT JOIN donor_matching dm ON d.id = dm.donor_id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['status'])) {
        $sql .= " AND d.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['blood_type'])) {
        $sql .= " AND d.blood_type = ?";
        $params[] = $filters['blood_type'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(d.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(d.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $sql .= " GROUP BY d.id ORDER BY d.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

?> 