<?php
// Database diagnostic test file
// This file checks for table existence and structure in PostgreSQL

// Set headers for plain text output
header('Content-Type: text/plain');

// Include database connection
require_once 'db.php';
require_once 'pg_compat.php';

echo "=== DATABASE DIAGNOSTIC TEST ===\n\n";

// Check which database type we're using
echo "Database Type: " . (defined('DB_TYPE') ? DB_TYPE : "Unknown") . "\n\n";

// Function to check if a table exists
function checkTableExists($pdo, $tableName) {
    try {
        // For PostgreSQL
        $stmt = $pdo->prepare("SELECT to_regclass('public.$tableName') AS exists");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return !empty($result['exists']);
    } catch (Exception $e) {
        return false;
    }
}

// List of tables to check
$tables = [
    'users',
    'users_new',
    'donors',
    'donors_new',
    'blood_inventory',
    'blood_requests'
];

echo "Checking table existence:\n";
echo "------------------------\n";

foreach ($tables as $table) {
    $exists = checkTableExists($pdo, $table);
    echo "$table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\n";

// Get list of all tables in the database
echo "All tables in database:\n";
echo "---------------------\n";

try {
    // For PostgreSQL
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($allTables as $table) {
        echo "- $table\n";
    }
} catch (Exception $e) {
    echo "Error listing tables: " . $e->getMessage() . "\n";
}

// Check donors table structure (if it exists)
echo "\nChecking donors table structure:\n";
echo "-----------------------------\n";

try {
    // Try to get the structure of the donors table (or donors_new if it exists)
    $donorsTable = checkTableExists($pdo, 'donors_new') ? 'donors_new' : 
                  (checkTableExists($pdo, 'donors') ? 'donors' : null);
    
    if ($donorsTable) {
        $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$donorsTable'");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Columns in $donorsTable table:\n";
        foreach ($columns as $column) {
            echo "- {$column['column_name']} ({$column['data_type']})\n";
        }
    } else {
        echo "No donors table found to check structure.\n";
    }
} catch (Exception $e) {
    echo "Error checking table structure: " . $e->getMessage() . "\n";
}

echo "\n=== END OF DIAGNOSTIC TEST ===\n";
?>