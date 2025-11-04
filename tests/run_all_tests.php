<?php
// Run all blood inventory tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Simulate admin login for tests
session_start();
$_SESSION['user_id'] = 1; // A dummy user ID for testing
$_SESSION['role'] = 'admin';

// Include the database connection
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/utils.php';

require_once __DIR__ . '/inventory_manager_consistency.php';
require_once __DIR__ . '/dashboard_summary_consistency.php';
require_once __DIR__ . '/admin_modern_page_consistency.php';
require_once __DIR__ . '/inventory_backfill_eligibility.php';
require_once __DIR__ . '/backfill_run_and_verify.php';
require_once __DIR__ . '/pending_donors_schema_and_query.php';
require_once __DIR__ . '/pending_donors_admin_integration.php';

echo $t_output;

?>