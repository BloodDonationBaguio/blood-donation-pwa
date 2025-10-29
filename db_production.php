<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/error.log');

// Load .env if present for deployments that use plain env vars
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) { continue; }
        if (strpos($line, '=') !== false) {
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            putenv("$k=$v");
        }
    }
}

// Primary: Render/Heroku-style DATABASE_URL (PostgreSQL)
$database_url = getenv('DATABASE_URL');
$pdo = null;

if ($database_url) {
    $db = parse_url($database_url);
    $dbHost = $db['host'] ?? 'localhost';
    $dbName = ltrim($db['path'] ?? '', '/');
    $dbUser = $db['user'] ?? '';
    $dbPass = $db['pass'] ?? '';
    $dbPort = isset($db['port']) ? $db['port'] : 5432;

    try {
        $pdo = new PDO(
            "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        error_log("PostgreSQL connection established successfully (DATABASE_URL)");
    } catch (PDOException $e) {
        error_log("PostgreSQL connection failed (DATABASE_URL): " . $e->getMessage());
        $pdo = null; // fall back to other strategies
    }
}

// Fallback: explicit env vars (DB_TYPE, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS)
if ($pdo === null) {
    $envDbType = getenv('DB_TYPE') ?: 'mysql';
    $envHost   = getenv('DB_HOST') ?: null;
    $envPort   = getenv('DB_PORT') ?: null; // optional
    $envName   = getenv('DB_NAME') ?: null;
    $envUser   = getenv('DB_USER') ?: null;
    $envPass   = getenv('DB_PASS') ?: null;

    if ($envHost && $envName && $envUser) {
        try {
            if (in_array(strtolower($envDbType), ['pgsql','postgres','postgresql'], true)) {
                $dsn = "pgsql:host={$envHost}" . ($envPort ? ";port={$envPort}" : '') . ";dbname={$envName}";
            } else {
                $dsn = "mysql:host={$envHost}" . ($envPort ? ";port={$envPort}" : '') . ";dbname={$envName};charset=utf8mb4";
            }

            $pdo = new PDO(
                $dsn,
                $envUser,
                $envPass ?: '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            error_log("Database connection established via explicit env vars ({$envDbType})");
        } catch (PDOException $e) {
            error_log("Database connection failed via explicit env vars: " . $e->getMessage());
            $pdo = null;
        }
    }
}

// Last resort: include a local config file that defines $pdo
// Prefer db.php first to avoid includes/config.php hard-failing on connection errors
if ($pdo === null) {
    $candidates = [
        __DIR__ . '/db.php',            // local MySQL fallback (preferred)
        __DIR__ . '/db.example.php',    // template with defaults
        __DIR__ . '/includes/config.php', // legacy config (use as last resort)
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            require_once $path;
            if (isset($pdo) && $pdo instanceof PDO) {
                error_log("Database connection established via included config: " . basename($path));
                break;
            }
        }
    }
}

if ($pdo === null) {
    die("Database not configured. Set 'DATABASE_URL' (PostgreSQL) OR 'DB_HOST/DB_NAME/DB_USER/DB_PASS' env vars. Alternatively, configure includes/config.php. Domain: " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown'));
}

// Helper functions compatible with both MySQL and PostgreSQL
if (!function_exists('tableExists')) {
    function tableExists($pdo, $table) {
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $safe = str_replace("'", "''", $table);
                $stmt = $pdo->query("SELECT to_regclass('public." . $safe . "')");
                return $stmt->fetchColumn() !== null;
            } else {
                $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
                return $stmt->rowCount() > 0;
            }
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('getTableStructure')) {
    function getTableStructure($pdo, $table) {
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $safe = str_replace("'", "''", $table);
                $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '" . $safe . "'");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query("DESCRIBE `" . str_replace('`', '``', $table) . "`");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>