<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$db   = 'blood_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Connect to database
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `requests` LIKE 'desired_date'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add the column
        $pdo->exec("ALTER TABLE `requests` ADD COLUMN `desired_date` DATE NULL DEFAULT NULL AFTER `blood_type_needed`");
        echo "✅ Successfully added 'desired_date' column to the requests table.\n";
    } else {
        echo "ℹ️ 'desired_date' column already exists in the requests table.\n";
    }
    
    // Verify the column was added
    $stmt = $pdo->query("DESCRIBE `requests`");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('desired_date', $columns)) {
        echo "✅ Verification: 'desired_date' column is present in the requests table.\n";
    } else {
        echo "❌ Error: Failed to add 'desired_date' column.\n";
    }
    
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage() . "\n");
}

echo "\nScript execution complete. You can now close this page.\n";
?>

<!-- Simple styling for better readability -->
<style>
    body { 
        font-family: Arial, sans-serif; 
        line-height: 1.6; 
        padding: 20px;
        max-width: 800px;
        margin: 0 auto;
    }
    pre { 
        background: #f4f4f4; 
        padding: 15px; 
        border-radius: 5px;
        overflow-x: auto;
    }
    .success { color: #28a745; }
    .info { color: #17a2b8; }
    .error { color: #dc3545; }
</style>
