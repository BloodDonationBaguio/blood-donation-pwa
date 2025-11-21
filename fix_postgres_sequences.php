<?php
/**
 * PostgreSQL Sequence Fix Script
 * 
 * This script fixes the sequence issues with PostgreSQL tables where
 * the ID column is not properly associated with its sequence.
 */

// Set error reporting for diagnostics
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/db.php';

// Check if we're using PostgreSQL
$driver = '';
try {
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
} catch (Throwable $e) {
    die("Error detecting database driver: " . $e->getMessage());
}

if ($driver !== 'pgsql') {
    die("This script is only for PostgreSQL databases. Current driver: $driver");
}

// Output page header
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>PostgreSQL Sequence Fix</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow: auto; }
        .success { color: #4CAF50; }
        .error { color: #F44336; }
        .info { color: #2196F3; }
    </style>
</head>
<body>
    <h1>PostgreSQL Sequence Fix</h1>
    <p>This script fixes sequence issues with PostgreSQL tables.</p>
";

// Function to fix a table's sequence
function fixTableSequence($pdo, $table, $idColumn = 'id', $sequenceName = null) {
    try {
        // If sequence name not provided, use the default PostgreSQL naming convention
        if ($sequenceName === null) {
            $sequenceName = "{$table}_{$idColumn}_seq";
        }
        
        // Check if the sequence exists
        $stmt = $pdo->prepare("SELECT EXISTS(SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = :seq)");
        $stmt->execute([':seq' => $sequenceName]);
        $sequenceExists = (bool)$stmt->fetchColumn();
        
        if (!$sequenceExists) {
            echo "<p class='error'>Sequence '$sequenceName' does not exist. Creating it...</p>";
            
            // Create the sequence
            $pdo->exec("CREATE SEQUENCE IF NOT EXISTS $sequenceName");
            
            // Set the sequence to start from the maximum ID + 1
            $stmt = $pdo->prepare("SELECT COALESCE(MAX($idColumn), 0) + 1 FROM $table");
            $stmt->execute();
            $nextVal = $stmt->fetchColumn();
            
            $pdo->exec("ALTER SEQUENCE $sequenceName RESTART WITH $nextVal");
            
            echo "<p class='success'>Created sequence '$sequenceName' starting from $nextVal</p>";
        }
        
        // Check if the column is using the sequence
        $stmt = $pdo->prepare("
            SELECT pg_get_serial_sequence('$table', '$idColumn') AS seq_name
        ");
        $stmt->execute();
        $currentSeq = $stmt->fetchColumn();
        
        if ($currentSeq !== "public.$sequenceName") {
            echo "<p class='info'>Column '$idColumn' in table '$table' is not using sequence '$sequenceName'. Current sequence: " . ($currentSeq ?: 'none') . "</p>";
            
            // Set the default value for the column to use the sequence
            $pdo->exec("ALTER TABLE $table ALTER COLUMN $idColumn SET DEFAULT nextval('$sequenceName')");
            
            echo "<p class='success'>Set column '$idColumn' in table '$table' to use sequence '$sequenceName'</p>";
        } else {
            echo "<p class='success'>Column '$idColumn' in table '$table' is already using sequence '$sequenceName'</p>";
        }
        
        // Update the sequence value to be at least max(id) + 1
        $stmt = $pdo->prepare("SELECT COALESCE(MAX($idColumn), 0) + 1 FROM $table");
        $stmt->execute();
        $nextVal = $stmt->fetchColumn();
        
        $pdo->exec("SELECT setval('$sequenceName', $nextVal, false)");
        
        echo "<p class='success'>Updated sequence '$sequenceName' to start from $nextVal</p>";
        
        return true;
    } catch (Throwable $e) {
        echo "<p class='error'>Error fixing sequence for table '$table': " . $e->getMessage() . "</p>";
        return false;
    }
}

// Tables to fix
$tables = [
    'donor_notes',
    'donations_new',
    'admin_audit_log',
    'donors',
    'blood_inventory'
];

echo "<h2>Fixing Sequences</h2>";

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    
    // Check if the table exists
    $stmt = $pdo->prepare("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
    $stmt->execute([':t' => $table]);
    $tableExists = (bool)$stmt->fetchColumn();
    
    if ($tableExists) {
        fixTableSequence($pdo, $table);
    } else {
        echo "<p class='info'>Table '$table' does not exist. Skipping.</p>";
    }
}

// Test inserting into tables that had issues
echo "<h2>Testing Inserts</h2>";

try {
    // Test donor_notes
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by) VALUES (1, 'Test note after sequence fix', 'sequence_fix_script')");
    $stmt->execute();
    $noteId = $pdo->lastInsertId('donor_notes_id_seq');
    
    echo "<p class='success'>Successfully inserted into donor_notes with ID: $noteId</p>";
    
    // Test admin_audit_log
    $stmt = $pdo->prepare("INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description) VALUES ('sequence_fix_script', 'test_action', 'test_table', '1', 'Test audit log entry after sequence fix')");
    $stmt->execute();
    $logId = $pdo->lastInsertId('admin_audit_log_id_seq');
    
    echo "<p class='success'>Successfully inserted into admin_audit_log with ID: $logId</p>";
    
    $pdo->rollBack(); // Don't actually insert the test data
    echo "<p class='info'>Test inserts were rolled back to avoid adding test data to the database.</p>";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p class='error'>Error testing inserts: " . $e->getMessage() . "</p>";
}

echo "<h2>Summary</h2>";
echo "<p>The sequence fix script has completed. If all operations were successful, the sequence issues should be resolved.</p>";
echo "<p>You can now run the verify_fixes.php script again to confirm that all issues are fixed.</p>";

// Footer
echo "</body></html>";
?>
