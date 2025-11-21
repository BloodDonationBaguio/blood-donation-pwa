<?php
/**
 * Performance Analyzer - Identifies system bottlenecks
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
set_time_limit(60);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'performance_issues' => [],
    'connection_analysis' => [],
    'query_performance' => [],
    'recommendations' => []
];

// 1. Analyze Connection Performance
function analyzeConnectionPerformance($pdo) {
    $tests = [];
    
    // Test multiple connections
    for ($i = 0; $i < 5; $i++) {
        $start = microtime(true);
        try {
            $stmt = $pdo->query("SELECT 1");
            $result = $stmt->fetchColumn();
            $time = round((microtime(true) - $start) * 1000, 2);
            $tests[] = $time;
        } catch (Exception $e) {
            $tests[] = 'FAILED';
        }
    }
    
    $valid_tests = array_filter($tests, 'is_numeric');
    
    return [
        'individual_tests' => $tests,
        'avg_time' => count($valid_tests) > 0 ? round(array_sum($valid_tests) / count($valid_tests), 2) : 0,
        'min_time' => count($valid_tests) > 0 ? min($valid_tests) : 0,
        'max_time' => count($valid_tests) > 0 ? max($valid_tests) : 0,
        'consistency' => count($valid_tests) > 0 ? (max($valid_tests) - min($valid_tests)) : 0
    ];
}

$results['connection_analysis'] = analyzeConnectionPerformance($pdo);

// 2. Test Query Performance
$test_queries = [
    'simple_select' => "SELECT 1",
    'timestamp' => "SELECT current_timestamp",
    'version' => "SELECT version()",
    'table_count' => "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'",
    'donor_count' => "SELECT count(*) FROM donors",
    'inventory_count' => "SELECT count(*) FROM blood_inventory",
    'recent_donations' => "SELECT count(*) FROM donations_new WHERE created_at >= CURRENT_DATE - INTERVAL '7 days'",
    'complex_join' => "SELECT d.blood_type, count(*) FROM donors d LEFT JOIN donations_new dn ON d.id = dn.donor_id GROUP BY d.blood_type"
];

foreach ($test_queries as $name => $query) {
    $start = microtime(true);
    try {
        $stmt = $pdo->query($query);
        $result = $stmt->fetchAll();
        $time = round((microtime(true) - $start) * 1000, 2);
        
        $results['query_performance'][$name] = [
            'time' => $time,
            'status' => 'success',
            'rows' => count($result),
            'query' => substr($query, 0, 50) . '...'
        ];
        
        if ($time > 500) {
            $results['performance_issues'][] = "Slow query: $name took {$time}ms";
        }
    } catch (Exception $e) {
        $results['query_performance'][$name] = [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'query' => substr($query, 0, 50) . '...'
        ];
    }
}

// 3. Database Connection Pool Analysis
try {
    $stmt = $pdo->query("
        SELECT 
            count(*) as total_connections,
            count(*) FILTER (WHERE state = 'active') as active_connections,
            count(*) FILTER (WHERE state = 'idle') as idle_connections,
            count(*) FILTER (WHERE state = 'idle in transaction') as idle_in_transaction,
            max(now() - query_start) as longest_running_query,
            avg(now() - query_start) as avg_query_time
        FROM pg_stat_activity 
        WHERE datname = current_database()
    ");
    
    $pool_stats = $stmt->fetch();
    $results['connection_pool'] = $pool_stats;
    
    if ($pool_stats['total_connections'] > 20) {
        $results['performance_issues'][] = "High connection count: {$pool_stats['total_connections']} connections";
    }
    
    if ($pool_stats['idle_in_transaction'] > 5) {
        $results['performance_issues'][] = "Too many idle transactions: {$pool_stats['idle_in_transaction']}";
    }
    
} catch (Exception $e) {
    $results['connection_pool'] = ['error' => $e->getMessage()];
}

// 4. Check for Slow Queries
try {
    $stmt = $pdo->query("
        SELECT 
            query,
            calls,
            total_time,
            mean_time,
            rows
        FROM pg_stat_statements 
        WHERE mean_time > 100 
        ORDER BY mean_time DESC 
        LIMIT 10
    ");
    
    $slow_queries = $stmt->fetchAll();
    $results['slow_queries'] = $slow_queries;
    
    if (count($slow_queries) > 0) {
        $results['performance_issues'][] = count($slow_queries) . " slow queries detected (>100ms average)";
    }
    
} catch (Exception $e) {
    $results['slow_queries'] = ['note' => 'pg_stat_statements not available: ' . $e->getMessage()];
}

// 5. Memory and Resource Analysis
$results['system_resources'] = [
    'php_memory_limit' => ini_get('memory_limit'),
    'current_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
    'peak_memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
    'max_execution_time' => ini_get('max_execution_time') . 's'
];

// 6. Network Latency Test
function testNetworkLatency($host, $port) {
    $start = microtime(true);
    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
    $latency = round((microtime(true) - $start) * 1000, 2);
    
    if ($connection) {
        fclose($connection);
        return ['latency' => $latency, 'status' => 'success'];
    } else {
        return ['latency' => $latency, 'status' => 'failed', 'error' => $errstr];
    }
}

$db_host = getenv('SUPABASE_DB_HOST');
if ($db_host) {
    $results['network_latency'] = testNetworkLatency($db_host, 5432);
    
    if ($results['network_latency']['latency'] > 200) {
        $results['performance_issues'][] = "High network latency: {$results['network_latency']['latency']}ms to database";
    }
}

// 7. Generate Recommendations
$recommendations = [];

// Connection performance
if ($results['connection_analysis']['avg_time'] > 300) {
    $recommendations[] = "Database connections are slow ({$results['connection_analysis']['avg_time']}ms). Consider connection pooling.";
}

if ($results['connection_analysis']['consistency'] > 500) {
    $recommendations[] = "Connection times are inconsistent. Check network stability.";
}

// Query performance
$slow_query_count = count(array_filter($results['query_performance'], function($q) {
    return isset($q['time']) && $q['time'] > 200;
}));

if ($slow_query_count > 0) {
    $recommendations[] = "$slow_query_count queries are running slowly. Consider database optimization.";
}

// System resources
$memory_usage = (int)str_replace('MB', '', $results['system_resources']['current_memory_usage']);
$memory_limit = (int)str_replace('M', '', $results['system_resources']['php_memory_limit']);

if ($memory_usage > ($memory_limit * 0.8)) {
    $recommendations[] = "High memory usage. Consider increasing PHP memory limit.";
}

// Network
if (isset($results['network_latency']) && $results['network_latency']['latency'] > 100) {
    $recommendations[] = "Network latency to database is high. Consider geographic optimization.";
}

// Connection pool
if (isset($results['connection_pool']['total_connections']) && $results['connection_pool']['total_connections'] > 15) {
    $recommendations[] = "High database connection count. Implement connection pooling.";
}

$results['recommendations'] = $recommendations;

// 8. Performance Score
$score = 100;
$score -= count($results['performance_issues']) * 10;
$score -= ($results['connection_analysis']['avg_time'] > 200) ? 20 : 0;
$score -= ($slow_query_count * 5);

$results['performance_score'] = max(0, $score);
$results['performance_grade'] = $score >= 80 ? 'A' : ($score >= 60 ? 'B' : ($score >= 40 ? 'C' : 'D'));

echo json_encode($results, JSON_PRETTY_PRINT);
?>
