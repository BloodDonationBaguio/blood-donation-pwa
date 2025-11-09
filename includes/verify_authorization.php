<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/authorization_config.php';

// Require admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Read input (supports JSON or form-encoded)
$raw = file_get_contents('php://input');
$data = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if (empty($data)) {
    $data = $_POST;
}

$password = $data['password'] ?? '';
if ($password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit();
}

// Validate password via config
$result = AuthorizationConfig::validatePassword($password);
if (!empty($result['success'])) {
    // Grant temporary authorization for editing actions
    $ttlSeconds = 15 * 60; // 15 minutes
    $_SESSION['authorization_verified'] = true;
    $_SESSION['authorization_verified_expires'] = time() + $ttlSeconds;
    echo json_encode(['success' => true, 'expires_in' => $ttlSeconds]);
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Authorization failed']);
}
?>