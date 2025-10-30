<?php
// Dashboard Summary Consistency & Fallback Tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerRobust.php';
require_once __DIR__ . '/utils.php';

$passed = 0; $failed = 0; $skipped = 0;
t_section('Dashboard Summary Consistency');

$complete = new BloodInventoryManagerComplete($pdo);
$robust = new BloodInventoryManagerRobust($pdo, true);

// Baseline DB counts
$invCount = 0; $servedCount = 0;
try {
    $invCount = (int)$pdo->query("SELECT COUNT(*) FROM blood_inventory")->fetchColumn();
} catch (Exception $e) {}
try {
    $servedCount = (int)$pdo->query("SELECT COUNT(*) FROM donors_new WHERE status IN ('served','completed')")->fetchColumn();
} catch (Exception $e) {
    try {
        $servedCount = (int)$pdo->query("SELECT COUNT(*) FROM donors WHERE status = 'served'")->fetchColumn();
    } catch (Exception $e2) {}
}

$sumC = $complete->getDashboardSummary();
$sumR = $robust->getDashboardSummary();

// Basic invariants
$ok = true;
$ok &= t_assert(isset($sumC['total_units']), 'Complete: total_units present');
$ok &= t_assert(isset($sumC['available_units']), 'Complete: available_units present');
$ok &= t_assert($sumC['total_units'] >= 0 && $sumC['available_units'] >= 0, 'Complete: non-negative counts');

$ok &= t_assert(isset($sumR['total_units']), 'Robust: total_units present');
$ok &= t_assert(isset($sumR['available_units']), 'Robust: available_units present');
$ok &= t_assert($sumR['total_units'] >= 0 && $sumR['available_units'] >= 0, 'Robust: non-negative counts');
if ($ok) { $passed += 6; } else { $failed += 1; }

// Fallback behavior when inventory is empty but donors served/completed exist
if ($invCount === 0 && $servedCount > 0) {
    $ok2 = true;
    $ok2 &= t_assert((int)$sumR['total_units'] === $servedCount, 'Robust: total equals served/completed donors when inventory empty');
    $ok2 &= t_assert((int)$sumR['available_units'] === $servedCount, 'Robust: available equals served/completed donors when inventory empty');

    $ok2 &= t_assert((int)$sumC['total_units'] === $servedCount, 'Complete: total equals served/completed donors when inventory empty');
    $ok2 &= t_assert((int)$sumC['available_units'] === $servedCount, 'Complete: available equals served/completed donors when inventory empty');
    if ($ok2) { $passed += 4; } else { $failed += 1; }
} else {
    // Otherwise, just ensure available does not exceed total
    $ok3 = true;
    $ok3 &= t_assert($sumC['available_units'] <= $sumC['total_units'], 'Complete: available <= total');
    $ok3 &= t_assert($sumR['available_units'] <= $sumR['total_units'], 'Robust: available <= total');
    if ($ok3) { $passed += 2; } else { $failed += 1; }
}

t_result($passed, $failed, $skipped);

?>