<?php
// Admin Blood Types Coverage Test
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Ensure we always render something even if a fatal occurs
$__direct_access = (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__);
register_shutdown_function(function() use ($__direct_access) {
    if ($__direct_access) {
        $safe = htmlspecialchars(($GLOBALS['t_output'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($safe !== '') { echo "<pre>" . $safe . "</pre>"; }
        $err = error_get_last();
        if ($err && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR])) {
            $msg = sprintf("Fatal: %s in %s on line %d", $err['message'] ?? '', $err['file'] ?? '', (int)($err['line'] ?? 0));
            echo "<pre>" . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
        }
    }
});

require_once __DIR__ . '/utils.php';

// Fallback: if $pdo is not set (direct access), include a DB config
if (!isset($pdo) || !$pdo instanceof PDO) {
    $dbCandidates = [
        dirname(__DIR__) . '/db.php',
        dirname(__DIR__) . '/db_production.php',
        dirname(__DIR__) . '/blood-donation-pwa/db.php'
    ];
    foreach ($dbCandidates as $dbPath) {
        if (file_exists($dbPath)) { require_once $dbPath; break; }
    }
    // Re-enable display errors in case included db config disables it
    ini_set('display_errors', '1');
}

t_section('Admin Blood Types List Coverage');

$passed = 0; $failed = 0; $skipped = 0;

// Expect global $pdo provided by tests/run_all_tests.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    t_assert(false, 'Database connection (PDO) is available');
    $failed++;
    t_result($passed, $failed, $skipped);
    return;
}

// Helpers
function dbDriver(PDO $pdo) {
    try { return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); } catch (Throwable $e) { return 'mysql'; }
}

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, $table) {
        $driver = dbDriver($pdo);
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'pgsql') {
                $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
                $stmt->execute([':t' => $table]);
                return (bool)$stmt->fetchColumn();
            } else { // sqlite
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t");
                $stmt->execute([':t' => $table]);
                return $stmt->fetch() !== false;
            }
        } catch (Throwable $e) { return false; }
    }
}

function getTableColumns(PDO $pdo, $table) {
    $driver = dbDriver($pdo);
    $cols = [];
    try {
        if ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $cols[] = $row['Field'] ?? $row['COLUMN_NAME'] ?? null; }
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=:t");
            $stmt->execute([':t' => $table]);
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } else { // sqlite
            $stmt = $pdo->prepare("PRAGMA table_info({$table})");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $cols[] = $row['name'] ?? null; }
        }
    } catch (Throwable $e) {}
    return array_values(array_filter($cols));
}

function ensureTestDonor(PDO $pdo) {
    // Try existing donor
    try {
        $stmt = $pdo->query("SELECT id FROM donors_new ORDER BY id LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) { return (int)$id; }
    } catch (Throwable $e) {}

    if (!tableExists($pdo, 'donors_new')) { return null; }

    // Create a minimal test donor
    $now = date('Y-m-d H:i:s');
    $fields = getTableColumns($pdo, 'donors_new');
    $columns = [];
    $values = [];
    $params = [];

    $defaults = [
        'first_name' => 'Admin',
        'last_name' => 'BloodTypesTester',
        'email' => 'admin-blood-types@test.local',
        'phone' => '0000000000',
        'blood_type' => 'Unknown',
        'reference_code' => 'ADM-BLT-TEST',
        'status' => 'approved',
        'seed_flag' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    foreach ($defaults as $col => $val) {
        if (in_array($col, $fields, true)) { $columns[] = $col; $params[] = $val; }
    }

    if (!$columns) { return null; }

    $sql = "INSERT INTO donors_new (" . implode(',', $columns) . ") VALUES (" . rtrim(str_repeat('?,', count($columns)), ',') . ")";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        return null;
    }
}

function ensureUnitForType(PDO $pdo, $donorId, $bloodType, $columnsCache) {
    // Idempotent: if a unit for this test and type exists, do nothing
    $hasNotes = in_array('notes', $columnsCache, true);
    try {
        if ($hasNotes) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_inventory WHERE blood_type = ? AND notes LIKE ?");
            $stmt->execute([$bloodType, '%Admin Blood Types Test%']);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_inventory WHERE blood_type = ? AND unit_id LIKE ?");
            $stmt->execute([$bloodType, 'ADM-BLT-%']);
        }
        if ((int)$stmt->fetchColumn() > 0) { return true; }
    } catch (Throwable $e) {}

    $fields = $columnsCache;
    $nowDate = date('Y-m-d');
    $nowTs = date('Y-m-d H:i:s');

    $data = [
        'unit_id' => 'ADM-BLT-' . preg_replace('/[^A-Z0-9\+\-]/', '', strtoupper($bloodType)) . '-' . uniqid(),
        'donor_id' => (int)$donorId,
        'blood_type' => $bloodType,
        'collection_date' => $nowDate,
        'expiry_date' => date('Y-m-d', strtotime($nowDate . ' +42 days')),
        'status' => 'available',
        'notes' => 'Admin Blood Types Test Insert',
        'seed_flag' => 1,
        'created_at' => $nowTs,
        'updated_at' => $nowTs,
        // Optional fields, will be filtered by presence
        'collection_site' => 'Test Center',
        'storage_location' => 'Test Storage',
        'volume_ml' => 450,
        'screening_status' => 'passed',
        'location' => 'Test Location',
        'collection_center' => 'Test Center',
        'collection_staff' => 'Tester',
        'test_results' => 'negative',
    ];

    $insertCols = [];
    $params = [];
    foreach ($data as $col => $val) {
        if (in_array($col, $fields, true)) { $insertCols[] = $col; $params[] = $val; }
    }

    if (!in_array('unit_id', $insertCols, true) || !in_array('donor_id', $insertCols, true) || !in_array('blood_type', $insertCols, true)) {
        return false; // cannot insert into an unexpected schema
    }

    $sql = "INSERT INTO blood_inventory (" . implode(',', $insertCols) . ") VALUES (" . rtrim(str_repeat('?,', count($insertCols)), ',') . ")";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// 1) Verify UI filter includes 'Unknown'
try {
    $adminTabsPath = dirname(__DIR__) . '/includes/admin-tabs.php';
    $uiHasUnknown = false;
    if (file_exists($adminTabsPath)) {
        $contents = @file_get_contents($adminTabsPath);
        $uiHasUnknown = $contents !== false && (strpos($contents, 'option value="Unknown"') !== false || strpos($contents, '>Unknown</option>') !== false);
    }
    if (t_assert($uiHasUnknown, "Admin filter options include 'Unknown' blood type")) { $passed++; } else { $failed++; }
} catch (Throwable $e) {
    t_assert(false, "Failed to read admin filter definitions: " . $e->getMessage());
    $failed++;
}

// 2) Try to ensure inventory has at least one unit for each blood type (including Unknown)
$allTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
$inventoryReady = tableExists($pdo, 'blood_inventory');

if (!$inventoryReady) {
    t_pass('blood_inventory table not present; skipping data seeding and dashboard assertions');
    $skipped++;
    t_result($passed, $failed, $skipped);
    return;
}

$donorId = ensureTestDonor($pdo);
if (!$donorId) {
    t_pass('No donors_new table or insertion failed; skipping data seeding and dashboard assertions');
    $skipped++;
    t_result($passed, $failed, $skipped);
    return;
}

$columnsCache = getTableColumns($pdo, 'blood_inventory');
$seedOk = true;
foreach ($allTypes as $bt) {
    $ok = ensureUnitForType($pdo, $donorId, $bt, $columnsCache);
    if (!$ok) { $seedOk = false; }
}

if (t_assert($seedOk, 'Seeded or found minimal units for all blood types')) { $passed++; } else { $failed++; }

// 3) Assert dashboard summary includes all 9 types
try {
    // Prefer Enhanced/Simple manager; require if available
    $mgrPathEnhanced = dirname(__DIR__) . '/includes/BloodInventoryManagerEnhanced.php';
    $mgrPathSimple = dirname(__DIR__) . '/includes/BloodInventoryManagerSimple.php';
    if (file_exists($mgrPathEnhanced)) { require_once $mgrPathEnhanced; $manager = new BloodInventoryManagerEnhanced($pdo, 'admin', 1); }
    elseif (file_exists($mgrPathSimple)) { require_once $mgrPathSimple; $manager = new BloodInventoryManagerSimple($pdo); }
    else { $manager = null; }

    if ($manager === null) {
        t_pass('No inventory manager class found; skipping dashboard summary assertion');
        $skipped++;
        t_result($passed, $failed, $skipped);
        return;
    }

    $summary = $manager->getDashboardSummary();
    $byType = isset($summary['by_blood_type']) && is_array($summary['by_blood_type']) ? $summary['by_blood_type'] : [];
    $presentTypes = array_keys($byType);

    $allPresent = true;
    foreach ($allTypes as $bt) {
        $ok = in_array($bt, $presentTypes, true);
        if (t_assert($ok, "Dashboard includes blood type '$bt'")) { $passed++; } else { $failed++; $allPresent = false; }
    }
    // Aggregate assertion
    if (t_assert($allPresent, 'Dashboard summary covers all 9 blood types including Unknown')) { $passed++; } else { $failed++; }
} catch (Throwable $e) {
    t_assert(false, 'Dashboard summary check failed: ' . $e->getMessage());
    $failed++;
}

t_result($passed, $failed, $skipped);

?>
<?php
// If accessed directly (not via tests/run_all_tests.php), render the buffered output
if ((isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) || php_sapi_name() === 'cli') {
    $safe = htmlspecialchars($t_output ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<pre>" . $safe . "</pre>";
}
?>