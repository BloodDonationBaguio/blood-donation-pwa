<?php
// Enhanced health check endpoint with database connectivity
// Returns HTTP 200 with JSON and proper no-cache headers

// Force minimal resource usage
@ini_set('memory_limit', '64M');
@ini_set('max_execution_time', '10');

// Headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Build response
$now = new DateTime('now', new DateTimeZone('UTC'));
$serverTime = date('Y-m-d H:i:s');

$response = [
    'status' => 'ok',
    'timestamp' => $now->format('c'),
    'server_time' => $serverTime,
    'checks' => [
        'php' => 'ok',
        'database' => 'unknown'
    ]
];

// Quick database connectivity check
try {
    // Load environment variables
    $supabase_host = getenv('SUPABASE_DB_HOST');
    $supabase_password = getenv('SUPABASE_DB_PASSWORD');
    
    if ($supabase_host && $supabase_password) {
        $start_time = microtime(true);
        $pdo = new PDO(
            "pgsql:host={$supabase_host};port=5432;dbname=postgres;sslmode=require",
            getenv('SUPABASE_DB_USER') ?: 'postgres',
            $supabase_password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // Quick test query
        $stmt = $pdo->query("SELECT 1");
        $result = $stmt->fetchColumn();
        
        if ($result === 1) {
            $response['checks']['database'] = 'ok';
            $response['database_response_time'] = round((microtime(true) - $start_time) * 1000, 2);
        } else {
            $response['checks']['database'] = 'error';
            $response['status'] = 'degraded';
        }
    } else {
        $response['checks']['database'] = 'not_configured';
    }
} catch (Throwable $e) {
    $response['checks']['database'] = 'error';
    $response['database_error'] = $e->getMessage();
    $response['status'] = 'degraded';
}

// Determine overall status
if ($response['checks']['database'] === 'error') {
    http_response_code(503); // Service Unavailable
    $response['status'] = 'unhealthy';
} elseif ($response['status'] === 'degraded') {
    http_response_code(200); // Still OK but with warnings
} else {
    http_response_code(200);
}

// Graceful error handling
try {
    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Health check failed',
        'timestamp' => $now->format('c')
    ]);
}
?>