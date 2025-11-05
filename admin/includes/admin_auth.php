<?php
/**
 * Admin Authentication
 */

// Secure session settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true
]);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Load unified admin auth helpers from root includes
require_once dirname(__DIR__, 2) . '/includes/admin_auth.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/admin.php';
    // Redirect to legacy root-level admin login
    header('Location: /admin_login.php');
    exit();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
}
?>
