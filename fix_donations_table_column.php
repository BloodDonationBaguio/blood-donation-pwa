<?php
/**
 * Fix donations_new table column name
 * Changes 'donation_status' to 'status' to match the code expectations
 */

require_once 'db.php';

try {
    echo "<h2>Fixing donations_new table column...</h2>";
    
    // Check if the donations_new table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'donations_new'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>✓ Table donations_new doesn't exist yet. It will be created correctly when needed.</p>";
        exit;
    }
    
    // Check if the table has 'donation_status' column
    $stmt = $pdo->query("SHOW COLUMNS FROM donations_new LIKE 'donation_status'");
    if ($stmt->rowCount() > 0) {
        echo "<p>Found 'donation_status' column. Renaming to 'status'...</p>";
        
        // Rename the column
        $pdo->exec("ALTER TABLE donations_new CHANGE COLUMN donation_status status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled'");
        
        echo "<p style='color: green;'>✓ Successfully renamed 'donation_status' to 'status'</p>";
    } else {
        // Check if 'status' column already exists
        $stmt = $pdo->query("SHOW COLUMNS FROM donations_new LIKE 'status'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Table already has 'status' column. No changes needed.</p>";
        } else {
            echo "<p style='color: red;'>✗ Table exists but has neither 'donation_status' nor 'status' column. Manual intervention required.</p>";
        }
    }
    
    echo "<h3>Current donations_new table structure:</h3>";
    echo "<pre>";
    $stmt = $pdo->query("DESCRIBE donations_new");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']} - {$column['Default']}\n";
    }
    echo "</pre>";
    
    echo "<p style='color: green; font-weight: bold;'>✓ Migration complete!</p>";
    echo "<p><a href='admin_enhanced_donor_management.php'>Go back to Donor Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

