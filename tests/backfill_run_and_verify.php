<?php
// Test: Run backfill (non-dry) and verify counts improve
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/utils.php';

t_section('Backfill Run and Verify');

$complete = new BloodInventoryManagerComplete($pdo);

// Baseline
$donorTable = 'donors_new';
try { if ((int)$pdo->query("SELECT COUNT(*) FROM donors_new")->fetchColumn() > 0) { $donorTable = 'donors_new'; } } catch (Throwable $e) {}
// Separate conditions to avoid ambiguous `status` when joining
$servedCondWhere = ($donorTable === 'donors_new') ? "status IN ('served','completed')" : "status = 'served'";
$servedCondJoin  = ($donorTable === 'donors_new') ? "d.status IN ('served','completed')" : "d.status = 'served'";
$availBefore = (int)$pdo->query("SELECT COUNT(DISTINCT d.id) FROM {$donorTable} d JOIN blood_inventory bi ON bi.donor_id = d.id AND bi.status = 'available' WHERE {$servedCondJoin}")->fetchColumn();
$eligible = (int)$pdo->query("SELECT COUNT(*) FROM {$donorTable} WHERE {$servedCondWhere}")->fetchColumn();
$missingBefore = max(0, $eligible - $availBefore);
t_pass("Before: eligible={$eligible}, donorsWithAvailable={$availBefore}, missing={$missingBefore}");

// Run backfill
$res = $complete->backfillMissingUnits(500);
t_pass("Backfill result: " . json_encode($res));

// After
$availAfter = (int)$pdo->query("SELECT COUNT(DISTINCT d.id) FROM {$donorTable} d JOIN blood_inventory bi ON bi.donor_id = d.id AND bi.status = 'available' WHERE {$servedCondJoin}")->fetchColumn();
$missingAfter = max(0, $eligible - $availAfter);
t_pass("After: donorsWithAvailable={$availAfter}, missing={$missingAfter}");

if ($availAfter >= $availBefore) {
    t_pass('Backfill did not regress availability.');
} else {
    t_fail('Backfill reduced donors with AVAILABLE units unexpectedly.');
}

if ($missingAfter < $missingBefore) {
    t_pass('Backfill reduced the number of missing AVAILABLE units.');
} else if ($missingBefore === 0 && $missingAfter === 0) {
    t_skip('No missing donors before or after — environment already consistent.');
} else {
    t_fail('Backfill did not improve missing AVAILABLE units.');
}

?>