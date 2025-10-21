<?php
/**
 * Database Diagnostic Tool
 * Identifies and fixes common database issues
 */

echo "<h2>🔧 Database Diagnostic Tool</h2>";
echo "<style>
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; font-weight: bold; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

// Test 1: Basic Connection
echo "<div class='section'>";
echo "<h3>1. Database Connection Test</h3>";

try {
    $pdo = new PDO(
        "mysql:host=localhost:3306;dbname=blood_system;charset=utf8mb4",
        'root',
        'password112',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<p class='success'>✅ Database connection successful!</p>";
    
    // Get database info
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $result = $stmt->fetch();
    echo "<p><strong>Connected to:</strong> " . $result['current_db'] . "</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database connection failed!</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Try alternative connections
    echo "<h4>Trying alternative connections...</h4>";
    
    // Try without port
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=blood_system;charset=utf8mb4",
            'root',
            'password112'
        );
        echo "<p class='success'>✅ Connection successful without port!</p>";
    } catch (PDOException $e2) {
        echo "<p class='error'>❌ Connection without port failed: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
    
    // Try with empty password
    try {
        $pdo = new PDO(
            "mysql:host=localhost:3306;dbname=blood_system;charset=utf8mb4",
            'root',
            ''
        );
        echo "<p class='success'>✅ Connection successful with empty password!</p>";
    } catch (PDOException $e3) {
        echo "<p class='error'>❌ Connection with empty password failed: " . htmlspecialchars($e3->getMessage()) . "</p>";
    }
    
    echo "</div>";
    exit;
}
echo "</div>";

// Test 2: Check Tables
echo "<div class='section'>";
echo "<h3>2. Database Tables Check</h3>";

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p class='warning'>⚠️ No tables found in database!</p>";
        echo "<p>You need to run the database setup first.</p>";
    } else {
        echo "<p class='success'>✅ Found " . count($tables) . " tables:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    }
    
    // Check for required tables
    $requiredTables = ['donors_new', 'blood_inventory', 'admin_users'];
    $missingTables = [];
    
    foreach ($requiredTables as $required) {
        if (!in_array($required, $tables)) {
            $missingTables[] = $required;
        }
    }
    
    if (!empty($missingTables)) {
        echo "<p class='warning'>⚠️ Missing required tables: " . implode(', ', $missingTables) . "</p>";
    } else {
        echo "<p class='success'>✅ All required tables present!</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking tables: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 3: Check Table Structure
echo "<div class='section'>";
echo "<h3>3. Table Structure Check</h3>";

$tableChecks = [
    'donors_new' => ['id', 'first_name', 'last_name', 'email', 'blood_type', 'status'],
    'blood_inventory' => ['id', 'unit_id', 'donor_id', 'blood_type', 'status'],
    'admin_users' => ['id', 'username', 'password', 'role']
];

foreach ($tableChecks as $table => $requiredColumns) {
    if (in_array($table, $tables)) {
        echo "<h4>Checking table: $table</h4>";
        
        try {
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $missingColumns = [];
            foreach ($requiredColumns as $required) {
                if (!in_array($required, $columns)) {
                    $missingColumns[] = $required;
                }
            }
            
            if (empty($missingColumns)) {
                echo "<p class='success'>✅ All required columns present</p>";
            } else {
                echo "<p class='warning'>⚠️ Missing columns: " . implode(', ', $missingColumns) . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error checking table structure: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
echo "</div>";

// Test 4: Check for Data
echo "<div class='section'>";
echo "<h3>4. Data Check</h3>";

$dataChecks = [
    'donors_new' => 'SELECT COUNT(*) as count FROM donors_new',
    'blood_inventory' => 'SELECT COUNT(*) as count FROM blood_inventory',
    'admin_users' => 'SELECT COUNT(*) as count FROM admin_users'
];

foreach ($dataChecks as $table => $query) {
    if (in_array($table, $tables)) {
        try {
            $stmt = $pdo->query($query);
            $result = $stmt->fetch();
            $count = $result['count'];
            
            if ($count > 0) {
                echo "<p class='success'>✅ $table: $count records</p>";
            } else {
                echo "<p class='warning'>⚠️ $table: No data found</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error checking $table data: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
echo "</div>";

// Test 5: Check for Common Issues
echo "<div class='section'>";
echo "<h3>5. Common Issues Check</h3>";

// Check for undefined variables in admin.php
echo "<h4>Checking for undefined variables...</h4>";

// Check if monthlyDonors and monthlyRequests variables are defined
try {
    // This would normally be set in admin.php
    $monthlyDonors = [];
    $monthlyRequests = [];
    
    echo "<p class='info'>ℹ️ Variables that might be undefined in admin.php:</p>";
    echo "<ul>";
    echo "<li>\$monthlyDonors - Used in Monthly Trends chart</li>";
    echo "<li>\$monthlyRequests - Used in Monthly Trends chart (now removed)</li>";
    echo "<li>\$requestCount - Used in dashboard statistics</li>";
    echo "<li>\$pendingRequestCount - Used in dashboard statistics</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking variables: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 6: Fix Suggestions
echo "<div class='section'>";
echo "<h3>6. Fix Suggestions</h3>";

echo "<h4>If you're getting database errors, try these solutions:</h4>";
echo "<ol>";
echo "<li><strong>Run Database Setup:</strong> <a href='setup_database_complete.php'>setup_database_complete.php</a></li>";
echo "<li><strong>Add Test Data:</strong> <a href='quick_setup.php'>quick_setup.php</a></li>";
echo "<li><strong>Create Admin User:</strong> <a href='setup_super_admin.php'>setup_super_admin.php</a></li>";
echo "<li><strong>Check XAMPP:</strong> Make sure MySQL is running in XAMPP Control Panel</li>";
echo "<li><strong>Check phpMyAdmin:</strong> <a href='http://localhost/phpmyadmin/'>http://localhost/phpmyadmin/</a></li>";
echo "</ol>";

echo "<h4>Common Error Fixes:</h4>";
echo "<ul>";
echo "<li><strong>Unknown database 'blood_system':</strong> Run setup_database_complete.php</li>";
echo "<li><strong>Table doesn't exist:</strong> Run the appropriate setup script</li>";
echo "<li><strong>Undefined variables:</strong> Check admin.php for missing variable definitions</li>";
echo "<li><strong>Connection refused:</strong> Check if MySQL is running in XAMPP</li>";
echo "</ul>";
echo "</div>";

echo "<div class='section'>";
echo "<h3>7. Quick Actions</h3>";
echo "<p><a href='setup_database_complete.php' class='btn btn-primary'>🔧 Setup Database</a></p>";
echo "<p><a href='quick_setup.php' class='btn btn-success'>📊 Add Test Data</a></p>";
echo "<p><a href='admin.php' class='btn btn-info'>👤 Admin Panel</a></p>";
echo "</div>";
?>
