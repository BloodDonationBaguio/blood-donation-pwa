<?php
/**
 * Fix Audit Log Table Structure
 * Adds missing action_type column
 */

require_once 'db.php';

echo "<h1>Fixing Audit Log Table Structure</h1>";
echo "<hr>";

try {
    // Check current structure
    echo "<h2>Current Table Structure</h2>";
    $columns = $pdo->query("SHOW COLUMNS FROM admin_audit_log")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if action_type column exists
    $hasActionType = false;
    $hasAction = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'action_type') $hasActionType = true;
        if ($col['Field'] === 'action') $hasAction = true;
    }
    
    echo "<h2>Fixing Structure</h2>";
    
    // Fix action column
    if ($hasAction && !$hasActionType) {
        echo "<p>Found 'action' column but missing 'action_type'. Renaming...</p>";
        $pdo->exec("ALTER TABLE admin_audit_log CHANGE COLUMN `action` `action_type` VARCHAR(255) NOT NULL");
        echo "<p style='color:green'>✓ Renamed 'action' to 'action_type'</p>";
    } elseif (!$hasActionType) {
        echo "<p>Adding 'action_type' column...</p>";
        $pdo->exec("ALTER TABLE admin_audit_log ADD COLUMN action_type VARCHAR(255) NOT NULL AFTER admin_username");
        echo "<p style='color:green'>✓ Added 'action_type' column</p>";
    } else {
        echo "<p style='color:green'>✓ 'action_type' column exists</p>";
    }
    
    // Fix record_id column type
    $recordIdCol = null;
    foreach ($columns as $col) {
        if ($col['Field'] === 'record_id') {
            $recordIdCol = $col;
            break;
        }
    }
    
    if ($recordIdCol) {
        $currentType = strtolower($recordIdCol['Type']);
        if (strpos($currentType, 'int') !== false) {
            echo "<p>Found 'record_id' as integer type. Converting to VARCHAR...</p>";
            $pdo->exec("ALTER TABLE admin_audit_log MODIFY COLUMN record_id VARCHAR(255) NULL");
            echo "<p style='color:green'>✓ Converted 'record_id' to VARCHAR(255)</p>";
        } else {
            echo "<p style='color:green'>✓ 'record_id' column type is correct (VARCHAR)</p>";
        }
    } else {
        echo "<p style='color:orange'>⚠ 'record_id' column not found</p>";
    }
    
    // Verify final structure
    echo "<h2>Updated Table Structure</h2>";
    $columns = $pdo->query("SHOW COLUMNS FROM admin_audit_log")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test insert
    echo "<h2>Testing Insert</h2>";
    $stmt = $pdo->prepare("INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description, ip_address)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        'test_admin',
        'test_fix',
        'test_table',
        'TEST-999',
        'Testing after column fix',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    if ($result) {
        echo "<p style='color:green'>✓ Test insert successful!</p>";
    } else {
        echo "<p style='color:red'>✗ Test insert failed</p>";
    }
    
    // Show recent entries
    echo "<h2>Recent Audit Log Entries</h2>";
    $logs = $pdo->query("SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 5")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Date</th><th>Admin</th><th>Action Type</th><th>Table</th><th>Record ID</th><th>Description</th></tr>";
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['id']}</td>";
        echo "<td>{$log['created_at']}</td>";
        echo "<td>" . htmlspecialchars($log['admin_username'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($log['action_type'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($log['table_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($log['record_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($log['description'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<p style='color:green; font-size:1.2rem'><strong>✓ Audit log table structure fixed!</strong></p>";
    echo "<p><a href='admin.php?tab=audit-log'>Go to Audit Log</a></p>";
    echo "<p><a href='test_audit_log.php'>Run Diagnostic Again</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

