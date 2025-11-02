<?php
// Environment-aware DB config: PostgreSQL on Render (DATABASE_URL), MySQL locally
if (getenv('DATABASE_URL')) {
    // In production with DATABASE_URL, delegate to db_production.php
    require_once __DIR__ . '/db_production.php';
    return;
}
// Local/dev fallback: MySQL

// Check if this file is being included directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    die('This file should not be accessed directly.');
}

// Only proceed if database functions aren't already defined
if (!function_exists('tableExists')) {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Ensure logs directory exists
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Set error log location
    ini_set('error_log', $logDir . '/error.log');

    // Database configuration - MySQL (restored after fix)
    define('DB_TYPE', 'mysql'); // Using MySQL
    define('DB_HOST', 'localhost:3306'); // Using standard MySQL port
    define('DB_NAME', 'blood_system');
    define('DB_USER', 'root');
    define('DB_PASS', 'password112');
    define('DB_FILE', __DIR__ . '/database/blood_system.db');

    // Connection helper with retry to mitigate transient failures on Render
    $connectWithRetry = function($attempts = 2, $delayMs = 500) {
        $lastException = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                if (DB_TYPE === 'sqlite') {
                    $dbDir = __DIR__ . '/database';
                    if (!file_exists($dbDir)) {
                        mkdir($dbDir, 0755, true);
                    }
                    $pdo = new PDO(
                        "sqlite:" . DB_FILE,
                        null,
                        null,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]
                    );
                    $pdo->exec('PRAGMA foreign_keys = ON');
                } else {
                    // MySQL with explicit non-persistent connection and timeout
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_AUTOCOMMIT => true,
                        PDO::ATTR_PERSISTENT => false,
                        PDO::ATTR_TIMEOUT => 5
                    ];
                    $pdo = new PDO(
                        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                        DB_USER,
                        DB_PASS,
                        $options
                    );
                    // Ensure MySQL timeouts at the session level where possible
                    try {
                        $pdo->exec("SET SESSION wait_timeout=10");
                        $pdo->exec("SET SESSION interactive_timeout=10");
                    } catch (Throwable $t) {
                        // Ignore if not supported
                    }
                }
                return $pdo;
            } catch (PDOException $ex) {
                $lastException = $ex;
                usleep($delayMs * 1000);
            }
        }
        if ($lastException) throw $lastException;
        throw new PDOException('Unknown database connection error');
    };

    try {
        $pdo = $connectWithRetry(3, 400);
        error_log("Database connection established successfully");
    } catch (PDOException $e) {
        // Log detailed error information
        $error_message = "Database connection failed: " . $e->getMessage();
        error_log($error_message);
        
        // Return JSON error for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            die(json_encode([
                'success' => false,
                'message' => 'Database connection error. Please retry in a moment.',
                'error' => $error_message
            ]));
        } else {
            // For regular page loads, show a user-friendly error
            die("<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>
                <h2>Database Connection Error</h2>
                <p>Unable to connect to the database at the moment. This may be due to a temporary network issue or a cold start on the free tier.</p>
                <ul>
                    <li>Is the database server running?</li>
                    <li>Are the database credentials in <code>db.php</code> correct?</li>
                    <li>Does the database <code>blood_system</code> exist?</li>
                </ul>
                <p><strong>Error Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <p>Please refresh the page to retry. If the problem persists, check the error log at: " . htmlspecialchars(ini_get('error_log')) . "</p>
            </div>");
        }
    }

    /**
     * Check if a table exists in the database
     * 
     * @param PDO $pdo Database connection
     * @param string $table Table name
     * @return bool
     */
    function tableExists($pdo, $table) {
        try {
            if (DB_TYPE === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
                $stmt->execute([$table]);
            } else {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
            }
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error checking if table exists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the structure of a table
     * 
     * @param PDO $pdo Database connection
     * @param string $table Table name
     * @return array
     */
    function getTableStructure($pdo, $table) {
        try {
            if (DB_TYPE === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query("DESCRIBE `" . str_replace('`', '``', $table) . "`");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Error getting table structure: " . $e->getMessage());
            return [];
        }
    }
}
