<?php
// Inventory Manager Consistency Tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerRobust.php';
require_once __DIR__ . '/utils.php';

$passed = 0; $failed = 0; $skipped = 0;
t_section('Inventory Manager Consistency');

$complete = new BloodInventoryManagerComplete($pdo);
$robust = new BloodInventoryManagerRobust($pdo, true);

// Derive a candidate blood_type for filtering (prefer types present in inventory; fallback to donors)
$bt = null;
try {
    $btStmt = $pdo->query("SELECT DISTINCT blood_type FROM blood_inventory WHERE blood_type IS NOT NULL AND blood_type <> '' LIMIT 1");
    $bt = $btStmt->fetchColumn();
    if (!$bt) {
        $btStmt = $pdo->query("SELECT DISTINCT blood_type FROM donors_new WHERE blood_type IS NOT NULL AND blood_type <> '' LIMIT 1");
        $bt = $btStmt->fetchColumn();
    }
} catch (Exception $e) {
    // ignore
}
if (!$bt) { $bt = 'O+'; }

$filterSets = [
    [],
    ['status' => 'available'],
    ['status' => 'used'],
    ['status' => 'expired'],
    ['blood_type' => $bt],
];

foreach ($filterSets as $filters) {
    $label = http_build_query($filters);
    echo "\n-- Filters: " . ($label ?: 'none') . " --\n";
    $filters = normalize_filters($filters);

    // Use limit large enough to often avoid pagination effects, but bounded
    $limit = 100;
    $page = 1;

    // Complete manager
    $invC = $complete->getInventory($filters, $page, $limit);
    $cntC = (int)$complete->getInventoryCount($filters);

    // Basic assertions for Complete
    $ok = true;
    $ok &= t_assert(isset($invC['total']), 'Complete: inventory total present');
    $ok &= t_assert($cntC >= 0, 'Complete: count is non-negative');
    // When total <= limit, data length must equal total; otherwise equals limit
    $dataLenC = is_array($invC['data']) ? count($invC['data']) : 0;
    if ($invC['total'] <= $limit) {
        $ok &= t_assert($dataLenC === (int)$invC['total'], 'Complete: data rows equal total when <= limit');
    } else {
        $ok &= t_assert($dataLenC === $limit, 'Complete: data rows equal page limit when total > limit');
    }
    $ok &= t_assert($cntC === (int)$invC['total'], 'Complete: count matches inventory total');
    if ($ok) { $passed += 4; } else { $failed += 1; }

    // Robust manager
    $invR = $robust->getInventory($filters, $page, $limit);
    $cntR = (int)$robust->getInventoryCount($filters);

    $ok2 = true;
    $ok2 &= t_assert(isset($invR['total']), 'Robust: inventory total present');
    $ok2 &= t_assert($cntR >= 0, 'Robust: count is non-negative');
    $dataLenR = is_array($invR['data']) ? count($invR['data']) : 0;
    if ($invR['total'] <= $limit) {
        $ok2 &= t_assert($dataLenR === (int)$invR['total'], 'Robust: data rows equal total when <= limit');
    } else {
        $ok2 &= t_assert($dataLenR === $limit, 'Robust: data rows equal page limit when total > limit');
    }
    $ok2 &= t_assert($cntR === (int)$invR['total'], 'Robust: count matches inventory total');

    // Fallback semantics: donor-virtual cannot satisfy non-available status
    if (!empty($filters['status']) && strtolower($filters['status']) !== 'available') {
        if (isset($invR['source']) && $invR['source'] === 'virtual_from_donors') {
            $ok2 &= t_assert((int)$invR['total'] === 0, 'Robust: non-available filter returns 0 in donor-fallback');
        }
    }

    if ($ok2) { $passed += 5; } else { $failed += 1; }
}

t_result($passed, $failed, $skipped);

?>