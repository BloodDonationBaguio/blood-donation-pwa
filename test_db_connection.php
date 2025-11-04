<?php
// test_db_connection.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Attempting to connect to the database using db.php...\n";

try {
    // Include the database configuration
    require_once __DIR__ . '/db.php';

    if (isset($pdo)) {
        echo "Database connection successful!\n";
        // Optionally, run a simple query
        $stmt = $pdo->query("SELECT 1");
        if ($stmt) {
            echo "Test query executed successfully.\n";
        } else {
            echo "Test query failed.\n";
        }
    } else {
        echo "Database connection failed: \$pdo object not created.\n";
    }
} catch (Exception $e) {
    echo "An exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace: \n" . $e->getTraceAsString() . "\n";
}

