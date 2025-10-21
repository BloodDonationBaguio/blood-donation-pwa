<?php
// Database configuration
$host = "localhost";
$db = "blood_system";
$user = "root";
$pass = "";

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
    $pdo->exec("USE `$db`");
    
    // Read and execute the migration file
    $migration = file_get_contents('migrations/006_add_form_builder_tables.sql');
    $pdo->exec($migration);
    
    echo "Migration completed successfully!\n";
    
    // Verify the table was created
    $stmt = $pdo->query("SHOW TABLES LIKE 'form_configs'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'form_configs' was created successfully.\n";
        
        // Verify default data was inserted
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM form_configs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Number of form configurations: " . $result['count'] . "\n";
    } else {
        echo "Error: Table 'form_configs' was not created.\n";
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
