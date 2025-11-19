<?php
/**
 * Updated Database Configuration for Supabase Migration
 * This file replaces both db.php and db_production.php to use Supabase
 */

// Determine which database to use based on environment
$use_supabase_password = getenv('SUPABASE_DB_PASSWORD') || getenv('SUPABASE_CONNECTION_STRING');
$use_supabase_service = getenv('SUPABASE_URL') && getenv('SUPABASE_SERVICE_ROLE_KEY');
$use_supabase = $use_supabase_password || $use_supabase_service;
$use_render = getenv('DATABASE_URL');

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/database_error.log');

// Choose database connection based on available credentials
if ($use_supabase) {
    // Use Supabase (preferred for migration)
    error_log("Using Supabase database connection");
    require_once __DIR__ . '/supabase_db.php';
} elseif ($use_render) {
    // Fallback to Render PostgreSQL
    error_log("Using Render PostgreSQL connection");
    require_once __DIR__ . '/db_production.php';
} else {
    // Local development fallback to MySQL/SQLite
    error_log("Using local database connection (MySQL/SQLite)");
    
    // Local/dev fallback: MySQL or SQLite
    $db_type = getenv('DB_TYPE') ?: 'mysql';
    
    try {
        if ($db_type === 'sqlite') {
            // SQLite configuration
            $db_file = __DIR__ . '/database/blood_system.db';
            $dbDir = dirname($db_file);
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $pdo = new PDO(
                "sqlite:" . $db_file,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            $pdo->exec('PRAGMA foreign_keys = ON');
            
        } else {
            // MySQL configuration for local development
            $envHost = getenv('DB_HOST') ?: 'localhost';
            $envUser = getenv('DB_USER') ?: 'root';
            $envPass = getenv('DB_PASS') ?: '';
            $envDb = getenv('DB_NAME') ?: 'blood_system';
            $envPort = getenv('DB_PORT') ?: 3306;
            
            $dsn = "mysql:host={$envHost};port={$envPort};dbname={$envDb};charset=utf8mb4";
            $pdo = new PDO(
                $dsn,
                $envUser,
                $envPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_AUTOCOMMIT => true
                ]
            );
        }
        
        error_log("Local database connection established successfully");
        
    } catch (PDOException $e) {
        error_log("Local database connection failed: " . $e->getMessage());
        die("<div style='font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 6px;'>
            <h2>Database Connection Error</h2>
            <p>Unable to connect to any database. Please configure:</p>
            <ul>
                <li><strong>Supabase:</strong> Set SUPABASE_URL and SUPABASE_DB_PASSWORD (Settings → Database)</li>
                <li><strong>Render:</strong> Set DATABASE_URL</li>
                <li><strong>Local:</strong> Set DB_HOST, DB_USER, DB_PASS, DB_NAME</li>
            </ul>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        </div>");
    }
}

// Ensure helper functions exist (in case they weren't loaded by other files)
if (!function_exists('tableExists')) {
    function tableExists($pdo, $table) {
        try {
            if ($use_supabase) {
                // Supabase/PostgreSQL
                $result = $pdo->query("SELECT to_regclass('public." . $table . "')");
                return $result->fetchColumn() !== null;
            } elseif ($use_render) {
                // Render PostgreSQL
                $result = $pdo->query("SELECT to_regclass('public." . $table . "')");
                return $result->fetchColumn() !== null;
            } else {
                // Local MySQL/SQLite
                $db_type = getenv('DB_TYPE') ?: 'mysql';
                if ($db_type === 'sqlite') {
                    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $pdo->quote($table) . "'");
                } else {
                    $result = $pdo->query("SHOW TABLES LIKE '" . $pdo->quote($table) . "'");
                }
                return $result->rowCount() > 0;
            }
        } catch (PDOException $e) {
            error_log("Error checking table existence: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getTableStructure')) {
    function getTableStructure($pdo, $table) {
        try {
            if ($use_supabase || $use_render) {
                // PostgreSQL (Supabase or Render)
                $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '" . $table . "'");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Local MySQL/SQLite
                $db_type = getenv('DB_TYPE') ?: 'mysql';
                if ($db_type === 'sqlite') {
                    $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
                } else {
                    $stmt = $pdo->query("DESCRIBE `" . str_replace('`', '``', $table) . "`");
                }
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Error getting table structure: " . $e->getMessage());
            return [];
        }
    }
}

// Log successful connection
db_log_connection_success();

function db_log_connection_success() {
    $connection_type = 'Unknown';
    if (getenv('SUPABASE_URL')) {
        $connection_type = 'Supabase';
    } elseif (getenv('DATABASE_URL')) {
        $connection_type = 'Render PostgreSQL';
    } else {
        $connection_type = 'Local (' . (getenv('DB_TYPE') ?: 'mysql') . ')';
    }
    error_log("Database connection established successfully using: " . $connection_type);
}

?>