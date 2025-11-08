<?php
// Admin Pending Donors smoke render with session + fatal diagnostics
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('html_errors', '0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Ensure admin session so admin.php does not redirect/exit
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = $_SESSION['admin_username'] ?? 'diagnostic_admin';

// Allow relaxed checks in included pages
if (!defined('TEST_MODE')) { define('TEST_MODE', true); }

require_once __DIR__ . '/utils.php';
t_section('Admin Pending Donors Smoke Test');

// Fatal guard: show fatal errors instead of blank pages
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n[FATAL] {$e['message']} in {$e['file']}:{$e['line']}\n";
    }
});

// Render admin.php Pending Donors
$_GET['tab'] = 'pending-donors';
unset($_GET['donor_search']);

ob_start();
include __DIR__ . '/../admin.php';
$html = ob_get_clean();

$len = is_string($html) ? strlen($html) : 0;
t_pass("admin.php rendered length=$len");
t_assert(stripos($html, 'Pending Donors') !== false, 'Contains Pending Donors header');

// Basic row count (rough pattern; diagnostic only)
$rowCount = 0;
if (preg_match('/<tbody[^>]*>(.*?)<\/tbody>/is', $html, $m)) {
    $tbody = $m[1];
    $rowCount = preg_match_all('/<tr[\s>]/i', $tbody);
}
t_pass("approx data rows in table: {$rowCount}");

$list = $GLOBALS['pendingDonors'] ?? [];
t_pass('pendingDonors array size=' . (is_array($list) ? count($list) : 0));

echo $t_output;
echo "<hr><pre>" . htmlspecialchars(substr($html, 0, 2000)) . "</pre>";
?>