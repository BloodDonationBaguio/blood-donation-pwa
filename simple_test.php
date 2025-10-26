<?php
// ULTRA SIMPLE TEST - Shows all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html><html><head><title>Simple Test</title></head><body>";
echo "<h1>Simple Database Test</h1><pre>";

echo "PHP Version: " . phpversion() . "\n\n";

echo "Environment Variables:\n";
echo "DATABASE_URL exists: " . (getenv('DATABASE_URL') ? 'YES' : 'NO') . "\n";
if (getenv('DATABASE_URL')) {
    $db_url = getenv('DATABASE_URL');
    echo "DATABASE_URL (partial): " . substr($db_url, 0, 30) . "...\n";
    
    // Parse it
    $db = parse_url($db_url);
    echo "\nParsed Database Info:\n";
    echo "- Host: " . ($db['host'] ?? 'NOT SET') . "\n";
    echo "- Port: " . ($db['port'] ?? 'NOT SET') . "\n";
    echo "- Database: " . (isset($db['path']) ? ltrim($db['path'], '/') : 'NOT SET') . "\n";
    echo "- User: " . ($db['user'] ?? 'NOT SET') . "\n";
    echo "- Password: " . (isset($db['pass']) ? '[SET]' : 'NOT SET') . "\n";
}

echo "\nPDO PostgreSQL Extension: " . (extension_loaded('pdo_pgsql') ? 'LOADED ✅' : 'NOT LOADED ❌') . "\n";
echo "PDO Extension: " . (extension_loaded('pdo') ? 'LOADED ✅' : 'NOT LOADED ❌') . "\n";

echo "\n--- Testing Database Connection ---\n\n";

if (!getenv('DATABASE_URL')) {
    echo "❌ DATABASE_URL environment variable not set!\n";
    echo "This means Render PostgreSQL is not connected.\n";
} else {
    try {
        $database_url = getenv('DATABASE_URL');
        $db = parse_url($database_url);
        
        $host = $db['host'];
        $port = isset($db['port']) ? $db['port'] : 5432;
        $dbname = ltrim($db['path'], '/');
        $user = $db['user'];
        $pass = $db['pass'];
        
        echo "Attempting connection...\n";
        
        $pdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$dbname",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo "✅ CONNECTION SUCCESSFUL!\n\n";
        
        // Test query
        $result = $pdo->query("SELECT version()");
        $version = $result->fetchColumn();
        echo "PostgreSQL Version: $version\n\n";
        
        // Check tables
        echo "Checking tables...\n";
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tables)) {
            echo "❌ NO TABLES! Database is empty!\n";
            echo "\n🔧 SOLUTION: Visit setup_database_postgres.php\n";
        } else {
            echo "✅ Found " . count($tables) . " table(s):\n";
            foreach ($tables as $table) {
                echo "   - $table\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "❌ CONNECTION FAILED!\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "Code: " . $e->getCode() . "\n";
    }
}

echo "</pre>";
echo "<p><a href='index.php'>Home</a> | <a href='admin_login.php'>Admin Login</a></p>";
echo "</body></html>";
?>

