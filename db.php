<?php
// Environment-aware DB config: Prefer Supabase Postgres, then Render (DATABASE_URL), otherwise MySQL locally
// Prefer Supabase when explicit Supabase envs are present
if (getenv('SUPABASE_DB_PASSWORD') || getenv('SUPABASE_URL') || getenv('SUPABASE_DB_HOST') || getenv('NEXT_PUBLIC_SUPABASE_URL')) {
    require_once __DIR__ . '/supabase_db.php';
    return;
}

// Fallback to Render/Generic Postgres using DATABASE_URL
if (getenv('DATABASE_URL')) {
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

    // Database configuration - MySQL (environment-aware with fallbacks)
    define('DB_TYPE', 'mysql'); // Using MySQL
    // Prefer environment variables if provided
    $envHost = getenv('DB_HOST') ?: 'localhost';
    $envUser = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'root');
    $envPass = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: '');
    $envDb   = getenv('DB_NAME') ?: 'blood_system';
    $envPort = getenv('DB_PORT');
    if (!$envPort && strpos($envHost, ':') !== false) {
        // Extract port from host if given as host:port
        [$hostOnly, $portOnly] = explode(':', $envHost, 2);
        $envHost = $hostOnly;
        $envPort = $portOnly;
    }
    if (!$envPort) { $envPort = 3306; }

    // Candidate connection configurations to try in order
    $candidates = [
        ['host' => $envHost, 'port' => (int)$envPort, 'user' => $envUser, 'pass' => $envPass, 'db' => $envDb],
        ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => '',           'db' => 'blood_system'],
        ['host' => 'localhost',  'port' => 3306, 'user' => 'root', 'pass' => '',           'db' => 'blood_system'],
        ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => 'password112', 'db' => 'blood_system'],
        ['host' => 'localhost',  'port' => 3306, 'user' => 'root', 'pass' => 'password112','db' => 'blood_system'],
    ];

    try {
        if (DB_TYPE === 'sqlite') {
            // Create database directory if it doesn't exist
            $dbDir = __DIR__ . '/database';
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            // Create PDO instance for SQLite
            $pdo = new PDO(
                "sqlite:" . DB_FILE,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // Enable foreign keys
            $pdo->exec('PRAGMA foreign_keys = ON');
            
        } else {
            // Try each candidate configuration until one succeeds
            $lastException = null;
            foreach ($candidates as $cfg) {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], (int)$cfg['port'], $cfg['db']);
                try {
                    $pdo = new PDO(
                        $dsn,
                        $cfg['user'],
                        $cfg['pass'],
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_EMULATE_PREPARES => false,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_AUTOCOMMIT => true
                        ]
                    );
                    // Verify critical table exists; if not, still allow but warn
                    try {
                        $check = $pdo->query("SHOW TABLES LIKE 'admin_users'");
                        if ($check && $check->rowCount() === 0) {
                            error_log("Warning: 'admin_users' table not found in DB '" . $cfg['db'] . "'.");
                        }
                    } catch (Exception $inner) {
                        // Non-fatal
                        error_log("Table check failed: " . $inner->getMessage());
                    }
                    // Successful connection; stop trying further candidates
                    break;
                } catch (PDOException $eTry) {
                    $lastException = $eTry;
                    error_log(sprintf("DB connect failed for %s@%s:%d/%s: %s", $cfg['user'], $cfg['host'], (int)$cfg['port'], $cfg['db'], $eTry->getMessage()));
                    $pdo = null;
                }
            }
            if (!isset($pdo) || $pdo === null) {
                throw $lastException ?: new PDOException('Unable to connect using any configured MySQL credentials');
            }
        }
        
        // Log successful connection
        error_log("Database connection established successfully");
        
    } catch (PDOException $e) {
        // Log detailed error information
        $error_message = "Database connection failed: " . $e->getMessage();
        error_log($error_message);
        
        // Return JSON error for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'message' => 'Database connection error',
                'error' => $error_message
            ]));
        } else {
            // For regular page loads, show a user-friendly error
            // Show a diagnostic with attempted configurations
            $attemptsHtml = '';
            foreach ($candidates as $c) {
                $attemptsHtml .= sprintf('<li>%s@%s:%d/%s</li>', htmlspecialchars($c['user']), htmlspecialchars($c['host']), (int)$c['port'], htmlspecialchars($c['db']));
            }
            die("<div style='font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 6px;'>
                <h2>Database Connection Error</h2>
                <p>Unable to connect to MySQL. Please check:</p>
                <ul>
                    <li>Is MySQL running and listening on the expected port?</li>
                    <li>Are the credentials in environment variables or <code>db.php</code> correct?</li>
                    <li>Does the database <code>" . htmlspecialchars($envDb) . "</code> exist?</li>
                </ul>
                <p><strong>Last Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <h3>Attempts Tried:</h3>
                <ul>" . $attemptsHtml . "</ul>
                <p>Tip: Set <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code> in the environment or configure them here.</p>
                <p>See <a href='database_diagnostic.php'>database_diagnostic.php</a> for more tests.</p>
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
                $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $pdo->quote($table) . "'");
            } else {
                $result = $pdo->query("SHOW TABLES LIKE '" . $pdo->quote($table) . "'");
            }
            return $result->rowCount() > 0;
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

// Only load production DB config when DATABASE_URL is present (handled at top)
// Avoid loading db_production.php in local/dev to prevent function redeclarations
