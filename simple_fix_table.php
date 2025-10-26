<?php
// Simple table fix script - no redirects, no includes
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Fix Donors Table</title></head><body>";
echo "<h1>Fix Donors Table</h1>";
echo "<pre>";

try {
    // Direct database connection
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'blood_donation';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    
    // Try DATABASE_URL first (for Render)
    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl) {
        $pdo = new PDO($databaseUrl);
    } else {
        $dsn = "pgsql:host=$host;dbname=$dbname";
        $pdo = new PDO($dsn, $username, $password);
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connected successfully\n\n";
    
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
    echo "\n=== TESTING STATUS UPDATES ===\n";
    
    // Test a simple status update
    try {
        $testStmt = $pdo->prepare("UPDATE donors SET status = 'approved' WHERE id = 1");
        $testStmt->execute();
        echo "✓ Status update test successful\n";
    } catch (Exception $e) {
        echo "❌ Status update test failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='admin-login.php'>Go to Admin Login</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
echo "</body></html>";
?>
