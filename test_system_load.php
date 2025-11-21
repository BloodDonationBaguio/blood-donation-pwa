<?php
/**
 * System Load Testing Suite
 * Tests system performance under various loads
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
set_time_limit(300); // 5 minutes max

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'load_tests' => [],
    'memory_tests' => [],
    'concurrent_tests' => [],
    'stress_tests' => [],
    'system_info' => []
];

// System Information
$results['system_info'] = [
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'peak_memory_usage' => memory_get_peak_usage(true),
    'current_memory_usage' => memory_get_usage(true),
    'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : 'Not available'
];

// 1. Database Connection Load Test
function testConnectionLoad($connections = 10) {
    global $results;
    $start_time = microtime(true);
    $successful_connections = 0;
    $failed_connections = 0;
    $connection_times = [];
    
    for ($i = 0; $i < $connections; $i++) {
        $conn_start = microtime(true);
        try {
            $host = getenv('SUPABASE_DB_HOST');
            $port = getenv('SUPABASE_DB_PORT') ?: 5432;
            $dbname = getenv('SUPABASE_DB_NAME') ?: 'postgres';
            $user = getenv('SUPABASE_DB_USER') ?: 'postgres';
            $password = getenv('SUPABASE_DB_PASSWORD');
            
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
            
            $connection_times[] = round((microtime(true) - $conn_start) * 1000, 2);
            
        } catch (Exception $e) {
            $failed_connections++;
            $connection_times[] = round((microtime(true) - $conn_start) * 1000, 2);
        }
    }
    
    return [
        'total_connections' => $connections,
        'successful' => $successful_connections,
        'failed' => $failed_connections,
        'success_rate' => round(($successful_connections / $connections) * 100, 2) . '%',
        'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
        'avg_connection_time' => round(array_sum($connection_times) / count($connection_times), 2) . 'ms',
        'min_connection_time' => min($connection_times) . 'ms',
        'max_connection_time' => max($connection_times) . 'ms'
    ];
}

$results['load_tests']['connection_load_10'] = testConnectionLoad(10);
$results['load_tests']['connection_load_25'] = testConnectionLoad(25);
$results['load_tests']['connection_load_50'] = testConnectionLoad(50);

// 2. Query Performance Under Load
function testQueryLoad($queries = 100) {
    global $pdo;
    $start_time = microtime(true);
    $query_times = [];
    $successful_queries = 0;
    $failed_queries = 0;
    
    $test_queries = [
        "SELECT 1",
        "SELECT current_timestamp",
        "SELECT version()",
        "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'",
        "SELECT pg_database_size(current_database())"
    ];
    
    for ($i = 0; $i < $queries; $i++) {
        $query_start = microtime(true);
        try {
            $query = $test_queries[$i % count($test_queries)];
            $stmt = $pdo->query($query);
            $result = $stmt->fetchColumn();
            $successful_queries++;
        } catch (Exception $e) {
            $failed_queries++;
        }
        $query_times[] = round((microtime(true) - $query_start) * 1000, 2);
    }
    
    return [
        'total_queries' => $queries,
        'successful' => $successful_queries,
        'failed' => $failed_queries,
        'success_rate' => round(($successful_queries / $queries) * 100, 2) . '%',
        'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
        'avg_query_time' => round(array_sum($query_times) / count($query_times), 2) . 'ms',
        'queries_per_second' => round($queries / ((microtime(true) - $start_time)), 2)
    ];
}

$results['load_tests']['query_load_100'] = testQueryLoad(100);
$results['load_tests']['query_load_500'] = testQueryLoad(500);

// 3. Memory Usage Test
function testMemoryUsage() {
    $start_memory = memory_get_usage(true);
    $data = [];
    
    // Allocate memory progressively
    for ($i = 0; $i < 1000; $i++) {
        $data[] = str_repeat('x', 1000); // 1KB per iteration
    }
    
    $peak_memory = memory_get_peak_usage(true);
    $current_memory = memory_get_usage(true);
    
    // Clean up
    unset($data);
    
    $final_memory = memory_get_usage(true);
    
    return [
        'start_memory' => round($start_memory / 1024 / 1024, 2) . 'MB',
        'peak_memory' => round($peak_memory / 1024 / 1024, 2) . 'MB',
        'current_memory' => round($current_memory / 1024 / 1024, 2) . 'MB',
        'final_memory' => round($final_memory / 1024 / 1024, 2) . 'MB',
        'memory_allocated' => round(($peak_memory - $start_memory) / 1024 / 1024, 2) . 'MB',
        'memory_freed' => round(($current_memory - $final_memory) / 1024 / 1024, 2) . 'MB'
    ];
}

$results['memory_tests']['allocation_test'] = testMemoryUsage();

// 4. Transaction Stress Test
function testTransactionStress($transactions = 50) {
    global $pdo;
    $start_time = microtime(true);
    $successful_transactions = 0;
    $failed_transactions = 0;
    $transaction_times = [];
    
    for ($i = 0; $i < $transactions; $i++) {
        $trans_start = microtime(true);
        try {
            $pdo->beginTransaction();
            
            // Simulate multiple operations in transaction
            $pdo->exec("CREATE TEMP TABLE IF NOT EXISTS test_trans_$i (id SERIAL, data TEXT)");
            $pdo->exec("INSERT INTO test_trans_$i (data) VALUES ('test_data_$i')");
            $stmt = $pdo->query("SELECT COUNT(*) FROM test_trans_$i");
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $pdo->commit();
                $successful_transactions++;
            } else {
                $pdo->rollback();
                $failed_transactions++;
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $failed_transactions++;
        }
        
        $transaction_times[] = round((microtime(true) - $trans_start) * 1000, 2);
    }
    
    return [
        'total_transactions' => $transactions,
        'successful' => $successful_transactions,
        'failed' => $failed_transactions,
        'success_rate' => round(($successful_transactions / $transactions) * 100, 2) . '%',
        'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
        'avg_transaction_time' => round(array_sum($transaction_times) / count($transaction_times), 2) . 'ms'
    ];
}

$results['stress_tests']['transaction_stress'] = testTransactionStress(50);

// 5. Large Data Processing Test
function testLargeDataProcessing() {
    global $pdo;
    $start_time = microtime(true);
    
    try {
        // Create temporary table with large dataset
        $pdo->exec("CREATE TEMP TABLE large_test_data AS 
                   SELECT generate_series(1, 10000) as id, 
                          md5(random()::text) as data,
                          random() * 100 as score");
        
        // Perform various operations
        $operations = [
            "SELECT COUNT(*) FROM large_test_data",
            "SELECT AVG(score) FROM large_test_data",
            "SELECT * FROM large_test_data WHERE score > 90 ORDER BY score DESC LIMIT 100",
            "SELECT data, COUNT(*) FROM large_test_data GROUP BY data HAVING COUNT(*) > 1",
            "UPDATE large_test_data SET score = score * 1.1 WHERE id % 100 = 0"
        ];
        
        $operation_results = [];
        foreach ($operations as $op) {
            $op_start = microtime(true);
            $stmt = $pdo->query($op);
            if (strpos($op, 'SELECT') === 0) {
                $result = $stmt->fetchAll();
                $operation_results[] = [
                    'operation' => substr($op, 0, 50) . '...',
                    'time' => round((microtime(true) - $op_start) * 1000, 2) . 'ms',
                    'rows_affected' => count($result)
                ];
            } else {
                $rows_affected = $stmt->rowCount();
                $operation_results[] = [
                    'operation' => substr($op, 0, 50) . '...',
                    'time' => round((microtime(true) - $op_start) * 1000, 2) . 'ms',
                    'rows_affected' => $rows_affected
                ];
            }
        }
        
        // Clean up
        $pdo->exec("DROP TABLE large_test_data");
        
        return [
            'status' => 'success',
            'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
            'operations' => $operation_results
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'total_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
        ];
    }
}

$results['stress_tests']['large_data_processing'] = testLargeDataProcessing();

// 6. Concurrent Connection Simulation
function testConcurrentConnections() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                count(*) as total_connections,
                count(*) FILTER (WHERE state = 'active') as active,
                count(*) FILTER (WHERE state = 'idle') as idle,
                count(*) FILTER (WHERE state = 'idle in transaction') as idle_in_transaction,
                max(now() - query_start) as longest_running_query
            FROM pg_stat_activity 
            WHERE datname = current_database()
        ");
        
        $stats = $stmt->fetch();
        
        return [
            'status' => 'success',
            'connection_stats' => $stats,
            'recommendation' => $stats['total_connections'] > 50 ? 'Consider connection pooling' : 'Connection usage is healthy'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'failed',
            'error' => $e->getMessage()
        ];
    }
}

$results['concurrent_tests']['connection_analysis'] = testConcurrentConnections();

// Final system status
$results['final_system_info'] = [
    'peak_memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
    'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
    'memory_limit' => ini_get('memory_limit'),
    'memory_usage_percent' => round((memory_get_peak_usage(true) / (int)str_replace('M', '', ini_get('memory_limit')) / 1024 / 1024) * 100, 2) . '%'
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
