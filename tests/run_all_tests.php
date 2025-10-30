<?php
// Run all blood inventory tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "\n==============================\n";
echo "Blood Inventory Test Suite\n";
echo "==============================\n";

require_once __DIR__ . '/inventory_manager_consistency.php';
require_once __DIR__ . '/dashboard_summary_consistency.php';
require_once __DIR__ . '/admin_modern_page_consistency.php';

echo "\nAll tests executed. See summaries above.\n";
?>