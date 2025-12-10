<?php
// Include the root db.php file which contains all database configuration and functions
require_once __DIR__ . '/../blood-donation-pwa/db.php';

// This file is now just a wrapper that includes the main database configuration
// All database functionality is now centralized in the root db.php file

// The $pdo variable and database functions (tableExists, getTableStructure)
// are now available from the included file

// Force application timezone to Asia/Manila to ensure consistent user-facing times
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Manila');
}
?>
