<?php
require_once 'db.php';

echo "<h1>Fix Donors Table</h1>";
echo "<pre>";

try {
    // Check current table structure
    echo "=== CHECKING CURRENT TABLE STRUCTURE ===\n";
    $result = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default 
        FROM information_schema.columns 
        WHERE table_name = 'donors' 
        ORDER BY ordinal_position
    ");
    
    $existingColumns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['column_name'];
        echo "Column: {$row['column_name']} ({$row['data_type']})\n";
    }
    
    // Add missing columns
    echo "\n=== ADDING MISSING COLUMNS ===\n";
    
    $columnsToAdd = [
        'rejection_reason' => 'TEXT',
        'unserved_reason' => 'TEXT',
        'served_date' => 'TIMESTAMP',
        'last_donation_date' => 'TIMESTAMP'
    ];
    
    foreach ($columnsToAdd as $column => $type) {
        if (!in_array($column, $existingColumns)) {
            echo "Adding column: $column ($type)...\n";
            $pdo->exec("ALTER TABLE donors ADD COLUMN $column $type");
            echo "✓ Added $column\n";
        } else {
            echo "✓ Column $column already exists\n";
        }
    }
    
    // Check final structure
    echo "\n=== FINAL TABLE STRUCTURE ===\n";
    $result = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default 
        FROM information_schema.columns 
        WHERE table_name = 'donors' 
        ORDER BY ordinal_position
    ");
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "Column: {$row['column_name']} ({$row['data_type']})\n";
    }
    
    echo "\n✅ DONORS TABLE FIXED!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='admin.php'>Go to Admin Panel</a></p>";
?>
