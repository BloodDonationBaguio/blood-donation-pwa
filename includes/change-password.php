<?php
session_start();
require_once(__DIR__ . '/../db.php');

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo 'Method not allowed';
    exit();
}

// Get form data
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate inputs
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['error'] = 'All fields are required';
    header('Location: ../admin-dashboard.php?tab=dashboard&password_error=1');
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error'] = 'New passwords do not match';
    header('Location: ../admin-dashboard.php?tab=dashboard&password_error=1');
    exit();
}

if (strlen($new_password) < 8) {
    $_SESSION['error'] = 'New password must be at least 8 characters long';
    header('Location: ../admin-dashboard.php?tab=dashboard&password_error=1');
    exit();
}

// Get admin user from database
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    $_SESSION['error'] = 'Admin user not found';
    header('Location: ../admin-dashboard.php?tab=dashboard&password_error=1');
    exit();
}

// Verify current password
if (!password_verify($current_password, $admin['password'])) {
    $_SESSION['error'] = 'Current password is incorrect';
    header('Location: ../admin-dashboard.php?tab=dashboard&password_error=1');
    exit();
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in database
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$result = $stmt->execute([$hashed_password]);

if ($result) {
    $_SESSION['success'] = 'Password updated successfully';
    header('Location: ../admin-dashboard.php?tab=dashboard&password_success=1');
} else {
    $_SESSION['error'] = 'Failed to update password. Please try again.';
    header('Location: ../admin-dashboard.php?tab=dashboard&password_error=1');
}

exit();
?>
