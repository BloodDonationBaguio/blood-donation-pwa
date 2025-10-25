<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session and include database connection
session_start();
require_once __DIR__ . '/../../db.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'debug' => []
];

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('CSRF token validation failed');
    }

    // Validate required fields
    $required = ['donor_id', 'status'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $donorId = (int)$_POST['donor_id'];
    $status = $_POST['status'];

    // Validate status value
    if (!in_array($status, ['pending', 'approved', 'served', 'unserved', 'rejected', 'suspended'])) {
        throw new Exception('Invalid status value');
    }

    // Update donor status in the database
    $stmt = $pdo->prepare("UPDATE donors_new SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $donorId]);

    if ($result && $stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Donor status updated successfully';

        // AUTOMATICALLY CREATE BLOOD UNIT if status is changed to 'served'
        if ($status === 'served') {
            try {
                require_once __DIR__ . '/../../includes/BloodInventoryManagerComplete.php';
                $inventoryManager = new BloodInventoryManagerComplete($pdo);
                
                $bloodUnitData = [
                    'donor_id' => $donorId,
                    'collection_date' => date('Y-m-d'),
                    'collection_site' => 'Main Center',
                    'storage_location' => 'Storage A'
                ];
                
                $unitResult = $inventoryManager->addBloodUnit($bloodUnitData);
                
                if ($unitResult['success']) {
                    $response['blood_unit_created'] = true;
                    $response['unit_id'] = $unitResult['unit_id'];
                } else {
                    error_log("Failed to auto-create blood unit for donor $donorId: " . $unitResult['message']);
                }
            } catch (Exception $e) {
                error_log("Error auto-creating blood unit for donor $donorId: " . $e->getMessage());
            }
        }

        // Send email notification
        $to = 'recipient@example.com';
        $subject = 'Donor Status Update';
        $body = "Donor status updated to $status for donor ID $donorId";
        $headers = ['From' => 'sender@example.com', 'Content-Type' => 'text/plain'];
        mail($to, $subject, $body, $headers);
    } else {
        $response['message'] = 'No changes made or donor not found';
        $response['debug']['donor_id'] = $donorId;
        $response['debug']['status'] = $status;
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400); // Bad Request
    // Log the error
    error_log($e->getMessage());
}

// Output the JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
