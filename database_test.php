<?php

require_once __DIR__ . '/includes/config.php';

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Database connection successful!\n";

    // List of tables to check
    $tables = ['admin_users', 'blood_inventory', 'users_new'];

    foreach ($tables as $table) {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            echo "Table '$table' does not exist or is empty.\n";
        } else {
            echo "Table '$table' exists and has " . count($result) . " columns.\n";
        }
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}

?>