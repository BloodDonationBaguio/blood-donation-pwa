<?php
// scripts/diagnostics/list_pg_tables.php
// Print public schema tables using the app's production DB bootstrap

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__, 2);
require_once $root . '/db_production.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "No PDO connection. Ensure DATABASE_URL is set for PostgreSQL.\n");
    exit(1);
}

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'pgsql') {
        fwrite(STDERR, "Connected driver is '{$driver}', expected 'pgsql'.\n");
        exit(1);
    }
} catch (Throwable $e) {}

$sql = "SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY 1";
$stmt = $pdo->query($sql);
foreach ($stmt as $row) {
    echo $row['table_name'], "\n";
}