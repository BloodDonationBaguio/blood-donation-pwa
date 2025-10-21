<?php
/**
 * Admin Actions Logging System
 * Tracks all admin actions for audit trail
 */

function logAdminAction($pdo, $actionType, $tableName, $recordId, $actionDetails = null, $adminId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log 
            (admin_username, action_type, table_name, record_id, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $adminId ?? ($_SESSION['admin_username'] ?? 'unknown'),
            $actionType,
            $tableName,
            $recordId,
            $actionDetails,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
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