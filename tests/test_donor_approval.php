<?php
require_once __DIR__ . '/../includes/enhanced_donor_management.php';

try {
    // Test to ensure that the donor approval and rejection functionality works correctly

    // Add a new pending donor for testing
    $stmt = $pdo->prepare("INSERT INTO donors_new (first_name, last_name, email, phone, blood_type, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Test', 'Donor Approval', 'test.approval@example.com', '1234567890', 'A+', 'pending']);
    $donorId = $pdo->lastInsertId();

    echo "Inserted test donor with ID: $donorId\n";

    // Approve the donor
    approveDonor($pdo, $donorId, 1);

    // Verify the donor's status is now 'approved'
    $stmt = $pdo->prepare("SELECT status FROM donors_new WHERE id = ?");
    $stmt->execute([$donorId]);
    $status = $stmt->fetchColumn();

    if ($status === 'approved') {
        echo "Test passed: Donor status updated to 'approved'\n";
    } else {
        echo "Test failed: Donor status is '$status' instead of 'approved'\n";
    }

    // Clean up the test donor
    $stmt = $pdo->prepare("DELETE FROM donors_new WHERE id = ?");
    $stmt->execute([$donorId]);

    echo "Cleaned up test donor with ID: $donorId\n";

    // Test donor rejection
    $stmt = $pdo->prepare("INSERT INTO donors_new (first_name, last_name, email, phone, blood_type, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Test', 'Donor Rejection', 'test.rejection@example.com', '1234567890', 'B+', 'pending']);
    $rejectionDonorId = $pdo->lastInsertId();

    echo "Inserted test donor for rejection with ID: $rejectionDonorId\n";

    updateDonorStatus($pdo, $rejectionDonorId, 'rejected', 'Test rejection reason', 1);

    // Verify the donor's status is now 'rejected'
    $stmt = $pdo->prepare("SELECT status FROM donors_new WHERE id = ?");
    $stmt->execute([$rejectionDonorId]);
    $status = $stmt->fetchColumn();

    if ($status === 'rejected') {
        echo "Test passed: Donor status updated to 'rejected'\n";
    } else {
        echo "Test failed: Donor status is '$status' instead of 'rejected'\n";
    }

    // Clean up the test donor
    $stmt = $pdo->prepare("DELETE FROM donors_new WHERE id = ?");
    $stmt->execute([$rejectionDonorId]);

    echo "Cleaned up test donor with ID: $rejectionDonorId\n";
} catch (Throwable $e) {
    echo "Test failed with error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

?>