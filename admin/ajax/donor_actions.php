<?php
// Admin donor actions (AJAX)
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/enhanced_donor_management.php';

    // Optional CSRF check if token provided
    if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token']) && $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing/invalid donor id']);
        exit;
    }

    $ok = false;
    switch ($action) {
        case 'approve':
            $ok = approveDonor($pdo, $id, $_SESSION['admin_username'] ?? null);
            break;
        case 'reject':
            $reason = trim($_POST['reason'] ?? 'Eligibility criteria not met');
            $ok = updateDonorStatus($pdo, $id, 'rejected', $reason, $_SESSION['admin_username'] ?? null);
            break;
        case 'served':
            $ok = markDonorServed($pdo, $id, date('Y-m-d'), $_SESSION['admin_username'] ?? null);
            break;
        case 'unserved':
            $reason = trim($_POST['reason'] ?? 'No show');
            $ok = markDonorUnserved($pdo, $id, $reason, '', $_SESSION['admin_username'] ?? null);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        $msg = $GLOBALS['last_donor_error'] ?? 'Action failed';
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $msg]);
    }
} catch (Throwable $e) {
    error_log('donor_actions.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
