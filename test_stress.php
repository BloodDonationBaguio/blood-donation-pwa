<?php
/**
 * Stress Testing Suite
 * Tests system under heavy load
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
set_time_limit(300); // 5 minutes

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'stress_tests' => [],
    'system_info' => [
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'start_memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
    ]
];

// Stress Test 1: Multiple Rapid Queries
function stressTestQueries($pdo, $query_count = 1000) {
    $start_time = microtime(true);
    $successful = 0;
    $failed = 0;
    $times = [];
    
    for ($i = 0; $i < $query_count; $i++) {
        $query_start = microtime(true);
        try {
            $stmt = $pdo->query("SELECT $i as test_number, current_timestamp");
            $result = $stmt->fetch();
            $successful++;
        } catch (Exception $e) {
            $failed++;
        }
        $times[] = round((microtime(true) - $query_start) * 1000, 2);
    }
    
    return [
        'total_queries' => $query_count,
        'successful' => $successful,
        'failed' => $failed,
        'success_rate' => round(($successful / $query_count) * 100, 2) . '%',
        'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
        'avg_query_time' => round(array_sum($times) / count($times), 2) . 'ms',
        'min_time' => min($times) . 'ms',
        'max_time' => max($times) . 'ms',
        'queries_per_second' => round($query_count / (microtime(true) - $start_time), 2)
    ];
}

$results['stress_tests']['rapid_queries_100'] = stressTestQueries($pdo, 100);
$results['stress_tests']['rapid_queries_500'] = stressTestQueries($pdo, 500);

// Stress Test 2: Large Data Processing
function stressTestLargeData($pdo) {
    $start_time = microtime(true);
    $start_memory = memory_get_usage(true);
    
    try {
        // Create large temporary dataset
        $pdo->exec("CREATE TEMP TABLE stress_test_data AS 
                   SELECT 
                       generate_series(1, 50000) as id,
                       md5(random()::text) as hash_value,
                       random() * 1000 as score,
                       current_timestamp - (random() * interval '365 days') as created_at");
        
        $creation_time = microtime(true);
        
        // Perform complex operations
        $operations = [
            "SELECT COUNT(*) FROM stress_test_data",
            "SELECT AVG(score), MIN(score), MAX(score) FROM stress_test_data",
            "SELECT COUNT(*) FROM stress_test_data WHERE score > 500",
            "SELECT date_trunc('month', created_at), COUNT(*) FROM stress_test_data GROUP BY 1 ORDER BY 1",
            "UPDATE stress_test_data SET score = score * 1.1 WHERE id % 1000 = 0"
        ];
        
        $operation_results = [];
        foreach ($operations as $op) {
            $op_start = microtime(true);
            $stmt = $pdo->query($op);
            $op_time = round((microtime(true) - $op_start) * 1000, 2);
            
            if (strpos($op, 'SELECT') === 0) {
                $result = $stmt->fetchAll();
                $operation_results[] = [
                    'operation' => substr($op, 0, 50) . '...',
                    'time' => $op_time . 'ms',
                    'rows' => count($result)
                ];
            } else {
                $rows_affected = $stmt->rowCount();
                $operation_results[] = [
                    'operation' => substr($op, 0, 50) . '...',
                    'time' => $op_time . 'ms',
                    'rows_affected' => $rows_affected
                ];
            }
        }
        
        // Clean up
        $pdo->exec("DROP TABLE stress_test_data");
        
        $total_time = round((microtime(true) - $start_time) * 1000, 2);
        $peak_memory = memory_get_peak_usage(true);
        $memory_used = round(($peak_memory - $start_memory) / 1024 / 1024, 2);
        
        return [
            'status' => 'success',
            'total_time' => $total_time . 'ms',
            'data_creation_time' => round(($creation_time - $start_time) * 1000, 2) . 'ms',
            'memory_used' => $memory_used . 'MB',
            'operations' => $operation_results
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'time_before_failure' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
        ];
    }
}

$results['stress_tests']['large_data_processing'] = stressTestLargeData($pdo);

// Stress Test 3: Transaction Stress
function stressTestTransactions($pdo, $transaction_count = 100) {
    $start_time = microtime(true);
    $successful = 0;
    $failed = 0;
    $times = [];
    
    for ($i = 0; $i < $transaction_count; $i++) {
        $trans_start = microtime(true);
        try {
            $pdo->beginTransaction();
            
            // Multiple operations in transaction
            $pdo->exec("CREATE TEMP TABLE IF NOT EXISTS trans_test_$i (id SERIAL, data TEXT)");
            
            for ($j = 0; $j < 5; $j++) {
                $stmt = $pdo->prepare("INSERT INTO trans_test_$i (data) VALUES (?)");
                $stmt->execute(["test_data_{$i}_{$j}"]);
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM trans_test_$i");
            $count = $stmt->fetchColumn();
            
            if ($count === 5) {
                $pdo->commit();
                $successful++;
            } else {
                $pdo->rollback();
                $failed++;
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $failed++;
        }
        
        $times[] = round((microtime(true) - $trans_start) * 1000, 2);
    }
    
    return [
        'total_transactions' => $transaction_count,
        'successful' => $successful,
        'failed' => $failed,
        'success_rate' => round(($successful / $transaction_count) * 100, 2) . '%',
        'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
        'avg_transaction_time' => round(array_sum($times) / count($times), 2) . 'ms',
        'transactions_per_second' => round($transaction_count / (microtime(true) - $start_time), 2)
    ];
}

$results['stress_tests']['transaction_stress'] = stressTestTransactions($pdo, 50);

// Stress Test 4: Memory Usage Test
function stressTestMemory() {
    $start_memory = memory_get_usage(true);
    $data_arrays = [];
    
    // Progressively allocate memory
    for ($i = 0; $i < 100; $i++) {
        $data_arrays[] = array_fill(0, 1000, str_repeat('x', 100)); // ~100KB per iteration
        
        if ($i % 10 === 0) {
            $current_memory = memory_get_usage(true);
            $memory_increase = round(($current_memory - $start_memory) / 1024 / 1024, 2);
            
            if ($memory_increase > 50) { // Stop if using more than 50MB
                break;
            }
        }
    }
    
    $peak_memory = memory_get_peak_usage(true);
    $final_memory = memory_get_usage(true);
    
    // Clean up
    unset($data_arrays);
    
    $after_cleanup = memory_get_usage(true);
    
    return [
        'start_memory' => round($start_memory / 1024 / 1024, 2) . 'MB',
        'peak_memory' => round($peak_memory / 1024 / 1024, 2) . 'MB',
        'final_memory' => round($final_memory / 1024 / 1024, 2) . 'MB',
        'after_cleanup' => round($after_cleanup / 1024 / 1024, 2) . 'MB',
        'memory_allocated' => round(($peak_memory - $start_memory) / 1024 / 1024, 2) . 'MB',
        'memory_freed' => round(($final_memory - $after_cleanup) / 1024 / 1024, 2) . 'MB',
        'iterations_completed' => $i
    ];
}

$results['stress_tests']['memory_usage'] = stressTestMemory();

// Stress Test 5: Connection Pool Test
function stressTestConnections($connection_count = 20) {
    $start_time = microtime(true);
    $successful_connections = 0;
    $failed_connections = 0;
    $connection_times = [];
    
    $host = getenv('SUPABASE_DB_HOST');
    $port = getenv('SUPABASE_DB_PORT') ?: 5432;
    $dbname = getenv('SUPABASE_DB_NAME') ?: 'postgres';
    $user = getenv('SUPABASE_DB_USER') ?: 'postgres';
    $password = getenv('SUPABASE_DB_PASSWORD');
    
    for ($i = 0; $i < $connection_count; $i++) {
        $conn_start = microtime(true);
        try {
            $pdo = new PDO(
                "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
            );
            
            $stmt = $pdo->query("SELECT 1");
            $result = $stmt->fetchColumn();
            
            if ($result === 1) {
                $successful_connections++;
            }
            
            $pdo = null; // Close connection
            
        } catch (Exception $e) {
            $failed_connections++;
        }
        
        $connection_times[] = round((microtime(true) - $conn_start) * 1000, 2);
    }
    
    return [
        'total_attempts' => $connection_count,
        'successful' => $successful_connections,
        'failed' => $failed_connections,
        'success_rate' => round(($successful_connections / $connection_count) * 100, 2) . '%',
        'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
        'avg_connection_time' => round(array_sum($connection_times) / count($connection_times), 2) . 'ms',
        'min_connection_time' => min($connection_times) . 'ms',
        'max_connection_time' => max($connection_times) . 'ms'
    ];
}

$results['stress_tests']['connection_pool'] = stressTestConnections(15);

// Final System Information
$results['final_system_info'] = [
    'peak_memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
    'current_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
    'total_execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
    'memory_limit' => ini_get('memory_limit'),
    'time_limit' => ini_get('max_execution_time') . 's'
];

// Overall Assessment
$all_tests = $results['stress_tests'];
$failed_tests = array_filter($all_tests, function($test) {
    return isset($test['status']) && $test['status'] === 'failed';
});

$results['overall_assessment'] = [
    'total_stress_tests' => count($all_tests),
    'failed_tests' => count($failed_tests),
    'system_stability' => count($failed_tests) === 0 ? 'STABLE' : 'UNSTABLE',
    'performance_rating' => count($failed_tests) === 0 ? 'GOOD' : 'NEEDS_IMPROVEMENT',
    'recommendations' => count($failed_tests) > 0 ? 
        ['Review failed tests', 'Consider resource optimization', 'Monitor system performance'] :
        ['System performing well under stress', 'Continue monitoring', 'Consider scaling if needed']
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
