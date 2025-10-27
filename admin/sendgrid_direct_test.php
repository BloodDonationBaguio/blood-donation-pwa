<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<h2>SendGrid Direct SMTP Test</h2>';

// Get environment variables
$host = getenv('MAIL_HOST') ?: 'smtp.sendgrid.net';
$port = getenv('MAIL_PORT') ?: 587;
$user = getenv('MAIL_USER') ?: 'apikey';
$pass = getenv('MAIL_PASS') ?: '';

echo '<strong>Configuration:</strong><br>';
echo 'Host: ' . htmlspecialchars($host) . '<br>';
echo 'Port: ' . htmlspecialchars($port) . '<br>';
echo 'User: ' . htmlspecialchars($user) . '<br>';
echo 'Pass: ' . (empty($pass) ? '<span style="color:red;">EMPTY!</span>' : '<span style="color:green;">Set (hidden)</span>') . '<br><br>';

if (empty($pass)) {
    echo '<span style="color:red;"><strong>ERROR: MAIL_PASS is empty. SendGrid API key not set in Render environment variables.</strong></span>';
    exit;
}

// Test SMTP connection
$smtp = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$smtp) {
    echo '<span style="color:red;">FAIL: Cannot connect to ' . htmlspecialchars($host) . ':' . htmlspecialchars($port) . '<br>';
    echo 'Error: ' . htmlspecialchars($errstr) . ' (Code: ' . $errno . ')</span>';
} else {
    echo '<span style="color:green;">SUCCESS: Connected to ' . htmlspecialchars($host) . ':' . htmlspecialchars($port) . '</span><br>';
    fclose($smtp);
    echo '<br><strong>SMTP connection is working. The issue is likely in PHPMailer configuration.</strong>';
}
