<?php
// Minimal registration page for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Simple Registration Test</h1>";

try {
    session_start();
    echo "<p>✅ Session started</p>";
    
    require_once __DIR__ . "/includes/db.php";
    echo "<p>✅ Database connected</p>";
    
    echo "<p>✅ Basic PHP functionality working</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

echo "<p><a href='donor-registration.php'>Try Full Registration Page</a></p>";
echo "<p><a href='test_page.php'>Back to Test Page</a></p>";
?>
