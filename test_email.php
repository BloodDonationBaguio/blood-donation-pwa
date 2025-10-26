<?php
// Test email functionality on production
require_once 'includes/mail_helper.php';

echo "<!DOCTYPE html><html><head><title>Email Test</title></head><body>";
echo "<h1>Email Configuration Test</h1><pre>";

// Test email configuration
$testEmail = 'test@example.com'; // Change this to your email for testing
$subject = 'Blood Donation System - Email Test';
$message = '<h2>Email Test Successful!</h2><p>This confirms that the email system is working properly on the production server.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';

echo "Testing email configuration...\n";
echo "SMTP Host: smtp.gmail.com\n";
echo "SMTP Port: 587\n";
echo "From: prc.baguio.blood@gmail.com\n";
echo "To: $testEmail\n\n";

try {
    echo "Attempting to send test email...\n";
    $result = send_confirmation_email($testEmail, $subject, $message, 'Test User');
    
    if ($result) {
        echo "✅ EMAIL SENT SUCCESSFULLY!\n";
        echo "✅ Email system is working properly\n";
        echo "✅ Donors will receive confirmation emails\n\n";
        
        echo "Check the following log files for details:\n";
        echo "- logs/email_success.log\n";
        echo "- logs/mail_debug.log\n";
    } else {
        echo "❌ EMAIL SENDING FAILED!\n";
        echo "❌ Check logs/email_errors.log for details\n";
        echo "❌ Donors may not receive confirmation emails\n\n";
        
        echo "Possible issues:\n";
        echo "1. Render.com might block SMTP ports\n";
        echo "2. Gmail App Password might be incorrect\n";
        echo "3. Network connectivity issues\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "❌ Email system has issues\n";
}

echo "\n=====================================\n";
echo "Email Test Complete\n";
echo "=====================================\n";

echo "</pre>";
echo "<p><a href='donor-registration.php'>Test Donor Registration</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
echo "</body></html>";
?>
