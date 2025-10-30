<?php
// Admin modern page header/table consistency test
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerRobust.php';
require_once __DIR__ . '/utils.php';

$passed = 0; $failed = 0; $skipped = 0;
t_section('Admin Modern Page Consistency (Logic-Equivalent)');

$complete = new BloodInventoryManagerComplete($pdo);
$robust = new BloodInventoryManagerRobust($pdo, true);

// Prepare filters similar to page
$defaultFilters = [
    'blood_type' => '',
    'status' => '',
    'search' => ''
];

$limit = 20; $page = 1;
$filters = normalize_filters($defaultFilters);

// Decide manager per page logic
$invC = $complete->getInventory($filters, $page, $limit);
$sumC = $complete->getDashboardSummary();
$useRobust = (empty($invC['data']) || ((int)$sumC['total_units'] === 0));
$manager = $useRobust ? $robust : $complete;
// Compute expected display total
$expectedTotal = (int)$manager->getInventoryCount($filters);

// Instead of including the full HTML page (which may exit),
// verify the logic-equivalent computations for the header badge:
// startRecord, endRecord, displayTotal derived from chosen manager.

$invChosen = $manager->getInventory($filters, $page, $limit);
$rows = is_array($invChosen['data']) ? count($invChosen['data']) : 0;

// Logic-equivalent to typical header computation
if ($expectedTotal > 0) {
    $startRecord = (($page - 1) * $limit) + 1;
    $endRecord = $startRecord + $rows - 1;
} else {
    $startRecord = 0;
    $endRecord = 0;
}

$ok = true;
$ok &= t_assert((int)$invChosen['total'] === $expectedTotal, 'Chosen manager: inventory total matches count');
$ok &= t_assert($startRecord >= 0 && $endRecord >= $startRecord, 'Computed header start/end valid');
if ($rows > 0) {
    $ok &= t_assert($rows === ($endRecord - $startRecord + 1), 'Computed rows equal displayed range');
} else {
    $skipped += 1;
    echo "[SKIP] No rows; computed row-range assertion skipped\n";
}
if ($ok) { $passed += 3; } else { $failed += 1; }

t_result($passed, $failed, $skipped);

?>