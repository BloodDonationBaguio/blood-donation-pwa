<?php
/**
 * PostgreSQL Compatibility Helper
 * Provides MySQL-compatible functions for PostgreSQL
 */

if (!function_exists('mysql_now')) {
    /**
     * MySQL NOW() equivalent for PostgreSQL
     * @return string Current timestamp in format compatible with both
     */
    function mysql_now() {
        return 'CURRENT_TIMESTAMP';
    }
}

if (!function_exists('tableExists')) {
    /**
     * Check if table exists (PostgreSQL version)
     */
    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT to_regclass('public." . $table . "')");
            return $result->fetchColumn() !== null;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('getTableColumns')) {
    /**
     * Get table columns (PostgreSQL version)
     */
    function getTableColumns($pdo, $table) {
        try {
            $stmt = $pdo->prepare("
                SELECT column_name, data_type, is_nullable, column_default
                FROM information_schema.columns 
                WHERE table_name = ? AND table_schema = 'public'
                ORDER BY ordinal_position
            ");
            $stmt->execute([$table]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}

/**
 * Sanitize table name (prevent SQL injection)
 */
function sanitizeTableName($tableName) {
    // Only allow alphanumeric and underscores
    return preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
}

/**
 * Get correct table name (handle renamed tables)
 */
function getTableName($pdo, $requestedTable) {
    $tableMap = [
        'donors_new' => 'donors',
        'users_new' => 'donors',  // Users table doesn't exist, use donors
        'users' => 'donors',       // Map users to donors
    ];
    
    // Check if table needs mapping
    if (isset($tableMap[$requestedTable])) {
        $mappedTable = $tableMap[$requestedTable];
        // Verify mapped table exists
        if (tableExists($pdo, $mappedTable)) {
            return $mappedTable;
        }
    }
    
    // Check if requested table exists as-is
    if (tableExists($pdo, $requestedTable)) {
        return $requestedTable;
    }
    
    // Return original name if nothing else works
    return $requestedTable;
}

/**
 * Execute query with automatic table name fixing
 */
function executeCompatQuery($pdo, $sql, $params = []) {
    // Replace NOW() with CURRENT_TIMESTAMP
    $sql = preg_replace('/\bNOW\(\)/i', 'CURRENT_TIMESTAMP', $sql);
    
    // Fix common table names
    $sql = preg_replace('/\bdonors_new\b/i', 'donors', $sql);
    $sql = preg_replace('/\bFROM\s+users\b/i', 'FROM donors', $sql);
    $sql = preg_replace('/\bJOIN\s+users\b/i', 'JOIN donors', $sql);
    $sql = preg_replace('/\bINSERT\s+INTO\s+users\b/i', 'INSERT INTO donors', $sql);
    $sql = preg_replace('/\bUPDATE\s+users\b/i', 'UPDATE donors', $sql);
    
    // Execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

?>

