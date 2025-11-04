<?php
require_once 'includes/config.php';

try {
    // Establish database connection
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>Database Connection Successful</h3>";

    // Check for critical tables
    $tables = ['admin_users', 'blood_inventory', 'users_new'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "Table '$table' exists.<br>";
        } else {
            echo "<strong>Table '$table' is missing!</strong><br>";
        }
    }
} catch (PDOException $e) {
    // Detailed error message
    echo "<h3>Could not connect to the database.</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Check your database credentials and server configuration.</p>";
}
?>