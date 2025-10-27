<?php
// Direct DB connection test for Render debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
echo '<h2>DB Connection Test</h2>';
try {
    if (getenv('DATABASE_URL')) {
        $db = parse_url(getenv('DATABASE_URL'));
        $dsn = 'pgsql:host=' . $db['host'] . ';port=' . (isset($db['port']) ? $db['port'] : 5432) . ';dbname=' . ltrim($db['path'], '/');
        echo '<b>Using PostgreSQL:</b><br>';
        echo 'DSN: ' . htmlspecialchars($dsn) . '<br>';
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<span style="color:green;">SUCCESS: Connected to PostgreSQL!</span>';
    } else {
        echo '<b>Using MySQL (local/dev):</b><br>';
        $pdo = new PDO('mysql:host=localhost;dbname=blood_system', 'root', 'password112', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<span style="color:green;">SUCCESS: Connected to MySQL!</span>';
    }
} catch (Throwable $e) {
    echo '<span style="color:red;">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
}

// TEST DATABASE CONNECTION
echo "<!DOCTYPE html><html><head><title>DB Test</title></head><body>";
echo "<h1>Database Connection Test</h1><pre>";

try {
    echo "Step 1: Loading db.php...\n";
    require_once 'db.php';
    echo "✅ db.php loaded\n\n";
    
    echo "Step 2: Testing PDO connection...\n";
    if (isset($pdo)) {
        echo "✅ PDO object exists\n\n";
    } else {
        echo "❌ PDO object NOT found!\n";
        die();
    }
    
    echo "Step 3: Testing simple query...\n";
    $result = $pdo->query("SELECT version()");
    $version = $result->fetchColumn();
    echo "✅ PostgreSQL Version: $version\n\n";
    
    echo "Step 4: Checking tables...\n";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "❌ NO TABLES FOUND! Database is empty!\n";
        echo "\n⚠️ YOU NEED TO RUN: setup_database_postgres.php\n";
    } else {
        echo "✅ Tables found:\n";
        foreach ($tables as $table) {
            echo "   - $table\n";
        }
    }
    
    echo "\nStep 5: Checking admin_users table...\n";
    if (in_array('admin_users', $tables)) {
        echo "✅ admin_users table exists\n";
        
        // Check structure
        $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'admin_users'");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nColumns:\n";
        foreach ($columns as $col) {
            echo "   - {$col['column_name']} ({$col['data_type']})\n";
        }
        
        // Check for admin user
        echo "\nStep 6: Checking for admin user...\n";
        $stmt = $pdo->query("SELECT username, email FROM admin_users WHERE username = 'admin'");
        $admin = $stmt->fetch();
        if ($admin) {
            echo "✅ Admin user found: {$admin['username']} ({$admin['email']})\n";
        } else {
            echo "❌ Admin user NOT found!\n";
            echo "\n⚠️ YOU NEED TO RUN: setup_database_postgres.php\n";
        }
    } else {
        echo "❌ admin_users table NOT found!\n";
        echo "\n⚠️ YOU NEED TO RUN: setup_database_postgres.php\n";
    }
    
    echo "\n=====================================\n";
    echo "✅ DATABASE CONNECTION TEST COMPLETE\n";
    echo "=====================================\n";
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
    echo "\nError details:\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='index.php'>Home</a> | <a href='admin_login.php'>Admin Login</a></p>";
echo "</body></html>";
?>

