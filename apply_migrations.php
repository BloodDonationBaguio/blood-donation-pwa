<?php
// Database connection settings
$host = 'localhost';
$dbname = 'blood_system';
$username = 'root';
$password = '';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting database setup...\n";

try {
    // First connect without database to create it if needed
    $pdo = new PDO("mysql:host=$host", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    
    echo "Database '$dbname' is ready.\n";

    // Import schema
    $schemaFile = __DIR__ . '/database.sql';
    if (file_exists($schemaFile)) {
        echo "Importing database schema...\n";
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
        echo "Schema imported successfully.\n";
    } else {
        echo "Warning: database.sql not found. Only basic database creation was performed.\n";
    }

    // Check if column exists
    $checkColumn = $pdo->prepare("
        SELECT COUNT(*) as column_exists 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'requests' 
        AND COLUMN_NAME = 'desired_date'
    ");
    $checkColumn->execute([$dbname]);
    $result = $checkColumn->fetch();

    if ($result['column_exists'] == 0) {
        echo "Adding 'desired_date' column to 'requests' table...\n";
        
        // Add the column
        $pdo->exec("
            ALTER TABLE requests 
            ADD COLUMN desired_date DATE 
            AFTER blood_type_needed
        ");

        // Set default value for existing records
        $pdo->exec("
            UPDATE requests 
            SET desired_date = CURDATE() 
            WHERE desired_date IS NULL
        ");

        echo "Successfully added 'desired_date' column.\n";
    } else {
        echo "'desired_date' column already exists.\n";
    }

    echo "Database setup completed successfully.\n";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}

echo "All done!\n";
