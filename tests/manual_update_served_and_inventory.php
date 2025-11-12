<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/enhanced_donor_management.php';
$donorId = isset($argv[1]) ? (int)$argv[1] : 0;
if (!$donorId) { echo "ERR no donor id\n"; exit(1); }
$ok = updateDonorStatus($pdo, $donorId, 'served', 'verification');
if (!$ok) { echo "ERR update failed: " . ($GLOBALS['last_donor_error'] ?? 'unknown') . "\n"; exit(1); }
$stmt = $pdo->prepare('SELECT unit_id, status FROM blood_inventory WHERE donor_id = ? ORDER BY id DESC LIMIT 5');
$stmt->execute([$donorId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) { echo ($r['unit_id'] ?? '') . " " . ($r['status'] ?? '') . "\n"; }
echo "OK\n";
