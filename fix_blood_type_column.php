<?php
/**
 * Fix blood_type column size in donations_new table
 * Ensures it can hold blood type values like "A+", "AB-", etc.
 */

require_once 'db.php';

try {
    echo "<h2>Fixing blood_type column in donations_new table...</h2>";
    
    // Check if the donations_new table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'donations_new'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>Table donations_new doesn't exist yet. It will be created correctly when needed.</p>";
        exit;
    }
    
    // Check current blood_type column definition
    $stmt = $pdo->query("SHOW COLUMNS FROM donations_new WHERE Field = 'blood_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$column) {
        echo "<p style='color: red;'>✗ blood_type column doesn't exist in donations_new table!</p>";
        exit;
    }
    
    echo "<p>Current blood_type column definition: <strong>{$column['Type']}</strong></p>";
    
    // Check if it needs to be expanded
    if (stripos($column['Type'], 'varchar(5)') === false && 
        stripos($column['Type'], 'varchar(10)') === false &&
        stripos($column['Type'], 'varchar(3)') === false) {
        
        echo "<p style='color: orange;'>Column definition is: {$column['Type']}</p>";
        echo "<p>Updating to VARCHAR(10) to safely hold all blood type values...</p>";
        
        $pdo->exec("ALTER TABLE donations_new MODIFY COLUMN blood_type VARCHAR(10) NULL");
        
        echo "<p style='color: green;'>✓ Successfully updated blood_type column to VARCHAR(10)</p>";
    } else if (stripos($column['Type'], 'varchar(3)') !== false) {
        echo "<p style='color: orange;'>Column is VARCHAR(3), which might be too small. Updating to VARCHAR(10)...</p>";
        
        $pdo->exec("ALTER TABLE donations_new MODIFY COLUMN blood_type VARCHAR(10) NULL");
        
        echo "<p style='color: green;'>✓ Successfully updated blood_type column to VARCHAR(10)</p>";
    } else {
        echo "<p style='color: green;'>✓ blood_type column size is adequate ({$column['Type']})</p>";
    }
    
    // Also check and show a sample donor's blood_type to see what we're working with
    echo "<h3>Checking actual donor blood_type values...</h3>";
    $stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, blood_type, LENGTH(blood_type) as len 
                         FROM donors_new 
                         WHERE id = 63 
                         LIMIT 1");
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($donor) {
        echo "<p>Donor ID 63 (Ali Lubaton):</p>";
        echo "<ul>";
        echo "<li>Blood Type: <strong>" . htmlspecialchars($donor['blood_type']) . "</strong></li>";
        echo "<li>Length: <strong>{$donor['len']}</strong> characters</li>";
        echo "</ul>";
        
        if ($donor['len'] > 10) {
            echo "<p style='color: red;'>⚠️ WARNING: This blood_type value is too long! It has {$donor['len']} characters.</p>";
            echo "<p>The value might be corrupted. Expected values: A+, A-, B+, B-, AB+, AB-, O+, O-</p>";
        }
    }
    
    echo "<h3>Current donations_new table structure:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    $stmt = $pdo->query("DESCRIBE donations_new");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td><strong>{$col['Type']}</strong></td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green; font-weight: bold; margin-top: 20px;'>✓ Fix complete!</p>";
    echo "<p><a href='admin_enhanced_donor_management.php?donor_id=63'>← Go back and try updating donor status again</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

