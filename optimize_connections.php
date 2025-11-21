<?php
/**
 * Database Connection Optimization Script
 * Helps reduce connection timeouts and improve performance
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'optimizations' => [],
    'status' => 'success'
];

try {
    // 1. Test current connection performance
    $start_time = microtime(true);
    
    // Test basic connectivity
    $stmt = $pdo->query("SELECT 1 as test");
    $test_result = $stmt->fetchColumn();
    
    $connection_time = round((microtime(true) - $start_time) * 1000, 2);
    $results['current_connection_time'] = $connection_time . 'ms';
    
    if ($test_result !== 1) {
        throw new Exception('Basic connectivity test failed');
    }
    
    // 2. Optimize PostgreSQL connection settings
    $optimizations = [
        // Set connection timeout
        "SET statement_timeout = '30s'",
        
        // Set timezone to match application
        "SET timezone = 'Asia/Manila'",
        
        // Optimize for web application workload
        "SET work_mem = '4MB'",
        
        // Enable connection pooling optimizations
        "SET tcp_keepalives_idle = 600",
        "SET tcp_keepalives_interval = 30",
        "SET tcp_keepalives_count = 3"
    ];
    
    foreach ($optimizations as $sql) {
        try {
            $pdo->exec($sql);
            $results['optimizations'][] = [
                'query' => $sql,
                'status' => 'applied'
            ];
        } catch (PDOException $e) {
            $results['optimizations'][] = [
                'query' => $sql,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // 3. Test connection pool settings
    try {
        $stmt = $pdo->query("SHOW max_connections");
        $max_connections = $stmt->fetchColumn();
        $results['database_info']['max_connections'] = $max_connections;
        
        $stmt = $pdo->query("SELECT count(*) FROM pg_stat_activity WHERE state = 'active'");
        $active_connections = $stmt->fetchColumn();
        $results['database_info']['active_connections'] = $active_connections;
        
        $connection_usage = round(($active_connections / $max_connections) * 100, 2);
        $results['database_info']['connection_usage_percent'] = $connection_usage;
        
        if ($connection_usage > 80) {
            $results['warnings'][] = "High connection usage: {$connection_usage}%";
        }
        
    } catch (PDOException $e) {
        $results['warnings'][] = "Could not retrieve connection info: " . $e->getMessage();
    }
    
    // 4. Test query performance
    $performance_tests = [
        "SELECT version()" => "Database version check",
        "SELECT current_timestamp" => "Timestamp query",
        "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'" => "Table count"
    ];
    
    $results['performance_tests'] = [];
    foreach ($performance_tests as $query => $description) {
        $start = microtime(true);
        try {
            $stmt = $pdo->query($query);
            $result = $stmt->fetchColumn();
            $time = round((microtime(true) - $start) * 1000, 2);
            
            $results['performance_tests'][] = [
                'description' => $description,
                'query_time' => $time . 'ms',
                'status' => 'success',
                'result' => $result
            ];
        } catch (PDOException $e) {
            $results['performance_tests'][] = [
                'description' => $description,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // 5. Generate recommendations
    $recommendations = [];
    
    if ($connection_time > 1000) {
        $recommendations[] = "Connection time is slow ({$connection_time}ms). Consider using connection pooling.";
    }
    
    if (isset($connection_usage) && $connection_usage > 70) {
        $recommendations[] = "Consider implementing connection pooling to reduce database load.";
    }
    
    // Check for slow queries
    $slow_queries = array_filter($results['performance_tests'], function($test) {
        return isset($test['query_time']) && (float)str_replace('ms', '', $test['query_time']) > 500;
    });
    
    if (!empty($slow_queries)) {
        $recommendations[] = "Some queries are running slowly. Consider database optimization.";
    }
    
    $results['recommendations'] = $recommendations;
    
    // 6. Test after optimizations
    $start_time = microtime(true);
    $stmt = $pdo->query("SELECT 1 as test");
    $test_result = $stmt->fetchColumn();
    $optimized_time = round((microtime(true) - $start_time) * 1000, 2);
    
    $results['optimized_connection_time'] = $optimized_time . 'ms';
    
    if ($optimized_time < $connection_time) {
        $improvement = round((($connection_time - $optimized_time) / $connection_time) * 100, 2);
        $results['improvement'] = "{$improvement}% faster";
    }
    
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['error'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
