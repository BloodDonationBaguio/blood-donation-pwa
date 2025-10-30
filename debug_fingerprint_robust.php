<?php
// Fingerprint the Robust manager file to confirm which version is deployed
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$file = __DIR__ . '/includes/BloodInventoryManagerRobust.php';
if (!file_exists($file)) {
    echo "Robust file not found at: $file\n";
    exit;
}

$src = file_get_contents($file);
$md5 = md5($src);
$mtime = @date('c', @filemtime($file));

// Heuristic feature checks for the fixed implementation
$hasSetTotalEqCount = (
    strpos($src, "result['total'] = \$count") !== false
    || strpos($src, 'result[\'total\'] = $count') !== false
);
$hasSourceInventory = (strpos($src, "'source' => 'blood_inventory'") !== false);
$hasSourceDonors = (strpos($src, "'source' => 'virtual_from_donors'") !== false);
$hasGetInventoryCountCall = (strpos($src, 'getInventoryCount($filters)') !== false);

$features = [
    'set_total_equals_count' => $hasSetTotalEqCount,
    'source_blood_inventory_present' => $hasSourceInventory,
    'source_virtual_from_donors_present' => $hasSourceDonors,
    'calls_getInventoryCount_in_getInventory' => $hasGetInventoryCountCall,
];

$isFixed = $hasSetTotalEqCount && $hasSourceInventory && $hasGetInventoryCountCall;

echo "Robust file mtime: $mtime\n";
echo "Robust file md5: $md5\n\n";
foreach ($features as $k => $v) {
    echo $k . ': ' . ($v ? 'true' : 'false') . "\n";
}
echo "\nVersion verdict: " . ($isFixed ? 'FIXED' : 'OLD_OR_UNKNOWN') . "\n";

// Show a small snippet around getInventory
if (preg_match('/function\s+getInventory\s*\(.*\)\s*\{[\s\S]*?\}/m', $src, $m)) {
    $snippet = substr($m[0], 0, 500);
    echo "\ngetInventory snippet (first 500 chars):\n";
    echo $snippet . "\n";
}
?>