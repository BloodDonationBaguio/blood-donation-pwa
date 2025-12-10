<?php
// Quick email test to specific address
require_once __DIR__ . '/includes/mail_helper.php';
require_once __DIR__ . '/includes/sendgrid_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$testTo = 'nageb964144@gmail.com';
$testSubject = 'Test Email from Blood Donation System - ' . date('Y-m-d H:i:s');
$testMessage = '<h2>Test Email</h2><p>This is a test to verify email sending is working.</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
$testName = 'Test Recipient';

echo "=== Sending Test Email ===\n";
echo "To: $testTo\n";
echo "Subject: $testSubject\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

echo "Attempting direct SendGrid API test...\n";
if (function_exists('sendgrid_send_email')) {
    $sgResult = sendgrid_send_email($testTo, $testSubject, $testMessage, $testName);
    echo "SendGrid API result: " . ($sgResult ? 'SUCCESS' : 'FAILED') . "\n\n";
} else {
    echo "SendGrid helper function not available.\n\n";
}

echo "Attempting to send via helper...\n";
$result = send_confirmation_email($testTo, $testSubject, $testMessage, $testName);
echo "Helper result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n\n";

echo "Recent logs:\n";
$logDir = __DIR__ . '/logs';
$errorLog = $logDir . '/email_errors.log';
$successLog = $logDir . '/email_success.log';

if (file_exists($errorLog)) {
    echo "--- Last 10 lines of email_errors.log ---\n";
    $lines = file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_slice($lines, -10) as $line) {
        echo $line . "\n";
    }
} else {
    echo "email_errors.log not found.\n";
}

echo "\n";

if (file_exists($successLog)) {
    echo "--- Last 10 lines of email_success.log ---\n";
    $lines = file($successLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_slice($lines, -10) as $line) {
        echo $line . "\n";
    }
} else {
    echo "email_success.log not found.\n";
}
?>
