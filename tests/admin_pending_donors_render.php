<?php
// Admin Pending Donors Render — ensures admin.php Pending Donors tab produces non-blank HTML
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Print a minimal header early so browser never shows a blank page
if (php_sapi_name() !== 'cli') {
    echo "<meta charset=\"utf-8\"><title>Admin Pending Donors Render Test</title>\n";
    echo "<pre>Admin Pending Donors tab renders non-blank HTML</pre>\n";
}

// Surface fatal errors instead of a white screen
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && php_sapi_name() !== 'cli') {
        $type = $err['type'] ?? 0;
        $msg  = htmlspecialchars($err['message'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $file = htmlspecialchars($err['file'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $line = (int)($err['line'] ?? 0);
        echo "<pre>Fatal error (type {$type}): {$msg}\nIn {$file} on line {$line}</pre>";
    }
});

// Start buffering BEFORE any output to avoid header/session warnings
if (ob_get_level() === 0) { ob_start(); }

// Enable relaxed auth for admin.php when running outside the aggregator
if (!defined('TEST_MODE')) { define('TEST_MODE', true); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// Align with site admin auth keys so admin.php doesn't redirect
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = $_SESSION['admin_username'] ?? 'test-admin';
$_SESSION['admin_role'] = $_SESSION['admin_role'] ?? 'super_admin';
$_SESSION['admin_last_activity'] = $_SESSION['admin_last_activity'] ?? time();

require_once __DIR__ . '/utils.php';

t_section('Admin Pending Donors tab renders non-blank HTML');

// Simulate access to the page and capture output
$_GET['tab'] = 'pending-donors';
ob_start();
include_once dirname(__DIR__) . '/admin.php';
$html = ob_get_clean();

$nonBlank = t_assert(strlen(trim($html)) > 0, 'admin.php output is not blank');

// Basic sanity: ensure the Pending Donors header exists and a table is present
$hasHeader = (stripos($html, 'Pending Donors') !== false) || (stripos($html, 'pending-donors') !== false);
$hasTable = (stripos($html, '<table') !== false);
t_assert($hasHeader, 'Pending Donors header or label present');
t_assert($hasTable, 'Table element present');

t_result($nonBlank ? 3 : 2, ($nonBlank ? 0 : 1) + ($hasHeader ? 0 : 1) + ($hasTable ? 0 : 1), 0);

// After assertions, print the captured admin output for visual verification
if (php_sapi_name() !== 'cli') {
    echo $html; // show captured admin output if any
}

// Flush any buffered output at end
if (ob_get_level() > 0) { ob_end_flush(); }

?>