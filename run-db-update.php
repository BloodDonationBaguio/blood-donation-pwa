<?php
// Database Update Script
require_once __DIR__ . '/includes/db.php';

// Only allow access from localhost for security
$allowed = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed)) {
    die('Access denied');
}

// Simple HTML header
echo "<html><head><title>Database Update</title>
<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; }
</style>
</head><body>";

echo "<h1>Database Update Tool</h1>";

// Check if migration file exists
$migrationFile = __DIR__ . '/migrations/002_update_donors_table.php';
if (!file_exists($migrationFile)) {
    die("<p class='error'>Error: Migration file not found at $migrationFile</p></body></html>");
}

// Run the migration
echo "<h2>Running Migration: Update Donors Table</h2>";
echo "<pre>";
ob_start();
include $migrationFile;
$output = ob_get_clean();
echo htmlspecialchars($output);
echo "</pre>";

// Add a link to test the registration form
echo "<p><a href='/blood-donation-pwa/donor-registration.php' class='button'>Test Registration Form</a></p>";

echo "<p><strong>Note:</strong> Please delete this file after use for security reasons.</p>";
echo "</body></html>";

// Delete this file after execution
@unlink(__FILE__);
?>
