<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/utils.php';

t_section('Inventory Backfill Eligibility (Variant)');

$complete = new BloodInventoryManagerComplete($pdo);

$donorTable = 'donors';
try {
    $countNew = (int)$pdo->query("SELECT COUNT(*) FROM donors_new")->fetchColumn();
    if ($countNew >= 0) { $donorTable = 'donors_new'; }
} catch (Throwable $e) {}

$servedCond = ($donorTable === 'donors_new') ? "status IN ('served','completed')" : "status = 'served'";

$eligibleDonors = (int)$pdo->query("SELECT COUNT(*) FROM {$donorTable} WHERE {$servedCond}")->fetchColumn();
$donorsWithAvailable = (int)$pdo->query("SELECT COUNT(DISTINCT d.id) FROM {$donorTable} d JOIN blood_inventory bi ON bi.donor_id = d.id AND bi.status = 'available' WHERE {$servedCond}")->fetchColumn();
$missingAvailable = max(0, $eligibleDonors - $donorsWithAvailable);

echo "Eligible donors: {$eligibleDonors}\n";
echo "Donors with AVAILABLE unit: {$donorsWithAvailable}\n";
echo "Donors missing AVAILABLE unit: {$missingAvailable}\n";

if ($missingAvailable > 0) {
    t_fail("Found {$missingAvailable} eligible donors without AVAILABLE units — run backfill.");
} else {
    t_pass('All eligible donors have an AVAILABLE unit.');
}

$summary = $complete->getDashboardSummary();
echo "Summary: " . json_encode($summary) . "\n";

if ((int)$summary['total_units'] >= $donorsWithAvailable) {
    t_pass('Dashboard total is consistent with donors who have AVAILABLE units.');
} else {
    t_fail('Dashboard total is less than donors with AVAILABLE units.');
}

?>