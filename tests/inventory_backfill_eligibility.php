<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/utils.php';

t_section('Inventory Backfill Eligibility');

$complete = new BloodInventoryManagerComplete($pdo);

// Resolve donor table robustly: default to legacy 'donors', prefer 'donors_new' when present and populated
$donorTable = 'donors';
try {
    $cntNew = (int)$pdo->query("SELECT COUNT(*) FROM donors_new")->fetchColumn();
    if ($cntNew > 0) { $donorTable = 'donors_new'; }
} catch (Throwable $e) {
    // donors_new not available; keep fallback 'donors'
}
// Use different WHERE clauses for single-table vs join to avoid ambiguous column errors
$servedCondWhere = ($donorTable === 'donors_new') ? "status IN ('served','completed')" : "status = 'served'";
$servedCondJoin  = ($donorTable === 'donors_new') ? "d.status IN ('served','completed')" : "d.status = 'served'";

$eligibleDonors = (int)$pdo->query("SELECT COUNT(*) FROM {$donorTable} WHERE {$servedCondWhere}")->fetchColumn();
$donorsWithAvailable = (int)$pdo->query("SELECT COUNT(DISTINCT d.id) FROM {$donorTable} d JOIN blood_inventory bi ON bi.donor_id = d.id AND bi.status = 'available' WHERE {$servedCondJoin}")->fetchColumn();
$missingAvailable = max(0, $eligibleDonors - $donorsWithAvailable);

t_pass("Eligible donors: {$eligibleDonors}");
t_pass("Donors with AVAILABLE unit: {$donorsWithAvailable}");
t_pass("Donors missing AVAILABLE unit: {$missingAvailable}");

if ($missingAvailable > 0) {
    t_fail("Found {$missingAvailable} eligible donors without AVAILABLE units — run backfill.");
} else {
    t_pass('All eligible donors have an AVAILABLE unit.');
}

$summary = $complete->getDashboardSummary();
t_pass("Summary: " . json_encode($summary));

if ((int)$summary['total_units'] >= $donorsWithAvailable) {
    t_pass('Dashboard total is consistent with donors who have AVAILABLE units.');
} else {
    t_fail('Dashboard total is less than donors with AVAILABLE units.');
}

?>