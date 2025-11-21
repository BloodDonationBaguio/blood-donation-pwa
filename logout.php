<?php
session_start();
require_once('includes/session_manager.php');

// Check if this is an admin logout BEFORE destroying session
$isAdminLogout = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isUserLogout = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Debug: Log the logout attempt (remove in production)
error_log("Logout attempt - Admin: " . ($isAdminLogout ? 'yes' : 'no') . ", User: " . ($isUserLogout ? 'yes' : 'no'));

// Logout using the session manager
logoutUser();

// Clear any additional session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect based on user type - prioritize user logout over admin
if ($isUserLogout && !$isAdminLogout) {
    // Regular user logout - redirect to homepage
    header('Location: /index.php?logout=success');
} else if ($isAdminLogout) {
    // Admin logout - redirect to admin login
    header('Location: /admin-login.php?logout=success');
} else {
    // Default case - redirect to homepage
    header('Location: /index.php?logout=success');
}
exit();
