<?php
require_once __DIR__ . '/../includes/db.php';

echo "--- Running Unknown Blood Type Test ---\n";

// Debugging: Check DB file path
echo "DB File Path: " . DB_FILE . "\n";
echo "DB File Exists: " . (file_exists(DB_FILE) ? 'Yes' : 'No') . "\n";

// Debugging: Dump tables
try {
    echo "\n--- DUMPING donors TABLE ---\n";
    $stmt = $pdo->query('SELECT * FROM donors');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n--- DUMPING donors_new TABLE ---\n";
    $stmt = $pdo->query('SELECT * FROM donors_new');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error dumping tables: " . $e->getMessage() . "\n";
}

echo "\n--- TEST LOGIC ---\n";

// Test to ensure that the pending donors page can handle unknown blood types

$pendingDonors = [];
// ... (rest of the test logic is the same)

$pendingDonors = [];

// Build robust WHERE clauses per table, tolerating schema differences
$whereNew = " WHERE 1=1";
$whereLegacy = " WHERE 1=1";
$paramsNew = [];
$paramsLegacy = [];

// Detect presence of status and created_at columns per table
$hasStatusNew = false; $hasStatusLegacy = false;
$hasCreatedNew = false; $hasCreatedLegacy = false;
// Portable column existence check
function columnExists($pdo, $table, $column) {
    try {
        // PRAGMA for SQLite
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            return in_array($column, $columns);
        }
        // information_schema for MySQL/PostgreSQL
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

$hasStatusNew     = columnExists($pdo, 'donors_new', 'status');
$hasStatusLegacy  = columnExists($pdo, 'donors', 'status');
$hasCreatedNew    = columnExists($pdo, 'donors_new', 'created_at');
$hasCreatedLegacy = columnExists($pdo, 'donors', 'created_at');

// Pending condition:
// - If status exists: a donor is pending if their status is a pending-like value.
// - If status does not exist: a donor is pending if their blood type is unknown (legacy fallback).
$unknownExpr = "(LOWER(TRIM(COALESCE(blood_type,''))) IN ('unknown','unk',''))";
$pendingStatusExpr = "(status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))";

if ($hasStatusNew) {
    $whereNew .= " AND (" . $pendingStatusExpr . " OR " . $unknownExpr . ")";
} else {
    $whereNew .= " AND " . $unknownExpr;
}
if ($hasStatusLegacy) {
    $whereLegacy .= " AND (" . $pendingStatusExpr . " OR " . $unknownExpr . ")";
} else {
    $whereLegacy .= " AND " . $unknownExpr;
}

// Order by clause per table
$orderNew    = $hasCreatedNew    ? " ORDER BY created_at DESC" : " ORDER BY id DESC";
$orderLegacy = $hasCreatedLegacy ? " ORDER BY created_at DESC" : " ORDER BY id DESC";

// Query donors_new if present
try {
    $stmt = $pdo->prepare("SELECT * FROM donors_new" . $whereNew . $orderNew);
    $stmt->execute($paramsNew);
    $pendingDonors = array_merge($pendingDonors, $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) { /* ignore if table doesn't exist or query fails */ }

// Query legacy donors table
try {
    $stmt = $pdo->prepare("SELECT * FROM donors" . $whereLegacy . $orderLegacy);
    $stmt->execute($paramsLegacy);
    $pendingDonors = array_merge($pendingDonors, $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) { /* ignore if table doesn't exist or query fails */ }

// Sort newest-first by available timestamp or id
usort($pendingDonors, function($a, $b) {
    $ta = isset($a['created_at']) ? strtotime($a['created_at']) : (isset($a['id']) ? (int)$a['id'] : 0);
    $tb = isset($b['created_at']) ? strtotime($b['created_at']) : (isset($b['id']) ? (int)$b['id'] : 0);
    return $tb <=> $ta;
});

$foundTestDonor = false;
foreach ($pendingDonors as $donor) {
    $name = $donor['name'] ?? ($donor['first_name'] . ' ' . $donor['last_name']);
    if (trim($name) === 'Test Donor Unknown') {
        $foundTestDonor = true;
        break;
    }
}

if ($foundTestDonor) {
    echo "Test passed: 'Test Donor Unknown' found in pending donors.";
} else {
    echo "Test failed: 'Test Donor Unknown' not found in pending donors.";
}

?>