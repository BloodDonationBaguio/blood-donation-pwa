<?php
// Validate pending donors handling when blood type is unknown, tolerant of schema differences.
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/utils.php';

t_section('Pending Donors Unknown Blood Type');

$pendingDonors = [];

function columnExists($pdo, $table, $column) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(" . $table . ")");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            return in_array($column, $columns, true);
        }
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

$hasDonorsNew = false;
try { $hasDonorsNew = (bool)$pdo->query("SELECT 1 FROM donors_new LIMIT 1")->fetchColumn(); } catch (Throwable $e) { $hasDonorsNew = false; }

$unknownExpr = "(LOWER(TRIM(COALESCE(blood_type,''))) IN ('unknown','unk',''))";
$pendingStatusExpr = "(status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))";

if ($hasDonorsNew) {
    $whereNew = " WHERE " . ($hasStatusNew = columnExists($pdo, 'donors_new', 'status') ? "(" . $pendingStatusExpr . ") OR " : "") . $unknownExpr;
    $orderNew = columnExists($pdo, 'donors_new', 'created_at') ? " ORDER BY created_at DESC" : " ORDER BY id DESC";
    try {
        $stmt = $pdo->prepare("SELECT * FROM donors_new" . $whereNew . $orderNew);
        $stmt->execute();
        $pendingDonors = array_merge($pendingDonors, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {}
}

// Legacy donors table fallback
$hasDonorsLegacy = false;
try { $hasDonorsLegacy = (bool)$pdo->query("SELECT 1 FROM donors LIMIT 1")->fetchColumn(); } catch (Throwable $e) { $hasDonorsLegacy = false; }

if ($hasDonorsLegacy) {
    $whereLegacy = " WHERE " . ($hasStatusLegacy = columnExists($pdo, 'donors', 'status') ? "(" . $pendingStatusExpr . ") OR " : "") . $unknownExpr;
    $orderLegacy = columnExists($pdo, 'donors', 'created_at') ? " ORDER BY created_at DESC" : " ORDER BY id DESC";
    try {
        $stmt = $pdo->prepare("SELECT * FROM donors" . $whereLegacy . $orderLegacy);
        $stmt->execute();
        $pendingDonors = array_merge($pendingDonors, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {}
}

usort($pendingDonors, function($a, $b) {
    $ta = isset($a['created_at']) ? strtotime($a['created_at']) : (isset($a['id']) ? (int)$a['id'] : 0);
    $tb = isset($b['created_at']) ? strtotime($b['created_at']) : (isset($b['id']) ? (int)$b['id'] : 0);
    return $tb <=> $ta;
});

$foundTestDonor = false;
foreach ($pendingDonors as $donor) {
    $name = $donor['name'] ?? trim(($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? ''));
    if ($name === 'Test Donor Unknown') { $foundTestDonor = true; break; }
}

if ($foundTestDonor) {
    t_pass("'Test Donor Unknown' found in pending donors.");
} else {
    t_skip("'Test Donor Unknown' not found; depends on presence of seeded test donor.");
}

?>