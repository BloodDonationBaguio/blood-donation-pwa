<?php
/**
 * Enhanced Blood Inventory Migration Script
 * Run this script to set up the enhanced blood inventory system
 */

require_once 'db.php';

echo "Starting Enhanced Blood Inventory Migration...\n\n";

try {
    // Read the SQL file
    $sqlFile = 'sql/enhanced_blood_inventory_migration.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            $errorCount++;
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
        }
    }
    
    echo "\nMigration Summary:\n";
    echo "✓ Successful statements: $successCount\n";
    echo "✗ Failed statements: $errorCount\n\n";
    
    if ($errorCount === 0) {
        echo "🎉 Enhanced Blood Inventory system setup completed successfully!\n";
        echo "\nNext steps:\n";
        echo "1. Update admin.php to link to admin_blood_inventory_redesigned.php\n";
        echo "2. Test the new inventory management interface\n";
        echo "3. Configure user roles and permissions\n";
    } else {
        echo "⚠️  Migration completed with some errors. Please review the errors above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
