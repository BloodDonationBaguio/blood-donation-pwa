<?php
/**
 * Audit Log Diagnostic Script
 * Tests audit logging functionality
 */

session_start();
require_once 'db.php';

// Set admin session for testing
$_SESSION['admin_username'] = $_SESSION['admin_username'] ?? 'test_admin';
$_SESSION['admin_logged_in'] = true;

echo "<h1>Audit Log Diagnostic Test</h1>";
echo "<hr>";

// Check if tables exist
echo "<h2>1. Checking Tables</h2>";
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><strong>Existing tables:</strong> " . implode(', ', $tables) . "</p>";
    
    $hasAuditLog = in_array('admin_audit_log', $tables);
    $hasBloodAudit = in_array('blood_inventory_audit', $tables);
    
    echo "<p>✓ admin_audit_log exists: " . ($hasAuditLog ? 'YES' : 'NO') . "</p>";
    echo "<p>✓ blood_inventory_audit exists: " . ($hasBloodAudit ? 'YES' : 'NO') . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Create tables if they don't exist
echo "<h2>2. Creating Tables (if needed)</h2>";
try {
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
    echo "<p>✓ admin_audit_log table ready</p>";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS blood_inventory_audit (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_id VARCHAR(100),
        action VARCHAR(100),
        old_values TEXT,
        new_values TEXT,
        admin_name VARCHAR(255),
        ip_address VARCHAR(64),
        user_agent TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p>✓ blood_inventory_audit table ready</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error creating tables: " . $e->getMessage() . "</p>";
}

// Test insert into admin_audit_log
echo "<h2>3. Testing Insert into admin_audit_log</h2>";
try {
    // Check if table has action_type column
    $columns = $pdo->query("SHOW COLUMNS FROM admin_audit_log")->fetchAll(PDO::FETCH_COLUMN);
    $hasActionType = in_array('action_type', $columns);
    
    if (!$hasActionType) {
        echo "<p style='color:orange'>⚠ Table missing 'action_type' column. <a href='fix_audit_log_table.php'>Click here to fix</a></p>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description, ip_address)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $_SESSION['admin_username'],
            'test_action',
            'test_table',
            'TEST-123',
            'This is a test audit log entry',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        if ($result) {
            $insertId = $pdo->lastInsertId();
            echo "<p style='color:green'>✓ Successfully inserted test record (ID: $insertId)</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to insert test record</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    echo "<p><a href='fix_audit_log_table.php' class='btn btn-warning'>Fix Table Structure</a></p>";
}

// Check current audit log entries
echo "<h2>4. Current Audit Log Entries</h2>";
try {
    $logs = $pdo->query("SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 10")->fetchAll();
    
    if (empty($logs)) {
        echo "<p style='color:orange'>No audit log entries found</p>";
    } else {
        echo "<p><strong>Found " . count($logs) . " entries (showing last 10)</strong></p>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>Date</th><th>Admin</th><th>Action</th><th>Table</th><th>Record ID</th><th>Description</th></tr>";
        foreach ($logs as $log) {
            // Handle both old 'action' column and new 'action_type' column
            $actionValue = $log['action_type'] ?? $log['action'] ?? '';
            
            echo "<tr>";
            echo "<td>" . ($log['id'] ?? '') . "</td>";
            echo "<td>" . ($log['created_at'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($log['admin_username'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($actionValue) . "</td>";
            echo "<td>" . htmlspecialchars($log['table_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($log['record_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($log['description'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Check blood inventory audit
echo "<h2>5. Blood Inventory Audit Entries</h2>";
try {
    $logs = $pdo->query("SELECT * FROM blood_inventory_audit ORDER BY timestamp DESC LIMIT 10")->fetchAll();
    
    if (empty($logs)) {
        echo "<p style='color:orange'>No blood inventory audit entries found</p>";
    } else {
        echo "<p><strong>Found " . count($logs) . " entries</strong></p>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>Unit ID</th><th>Action</th><th>Admin</th><th>Timestamp</th></tr>";
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td>" . $log['id'] . "</td>";
            echo "<td>" . htmlspecialchars($log['unit_id']) . "</td>";
            echo "<td>" . htmlspecialchars($log['action']) . "</td>";
            echo "<td>" . htmlspecialchars($log['admin_name']) . "</td>";
            echo "<td>" . $log['timestamp'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Check error log
echo "<h2>6. Recent Error Log</h2>";
$logFile = __DIR__ . '/logs/error.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -20);
    echo "<pre style='background:#f4f4f4; padding:10px; overflow:auto; max-height:300px'>";
    foreach ($recent as $line) {
        if (stripos($line, 'audit') !== false) {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";
} else {
    echo "<p>Error log file not found at: $logFile</p>";
}

echo "<hr>";
echo "<p><a href='admin.php?tab=audit-log'>Go to Admin Audit Log</a></p>";
echo "<p><a href='admin_blood_inventory_modern.php'>Go to Blood Inventory</a></p>";
?>

