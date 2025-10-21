<?php
/**
 * Simple Blood Inventory Migration Script
 * Run this script to set up the enhanced blood inventory system
 */

require_once 'db.php';

echo "Starting Simple Blood Inventory Migration...\n\n";

try {
    // Read the SQL file
    $sqlFile = 'sql/enhanced_blood_inventory_simple.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL file");
    }
    
    // Split SQL into individual statements, handling multi-line statements
    $statements = [];
    $currentStatement = '';
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }
        
        $currentStatement .= $line . "\n";
        
        if (substr($line, -1) === ';') {
            $statements[] = trim($currentStatement);
            $currentStatement = '';
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
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
