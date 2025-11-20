<?php
// Verify fixes for the three issues:
// 1. Donor notes creation
// 2. Donations This Year counter
// 3. Audit logging for donor management actions

// Set error reporting for diagnostics
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/enhanced_donor_management.php';
require_once __DIR__ . '/includes/admin_actions.php';

// Start session for admin access
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'diagnostic_script';
$_SESSION['admin_id'] = 999;

// Helper function to output results
function output_section($title, $status, $details = '') {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid " . ($status ? '#4CAF50' : '#F44336') . "; border-radius: 5px;'>";
    echo "<h3 style='margin-top: 0; color: " . ($status ? '#4CAF50' : '#F44336') . ";'>" . ($status ? '✅ ' : '❌ ') . $title . "</h3>";
    if (!empty($details)) {
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 3px; overflow: auto;'>" . htmlspecialchars($details) . "</pre>";
    }
    echo "</div>";
}

// Helper function to get database driver
function get_db_driver($pdo) {
    try {
        return strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    } catch (Throwable $e) {
        return 'mysql';
    }
}

// Output page header
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow: auto; }
        .success { color: #4CAF50; }
        .error { color: #F44336; }
    </style>
</head>
<body>
    <h1>Fix Verification</h1>
    <p>This script verifies that all three issues have been fixed.</p>
";

// 1. Test donor_notes table and adding notes
echo "<h2>1. Testing donor_notes table and note creation</h2>";

try {
    // Check if donor_notes table exists
    $tableExists = false;
    $driver = get_db_driver($pdo);
    
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
        $stmt->execute([':t' => 'donor_notes']);
        $tableExists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'donor_notes'");
        $stmt->execute();
        $tableExists = $stmt->fetch() !== false;
    }
    
    if (!$tableExists) {
        // Create the table using our function
        ensureDonorNotesTableExists($pdo);
        
        // Check again if it was created
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
            $stmt->execute([':t' => 'donor_notes']);
            $tableExists = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'donor_notes'");
            $stmt->execute();
            $tableExists = $stmt->fetch() !== false;
        }
    }
    
    output_section("donor_notes table exists", $tableExists);
    
    // Test adding a note to a donor
    // First, find a donor to add a note to
    $stmt = $pdo->query("SELECT id FROM donors LIMIT 1");
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($donor) {
        $donorId = $donor['id'];
        $noteText = "Test note from verify_fixes.php script - " . date('Y-m-d H:i:s');
        
        // Add the note
        $noteAdded = addDonorNote($pdo, $donorId, $noteText, 'diagnostic_script');
        
        // Verify the note was added
        $stmt = $pdo->prepare("SELECT note FROM donor_notes WHERE donor_id = ? AND note LIKE ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$donorId, "%$noteText%"]);
        $foundNote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        output_section("Adding note to donor ID $donorId", $noteAdded && $foundNote, 
            "Note text: $noteText\n" . 
            "Note found in database: " . ($foundNote ? "Yes" : "No")
        );
    } else {
        output_section("Finding donor to test note addition", false, "No donors found in the database");
    }
} catch (Throwable $e) {
    output_section("Testing donor_notes", false, "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

// 2. Test Donations This Year counter
echo "<h2>2. Testing Donations This Year counter</h2>";

try {
    $currentYear = date('Y');
    $driver = get_db_driver($pdo);
    
    // Count completed donations in current year
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND EXTRACT(YEAR FROM donation_date) = ?");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND YEAR(donation_date) = ?");
    }
    
    $stmt->execute([$currentYear]);
    $completedCount = (int)$stmt->fetchColumn();
    
    // Count served donors in current year
    if ($driver === 'pgsql') {
        $servedStmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE status = 'served' AND EXTRACT(YEAR FROM COALESCE(served_date, created_at)) = ?");
    } else {
        $servedStmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE status = 'served' AND YEAR(COALESCE(served_date, created_at)) = ?");
    }
    
    $servedStmt->execute([$currentYear]);
    $servedCount = (int)$servedStmt->fetchColumn();
    
    output_section("Donations This Year counter", $completedCount > 0 || $servedCount > 0, 
        "Completed donations in $currentYear: $completedCount\n" .
        "Served donors in $currentYear: $servedCount\n" .
        "Counter should show: " . max($completedCount, $servedCount)
    );
    
    // If no completed donations but we have served donors, add them to donations_new
    if ($completedCount === 0 && $servedCount > 0) {
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #FFC107; border-radius: 5px;'>";
        echo "<h3 style='margin-top: 0; color: #FFC107;'>⚠️ Recommendation</h3>";
        echo "<p>You have $servedCount served donors but no completed donations. Consider running the diagnostic_fix.php script to populate the donations_new table.</p>";
        echo "</div>";
    }
} catch (Throwable $e) {
    output_section("Testing Donations This Year counter", false, "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

// 3. Test audit logging for donor management actions
echo "<h2>3. Testing audit logging for donor management actions</h2>";

try {
    // Check if admin_audit_log table exists
    $tableExists = false;
    $driver = get_db_driver($pdo);
    
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
        $stmt->execute([':t' => 'admin_audit_log']);
        $tableExists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'admin_audit_log'");
        $stmt->execute();
        $tableExists = $stmt->fetch() !== false;
    }
    
    if (!$tableExists) {
        // Create the table using our function
        ensureAuditLogTableExists($pdo);
        
        // Check again if it was created
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
            $stmt->execute([':t' => 'admin_audit_log']);
            $tableExists = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'admin_audit_log'");
            $stmt->execute();
            $tableExists = $stmt->fetch() !== false;
        }
    }
    
    output_section("admin_audit_log table exists", $tableExists);
    
    // Test adding a donor action to the audit log
    $actionType = 'diagnostic_test';
    $tableName = 'donors';
    $recordId = '0';
    $description = 'Test audit log entry from verify_fixes.php - ' . date('Y-m-d H:i:s');
    
    // Add the audit log entry (correct parameter order: $pdo, $actionType, $tableName, $recordId, $actionDetails, $adminId)
    $logAdded = logAdminAction($pdo, $actionType, $tableName, $recordId, $description, 'diagnostic_script');
    
    // Verify the log entry was added
    $stmt = $pdo->prepare("SELECT * FROM admin_audit_log WHERE action_type = ? AND description LIKE ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$actionType, "%$description%"]);
    $foundLog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    output_section("Adding audit log entry", $logAdded && $foundLog, 
        "Action type: $actionType\n" .
        "Description: $description\n" .
        "Log entry found in database: " . ($foundLog ? "Yes" : "No")
    );
    
    // Check for donor action types in the audit log
    $donorActionTypes = ['donor_approved', 'donor_rejected', 'donor_marked_served', 'donor_marked_unserved', 'donor_deleted', 'donor_status_updated'];
    $stmt = $pdo->prepare("SELECT action_type, COUNT(*) as count FROM admin_audit_log WHERE action_type IN ('" . implode("','", $donorActionTypes) . "') GROUP BY action_type");
    $stmt->execute();
    $donorActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $donorActionsFound = !empty($donorActions);
    $donorActionDetails = "";
    foreach ($donorActions as $action) {
        $donorActionDetails .= $action['action_type'] . ": " . $action['count'] . " entries\n";
    }
    
    if (empty($donorActionDetails)) {
        $donorActionDetails = "No donor action types found in the audit log";
    }
    
    output_section("Donor action types in audit log", $donorActionsFound, $donorActionDetails);
    
    // If no donor actions found, add a test entry for each type
    if (!$donorActionsFound) {
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #FFC107; border-radius: 5px;'>";
        echo "<h3 style='margin-top: 0; color: #FFC107;'>⚠️ Adding test donor action entries</h3>";
        
        foreach ($donorActionTypes as $type) {
            $logAdded = logAdminAction($pdo, 'diagnostic_script', $type, 'donors', '0', "Test $type action from verify_fixes.php");
            echo "<p>" . ($logAdded ? "✅" : "❌") . " Added test entry for: $type</p>";
        }
        
        echo "</div>";
    }
} catch (Throwable $e) {
    output_section("Testing audit logging", false, "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

// Summary
echo "<h2>Summary</h2>";
echo "<p>The verification script has completed. Please review the results above to confirm all issues have been fixed.</p>";
echo "<p>If any tests failed, you may need to run the diagnostic_fix.php script or manually fix the remaining issues.</p>";

// Footer
echo "</body></html>";
?>
