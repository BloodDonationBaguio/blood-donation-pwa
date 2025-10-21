<?php
/**
 * Complete Blood Inventory Migration Script
 */

require_once 'db.php';

echo "Starting Complete Blood Inventory Migration...\n\n";

try {
    // Read the SQL file
    $sqlFile = 'sql/complete_blood_inventory_migration.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL file");
    }
    
    // Split SQL into individual statements
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
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
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
        echo "🎉 Complete Blood Inventory system setup completed successfully!\n";
        echo "\nFeatures included:\n";
        echo "- Full CRUD operations for blood units\n";
        echo "- Role-based access control\n";
        echo "- Complete audit trail\n";
        echo "- Alerts and monitoring\n";
        echo "- Export capabilities\n";
        echo "- PII masking for privacy compliance\n";
    } else {
        echo "⚠️  Migration completed with some errors. Please review the errors above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
