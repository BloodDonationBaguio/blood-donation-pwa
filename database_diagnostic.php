<?php
// database_diagnostic.php
// This script now only contains the core diagnostic logic to verify table existence.
// All test data manipulation has been removed.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Manila');
}

require_once 'db.php';

try {
    // Verify 'donors_new' table existence
    if (tableExists($pdo, 'donors_new')) {
        echo "Table 'donors_new' exists.\n";
    } else {
        echo "Table 'donors_new' does not exist.\n";
    }

    // Verify 'donors' table existence
    if (tableExists($pdo, 'donors')) {
        echo "Table 'donors' exists.\n";
    } else {
        echo "Table 'donors' does not exist.\n";
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
?>
