<?php
// Manual check script to compare getInventoryCount vs getInventory['total'] for various filters
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/../includes/BloodInventoryManagerRobust.php';

$complete = new BloodInventoryManagerComplete($pdo);
$robust = new BloodInventoryManagerRobust($pdo, true);

$filtersList = [
  [],
  ['status' => 'available'],
  ['status' => 'used'],
  ['status' => 'expired'],
];

foreach ($filtersList as $f) {
  $label = http_build_query($f) ?: 'none';
  $cntC = (int)$complete->getInventoryCount($f);
  $invC = $complete->getInventory($f, 1, 100);
  $cntR = (int)$robust->getInventoryCount($f);
  $invR = $robust->getInventory($f, 1, 100);
  echo "\n=== Filters: {$label} ===\n";
  echo "Complete: count={$cntC}, total={$invC['total']}\n";
  echo "Robust:   count={$cntR}, total={$invR['total']} (src=" . ($invR['source'] ?? 'unknown') . ")\n";
}

?>