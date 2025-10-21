<?php
/**
 * Add seed_flag column to blood_inventory table
 */

require_once 'db.php';

echo "=== Adding seed_flag column to blood_inventory ===\n";

try {
    // Check if column exists
    $checkQuery = "SHOW COLUMNS FROM blood_inventory LIKE 'seed_flag'";
    $checkStmt = $pdo->query($checkQuery);
    $columnExists = $checkStmt->fetch() !== false;
    
    if (!$columnExists) {
        // Add the column
        $alterQuery = "ALTER TABLE blood_inventory ADD COLUMN seed_flag TINYINT(1) DEFAULT 0";
        $pdo->exec($alterQuery);
        echo "✅ Added seed_flag column to blood_inventory table\n";
    } else {
        echo "✅ seed_flag column already exists\n";
    }
    
    // Also check donors_new table
    $checkDonorsQuery = "SHOW COLUMNS FROM donors_new LIKE 'seed_flag'";
    $checkDonorsStmt = $pdo->query($checkDonorsQuery);
    $donorsColumnExists = $checkDonorsStmt->fetch() !== false;
    
    if (!$donorsColumnExists) {
        // Add the column
        $alterDonorsQuery = "ALTER TABLE donors_new ADD COLUMN seed_flag TINYINT(1) DEFAULT 0";
        $pdo->exec($alterDonorsQuery);
        echo "✅ Added seed_flag column to donors_new table\n";
    } else {
        echo "✅ seed_flag column already exists in donors_new\n";
    }
    
    echo "✅ Database structure updated successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
