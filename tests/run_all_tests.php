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

// Preferred order for core tests; any remaining files will be auto-included
$preferred = [
  // Ensure served-flow tests run first so their results are visible even if later tests fail
  'test_mark_donor_served.php',
  'test_mark_multiple_donors_served.php',
  // Core consistency and backfill tests
  'inventory_manager_consistency.php',
  'dashboard_summary_consistency.php',
  'admin_modern_page_consistency.php',
  'inventory_backfill_eligibility.php',
  'inventory_backfill_eligibility_test.php',
  'backfill_run_and_verify.php',
  'pending_donors_schema_and_query.php',
  'pending_donors_admin_integration.php',
  'data_orphans_and_duplicates.php',
  'blood_type_normalization.php',
  'status_inventory_invariants.php',
  'test_donor_approval.php',
  'test_unknown_blood_type.php',
];

foreach ($preferred as $file) {
  $path = __DIR__ . '/' . $file;
  if (file_exists($path)) {
    // Trace which test is running to diagnose early exits
    echo "\n>>> Running: $file\n";
    require_once $path;
  }
  // Echo progress after each test to surface early results even if a later test exits
  if (isset($t_output)) { echo $t_output; }
}

// Auto-include any other test_* files (skip diagnostic/utility pages that may exit)
$excluded = ['run_all_tests.php', 'utils.php'];
$already = array_flip($preferred);
foreach (glob(__DIR__ . '/*.php') as $path) {
  $name = basename($path);
  if (in_array($name, $excluded)) { continue; }
  if (isset($already[$name])) { continue; }
  if (!preg_match('/^test_.*\.php$/', $name)) { continue; }
  echo "\n>>> Running: $name\n";
  require_once $path;
  if (isset($t_output)) { echo $t_output; }
}

// Final summary (may already be partially printed above)
if (isset($t_output)) { echo $t_output; }

?>