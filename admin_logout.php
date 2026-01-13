<?php
// Set timezone to Baguio, Philippines
require_once __DIR__ . '/config/timezone.php';

// admin_logout.php
// This script will clear the admin session and redirect to login

session_start();

// Clear all session variables
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

// Redirect to canonical admin login page with correct base path
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$redirectPath = $basePath ? $basePath . '/admin-login.php' : '/admin-login.php';
header('Location: ' . $redirectPath);
exit();
?>