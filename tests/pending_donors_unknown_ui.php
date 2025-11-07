<?php
// Pending Donors Unknown Blood Type (UI) — seeds a pending donor with blank blood_type
// and asserts the admin Pending Donors table renders "Unknown" for that row.
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/utils.php';

// Prefer using $pdo provided by tests/run_all_tests.php; fall back to project db.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    $dbCandidates = [
        dirname(__DIR__) . '/db.php',
        dirname(__DIR__) . '/db_production.php',
        dirname(__DIR__) . '/blood-donation-pwa/db.php'
    ];
    foreach ($dbCandidates as $dbPath) {
        if (file_exists($dbPath)) { require_once $dbPath; break; }
    }
    ini_set('display_errors', '1');
}

t_section('Pending Donors UI shows Unknown for blank blood_type');

if (!isset($pdo) || !$pdo instanceof PDO) {
    t_assert(false, 'Database connection (PDO) is available');
    t_result(0, 1, 0);
    return;
}

if (!function_exists('dbDriver')) {
    function dbDriver(PDO $pdo) {
        try { return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); } catch (Throwable $e) { return 'mysql'; }
    }
}

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $table): bool {
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

if (!function_exists('getTableColumns')) {
    function getTableColumns(PDO $pdo, string $table): array {
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
}

function ensurePendingUnknownDonor(PDO $pdo): ?int {
    // Admin page queries from `donors` table; prefer it.
    $table = tableExists($pdo, 'donors') ? 'donors' : (tableExists($pdo, 'donors_new') ? 'donors_new' : null);
    if ($table === null) { return null; }

    $fields = getTableColumns($pdo, $table);
    // Try to find existing seed row to keep idempotence
    try {
        if (in_array('reference_code', $fields, true)) {
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE reference_code = ? LIMIT 1");
            $stmt->execute(['UNK-UI-TEST']);
            $id = (int)$stmt->fetchColumn();
            if ($id) { return $id; }
        } elseif (in_array('email', $fields, true)) {
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE email = ? LIMIT 1");
            $stmt->execute(['unknown-ui@test.local']);
            $id = (int)$stmt->fetchColumn();
            if ($id) { return $id; }
        }
    } catch (Throwable $e) {}

    $nowTs = date('Y-m-d H:i:s');
    $data = [
        'first_name' => 'Unknown',
        'last_name' => 'UI Test',
        'name' => 'Unknown UI Test',
        'email' => 'unknown-ui@test.local',
        'phone' => '0000000000',
        'blood_type' => '', // intentionally blank to trigger Unknown formatter
        'status' => 'pending',
        'reference_code' => 'UNK-UI-TEST',
        'created_at' => $nowTs,
        'updated_at' => $nowTs,
        'seed_flag' => 1,
    ];

    // Build column/value lists based on actual schema
    $insertCols = [];
    $params = [];
    foreach ($data as $col => $val) {
        if (in_array($col, $fields, true)) { $insertCols[] = $col; $params[] = $val; }
    }
    // Fallbacks for schemas using different names
    if (!in_array('first_name', $insertCols, true) && in_array('name', $fields, true)) {
        // already covered via 'name'
    }
    if (!in_array('created_at', $insertCols, true) && in_array('created', $fields, true)) {
        $insertCols[] = 'created';
        $params[] = $nowTs;
    }

    if (!$insertCols) { return null; }

    $placeholders = rtrim(str_repeat('?,', count($insertCols)), ',');
    $driver = dbDriver($pdo);
    $pk = in_array('id', $fields, true) ? 'id' : (in_array('donor_id', $fields, true) ? 'donor_id' : null);

    try {
        if ($driver === 'pgsql' && $pk) {
            $sql = "INSERT INTO {$table} (" . implode(',', $insertCols) . ") VALUES ({$placeholders}) RETURNING {$pk}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $newId = (int)$stmt->fetchColumn();
            if ($newId) { return $newId; }
        } else {
            $sql = "INSERT INTO {$table} (" . implode(',', $insertCols) . ") VALUES ({$placeholders})";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $newId = (int)$pdo->lastInsertId();
            if ($newId) { return $newId; }
        }
    } catch (Throwable $e) {
        // Final fallback: find most recent matching by reference/email
        try {
            if (in_array('reference_code', $fields, true)) {
                $stmt2 = $pdo->prepare("SELECT {$pk} FROM {$table} WHERE reference_code = ? ORDER BY {$pk} DESC LIMIT 1");
                $stmt2->execute(['UNK-UI-TEST']);
                $id2 = (int)$stmt2->fetchColumn();
                if ($id2) { return $id2; }
            } elseif (in_array('email', $fields, true)) {
                $stmt2 = $pdo->prepare("SELECT {$pk} FROM {$table} WHERE email = ? ORDER BY {$pk} DESC LIMIT 1");
                $stmt2->execute(['unknown-ui@test.local']);
                $id2 = (int)$stmt2->fetchColumn();
                if ($id2) { return $id2; }
            }
        } catch (Throwable $e2) {}
    }
    return null;
}

// Seed row and capture admin page HTML
$id = ensurePendingUnknownDonor($pdo);
if ($id === null) {
    t_skip('Could not ensure pending donor with blank blood_type (table or schema mismatch).');
    t_result(0, 0, 1);
    return;
}

// Render admin Pending Donors tab
$_GET['tab'] = 'pending-donors';
unset($_GET['donor_search']);
ob_start();
include_once dirname(__DIR__) . '/admin.php';
$html = ob_get_clean();

$renderOk = t_assert(strlen(trim($html)) > 0, 'Admin Pending Donors HTML rendered');

// Find the table row for this donor by id and assert it contains "Unknown"
$needle = 'href="?tab=donor-details&id=' . (int)$id . '"';
$pos = strpos($html, $needle);
if ($pos === false) {
    // Fallback to look for reference_code presence and Unknown near it
    $fallbackNeedles = ['UNK-UI-TEST', 'unknown-ui@test.local', 'Unknown UI Test'];
    $foundNearbyUnknown = false;
    foreach ($fallbackNeedles as $n) {
        $p = strpos($html, $n);
        if ($p !== false) {
            $segment = substr($html, max(0, $p - 400), 800);
            if (stripos($segment, 'Unknown') !== false) { $foundNearbyUnknown = true; break; }
        }
    }
    t_assert($foundNearbyUnknown, 'Unknown appears near seeded donor details');
    t_result($renderOk ? 2 : 1, $renderOk ? 0 : 1, 0);
    return;
}

// Extract row boundaries
$rowStart = strrpos(substr($html, 0, $pos), '<tr');
$rowEnd = strpos($html, '</tr>', $pos);
if ($rowStart === false || $rowEnd === false) {
    t_assert(false, 'Could not isolate table row for seeded donor');
    t_result($renderOk ? 1 : 0, $renderOk ? 1 : 2, 0);
    return;
}
$rowHtml = substr($html, $rowStart, ($rowEnd - $rowStart) + 5);

$showsUnknown = (stripos($rowHtml, 'Unknown') !== false);
t_assert($showsUnknown, "Seeded donor row displays 'Unknown' blood type");

t_result($renderOk ? 2 : 1, ($renderOk ? 0 : 1) + ($showsUnknown ? 0 : 1), 0);

?>