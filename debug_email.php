<?php
// Quick email test/debug endpoint
require_once __DIR__ . '/includes/mail_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$testTo = 'prc.baguio.blood@gmail.com';
$testSubject = 'Test Email from Blood Donation System - ' . date('Y-m-d H:i:s');
$testMessage = '<h2>Test Email</h2><p>This is a test to verify email sending is working.</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
$testName = 'Admin';

echo "=== Email Sending Test ===\n";
echo "To: $testTo\n";
echo "Subject: $testSubject\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

echo "Environment checks:\n";
echo "MAIL_HOST: " . (getenv('MAIL_HOST') ?: 'NOT SET') . "\n";
echo "MAIL_USER: " . (getenv('MAIL_USER') ?: 'NOT SET') . "\n";
echo "MAIL_PASS: " . (getenv('MAIL_PASS') ? 'SET' : 'NOT SET') . "\n";
echo "MAIL_PORT: " . (getenv('MAIL_PORT') ?: 'NOT SET') . "\n";
echo "MAIL_SECURE: " . (getenv('MAIL_SECURE') ?: 'NOT SET') . "\n";
echo "MAIL_FROM: " . (getenv('MAIL_FROM') ?: 'NOT SET') . "\n";
echo "MAIL_FROM_NAME: " . (getenv('MAIL_FROM_NAME') ?: 'NOT SET') . "\n";
echo "SENDGRID_API_KEY: " . (getenv('SENDGRID_API_KEY') ? 'SET' : 'NOT SET') . "\n\n";

echo "Attempting plain PHP mail() test...\n";
$plainSubject = 'Plain Test from Blood Donation System - ' . date('Y-m-d H:i:s');
$plainBody = "This is a plain text test email.\nSent at: " . date('Y-m-d H:i:s');
$plainHeaders = "From: Blood Donation System <prc.baguio.blood@gmail.com>\r\n";
$plainResult = mail($testTo, $plainSubject, $plainBody, $plainHeaders);
echo "Plain mail() result: " . ($plainResult ? 'SUCCESS' : 'FAILED') . "\n\n";

echo "Attempting direct SendGrid API test...\n";
if (file_exists(__DIR__ . '/includes/sendgrid_helper.php') && function_exists('sendgrid_send_email')) {
    $sgResult = sendgrid_send_email($testTo, $testSubject, $testMessage, $testName);
    echo "SendGrid API result: " . ($sgResult ? 'SUCCESS' : 'FAILED') . "\n\n";
} else {
    echo "SendGrid helper not available.\n\n";
}

echo "Attempting to send via helper...\n";
$result = send_confirmation_email($testTo, $testSubject, $testMessage, $testName);
echo "Helper result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n\n";

echo "Recent logs:\n";
$logDir = __DIR__ . '/logs';
$errorLog = $logDir . '/email_errors.log';
$successLog = $logDir . '/email_success.log';

if (file_exists($errorLog)) {
    echo "--- Last 20 lines of email_errors.log ---\n";
    $lines = file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_slice($lines, -20) as $line) {
        echo $line . "\n";
    }
} else {
    echo "email_errors.log not found.\n";
}

echo "\n";

if (file_exists($successLog)) {
    echo "--- Last 20 lines of email_success.log ---\n";
    $lines = file($successLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_slice($lines, -20) as $line) {
        echo $line . "\n";
    }
} else {
    echo "email_success.log not found.\n";
}
?>
