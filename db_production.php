<?php
// Production database configuration for Render
// Uses environment variables

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/error.log');

// Get database credentials from environment variables
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'blood_system';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

define('DB_TYPE', 'mysql');
define('DB_HOST', $db_host);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    error_log("Database connection established successfully");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please check configuration.");
}

function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '" . $pdo->quote($table) . "'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking if table exists: " . $e->getMessage());
        return false;
    }
}

function getTableStructure($pdo, $table) {
    try {
        $stmt = $pdo->query("DESCRIBE `" . str_replace('`', '``', $table) . "`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting table structure: " . $e->getMessage());
        return [];
    }
}
?>