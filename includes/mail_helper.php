<?php
require_once __DIR__ . '/phpmailer_loader.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';
// Enable error reporting for debugging (log only, no display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Prevent direct access through web browser, but allow from our debug script and password reset pages
if (php_sapi_name() !== 'cli') {
    $is_debug_script = (basename($_SERVER['SCRIPT_FILENAME']) === 'debug_email.php');
    $is_password_reset = in_array(basename($_SERVER['SCRIPT_FILENAME']), [
        'admin-forgot-password.php', 
        'admin-reset-password.php',
        'admin-login.php',
        'admin.php',
        'donor-registration.php',
        'donor-registration-new.php'
    ]);
    
    if (!defined('ABSPATH') && !defined('INCLUDES_PATH') && !$is_debug_script && !$is_password_reset) {
        // If this is an API request, allow it
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            die('Direct access not permitted');
        }
    }
}

/**
 * Send a confirmation email with HTML content using PHPMailer with SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlMessage HTML formatted message
 * @param string $toName Optional recipient name
 * @return bool True if email was sent successfully, false otherwise
 */
function send_confirmation_email($to, $subject, $htmlMessage, $toName = '') {
    // Create a new PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // Set up logging
    $logDir = __DIR__ . '/../logs';
    $debugLog = $logDir . '/mail_debug.log';
    $errorLog = $logDir . '/email_errors.log';
    $successLog = $logDir . '/email_success.log';
    
    // Ensure logs directory exists and is writable
    if (!file_exists($logDir)) {
        if (!@mkdir($logDir, 0755, true)) {
            error_log("Failed to create logs directory: $logDir");
        }
    }
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'prc.baguio.blood@gmail.com';
        $mail->Password = 'anoi yppm telm vmfy'; // App Password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPKeepAlive = true; // Enable keep-alive
        $mail->Timeout = 30; // Set a reasonable timeout
        
        // Force SMTP to be used
        $mail->Mailer = 'smtp';
        
        // Debug settings - only enable if we can write to the logs directory
        if (is_writable($logDir)) {
            $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) use ($debugLog) {
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . ' - ' . trim($str) . "\n", FILE_APPEND);
            };
        } else {
            $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
            error_log("Log directory not writable: $logDir");
        }
        
        // Disable strict certificate verification for local testing
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Recipients — set From to authenticated Gmail to improve deliverability
        $mail->setFrom('prc.baguio.blood@gmail.com', 'Blood Donation System');
        
        // Add recipient with name if provided
        if (!empty($toName)) {
            $mail->addAddress($to, $toName);
        } else {
            $mail->addAddress($to);
        }
        
        $mail->addReplyTo('prc.baguio.blood@gmail.com', 'Blood Donation System');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlMessage;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlMessage));
        
        // Send the email
        $result = $mail->send();
        
        // Log the email sending attempt
        $logMessage = sprintf(
            "[%s] Email %s to %s - %s\n",
            date('Y-m-d H:i:s'),
            $result ? 'SENT' : 'FAILED',
            $to,
            $result ? '' : 'Error: ' . $mail->ErrorInfo
        );
        
        // Log to appropriate files if directory is writable
        if (is_writable($logDir)) {
            if ($result) {
                @file_put_contents($successLog, $logMessage, FILE_APPEND);
            } else {
                @file_put_contents($errorLog, $logMessage, FILE_APPEND);
            }
            @file_put_contents($debugLog, $logMessage, FILE_APPEND);
        }
        
        error_log(trim($logMessage));
        return $result;
        
    } catch (Exception $e) {
        $errorMsg = "Failed to send email to $to: " . $e->getMessage();
        error_log($errorMsg);
        
        // Log to error file if directory is writable
        if (is_writable($logDir)) {
            @file_put_contents(
                $errorLog,
                '[' . date('Y-m-d H:i:s') . '] ' . $errorMsg . "\n",
                FILE_APPEND
            );
            @file_put_contents(
                $debugLog,
                '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $errorMsg . "\n",
                FILE_APPEND
            );
        }
        
        return false;
    }
}
