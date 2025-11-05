<?php
/**
 * Comprehensive fix for all donor status update issues
 * Fixes both column name and column size problems
 */

require_once 'db.php';

$fixes_applied = [];
$warnings = [];

try {
    echo "<h1>Comprehensive Donor System Fix</h1>";
    echo "<p>This script will fix all known issues with the donor status update system.</p>";
    echo "<hr>";
    
    // Fix 1: Ensure donations_new table exists
    echo "<h2>1. Checking donations_new table...</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'donations_new'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>⚠️ Table donations_new doesn't exist. Creating it now...</p>";
        $pdo->exec("CREATE TABLE IF NOT EXISTS donations_new (
            id INT AUTO_INCREMENT PRIMARY KEY,
            donor_id INT NOT NULL,
            donation_date DATE NOT NULL,
            blood_type VARCHAR(10),
            units_donated INT DEFAULT 1,
            status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_donor_id (donor_id),
            INDEX idx_donation_date (donation_date)
        )");
        echo "<p style='color: green;'>✓ Created donations_new table with correct schema</p>";
        $fixes_applied[] = "Created donations_new table";
    } else {
        echo "<p style='color: green;'>✓ donations_new table exists</p>";
    }
    
    // Fix 2: Check and fix column name (donation_status vs status)
    echo "<h2>2. Checking column name (donation_status vs status)...</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM donations_new");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'Field');
    
    if (in_array('donation_status', $column_names) && !in_array('status', $column_names)) {
        echo "<p style='color: orange;'>⚠️ Found 'donation_status' column (should be 'status'). Renaming...</p>";
        $pdo->exec("ALTER TABLE donations_new CHANGE COLUMN donation_status status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled'");
        echo "<p style='color: green;'>✓ Renamed 'donation_status' to 'status'</p>";
        $fixes_applied[] = "Renamed donation_status column to status";
    } elseif (in_array('status', $column_names)) {
        echo "<p style='color: green;'>✓ Column name is correct ('status')</p>";
    } else {
        echo "<p style='color: red;'>✗ Table has neither 'donation_status' nor 'status' column!</p>";
        $warnings[] = "Missing status column in donations_new";
    }
    
    // Fix 3: Check and fix blood_type column size
    echo "<h2>3. Checking blood_type column size...</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM donations_new WHERE Field = 'blood_type'");
    $blood_type_col = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($blood_type_col) {
        echo "<p>Current definition: <strong>{$blood_type_col['Type']}</strong></p>";
        
        // Extract size from VARCHAR(n)
        if (preg_match('/varchar\((\d+)\)/i', $blood_type_col['Type'], $matches)) {
            $current_size = (int)$matches[1];
            if ($current_size < 10) {
                echo "<p style='color: orange;'>⚠️ blood_type column is too small (VARCHAR({$current_size})). Expanding to VARCHAR(10)...</p>";
                $pdo->exec("ALTER TABLE donations_new MODIFY COLUMN blood_type VARCHAR(10) NULL");
                echo "<p style='color: green;'>✓ Expanded blood_type column to VARCHAR(10)</p>";
                $fixes_applied[] = "Expanded blood_type column from VARCHAR($current_size) to VARCHAR(10)";
            } else {
                echo "<p style='color: green;'>✓ blood_type column size is adequate (VARCHAR({$current_size}))</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>✗ blood_type column not found!</p>";
        $warnings[] = "Missing blood_type column in donations_new";
    }
    
    // Fix 4: Check admin_audit_log table
    echo "<h2>4. Checking admin_audit_log table...</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_audit_log'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>⚠️ admin_audit_log table doesn't exist. Creating it...</p>";
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            admin_username VARCHAR(255) NULL,
            action_type VARCHAR(255) NOT NULL,
            table_name VARCHAR(255) NULL,
            record_id VARCHAR(255) NULL,
            description TEXT NULL,
            ip_address VARCHAR(64) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<p style='color: green;'>✓ Created admin_audit_log table</p>";
        $fixes_applied[] = "Created admin_audit_log table";
    } else {
        echo "<p style='color: green;'>✓ admin_audit_log table exists</p>";
    }
    
    // Fix 5: Check donors_new status column
    echo "<h2>5. Checking donors_new status column...</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM donors_new WHERE Field = 'status'");
    $donor_status_col = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($donor_status_col) {
        echo "<p>Current definition: <strong>{$donor_status_col['Type']}</strong></p>";
        // Check if 'served' is in the enum
        if (stripos($donor_status_col['Type'], "'served'") === false) {
            echo "<p style='color: orange;'>⚠️ 'served' status not in ENUM. Adding it...</p>";
            $pdo->exec("ALTER TABLE donors_new MODIFY COLUMN status ENUM('pending','approved','served','rejected','suspended','unserved') DEFAULT 'pending'");
            echo "<p style='color: green;'>✓ Added 'served' to status ENUM</p>";
            $fixes_applied[] = "Added 'served' to donors_new.status ENUM";
        } else {
            echo "<p style='color: green;'>✓ donors_new.status includes 'served'</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ donors_new.status column not found!</p>";
        $warnings[] = "Missing status column in donors_new";
    }
    
    // Check actual donor blood_type value
    echo "<h2>6. Checking donor ID 63 blood_type...</h2>";
    $stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, blood_type, LENGTH(blood_type) as len 
                         FROM donors_new 
                         WHERE id = 63 
                         LIMIT 1");
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($donor) {
        echo "<p><strong>Donor:</strong> {$donor['name']}</p>";
        echo "<p><strong>Blood Type:</strong> '{$donor['blood_type']}' ({$donor['len']} characters)</p>";
        
        if ($donor['len'] > 10) {
            echo "<p style='color: red;'>⚠️ WARNING: Blood type value is too long ({$donor['len']} chars)!</p>";
            echo "<p>Expected values are: A+, A-, B+, B-, AB+, AB-, O+, O- (2-3 characters)</p>";
            $warnings[] = "Donor 63 has abnormally long blood_type value: " . $donor['blood_type'];
        } else {
            echo "<p style='color: green;'>✓ Blood type value length is normal</p>";
        }
    } else {
        echo "<p style='color: orange;'>Donor ID 63 not found in database</p>";
    }
    
    // Summary
    echo "<hr>";
    echo "<h2>Summary</h2>";
    
    if (count($fixes_applied) > 0) {
        echo "<h3 style='color: green;'>✓ Fixes Applied:</h3>";
        echo "<ul>";
        foreach ($fixes_applied as $fix) {
            echo "<li>$fix</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: green;'><strong>✓ No fixes needed - all checks passed!</strong></p>";
    }
    
    if (count($warnings) > 0) {
        echo "<h3 style='color: orange;'>⚠️ Warnings:</h3>";
        echo "<ul>";
        foreach ($warnings as $warning) {
            echo "<li>$warning</li>";
        }
        echo "</ul>";
    }
    
    // Final table structure
    echo "<h3>Final donations_new Table Structure:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    $stmt = $pdo->query("DESCRIBE donations_new");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        $highlight = ($col['Field'] == 'status' || $col['Field'] == 'blood_type') ? "background: #ffffcc;" : "";
        echo "<tr style='$highlight'>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<p style='font-size: 18px; color: green; font-weight: bold;'>🎉 All fixes complete!</p>";
    echo "<p><a href='admin_enhanced_donor_management.php?donor_id=63' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>← Go back and try updating donor status</a></p>";
    
} catch (Exception $e) {
    echo "<hr>";
    echo "<h2 style='color: red;'>✗ Error Occurred</h2>";
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<details>";
    echo "<summary>Stack Trace (click to expand)</summary>";
    echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</details>";
}
?>

