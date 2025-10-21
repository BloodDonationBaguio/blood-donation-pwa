<?php
session_start();
require_once('includes/session_manager.php');

// Check if this is an admin logout BEFORE destroying session
$isAdminLogout = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

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

// Redirect based on user type
if ($isAdminLogout) {
    // Redirect admin to admin login page
    header('Location: admin_login.php?logout=success');
} else {
    // Redirect regular user to homepage
    header('Location: index.php?logout=success');
}
exit();
