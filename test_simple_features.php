<?php
/**
 * Simple Application Feature Tests
 * Tests core blood donation system functionality
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
set_time_limit(60);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => [],
    'summary' => []
];

// Test 1: Database Connection
try {
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetchColumn();
    $results['tests']['database_connection'] = [
        'status' => $result === 1 ? 'PASS' : 'FAIL',
        'message' => 'Database connection test',
        'details' => $result === 1 ? 'Connected successfully' : 'Connection failed'
    ];
} catch (Exception $e) {
    $results['tests']['database_connection'] = [
        'status' => 'FAIL',
        'message' => 'Database connection test',
        'details' => $e->getMessage()
    ];
}

// Test 2: Check Critical Tables
$critical_tables = ['donors', 'donations', 'admin_users', 'blood_inventory'];
foreach ($critical_tables as $table) {
    try {
        $stmt = $pdo->prepare("SELECT to_regclass('public.' || ?)");
        $stmt->execute([$table]);
        $exists = $stmt->fetchColumn() !== null;
        
        if ($exists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM \"$table\"");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            $results['tests']["table_$table"] = [
                'status' => 'PASS',
                'message' => "Table '$table' exists",
                'details' => "Records: $count"
            ];
        } else {
            $results['tests']["table_$table"] = [
                'status' => 'WARNING',
                'message' => "Table '$table' missing",
                'details' => 'Table does not exist'
            ];
        }
    } catch (Exception $e) {
        $results['tests']["table_$table"] = [
            'status' => 'FAIL',
            'message' => "Table '$table' test failed",
            'details' => $e->getMessage()
        ];
    }
}

// Test 3: Sequence Tests
$sequences = ['donor_notes_id_seq', 'admin_audit_log_id_seq'];
foreach ($sequences as $seq) {
    try {
        $stmt = $pdo->query("SELECT nextval('$seq')");
        $next_val = $stmt->fetchColumn();
        
        $results['tests']["sequence_$seq"] = [
            'status' => 'PASS',
            'message' => "Sequence '$seq' working",
            'details' => "Next value: $next_val"
        ];
    } catch (Exception $e) {
        $results['tests']["sequence_$seq"] = [
            'status' => 'FAIL',
            'message' => "Sequence '$seq' failed",
            'details' => $e->getMessage()
        ];
    }
}

// Test 4: Insert Test (with rollback)
try {
    $pdo->beginTransaction();
    
    // Test donor_notes insert
    $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by) VALUES (?, ?, ?) RETURNING id");
    $stmt->execute([999999, 'Test note', 'test_system']);
    $note_id = $stmt->fetchColumn();
    
    // Test admin_audit_log insert
    $stmt = $pdo->prepare("INSERT INTO admin_audit_log (admin_username, action_type, description) VALUES (?, ?, ?) RETURNING id");
    $stmt->execute(['test_admin', 'TEST', 'Test audit entry']);
    $audit_id = $stmt->fetchColumn();
    
    $pdo->rollback(); // Always rollback test data
    
    $results['tests']['insert_test'] = [
        'status' => 'PASS',
        'message' => 'Insert operations working',
        'details' => "Note ID: $note_id, Audit ID: $audit_id (rolled back)"
    ];
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    $results['tests']['insert_test'] = [
        'status' => 'FAIL',
        'message' => 'Insert test failed',
        'details' => $e->getMessage()
    ];
}

// Test 5: Performance Test
$start_time = microtime(true);
try {
    for ($i = 0; $i < 10; $i++) {
        $stmt = $pdo->query("SELECT current_timestamp");
        $stmt->fetchColumn();
    }
    $duration = round((microtime(true) - $start_time) * 1000, 2);
    
    $results['tests']['performance_test'] = [
        'status' => $duration < 1000 ? 'PASS' : 'WARNING',
        'message' => '10 query performance test',
        'details' => "Duration: {$duration}ms"
    ];
} catch (Exception $e) {
    $results['tests']['performance_test'] = [
        'status' => 'FAIL',
        'message' => 'Performance test failed',
        'details' => $e->getMessage()
    ];
}

// Test 6: API Endpoints
$base_url = 'https://' . $_SERVER['HTTP_HOST'];
$endpoints = ['/health.php', '/connection_diagnostic.php'];

foreach ($endpoints as $endpoint) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $results['tests']["api_$endpoint"] = [
            'status' => 'FAIL',
            'message' => "API endpoint $endpoint",
            'details' => $error
        ];
    } else {
        $status = ($http_code >= 200 && $http_code < 400) ? 'PASS' : 'WARNING';
        $results['tests']["api_$endpoint"] = [
            'status' => $status,
            'message' => "API endpoint $endpoint",
            'details' => "HTTP $http_code"
        ];
    }
}

// Calculate Summary
$total_tests = count($results['tests']);
$passed_tests = count(array_filter($results['tests'], function($test) {
    return $test['status'] === 'PASS';
}));
$warning_tests = count(array_filter($results['tests'], function($test) {
    return $test['status'] === 'WARNING';
}));
$failed_tests = count(array_filter($results['tests'], function($test) {
    return $test['status'] === 'FAIL';
}));

$results['summary'] = [
    'total_tests' => $total_tests,
    'passed' => $passed_tests,
    'warnings' => $warning_tests,
    'failed' => $failed_tests,
    'success_rate' => round(($passed_tests / $total_tests) * 100, 2) . '%',
    'overall_status' => $failed_tests === 0 ? ($warning_tests === 0 ? 'EXCELLENT' : 'GOOD') : 'NEEDS_ATTENTION'
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
