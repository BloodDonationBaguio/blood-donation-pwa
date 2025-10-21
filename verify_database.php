<?php
// Database Verification Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Verification</h1>";

try {
    // Test database connection
    require_once 'db.php';
    echo "<h2>✅ Database connection successful</h2>";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $db_name = $stmt->fetch(PDO::FETCH_ASSOC)['db_name'];
    echo "<p><strong>Current database:</strong> " . htmlspecialchars($db_name) . "</p>";
    
    // List all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Tables in database:</h2>";
    if (empty($tables)) {
        echo "<p>❌ No tables found in the database</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            $status = ($table === 'users_new') ? '✅' : 'ℹ️';
            echo "<li>$status " . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    }
    
    // Check specifically for users_new table
    if (in_array('users_new', $tables)) {
        echo "<h2>✅ users_new table exists</h2>";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE users_new");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table structure:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if table has data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users_new");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p><strong>Number of users:</strong> $count</p>";
        
        if ($count > 0) {
            echo "<h3>Sample users:</h3>";
            $stmt = $pdo->query("SELECT id, name, full_name, email, role, status FROM users_new LIMIT 5");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Name</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($user['status'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<h2>❌ users_new table does NOT exist</h2>";
        echo "<p>You need to run the database setup script first.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2>❌ Database connection failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>Is XAMPP MySQL service running?</li>";
    echo "<li>Are the database credentials in <code>db.php</code> correct?</li>";
    echo "<li>Does the database <code>blood_system</code> exist?</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li><a href='setup_database.php'>Run Database Setup</a></li>";
echo "<li><a href='profile.php'>Test Profile Page</a></li>";
echo "<li><a href='index.php'>Go to Homepage</a></li>";
echo "</ul>";
?>
