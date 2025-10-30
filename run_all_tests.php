<?php
// Convenience alias: allow /run_all_tests.php to execute tests/run_all_tests.php
// Helps bypass front-controller rewrites when the tests directory is present.
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/tests/run_all_tests.php';
?>