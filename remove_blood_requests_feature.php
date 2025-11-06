<?php
// remove_blood_requests_feature.php
// Permanently removes blood request-related tables and views safely.
// Read-only for other data; idempotent drops with driver-aware handling.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Manila');
}

// Try to include DB connector
$dbIncludePaths = [
    __DIR__ . '/db_production.php',
    __DIR__ . '/db.php',
    __DIR__ . '/blood-donation-pwa/db_production.php',
    __DIR__ . '/blood-donation-pwa/db.php',
];
$includedDb = false;
foreach ($dbIncludePaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $includedDb = true;
        break;
    }
}
if (!$includedDb) {
    http_response_code(500);
    die('Unable to locate database connector.');
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('connectWithRetry')) {
        $pdo = connectWithRetry();
    } else {
        http_response_code(500);
        die('Database connection ($pdo) not initialized.');
    }
}

$driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

// Helper to run a statement and capture outcome
function exec_drop(PDO $pdo, string $sql): array {
    try {
        $pdo->exec($sql);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Build driver-specific drop commands
$results = [];
if ($driver === 'pgsql') {
    // Drop potential view first, then tables
    $results['drop_requests_view'] = exec_drop($pdo, 'DROP VIEW IF EXISTS requests CASCADE');
    $results['drop_blood_requests_inventory'] = exec_drop($pdo, 'DROP TABLE IF EXISTS blood_requests_inventory CASCADE');
    $results['drop_blood_requests'] = exec_drop($pdo, 'DROP TABLE IF EXISTS blood_requests CASCADE');
    $results['drop_requests_table'] = exec_drop($pdo, 'DROP TABLE IF EXISTS requests CASCADE');
} elseif ($driver === 'mysql') {
    // Drop potential view first, then tables; order child before parent
    $results['drop_requests_view'] = exec_drop($pdo, 'DROP VIEW IF EXISTS `requests`');
    $results['drop_blood_requests_inventory'] = exec_drop($pdo, 'DROP TABLE IF EXISTS `blood_requests_inventory`');
    $results['drop_blood_requests'] = exec_drop($pdo, 'DROP TABLE IF EXISTS `blood_requests`');
    $results['drop_requests_table'] = exec_drop($pdo, 'DROP TABLE IF EXISTS `requests`');
} elseif ($driver === 'sqlite') {
    // SQLite does not enforce CASCADE on DROP; drops are idempotent
    $results['drop_requests_view'] = exec_drop($pdo, 'DROP VIEW IF EXISTS requests');
    $results['drop_blood_requests_inventory'] = exec_drop($pdo, 'DROP TABLE IF EXISTS blood_requests_inventory');
    $results['drop_blood_requests'] = exec_drop($pdo, 'DROP TABLE IF EXISTS blood_requests');
    $results['drop_requests_table'] = exec_drop($pdo, 'DROP TABLE IF EXISTS requests');
} else {
    http_response_code(500);
    die('Unsupported driver: ' . htmlspecialchars($driver));
}

header('Content-Type: application/json');
echo json_encode([
    'timestamp' => date('c'),
    'driver' => $driver,
    'results' => $results,
    'message' => 'Blood request-related tables and views have been removed if present.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);