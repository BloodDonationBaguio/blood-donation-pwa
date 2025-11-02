<?php
// CLI test: update a blood unit's status and verify notes
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db_production.php';
require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';

// Simulate admin session for audit logging
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'cli_tester';
$_SESSION['admin_role'] = 'super_admin';

function println($msg) { echo $msg . PHP_EOL; }

try {
    // Find an available unit, otherwise pick any unit
    $stmt = $pdo->query("SELECT unit_id, status, COALESCE(notes, '') AS notes FROM blood_inventory ORDER BY created_at DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        println('No blood_inventory rows found.');
        exit(1);
    }

    $unitId = $row['unit_id'];
    println("Testing unit_id: {$unitId}");
    println("Current status: {$row['status']}");

    $manager = new BloodInventoryManagerComplete($pdo);
    $reason = 'CLI test: mark as used';
    $res = $manager->updateUnitStatus($unitId, 'used', $reason);
    println('Update result: ' . json_encode($res));

    // Read back
    $stmt2 = $pdo->prepare("SELECT status, notes FROM blood_inventory WHERE unit_id = ?");
    $stmt2->execute([$unitId]);
    $after = $stmt2->fetch(PDO::FETCH_ASSOC);
    println('After status: ' . ($after['status'] ?? 'N/A'));
    $notesPreview = isset($after['notes']) ? substr($after['notes'], 0, 200) : '(no notes column or null)';
    println('After notes (preview): ' . $notesPreview);

    exit(0);
} catch (Throwable $e) {
    println('Error: ' . $e->getMessage());
    exit(1);
}
?>