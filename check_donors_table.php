<?php
require_once 'db.php';

echo "<h1>Donors Table Structure</h1>";
echo "<pre>";

try {
    // Get table structure
    $result = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default 
        FROM information_schema.columns 
        WHERE table_name = 'donors' 
        ORDER BY ordinal_position
    ");
    
    echo "=== DONORS TABLE STRUCTURE ===\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "Column: {$row['column_name']}\n";
        echo "  Type: {$row['data_type']}\n";
        echo "  Nullable: {$row['is_nullable']}\n";
        echo "  Default: " . ($row['column_default'] ?? 'NULL') . "\n";
        echo "---\n";
    }
    
    // Check current data
    echo "\n=== SAMPLE DATA ===\n";
    $sample = $pdo->query("SELECT * FROM donors LIMIT 3");
    while ($row = $sample->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
