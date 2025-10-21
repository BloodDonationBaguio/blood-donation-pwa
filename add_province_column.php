<?php
// Include database configuration
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getPDO();
    
    // Add province column if it doesn't exist
    $sql = "ALTER TABLE `donors` 
            ADD COLUMN IF NOT EXISTS `province` VARCHAR(100) NULL DEFAULT NULL 
            AFTER `city`";
    
    $pdo->exec($sql);
    
    echo "Successfully added 'province' column to donors table.\n";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}

echo "Database update complete.\n";
