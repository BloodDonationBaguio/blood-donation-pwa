<?php
/**
 * Comprehensive Database Testing Suite
 * Tests all database operations, tables, and functionality
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
set_time_limit(120);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'test_summary' => [
        'total_tests' => 0,
        'passed' => 0,
        'failed' => 0,
        'warnings' => 0
    ],
    'connection_tests' => [],
    'table_tests' => [],
    'crud_tests' => [],
    'sequence_tests' => [],
    'performance_tests' => [],
    'data_integrity_tests' => [],
    'errors' => []
];

function runTest($testName, $testFunction, &$results) {
    $results['test_summary']['total_tests']++;
    $startTime = microtime(true);
    
    try {
        $result = $testFunction();
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($result['status'] === 'pass') {
            $results['test_summary']['passed']++;
        } elseif ($result['status'] === 'warning') {
            $results['test_summary']['warnings']++;
        } else {
            $results['test_summary']['failed']++;
        }
        
        $result['duration'] = $duration . 'ms';
        $result['test_name'] = $testName;
        
        return $result;
    } catch (Exception $e) {
        $results['test_summary']['failed']++;
        $results['errors'][] = "Test '$testName' failed: " . $e->getMessage();
        
        return [
            'test_name' => $testName,
            'status' => 'fail',
            'error' => $e->getMessage(),
            'duration' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
        ];
    }
}

// 1. Connection Tests
$results['connection_tests']['basic_connection'] = runTest('Basic Connection', function() use ($pdo) {
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetchColumn();
    
    return [
        'status' => $result === 1 ? 'pass' : 'fail',
        'message' => $result === 1 ? 'Database connection successful' : 'Connection test failed',
        'result' => $result
    ];
}, $results);

$results['connection_tests']['version_check'] = runTest('Database Version', function() use ($pdo) {
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    
    return [
        'status' => 'pass',
        'message' => 'Database version retrieved',
        'version' => $version
    ];
}, $results);

$results['connection_tests']['timezone_check'] = runTest('Timezone Check', function() use ($pdo) {
    $stmt = $pdo->query("SELECT current_setting('timezone') as tz, now() as current_time");
    $result = $stmt->fetch();
    
    return [
        'status' => 'pass',
        'message' => 'Timezone information retrieved',
        'timezone' => $result['tz'],
        'current_time' => $result['current_time']
    ];
}, $results);

// 2. Table Structure Tests
$critical_tables = [
    'donors',
    'donations',
    'admin_users',
    'blood_inventory',
    'donor_notes',
    'admin_audit_log'
];

foreach ($critical_tables as $table) {
    $results['table_tests'][$table] = runTest("Table: $table", function() use ($pdo, $table) {
        // Check if table exists
        $stmt = $pdo->prepare("SELECT to_regclass('public.' || ?)");
        $stmt->execute([$table]);
        $exists = $stmt->fetchColumn() !== null;
        
        if (!$exists) {
            return [
                'status' => 'warning',
                'message' => "Table '$table' does not exist",
                'exists' => false
            ];
        }
        
        // Get table structure
        $stmt = $pdo->prepare("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_name = ? AND table_schema = 'public'
            ORDER BY ordinal_position
        ");
        $stmt->execute([$table]);
        $columns = $stmt->fetchAll();
        
        // Count records
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM \"$table\"");
        $stmt->execute();
        $record_count = $stmt->fetchColumn();
        
        return [
            'status' => 'pass',
            'message' => "Table '$table' exists and accessible",
            'exists' => true,
            'columns' => count($columns),
            'record_count' => $record_count,
            'structure' => $columns
        ];
    }, $results);
}

// 3. CRUD Operation Tests
$results['crud_tests']['donor_notes_insert'] = runTest('Insert Donor Note', function() use ($pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO donor_notes (donor_id, note, created_by) 
        VALUES (?, ?, ?) 
        RETURNING id
    ");
    $stmt->execute([999999, 'Test note - automated test', 'test_system']);
    $id = $stmt->fetchColumn();
    
    if ($id) {
        // Clean up
        $cleanup = $pdo->prepare("DELETE FROM donor_notes WHERE id = ?");
        $cleanup->execute([$id]);
        
        return [
            'status' => 'pass',
            'message' => 'Successfully inserted and deleted test donor note',
            'inserted_id' => $id
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'Failed to insert donor note'
        ];
    }
}, $results);

$results['crud_tests']['admin_audit_insert'] = runTest('Insert Admin Audit Log', function() use ($pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description) 
        VALUES (?, ?, ?, ?, ?) 
        RETURNING id
    ");
    $stmt->execute(['test_admin', 'TEST', 'test_table', '999999', 'Automated test entry']);
    $id = $stmt->fetchColumn();
    
    if ($id) {
        // Clean up
        $cleanup = $pdo->prepare("DELETE FROM admin_audit_log WHERE id = ?");
        $cleanup->execute([$id]);
        
        return [
            'status' => 'pass',
            'message' => 'Successfully inserted and deleted test audit log',
            'inserted_id' => $id
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'Failed to insert audit log'
        ];
    }
}, $results);

// 4. Sequence Tests
$results['sequence_tests']['donor_notes_sequence'] = runTest('Donor Notes Sequence', function() use ($pdo) {
    $stmt = $pdo->query("SELECT nextval('donor_notes_id_seq') as next_id");
    $next_id = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT currval('donor_notes_id_seq') as current_id");
    $current_id = $stmt->fetchColumn();
    
    return [
        'status' => 'pass',
        'message' => 'Donor notes sequence is working',
        'next_id' => $next_id,
        'current_id' => $current_id
    ];
}, $results);

$results['sequence_tests']['admin_audit_sequence'] = runTest('Admin Audit Sequence', function() use ($pdo) {
    $stmt = $pdo->query("SELECT nextval('admin_audit_log_id_seq') as next_id");
    $next_id = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT currval('admin_audit_log_id_seq') as current_id");
    $current_id = $stmt->fetchColumn();
    
    return [
        'status' => 'pass',
        'message' => 'Admin audit log sequence is working',
        'next_id' => $next_id,
        'current_id' => $current_id
    ];
}, $results);

// 5. Performance Tests
$results['performance_tests']['large_query'] = runTest('Large Query Performance', function() use ($pdo) {
    $stmt = $pdo->query("
        SELECT 
            schemaname,
            tablename,
            attname,
            n_distinct,
            correlation
        FROM pg_stats 
        WHERE schemaname = 'public'
        LIMIT 100
    ");
    $results = $stmt->fetchAll();
    
    return [
        'status' => count($results) > 0 ? 'pass' : 'warning',
        'message' => 'Large query executed successfully',
        'rows_returned' => count($results)
    ];
}, $results);

$results['performance_tests']['concurrent_connections'] = runTest('Connection Pool Test', function() use ($pdo) {
    $stmt = $pdo->query("
        SELECT 
            count(*) as total_connections,
            count(*) FILTER (WHERE state = 'active') as active_connections,
            count(*) FILTER (WHERE state = 'idle') as idle_connections
        FROM pg_stat_activity 
        WHERE datname = current_database()
    ");
    $stats = $stmt->fetch();
    
    return [
        'status' => 'pass',
        'message' => 'Connection statistics retrieved',
        'total_connections' => $stats['total_connections'],
        'active_connections' => $stats['active_connections'],
        'idle_connections' => $stats['idle_connections']
    ];
}, $results);

// 6. Data Integrity Tests
$results['data_integrity_tests']['foreign_keys'] = runTest('Foreign Key Constraints', function() use ($pdo) {
    $stmt = $pdo->query("
        SELECT 
            tc.table_name,
            tc.constraint_name,
            tc.constraint_type
        FROM information_schema.table_constraints tc
        WHERE tc.constraint_type = 'FOREIGN KEY'
        AND tc.table_schema = 'public'
    ");
    $foreign_keys = $stmt->fetchAll();
    
    return [
        'status' => 'pass',
        'message' => 'Foreign key constraints checked',
        'foreign_key_count' => count($foreign_keys),
        'constraints' => $foreign_keys
    ];
}, $results);

$results['data_integrity_tests']['indexes'] = runTest('Index Analysis', function() use ($pdo) {
    $stmt = $pdo->query("
        SELECT 
            schemaname,
            tablename,
            indexname,
            indexdef
        FROM pg_indexes 
        WHERE schemaname = 'public'
        ORDER BY tablename, indexname
    ");
    $indexes = $stmt->fetchAll();
    
    return [
        'status' => 'pass',
        'message' => 'Database indexes analyzed',
        'index_count' => count($indexes),
        'indexes' => $indexes
    ];
}, $results);

// 7. Security Tests
$results['security_tests']['user_permissions'] = runTest('User Permissions', function() use ($pdo) {
    $stmt = $pdo->query("SELECT current_user, session_user");
    $user_info = $stmt->fetch();
    
    $stmt = $pdo->query("
        SELECT 
            has_database_privilege(current_user, current_database(), 'CONNECT') as can_connect,
            has_database_privilege(current_user, current_database(), 'CREATE') as can_create
    ");
    $permissions = $stmt->fetch();
    
    return [
        'status' => 'pass',
        'message' => 'User permissions checked',
        'current_user' => $user_info['current_user'],
        'session_user' => $user_info['session_user'],
        'can_connect' => $permissions['can_connect'],
        'can_create' => $permissions['can_create']
    ];
}, $results);

// Calculate success rate
$total = $results['test_summary']['total_tests'];
$passed = $results['test_summary']['passed'];
$success_rate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;

$results['test_summary']['success_rate'] = $success_rate . '%';
$results['overall_status'] = $success_rate >= 90 ? 'excellent' : ($success_rate >= 75 ? 'good' : 'needs_attention');

echo json_encode($results, JSON_PRETTY_PRINT);
?>
