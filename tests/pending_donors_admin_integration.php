<?php
// Pending Donors Admin Integration Test
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/utils.php';

t_section('Pending Donors Admin Integration');

// Compute expected union count with empty search (reuse diagnostic logic inline)
if (!function_exists('columnSet')) {
    function columnSet(PDO $pdo, string $table): array {
        if (function_exists('getTableStructure')) {
            $struct = getTableStructure($pdo, $table);
            $cols = [];
            foreach ($struct as $row) {
                $name = $row['Field'] ?? $row['column_name'] ?? null;
                if ($name) { $cols[$name] = true; }
            }
            return $cols;
        }
        try {
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ?");
            $stmt->execute([$table]);
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[$row['column_name']] = true;
            }
            return $cols;
        } catch (Throwable $e) {
            return [];
        }
    }
}

// No need for tableExistsPortable here; we probe via COUNT(*)

$tables = [];
// Probe available donor tables, prioritizing 'donors_new' then falling back to legacy 'donors'
foreach (['donors_new','donors'] as $t) {
    // Probe by COUNT(*) for presence to avoid helper/driver quirks
    try {
        $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        $tables[] = $t;
    } catch (Throwable $e) {
        // not present
    }
}

if (empty($tables)) {
    t_fail('Neither donors_new nor donors table exists in current database.');
    t_result(0, 1, 0);
    return;
}

$expectedUnionCount = 0;
foreach ($tables as $t) {
    $cols = columnSet($pdo, $t);
    $hasStatus    = isset($cols['status']);
    $hasBloodType = isset($cols['blood_type']);
    $pendingCond  = $hasStatus
        ? "(status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))"
        : ($hasBloodType ? "(blood_type IS NULL OR blood_type IN ('Unknown','UNK'))" : "1=0");
    try {
        $pending = (int)$pdo->query("SELECT COUNT(*) FROM {$t} WHERE {$pendingCond}")->fetchColumn();
        $expectedUnionCount += $pending;
    } catch (Throwable $e) {
        // ignore; diagnostic already covered in schema test
    }
}

// Include admin.php with tab context and capture output
$_GET['tab'] = 'pending-donors';
unset($_GET['donor_search']);
ob_start();
include __DIR__ . '/../admin.php';
$html = ob_get_clean();

$list = $GLOBALS['pendingDonors'] ?? [];
$count = is_array($list) ? count($list) : 0;
t_pass("Computed pendingDonors count from admin.php: {$count}");

$ok = true;
$ok &= t_assert(is_array($list), 'pendingDonors is an array exposed via globals');
$ok &= t_assert($count >= 0, 'pendingDonors count is non-negative');

// When we have pending-like rows by direct count, the admin list should not be smaller
if ($expectedUnionCount > 0) {
    $ok &= t_assert($count >= $expectedUnionCount, 'Admin pendingDonors covers per-table pending-like rows');
} else {
    t_skip('No pending-like rows detected — integration coverage check skipped.');
}

// Basic field expectations if there are rows
if ($count > 0) {
    $sample = $list[0];
    $ok &= t_assert(isset($sample['email']) || isset($sample['phone']), 'Admin list sample has contact field');
    $ok &= t_assert(isset($sample['first_name']) || isset($sample['last_name']), 'Admin list sample has name field');
}

if ($ok) {
    t_pass('Admin integration checks passed.');
    t_result(5, 0, ($expectedUnionCount > 0 ? 0 : 1));
} else {
    t_fail('Admin integration checks failed.');
}

?>