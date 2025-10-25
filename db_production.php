<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/error.log');

// Get database URL from Render PostgreSQL
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    $db = parse_url($database_url);
    define('DB_HOST', $db['host']);
    define('DB_NAME', ltrim($db['path'], '/'));
    define('DB_USER', $db['user']);
    define('DB_PASS', $db['pass']);
    define('DB_PORT', isset($db['port']) ? $db['port'] : 5432);
    
    try {
        $pdo = new PDO(
            "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        error_log("PostgreSQL connection established successfully");
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection error. Please check logs.");
    }
} else {
    die("DATABASE_URL not found. Please configure database connection.");
}

function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT to_regclass('public." . $table . "')");
        return $result->fetchColumn() !== null;
    } catch (PDOException $e) {
        return false;
    }
}

function getTableStructure($pdo, $table) {
    try {
        $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '" . $table . "'");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
?>