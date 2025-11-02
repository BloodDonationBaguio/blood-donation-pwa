<?php
// Lightweight health check endpoint
// Returns HTTP 200 with JSON and proper no-cache headers

// Force minimal resource usage
@ini_set('memory_limit', '32M');
@ini_set('max_execution_time', '5');

// Prevent session and heavy includes for health
// Use microtime for quick timing and avoid DB unless needed

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
    'server_time' => $serverTime
];

// Graceful error handling
try {
    http_response_code(200);
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