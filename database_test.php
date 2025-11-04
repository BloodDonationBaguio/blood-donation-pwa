<?php
// Include the database configuration file
require_once __DIR__ . '/includes/config.php';

try {
    // Check the database connection
    $pdo->query("SELECT 1");
    echo "<p>Database connection successful.</p>";

    // Check for critical tables
    $tables = ['admin_users', 'blood_inventory', 'users_new'];
    foreach ($tables as $table) {
        $result = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        echo "<p>Table '{$table}' exists and is accessible.</p>";
    }
} catch (PDOException $e) {
    // Output detailed error message
    echo "<p>Could not connect to the database.</p>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Check your database credentials and server configuration.</p>";
}
?>