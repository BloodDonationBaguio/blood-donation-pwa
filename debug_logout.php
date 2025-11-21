<?php
session_start();

header('Content-Type: application/json');

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session_data' => $_SESSION,
    'admin_logged_in' => $_SESSION['admin_logged_in'] ?? 'not set',
    'user_logged_in' => $_SESSION['user_logged_in'] ?? 'not set',
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'admin_username' => $_SESSION['admin_username'] ?? 'not set',
    'session_id' => session_id(),
    'logout_detection' => [
        'is_admin_check' => isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true,
        'navbar_logic' => isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true ? '/admin_logout.php' : 'logout.php'
    ]
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
