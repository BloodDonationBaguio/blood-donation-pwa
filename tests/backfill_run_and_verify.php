<?php
// Test: Run backfill (non-dry) and verify counts improve
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Initialize DB and test session for standalone execution
if (!defined('TEST_MODE')) { define('TEST_MODE', true); }
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = $_SESSION['admin_username'] ?? 'admin';
$_SESSION['admin_id'] = $_SESSION['admin_id'] ?? 1;
require_once dirname(__DIR__) . '/db.php';

require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/utils.php';

t_section('Backfill Run and Verify');

$complete = new BloodInventoryManagerComplete($pdo);

// Baseline
// Resolve donor table robustly: default to legacy 'donors', prefer 'donors_new' when present and populated
$donorTable = 'donors';
try {
    $cntNew = (int)$pdo->query("SELECT COUNT(*) FROM donors_new")->fetchColumn();
    if ($cntNew > 0) { $donorTable = 'donors_new'; }
} catch (Throwable $e) {
    // donors_new not available; keep fallback 'donors'
}
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

// Print accumulated test output when run standalone
echo $t_output;

?>