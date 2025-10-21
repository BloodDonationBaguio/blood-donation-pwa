<?php
// Include PHPMailer classes directly
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

// Import the necessary classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send a confirmation email using PHPMailer with SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlMessage HTML content of the email
 * @param string $toName Recipient name (optional)
 * @return bool True if email was sent successfully, false otherwise
 */
function send_confirmation_email($to, $subject, $htmlMessage, $toName = '') {
    // Set up logging
    $logDir = __DIR__ . '/../logs';
    $debugLog = $logDir . '/mail_debug.log';
    $errorLog = $logDir . '/email_errors.log';
    
    // Ensure logs directory exists and is writable
    if (!file_exists($logDir)) {
        if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log("Failed to create logs directory: $logDir");
            return false;
        }
    }
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'nageb96414@gmail.com';
        $mail->Password = 'kvim umuh okmf aeua'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPKeepAlive = true;
        $mail->Timeout = 30;
        
        // Force SMTP to be used
        $mail->Mailer = 'smtp';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Debug settings - only enable if we can write to the logs directory
        if (is_writable($logDir)) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) use ($debugLog) {
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . ' - ' . trim($str) . "\n", FILE_APPEND);
            };
        } else {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            error_log("Log directory not writable: $logDir");
        }
        
        // Sender and recipient settings
        $mail->setFrom('noreply@blooddonation.com', 'Blood Donation System');
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlMessage;
        $mail->AltBody = strip_tags($htmlMessage);
        
        // Send the email
        $result = $mail->send();
        
        // Log the result
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
                @file_put_contents($logDir . '/email_success.log', $logMessage, FILE_APPEND);
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
