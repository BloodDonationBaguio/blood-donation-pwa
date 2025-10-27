<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/mail_helper.php';
$to = 'your@email.com'; // Change to your email for testing
$subject = 'Test Email from Blood Donation System';
$message = '<h2>This is a test email from your Render deployment.</h2>';
$result = false;
if (function_exists('send_confirmation_email')) {
    $result = send_confirmation_email($to, $subject, $message, 'Test User');
    echo $result ? '<span style="color:green;">SUCCESS: Email sent (check your inbox or spam).</span>' : '<span style="color:red;">FAIL: Email not sent. See logs/email_errors.log.</span>';
} else {
    echo '<span style="color:red;">send_confirmation_email() function not found.</span>';
}
