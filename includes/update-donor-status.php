<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_POST['donor_id']) || !isset($_POST['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$donorId = (int)$_POST['donor_id'];
$status = $_POST['status'];

// Validate status
$validStatuses = ['pending', 'approved', 'served', 'unserved', 'rejected', 'suspended'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    require_once __DIR__ . '/db.php';
    
    // Prepare and execute the update query
    $stmt = $pdo->prepare("UPDATE donors_new SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $donorId]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Donor status updated successfully',
            'status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made or donor not found'
        ]);
    }
} catch (PDOException $e) {
    error_log('Database error in update-donor-status.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Error in update-donor-status.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
