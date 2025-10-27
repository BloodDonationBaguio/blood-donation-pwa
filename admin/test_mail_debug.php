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
    ob_start();
    $result = send_confirmation_email($to, $subject, $message, 'Test User');
    $debug = ob_get_clean();
    if ($result) {
        echo '<span style="color:green;">SUCCESS: Email sent (check your inbox or spam).</span>';
    } else {
        echo '<span style="color:red;">FAIL: Email not sent.<br>';
        if (function_exists('error_get_last')) {
            $err = error_get_last();
            if ($err) echo 'PHP Error: ' . htmlspecialchars($err['message']) . '<br>';
        }
        echo 'If available, see logs/email_errors.log.<br>';
        if (!empty($debug)) echo '<pre>' . htmlspecialchars($debug) . '</pre>';
        echo '</span>';
    }
} else {
    echo '<span style="color:red;">send_confirmation_email() function not found.</span>';
}
