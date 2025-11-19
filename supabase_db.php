<?php
/**
 * Supabase Configuration for Blood Donation PWA
 * This file handles connection to Supabase PostgreSQL database
 */

// Optional: load .env file if present
foreach ([__DIR__ . '/.env', dirname(__DIR__) . '/.env'] as $envPath) {
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && !empty(trim($line))) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
                putenv(trim($key) . '=' . trim($value));
            }
        }
        break;
    }
}

// Get Supabase credentials from environment variables
$supabase_url = getenv('SUPABASE_URL') ?: getenv('NEXT_PUBLIC_SUPABASE_URL');
$supabase_anon_key = getenv('SUPABASE_ANON_KEY') ?: getenv('NEXT_PUBLIC_SUPABASE_ANON_KEY');
$supabase_service_key = getenv('SUPABASE_SERVICE_ROLE_KEY');

// Direct PostgreSQL connection credentials (preferred alternative to service key)
$supabase_db_host = getenv('SUPABASE_DB_HOST');
$supabase_db_port = getenv('SUPABASE_DB_PORT') ?: 5432;
$supabase_db_name = getenv('SUPABASE_DB_NAME') ?: 'postgres';
$supabase_db_user = getenv('SUPABASE_DB_USER') ?: 'postgres';
$supabase_db_password = getenv('SUPABASE_DB_PASSWORD');

// If environment variables aren't set, you can hardcode them here (not recommended for production)
if (!$supabase_url) {
    // Example: $supabase_url = 'https://your-project-id.supabase.co';
    die('SUPABASE_URL not configured. Please set environment variable.');
}

// Anon key is optional when connecting directly to Postgres
// If your app uses Supabase client libraries from the browser, keep this set

// Extract project ID from URL for host inference when SUPABASE_DB_HOST isn't provided
$project_id = str_replace(['https://', '.supabase.co'], '', $supabase_url);

// Database connection configuration (prefer explicit envs; infer host if needed)
define('SUPABASE_HOST', $supabase_db_host ?: ('db.' . $project_id . '.supabase.co'));
define('SUPABASE_PORT', (int)$supabase_db_port);
define('SUPABASE_DATABASE', $supabase_db_name);
define('SUPABASE_USER', $supabase_db_user);

// Password must be the Database Password from Supabase Settings > Database
// Do NOT use anon or service role keys for direct Postgres connections
define('SUPABASE_PASSWORD', $supabase_db_password ?: '');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/supabase_error.log');

try {
    if (!SUPABASE_PASSWORD) {
        throw new PDOException('Missing SUPABASE_DB_PASSWORD. Get it from Supabase > Settings > Database');
    }

    $hosts = [];
    if (SUPABASE_HOST) { $hosts[] = SUPABASE_HOST; }
    $hosts[] = 'db.' . $project_id . '.supabase.co';
    $hosts[] = 'db.' . $project_id . '.supabase.com';
    $hosts[] = $project_id . '.supabase.co';
    $hosts[] = $project_id . '.supabase.com';

    $connected = false;
    foreach ($hosts as $h) {
        try {
            $pdo = new PDO(
                "pgsql:host=" . $h . ";port=" . SUPABASE_PORT . ";dbname=" . SUPABASE_DATABASE . ";sslmode=require",
                SUPABASE_USER,
                SUPABASE_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $connected = true;
            break;
        } catch (PDOException $inner) {
            error_log('Supabase host try failed: ' . $h . ' | ' . $inner->getMessage());
        }
    }

    if (!$connected) {
        throw new PDOException('Unable to connect to any Supabase host candidates');
    }

    $pdo->exec("SET timezone = 'Asia/Manila'");
    error_log("Supabase PostgreSQL connection established successfully");

} catch (PDOException $e) {
    error_log("Supabase connection failed: " . $e->getMessage());
    if (getenv('SUPABASE_TEST_MODE') === '1') {
        throw $e;
    }
    die("<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>
        <h2>Supabase Connection Error</h2>
        <p>Failed to connect to Supabase PostgreSQL database.</p>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <h3>Please check:</h3>
        <ul>
            <li>SUPABASE_DB_PASSWORD is set from Settings > Database</li>
            <li>Host can be db.<em>project-ref</em>.supabase.co or <em>project-ref</em>.supabase.co</li>
            <li>SSL must be enabled (sslmode=require)</li>
            <li>Network connection to Supabase is working</li>
        </ul>
        <p>Get credentials: <a href='https://app.supabase.com/project/_/settings/database' target='_blank'>Supabase Dashboard → Settings → Database</a></p>
    </div>");
}

// Helper functions (compatible with existing code)
if (!function_exists('tableExists')) {
    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT to_regclass('public." . $table . "')");
            return $result->fetchColumn() !== null;
        } catch (PDOException $e) {
            error_log("Error checking table existence: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getTableStructure')) {
    function getTableStructure($pdo, $table) {
        try {
            $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '" . $table . "'");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting table structure: " . $e->getMessage());
            return [];
        }
    }
}

// Supabase-specific helper functions
if (!function_exists('getSupabaseAuth')) {
    function getSupabaseAuth() {
        global $supabase_url, $supabase_anon_key;
        return [
            'url' => $supabase_url,
            'anon_key' => $supabase_anon_key,
            'service_key' => $GLOBALS['supabase_service_key'] ?? null
        ];
    }
}

if (!function_exists('executeSupabaseQuery')) {
    function executeSupabaseQuery($query, $params = []) {
        global $pdo;
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Supabase query error: " . $e->getMessage() . " | Query: " . $query);
            throw $e;
        }
    }
}

?>