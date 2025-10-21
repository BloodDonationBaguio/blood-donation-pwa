<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Database connection test
$host = 'localhost';
$db   = 'blood_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
    echo "<p style='color: green;'>✅ Successfully connected to the database.</p>";
    
    // Check tables
    $tables = ['users', 'donors', 'requests', 'notifications'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "<p>✅ Table '$table' exists and contains {$result['count']} records.</p>";
            
            // Show table structure
            $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll();
            echo "<pre>Table structure for '$table':\n";
            foreach ($columns as $col) {
                echo "- {$col['Field']} ({$col['Type']})\n";
            }
            echo "</pre>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error with table '$table': " . $e->getMessage() . "</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    
    // Try to connect without database to check if it exists
    try {
        $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
        $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Available databases: " . implode(', ', $databases) . "</p>";
        
        if (!in_array($db, $databases)) {
            echo "<p style='color: orange;'>⚠️ Database '$db' does not exist. You may need to import the schema.</p>";
        }
    } catch (PDOException $e2) {
        echo "<p>Could not list databases: " . $e2->getMessage() . "</p>";
    }
}

// Check PHP version
echo "<h2>PHP Version: " . phpversion() . "</h2>";

// Check PDO extension
echo "<h2>PDO Extensions:</h2>";
$pdoDrivers = PDO::getAvailableDrivers();
echo "<p>Available PDO drivers: " . implode(', ', $pdoDrivers) . "</p>";

// Check if MySQL PDO driver is available
if (in_array('mysql', $pdoDrivers)) {
    echo "<p style='color: green;'>✅ MySQL PDO driver is available.</p>";
} else {
    echo "<p style='color: red;'>❌ MySQL PDO driver is NOT available.</p>";
}

// Check file permissions
$filesToCheck = [
    __DIR__ . '/db.php',
    __DIR__ . '/pending-actions.php',
    __DIR__ . '/../admin-dashboard.php'
];

echo "<h2>File Permissions:</h2>";
foreach ($filesToCheck as $file) {
    $exists = file_exists($file);
    $readable = is_readable($file);
    $writable = is_writable($file);
    
    echo "<p>";
    echo $exists ? "✅" : "❌";
    echo " $file - ";
    
    if (!$exists) {
        echo "<span style='color: red;'>File does not exist!</span>";
    } else {
        echo "<span style='color: " . ($readable ? "green" : "red") . "'>" . 
             ($readable ? "Readable" : "Not Readable") . "</span>, ";
        echo "<span style='color: " . ($writable ? "green" : "red") . "'>" . 
             ($writable ? "Writable" : "Not Writable") . "</span>";
    }
    echo "</p>";
}

// Check for common PHP configuration issues
$issues = [];

// Check if short open tags are enabled
if (!ini_get('short_open_tag')) {
    $issues[] = "short_open_tag is disabled. This might cause issues with some PHP files.";
}

// Check if display_errors is on
if (!ini_get('display_errors')) {
    $issues[] = "display_errors is disabled. You might not see PHP errors.";
}

// Check if error reporting is set to show all errors
if (error_reporting() !== E_ALL) {
    $issues[] = "error_reporting is not set to E_ALL. Some errors might be hidden.";
}

// Check if log_errors is enabled
if (!ini_get('log_errors')) {
    $issues[] = "log_errors is disabled. Errors won't be logged.";
}

// Check if error_log is set
if (!ini_get('error_log')) {
    $issues[] = "error_log is not set. Errors won't be logged to a file.";
}

// Display any issues found
if (!empty($issues)) {
    echo "<h2>Potential Issues:</h2><ul>";
    foreach ($issues as $issue) {
        echo "<li style='color: orange;'>⚠️ $issue</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✅ No major configuration issues detected.</p>";
}

// Check if the database schema is up to date
if (isset($pdo)) {
    try {
        $requiredColumns = [
            'requests' => ['id', 'user_id', 'blood_type_needed', 'status', 'created_at', 'updated_at', 'reference', 'note', 'appointment_date', 'appointment_time', 'appointment_location'],
            'donors' => ['id', 'user_id', 'full_name', 'email', 'phone', 'blood_type', 'status', 'reference_code', 'created_at', 'updated_at'],
            'users' => ['id', 'username', 'password', 'email', 'role', 'created_at', 'updated_at'],
            'notifications' => ['id', 'user_id', 'message', 'is_read', 'created_at']
        ];
        
        echo "<h2>Database Schema Check:</h2>";
        
        foreach ($requiredColumns as $table => $columns) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM $table");
                $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $missingColumns = array_diff($columns, $existingColumns);
                
                if (empty($missingColumns)) {
                    echo "<p>✅ Table '$table' has all required columns.</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ Table '$table' is missing columns: " . implode(', ', $missingColumns) . "</p>";
                }
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Could not check table '$table': " . $e->getMessage() . "</p>";
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error checking database schema: " . $e->getMessage() . "</p>";
    }
}

// Check if there are any pending database migrations
$migrationDir = __DIR__ . '/migrations';
if (is_dir($migrationDir)) {
    $migrations = glob("$migrationDir/*.sql");
    if (!empty($migrations)) {
        echo "<h2>Pending Migrations:</h2><ul>";
        foreach ($migrations as $migration) {
            echo "<li>" . basename($migration) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No pending migrations found in $migrationDir</p>";
    }
}

// Check PHP error log location
echo "<h2>PHP Error Log:</h2>";
$errorLog = ini_get('error_log');
if ($errorLog) {
    echo "<p>Error log is set to: $errorLog</p>";
    if (file_exists($errorLog)) {
        $logSize = filesize($errorLog);
        $logSizeMB = round($logSize / 1024 / 1024, 2);
        echo "<p>Log file exists. Size: $logSizeMB MB</p>";
        
        // Show last 5 lines of the error log if it's not too big
        if ($logSize < 5 * 1024 * 1024) { // Only show if log is smaller than 5MB
            $lastLines = `tail -n 20 "$errorLog"`;
            echo "<h3>Last 20 lines of error log:</h3>";
            echo "<pre>" . htmlspecialchars($lastLines) . "</pre>";
        } else {
            echo "<p>Log file is too large to display ($logSizeMB MB). Check it directly at: $errorLog</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Error log file does not exist at: $errorLog</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Error log is not configured in php.ini</p>";
}

// Check if we can write to the log file
echo "<h2>Log File Test:</h2>";
$testLog = __DIR__ . '/test_write.log';
if (file_put_contents($testLog, "Test write at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND) !== false) {
    echo "<p style='color: green;'>✅ Successfully wrote to test log file: $testLog</p>";
    unlink($testLog); // Clean up
} else {
    echo "<p style='color: red;'>❌ Could not write to test log file: $testLog</p>";
}

// Check if we can create a new file
$testFile = __DIR__ . '/test_' . time() . '.tmp';
if (touch($testFile)) {
    echo "<p style='color: green;'>✅ Successfully created test file: $testFile</p>";
    unlink($testFile); // Clean up
} else {
    echo "<p style='color: red;'>❌ Could not create test file in: " . __DIR__ . "</p>";
    echo "<p>Check directory permissions for: " . __DIR__ . "</p>";
}

// Check if we can connect to the mail server
echo "<h2>Mail Server Test:</h2>";
if (function_exists('fsockopen')) {
    $smtpHost = 'smtp.gmail.com';
    $smtpPort = 587;
    $timeout = 5;
    
    $sock = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, $timeout);
    
    if ($sock) {
        echo "<p style='color: green;'>✅ Successfully connected to $smtpHost on port $smtpPort</p>";
        fclose($sock);
    } else {
        echo "<p style='color: orange;'>⚠️ Could not connect to $smtpHost on port $smtpPort: $errstr ($errno)</p>";
        echo "<p>This might be normal if you're not using Gmail's SMTP server or if your firewall is blocking the connection.</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ fsockopen() is not available. Cannot test SMTP connection.</p>";
}

// Check if PHPMailer is installed
echo "<h2>PHPMailer Check:</h2>";
$phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
if (file_exists($phpmailerPath)) {
    echo "<p style='color: green;'>✅ PHPMailer is installed at: $phpmailerPath</p>";
} else {
    echo "<p style='color: orange;'>⚠️ PHPMailer is not installed in the expected location: $phpmailerPath</p>";
    echo "<p>You can install it using Composer: <code>composer require phpmailer/phpmailer</code></p>";
}

// Check if Composer autoloader exists
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    echo "<p style='color: green;'>✅ Composer autoloader found at: $composerAutoload</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Composer autoloader not found at: $composerAutoload</p>";
    echo "<p>Run <code>composer install</code> in the project root to install dependencies.</p>";
}

// Display PHP info if requested
if (isset($_GET['phpinfo'])) {
    echo "<h2>PHP Info:</h2>";
    phpinfo();
} else {
    echo "<p><a href='?phpinfo=1'>Show PHP Info</a></p>";
}

// Display a summary of the most critical issues
function getCriticalIssues() {
    $issues = [];
    
    // Check if database connection is working
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $issues[] = "Database connection failed. Check database credentials and server status.";
    }
    
    // Check if error logging is properly configured
    if (!ini_get('log_errors') || !ini_get('error_log')) {
        $issues[] = "Error logging is not properly configured. You won't see PHP errors.";
    }
    
    // Check if important directories are writable
    $writableDirs = [
        __DIR__ . '/../uploads',
        __DIR__ . '/../cache',
        __DIR__ . '/../logs'
    ];
    
    foreach ($writableDirs as $dir) {
        if (file_exists($dir) && !is_writable($dir)) {
            $issues[] = "Directory is not writable: $dir";
        }
    }
    
    return $issues;
}

$criticalIssues = getCriticalIssues();
if (!empty($criticalIssues)) {
    echo "<h2 style='color: red;'>Critical Issues:</h2><ul>";
    foreach ($criticalIssues as $issue) {
        echo "<li style='color: red;'>❌ $issue</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✅ No critical issues detected. The application should work correctly.</p>";
}

// Display a footer with quick actions
echo "<hr>";
echo "<h2>Quick Actions:</h2>";
echo "<ul>";
echo "<li><a href='admin-dashboard.php'>Go to Admin Dashboard</a></li>";
echo "<li><a href='index.php'>Go to Home Page</a></li>";
echo "<li><a href='db_check.php?phpinfo=1'>Show PHP Info</a></li>";
echo "</ul>";
?>
