<?php
// Check for PHP errors and server logs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Error Check</h1>";

// Check if logs directory exists and is readable
$logDir = __DIR__ . '/logs';
echo "<h3>Log Directory Status:</h3>";
if (is_dir($logDir)) {
    echo "<p style='color: green;'>✅ Logs directory exists</p>";
    echo "<p>Writable: " . (is_writable($logDir) ? "Yes" : "No") . "</p>";
    
    // List log files
    $logFiles = glob($logDir . '/*.log');
    echo "<h4>Log Files:</h4>";
    foreach ($logFiles as $file) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "<p>📄 " . basename($file) . " (Size: {$size} bytes, Modified: {$modified})</p>";
        
        // Show last few lines of error log
        if (basename($file) === 'error.log' && $size > 0) {
            echo "<h5>Last 10 lines of error.log:</h5>";
            $lines = file($file);
            $lastLines = array_slice($lines, -10);
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px;'>";
            foreach ($lastLines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Logs directory doesn't exist</p>";
}

// Test basic PHP functionality
echo "<h3>PHP Function Tests:</h3>";

// Test database connection
try {
    require_once 'db.php';
    echo "<p style='color: green;'>✅ Database connection works</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test JSON functions
if (function_exists('json_encode')) {
    echo "<p style='color: green;'>✅ JSON functions available</p>";
} else {
    echo "<p style='color: red;'>❌ JSON functions missing</p>";
}

// Test PDO
if (class_exists('PDO')) {
    echo "<p style='color: green;'>✅ PDO available</p>";
} else {
    echo "<p style='color: red;'>❌ PDO missing</p>";
}

// Test mail functions
if (function_exists('mail')) {
    echo "<p style='color: green;'>✅ Mail function available</p>";
} else {
    echo "<p style='color: red;'>❌ Mail function missing</p>";
}

// Check PHP version
echo "<h3>PHP Information:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";
echo "<p>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</p>";

// Test a simple database operation
echo "<h3>Database Test:</h3>";
try {
    $result = $pdo->query("SELECT COUNT(*) as count FROM donors");
    $count = $result->fetchColumn();
    echo "<p style='color: green;'>✅ Database query works (Donors count: $count)</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database query failed: " . $e->getMessage() . "</p>";
}

// Check for common issues
echo "<h3>Common Issues Check:</h3>";

// Check if required files exist
$requiredFiles = [
    'includes/mail_helper.php',
    'includes/medical_section.php',
    'config/email_config.json'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file missing</p>";
    }
}

echo "<hr>";
echo "<p><a href='test_registration.php'>Try Simple Registration</a></p>";
echo "<p><a href='donor-registration.php'>Try Full Registration</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
