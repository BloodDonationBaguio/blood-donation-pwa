<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Set test data in session
$_SESSION['test_time'] = date('Y-m-d H:i:s');
$_SESSION['test_user'] = 'test_user';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database & Session Test</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Database & Session Test</h1>
    
    <h2>1. PHP Info</h2>
    <p>PHP Version: <?= phpversion() ?></p>
    
    <h2>2. Session Test</h2>
    <?php
    if (isset($_SESSION['test_time'])) {
        echo "<p class='success'>✅ Session is working. Test time: " . htmlspecialchars($_SESSION['test_time']) . "</p>";
    } else {
        echo "<p class='error'>❌ Session is not working</p>";
    }
    ?>
    
    <h2>3. Database Test</h2>
    <?php
    try {
        require_once __DIR__ . '/includes/db.php';
        
        // Test query
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p class='success'>✅ Database connection successful!</p>";
        echo "<h3>Tables in database:</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
        
        // Test donors table
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors");
            $donorsCount = $stmt->fetch()['count'];
            echo "<p>Total donors: " . (int)$donorsCount . "</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error counting donors: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<h3>Connection details attempted:</h3>";
        echo "<pre>";
        echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'Not defined') . "\n";
        echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'Not defined') . "\n";
        echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'Not defined') . "\n";
        echo "DB_PASS: " . (defined('DB_PASS') ? '(hidden)' : 'Not defined') . "\n";
        echo "</pre>";
    }
    ?>
    
    <h2>4. PHP Info</h2>
    <p><a href="phpinfo.php">View phpinfo()</a></p>
    
    <h2>5. Next Steps</h2>
    <ul>
        <li><a href="admin-test.php">Test Admin Page</a> (simplified version)</li>
        <li><a href="admin-dashboard.php">Original Admin Dashboard</a></li>
        <li><a href="admin-fixed.php">Fixed Admin Dashboard</a></li>
    </ul>
</body>
</html>
