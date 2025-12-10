<?php
require_once 'includes/session_config.php';
require_once 'includes/session_manager.php';
require_once 'includes/email_queue_helper.php';

// Admin check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit();
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$file = $_GET['file'] ?? '';

if ($action === 'get' && $file) {
    $queueDir = __DIR__ . '/email_queue';
    $filepath = $queueDir . '/' . $file;
    if (file_exists($filepath)) {
        echo json_encode(['ok' => true] + json_decode(file_get_contents($filepath), true));
    } else {
        echo json_encode(['ok' => false, 'message' => 'Not found']);
    }
} elseif ($action === 'delete' && $file) {
    $deleted = delete_queued_email($file);
    echo json_encode(['ok' => $deleted]);
} else {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
}
?>
