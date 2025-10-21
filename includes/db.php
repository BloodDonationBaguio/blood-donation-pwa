<?php
// Include the root db.php file which contains all database configuration and functions
require_once __DIR__ . '/../db.php';

// This file is now just a wrapper that includes the main database configuration
// All database functionality is now centralized in the root db.php file

// The $pdo variable and database functions (tableExists, getTableStructure)
// are now available from the included file
?>
