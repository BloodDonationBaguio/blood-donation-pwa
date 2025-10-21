<?php
// Check existing table structure
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

echo "<h1>Checking users_new table structure</h1>";

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users_new'");
    if ($stmt->rowCount() > 0) {
        echo "<h2>✅ Table 'users_new' exists</h2>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE users_new");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Current columns:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if we need to add missing columns
        $existingColumns = array_column($columns, 'Field');
        $requiredColumns = ['id', 'name', 'email', 'password', 'role', 'status', 'created_at', 'updated_at'];
        $missingColumns = array_diff($requiredColumns, $existingColumns);
        
        if (!empty($missingColumns)) {
            echo "<h3>❌ Missing columns:</h3>";
            echo "<ul>";
            foreach ($missingColumns as $col) {
                echo "<li>" . htmlspecialchars($col) . "</li>";
            }
            echo "</ul>";
            
            echo "<h3>Adding missing columns...</h3>";
            
            // Add missing columns
            if (in_array('name', $missingColumns)) {
                $pdo->exec("ALTER TABLE users_new ADD COLUMN name VARCHAR(255) NOT NULL AFTER id");
                echo "✅ Added 'name' column<br>";
            }
            
            if (in_array('role', $missingColumns)) {
                $pdo->exec("ALTER TABLE users_new ADD COLUMN role ENUM('user', 'admin', 'super_admin') DEFAULT 'user' AFTER password");
                echo "✅ Added 'role' column<br>";
            }
            
            if (in_array('status', $missingColumns)) {
                $pdo->exec("ALTER TABLE users_new ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER role");
                echo "✅ Added 'status' column<br>";
            }
            
            if (in_array('created_at', $missingColumns)) {
                $pdo->exec("ALTER TABLE users_new ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                echo "✅ Added 'created_at' column<br>";
            }
            
            if (in_array('updated_at', $missingColumns)) {
                $pdo->exec("ALTER TABLE users_new ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                echo "✅ Added 'updated_at' column<br>";
            }
            
            if (in_array('last_login', $missingColumns)) {
                $pdo->exec("ALTER TABLE users_new ADD COLUMN last_login TIMESTAMP NULL AFTER updated_at");
                echo "✅ Added 'last_login' column<br>";
            }
            
            echo "<h3>✅ Table structure updated successfully!</h3>";
        } else {
            echo "<h3>✅ All required columns exist</h3>";
        }
        
    } else {
        echo "<h2>❌ Table 'users_new' does not exist</h2>";
    }
    
} catch (PDOException $e) {
    echo "<h2>❌ Error checking table structure</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='setup_database.php'>Run Database Setup Again</a></p>";
echo "<p><a href='profile.php'>Test Profile Page</a></p>";
?>
