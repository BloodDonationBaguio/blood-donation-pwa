<?php
echo "<h2>PHP Error Logging Configuration</h2>";

echo "<h3>Error Reporting</h3>";
echo "error_reporting: " . error_reporting() . "<br>";
echo "Current error_reporting value: " . (E_ALL & error_reporting()) . "<br>";

echo "<h3>Error Display</h3>";
echo "display_errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "<br>";
echo "display_startup_errors: " . (ini_get('display_startup_errors') ? 'On' : 'Off') . "<br>";

echo "<h3>Error Logging</h3>";
echo "log_errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "<br>";
echo "error_log: " . ini_get('error_log') . "<br>";

echo "<h3>Test Error Logging</h3>";
error_log("=== TEST ERROR LOG MESSAGE ===");
error_log("Current time: " . date('Y-m-d H:i:s'));
echo "Test error message sent to log file.<br>";

echo "<h3>Force an Error</h3>";
// This should generate a PHP error
$test = $undefined_variable['key'];
echo "If you see this, error logging may not be working properly.";

echo "<h3>Check Common Log Locations</h3>";
echo "<ul>";
echo "<li>Apache Error Log: C:\\xampp\\apache\\logs\\error.log</li>";
echo "<li>PHP Error Log: " . ini_get('error_log') . "</li>";
echo "<li>XAMPP PHP Logs: C:\\xampp\\php\\logs\\php_error_log</li>";
echo "</ul>";

echo "<h3>How to Check Logs</h3>";
echo "<ol>";
echo "<li>Open Command Prompt</li>";
echo "<li>Run: <code>type \"C:\\xampp\\apache\\logs\\error.log\"</code></li>";
echo "<li>Or run: <code>Get-Content \"C:\\xampp\\apache\\logs\\error.log\" -Tail 20</code></li>";
echo "</ol>";
?>
