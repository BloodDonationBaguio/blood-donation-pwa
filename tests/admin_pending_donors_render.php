<?php
// Admin Pending Donors Render — ensures admin.php Pending Donors tab produces non-blank HTML
error_reporting(E_ALL);
ini_set('display_errors', '1');

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

?>