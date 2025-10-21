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

    // Validate donor ID
    if (empty($_POST['donor_id'])) {
        throw new Exception('Donor ID is required');
    }

    $donorId = (int)$_POST['donor_id'];

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // First, delete related records (if any)
        // Example: $pdo->prepare("DELETE FROM related_table WHERE donor_id = ?")->execute([$donorId]);
        
        // Then delete the donor
        $stmt = $pdo->prepare("DELETE FROM donors WHERE id = ?");
        $result = $stmt->execute([$donorId]);
        $rowsAffected = $stmt->rowCount();

        if ($result && $rowsAffected > 0) {
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Donor deleted successfully';
        } else {
            $pdo->rollBack();
            $response['message'] = 'Donor not found or already deleted';
            $response['debug']['donor_id'] = $donorId;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400); // Bad Request
}

// Output the JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
