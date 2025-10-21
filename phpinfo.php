<?php
// Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test PHPMailer autoloading
require 'includes/mail_helper.php';

// Test email sending with error handling
try {
    $test_email = 'nageb96414@gmail.com';
    $subject = 'Test Email from PHP Info';
    $message = 'This is a test email from PHP Info';
    
    echo "<h1>Email Test</h1>";
    echo "<p>Attempting to send email to: $test_email</p>";
    
    // Test PHPMailer
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
    
    // Show PHPMailer debug output
    $mail->Debugoutput = function($str, $level) {
        echo "<pre>PHPMailer: $str</pre>";
    };
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'nageb96414@gmail.com';
    $mail->Password = 'your-app-password'; // Replace with your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Recipients
    $mail->setFrom('noreply@redcrossbaguio.org', 'Blood Donation System');
    $mail->addAddress($test_email);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $message;
    
    // Send the email
    $result = $mail->send();
    
    if ($result) {
        echo "<p style='color:green;'>✅ Email sent successfully!</p>";
    } else {
        echo "<p style='color:red;'>❌ Failed to send email.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Show PHP info
phpinfo();
?>
