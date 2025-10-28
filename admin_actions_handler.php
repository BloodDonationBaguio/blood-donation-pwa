<?php
/**
 * Admin Actions Handler - AJAX endpoint for admin actions
 */

session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/admin_actions.php';

header('Content-Type: application/json');
// Ensure proper CORS headers for same-origin requests
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_report':
            $reportType = $_POST['report_type'] ?? '';
            $filters = $_POST['filters'] ?? [];
            
            switch ($reportType) {
                case 'donors':
                    $data = generateDonorReport($pdo, $filters);
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid report type']);
                    exit();
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Admin action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>