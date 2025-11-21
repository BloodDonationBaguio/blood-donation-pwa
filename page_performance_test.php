<?php
/**
 * Page Performance Tester - Identifies what's causing 5-second delays
 */

$start_time = microtime(true);
$checkpoints = [];

// Checkpoint 1: Script start
$checkpoints['script_start'] = microtime(true) - $start_time;

// Checkpoint 2: Include database
require_once __DIR__ . '/db.php';
$checkpoints['db_include'] = microtime(true) - $start_time;

// Checkpoint 3: Database connection
$db_start = microtime(true);
try {
    $test_query = $pdo->query("SELECT 1");
    $checkpoints['db_connection'] = microtime(true) - $start_time;
    $db_connection_time = (microtime(true) - $db_start) * 1000;
} catch (Exception $e) {
    $checkpoints['db_connection_error'] = $e->getMessage();
    $db_connection_time = 0;
}

// Checkpoint 4: Simple query
$query_start = microtime(true);
try {
    $stmt = $pdo->query("SELECT count(*) FROM donors");
    $donor_count = $stmt->fetchColumn();
    $checkpoints['simple_query'] = microtime(true) - $start_time;
    $simple_query_time = (microtime(true) - $query_start) * 1000;
} catch (Exception $e) {
    $checkpoints['simple_query_error'] = $e->getMessage();
    $simple_query_time = 0;
}

// Checkpoint 5: Complex query (like admin dashboard might use)
$complex_start = microtime(true);
try {
    $stmt = $pdo->query("
        SELECT 
            d.blood_type, 
            count(*) as donor_count,
            avg(EXTRACT(YEAR FROM age(d.date_of_birth))) as avg_age
        FROM donors d 
        LEFT JOIN donations_new dn ON d.id = dn.donor_id 
        WHERE d.status = 'approved'
        GROUP BY d.blood_type 
        ORDER BY donor_count DESC
    ");
    $blood_stats = $stmt->fetchAll();
    $checkpoints['complex_query'] = microtime(true) - $start_time;
    $complex_query_time = (microtime(true) - $complex_start) * 1000;
} catch (Exception $e) {
    $checkpoints['complex_query_error'] = $e->getMessage();
    $complex_query_time = 0;
}

// Checkpoint 6: Multiple queries (simulating admin page)
$multi_start = microtime(true);
$query_times = [];

$test_queries = [
    "SELECT count(*) FROM donors WHERE status = 'approved'",
    "SELECT count(*) FROM blood_inventory WHERE expiry_date > CURRENT_DATE",
    "SELECT count(*) FROM donations_new WHERE donation_date >= CURRENT_DATE - INTERVAL '30 days'",
    "SELECT count(*) FROM admin_audit_log WHERE created_at >= CURRENT_DATE - INTERVAL '7 days'",
    "SELECT blood_type, count(*) FROM donors GROUP BY blood_type"
];

foreach ($test_queries as $i => $query) {
    $q_start = microtime(true);
    try {
        $stmt = $pdo->query($query);
        $result = $stmt->fetchAll();
        $query_times["query_" . ($i + 1)] = round((microtime(true) - $q_start) * 1000, 2);
    } catch (Exception $e) {
        $query_times["query_" . ($i + 1) . "_error"] = $e->getMessage();
    }
}

$checkpoints['multiple_queries'] = microtime(true) - $start_time;
$multi_query_time = (microtime(true) - $multi_start) * 1000;

// Checkpoint 7: File operations
$file_start = microtime(true);
$temp_file = __DIR__ . '/temp_test_' . time() . '.tmp';
file_put_contents($temp_file, 'test data');
$file_exists = file_exists($temp_file);
if ($file_exists) {
    unlink($temp_file);
}
$checkpoints['file_operations'] = microtime(true) - $start_time;
$file_time = (microtime(true) - $file_start) * 1000;

// Final checkpoint
$total_time = microtime(true) - $start_time;
$checkpoints['total_time'] = $total_time;

// Generate results
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_execution_time' => round($total_time * 1000, 2) . 'ms',
    'checkpoints_seconds' => array_map(function($time) {
        return is_numeric($time) ? round($time, 3) . 's' : $time;
    }, $checkpoints),
    'performance_breakdown' => [
        'db_connection' => round($db_connection_time, 2) . 'ms',
        'simple_query' => round($simple_query_time, 2) . 'ms', 
        'complex_query' => round($complex_query_time, 2) . 'ms',
        'multiple_queries_total' => round($multi_query_time, 2) . 'ms',
        'file_operations' => round($file_time, 2) . 'ms'
    ],
    'individual_query_times' => $query_times,
    'bottleneck_analysis' => [],
    'recommendations' => []
];

// Analyze bottlenecks
if ($db_connection_time > 500) {
    $results['bottleneck_analysis'][] = "Database connection is slow ({$db_connection_time}ms)";
    $results['recommendations'][] = "Optimize database connection pooling";
}

if ($simple_query_time > 200) {
    $results['bottleneck_analysis'][] = "Simple queries are slow ({$simple_query_time}ms)";
    $results['recommendations'][] = "Check database indexes and query optimization";
}

if ($complex_query_time > 500) {
    $results['bottleneck_analysis'][] = "Complex queries are very slow ({$complex_query_time}ms)";
    $results['recommendations'][] = "Add database indexes for JOIN operations";
}

if ($multi_query_time > 1000) {
    $results['bottleneck_analysis'][] = "Multiple queries taking too long ({$multi_query_time}ms total)";
    $results['recommendations'][] = "Consider query caching or combining queries";
}

$slow_queries = array_filter($query_times, function($time) {
    return is_numeric($time) && $time > 300;
});

if (count($slow_queries) > 0) {
    $results['bottleneck_analysis'][] = count($slow_queries) . " individual queries are slow (>300ms)";
    $results['recommendations'][] = "Optimize slow individual queries";
}

// Performance grade
$performance_score = 100;
$performance_score -= ($db_connection_time > 200) ? 20 : 0;
$performance_score -= ($simple_query_time > 100) ? 15 : 0;
$performance_score -= ($complex_query_time > 300) ? 25 : 0;
$performance_score -= (count($slow_queries) * 5);
$performance_score -= ($total_time > 2) ? 20 : 0;

$results['performance_score'] = max(0, $performance_score);
$results['performance_grade'] = $performance_score >= 80 ? 'A' : ($performance_score >= 60 ? 'B' : ($performance_score >= 40 ? 'C' : 'D'));

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
