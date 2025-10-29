<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';

try {
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    echo "Driver: {$driver}\n";
    // Current database/schema
    if ($driver === 'pgsql') {
        $stmt = $pdo->query("SELECT current_database()");
        $dbName = $stmt->fetchColumn();
        echo "Database: {$dbName}\n";
        $stmt = $pdo->query("SELECT to_regclass('public.blood_inventory')");
        $exists = $stmt->fetchColumn() !== null;
        echo "blood_inventory exists: " . ($exists ? 'YES' : 'NO') . "\n";
    } else {
        $stmt = $pdo->query("SELECT DATABASE()");
        $dbName = $stmt->fetchColumn();
        echo "Database: {$dbName}\n";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'blood_inventory'");
        $stmt->execute();
        $exists = (int)$stmt->fetchColumn() > 0;
        echo "blood_inventory exists: " . ($exists ? 'YES' : 'NO') . "\n";
        // List any similarly named tables
        $list = $pdo->query("SHOW TABLES LIKE 'blood%'")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables like 'blood%': " . (empty($list) ? 'NONE' : implode(', ', $list)) . "\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}