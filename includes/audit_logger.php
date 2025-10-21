<?php
/**
 * Audit Logger Helper Functions
 * Provides functions to log admin actions for audit trail
 */

/**
 * Log an admin action to the audit log
 * 
 * @param PDO $pdo Database connection
 * @param string $admin_username Username of the admin performing the action
 * @param string $action Description of the action performed
 * @param string $table_name Name of the database table affected (optional)
 * @param int $record_id ID of the record affected (optional)
 * @param string $description Additional details about the action (optional)
 * @return bool True if logged successfully, false otherwise
 */
function logAdminAction($pdo, $admin_username, $action, $table_name = null, $record_id = null, $description = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $sql = "INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $admin_username,
            $action,
            $table_name,
            $record_id,
            $description,
            $ip_address
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs with optional filtering
 * 
 * @param PDO $pdo Database connection
 * @param array $filters Optional filters (admin_username, action, date_from, date_to)
 * @param int $limit Maximum number of records to return
 * @return array Array of audit log records
 */
function getAuditLogs($pdo, $filters = [], $limit = 50) {
    try {
        $sql = "SELECT * FROM admin_audit_log WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['admin_username'])) {
            $sql .= " AND admin_username LIKE ?";
            $params[] = '%' . $filters['admin_username'] . '%';
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND action_type LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting audit logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Clear old audit logs (older than specified days)
 * 
 * @param PDO $pdo Database connection
 * @param int $days Number of days to keep logs
 * @return bool True if successful, false otherwise
 */
function clearOldAuditLogs($pdo, $days = 90) {
    try {
        $sql = "DELETE FROM admin_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$days]);
    } catch (Exception $e) {
        error_log("Error clearing old audit logs: " . $e->getMessage());
        return false;
    }
}
?>
