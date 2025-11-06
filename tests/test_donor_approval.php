<?php
// Exercise donor approval/rejection flows; skips when donors_new table is absent.
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/utils.php';
// enhanced_donor_management provides approveDonor/updateDonorStatus functions
require_once __DIR__ . '/../includes/enhanced_donor_management.php';

t_section('Donor Approval/Rejection Flow');

// Check donors_new table presence
$hasDonorsNew = false;
try { $hasDonorsNew = (bool)$pdo->query("SELECT 1 FROM donors_new LIMIT 1")->fetchColumn(); } catch (Throwable $e) { $hasDonorsNew = false; }
if (!$hasDonorsNew) { t_skip('donors_new table absent; skipping approval/rejection tests.'); return; }

try {
    // Insert a pending donor for approval test
    $stmt = $pdo->prepare("INSERT INTO donors_new (first_name, last_name, email, phone, blood_type, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Test', 'Donor Approval', 'test.approval@example.com', '1234567890', 'A+', 'pending']);
    $donorId = (int)$pdo->lastInsertId();
    echo "Inserted test donor for approval with ID: {$donorId}\n";

    // Approve donor
    approveDonor($pdo, $donorId, 1);

    $status = $pdo->prepare("SELECT status FROM donors_new WHERE id = ?");
    $status->execute([$donorId]);
    $curr = $status->fetchColumn();
    if ($curr === 'approved') { t_pass("Donor approved successfully."); }
    else { t_fail("Donor approval failed; got status '{$curr}'."); }

    // Cleanup
    $pdo->prepare("DELETE FROM donors_new WHERE id = ?")->execute([$donorId]);
    echo "Cleaned up approval test donor: {$donorId}\n";

    // Rejection test
    $stmt->execute(['Test', 'Donor Rejection', 'test.rejection@example.com', '1234567890', 'B+', 'pending']);
    $rejectionDonorId = (int)$pdo->lastInsertId();
    echo "Inserted test donor for rejection with ID: {$rejectionDonorId}\n";

    updateDonorStatus($pdo, $rejectionDonorId, 'rejected', 'Test rejection reason', 1);
    $status2 = $pdo->prepare("SELECT status FROM donors_new WHERE id = ?");
    $status2->execute([$rejectionDonorId]);
    $curr2 = $status2->fetchColumn();
    if ($curr2 === 'rejected') { t_pass("Donor rejected successfully."); }
    else { t_fail("Donor rejection failed; got status '{$curr2}'."); }

    // Cleanup
    $pdo->prepare("DELETE FROM donors_new WHERE id = ?")->execute([$rejectionDonorId]);
    echo "Cleaned up rejection test donor: {$rejectionDonorId}\n";
} catch (Throwable $e) {
    t_fail("Approval/Rejection tests threw: " . $e->getMessage());
    echo $e->getTraceAsString() . "\n";
}

?>