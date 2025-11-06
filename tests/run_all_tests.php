<?php
// Run all blood inventory tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Flag indicating test mode; allows included pages to relax auth redirections
if (!defined('TEST_MODE')) { define('TEST_MODE', true); }

// Simulate admin login for tests
session_start();
$_SESSION['user_id'] = 1; // A dummy user ID for testing
$_SESSION['role'] = 'admin';
// Ensure admin.php sees a valid admin session during web execution
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = $_SESSION['admin_username'] ?? 'admin';
$_SESSION['admin_id'] = $_SESSION['admin_id'] ?? 1;

// Include the database connection
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/utils.php';

require_once __DIR__ . '/inventory_manager_consistency.php';
require_once __DIR__ . '/dashboard_summary_consistency.php';
require_once __DIR__ . '/admin_modern_page_consistency.php';
require_once __DIR__ . '/inventory_backfill_eligibility.php';
require_once __DIR__ . '/inventory_backfill_eligibility_test.php';
require_once __DIR__ . '/backfill_run_and_verify.php';
require_once __DIR__ . '/pending_donors_schema_and_query.php';
require_once __DIR__ . '/pending_donors_admin_integration.php';
require_once __DIR__ . '/data_orphans_and_duplicates.php';
require_once __DIR__ . '/blood_type_normalization.php';
require_once __DIR__ . '/status_inventory_invariants.php';

echo $t_output;

?>