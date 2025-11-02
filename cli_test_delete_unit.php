<?php
// Quick CLI test to delete a unit by unit_id
// Usage: php cli_test_delete_unit.php <UNIT_ID>

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';

$unitId = $argv[1] ?? null;
if (!$unitId) {
    fwrite(STDERR, "Usage: php cli_test_delete_unit.php <UNIT_ID>\n");
    exit(1);
}

$manager = new BloodInventoryManagerComplete($pdo);

echo "Deleting unit_id: {$unitId}\n";
$result = $manager->deleteUnit($unitId, 'CLI test delete');
echo 'Delete result: ' . json_encode($result) . "\n";

// Verify
$stmt = $pdo->prepare('SELECT COUNT(*) FROM blood_inventory WHERE unit_id = ?');
$stmt->execute([$unitId]);
$count = (int)$stmt->fetchColumn();
echo "Remaining rows with unit_id={$unitId}: {$count}\n";

?>