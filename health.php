<?php
// Basic health check endpoint for uptime monitoring and rapid diagnostics
// Returns JSON with app status and database connectivity

header('Content-Type: application/json');

$status = [
    'app' => 'ok',
    'time' => date('c'),
    'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'db' => [
        'connected' => false,
        'driver' => null,
        'ping' => false,
        'tables' => []
    ]
];

// Try to use the production DB bootstrap, which falls back to local config
try {
    require_once __DIR__ . '/db_production.php';

    if (isset($pdo) && $pdo instanceof PDO) {
        $status['db']['connected'] = true;
        $status['db']['driver'] = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Simple ping
        try {
            $pdo->query('SELECT 1');
            $status['db']['ping'] = true;
        } catch (Throwable $e) {
            $status['db']['ping'] = false;
        }

        // Table existence checks using helper from db_production.php when available
        if (function_exists('tableExists')) {
            foreach (['users', 'donors', 'requests', 'blood_inventory'] as $tbl) {
                $status['db']['tables'][$tbl] = tableExists($pdo, $tbl);
            }
        }
    }
} catch (Throwable $e) {
    // Swallow errors and report as degraded
}

// Decide HTTP status: healthy only if DB is connected and ping succeeds
$healthy = $status['db']['connected'] && $status['db']['ping'];
http_response_code($healthy ? 200 : 503);

echo json_encode($status);
?>