<?php
// Create a temporary blood_inventory unit for testing deletes
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

$unitId = 'TEMP-DELETE-' . rand(1000, 9999);
$collectionDate = date('Y-m-d');
$expiryDate = date('Y-m-d', strtotime($collectionDate . ' +25 days'));

$stmt = $pdo->prepare("INSERT INTO blood_inventory (unit_id, blood_type, collection_date, expiry_date, status, collection_site, storage_location, created_at) VALUES (?, 'O+', ?, ?, 'available', 'Main Center', 'Storage A', CURRENT_TIMESTAMP)");
$stmt->execute([$unitId, $collectionDate, $expiryDate]);

echo "Created temp unit_id: {$unitId}\n";
?>