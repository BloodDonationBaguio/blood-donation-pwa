<?php
// Debug endpoint: fingerprint Robust/Complete manager behaviors on this environment
// Outputs plain text so you can quickly compare server vs local

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/BloodInventoryManagerRobust.php';
require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';

function norm_filters($arr) {
    return [
        'blood_type' => $arr['blood_type'] ?? '',
        'status' => $arr['status'] ?? '',
        'search' => $arr['search'] ?? ''
    ];
}

$robustFile = __DIR__ . '/includes/BloodInventoryManagerRobust.php';
$completeFile = __DIR__ . '/includes/BloodInventoryManagerComplete.php';

$robust = new BloodInventoryManagerRobust($pdo, true);
$complete = new BloodInventoryManagerComplete($pdo);

$filtersNone = norm_filters([]);
$filtersAvail = norm_filters(['status' => 'available']);
$filtersUsed = norm_filters(['status' => 'used']);

$cntNoneR = (int)$robust->getInventoryCount($filtersNone);
$invNoneR = $robust->getInventory($filtersNone, 1, 5);
$unifiedNoneR = ($cntNoneR === (int)($invNoneR['total'] ?? -1));
$srcNoneR = $invNoneR['source'] ?? 'unknown';

$cntAvailR = (int)$robust->getInventoryCount($filtersAvail);
$invAvailR = $robust->getInventory($filtersAvail, 1, 5);
$unifiedAvailR = ($cntAvailR === (int)($invAvailR['total'] ?? -1));
$srcAvailR = $invAvailR['source'] ?? 'unknown';

$cntUsedR = (int)$robust->getInventoryCount($filtersUsed);
$invUsedR = $robust->getInventory($filtersUsed, 1, 5);
$unifiedUsedR = ($cntUsedR === (int)($invUsedR['total'] ?? -1));
$srcUsedR = $invUsedR['source'] ?? 'unknown';

// Complete summary fallback fingerprint
$sumC = $complete->getDashboardSummary();

header('Content-Type: text/plain');
echo "Robust file mtime: " . (@date('c', @filemtime($robustFile))) . "\n";
echo "Complete file mtime: " . (@date('c', @filemtime($completeFile))) . "\n\n";

echo "Robust NONE: count={$cntNoneR}, total=" . (int)($invNoneR['total'] ?? -1) . ", unified=" . ($unifiedNoneR ? 'true' : 'false') . ", source={$srcNoneR}\n";
echo "Robust AVAILABLE: count={$cntAvailR}, total=" . (int)($invAvailR['total'] ?? -1) . ", unified=" . ($unifiedAvailR ? 'true' : 'false') . ", source={$srcAvailR}\n";
echo "Robust USED: count={$cntUsedR}, total=" . (int)($invUsedR['total'] ?? -1) . ", unified=" . ($unifiedUsedR ? 'true' : 'false') . ", source={$srcUsedR}\n\n";

echo "Complete Summary: total_units=" . (int)($sumC['total_units'] ?? -1) . ", available_units=" . (int)($sumC['available_units'] ?? -1) . "\n";
echo "Fingerprint: donor-fallback present (expects available==total when inventory empty).\n";
?>