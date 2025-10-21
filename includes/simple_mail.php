<?php
/**
 * Simple mail sending function using PHP's built-in mail()
 * with SMTP configuration in php.ini
 */

/**
 * Send a simple HTML email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlMessage HTML content of the email
 * @param string $toName Recipient name (optional)
 * @return bool True if email was sent successfully, false otherwise
 */
function send_simple_email($to, $subject, $htmlMessage, $toName = '') {
    // Set up logging
    $logDir = __DIR__ . '/../logs';
    $errorLog = $logDir . '/email_errors.log';
    $successLog = $logDir . '/email_success.log';
    
    // Ensure logs directory exists and is writable
    if (!file_exists($logDir)) {
        if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log("Failed to create logs directory: $logDir");
            return false;
        }
    }
    
    // Set up email headers
    $from = 'noreply@blooddonation.com';
    $fromName = 'Blood Donation System';
    
    // To send HTML mail, the Content-type header must be set
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . $fromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Convert HTML to plain text for the text version
    $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlMessage));
    
    // Create a boundary for the email
    $boundary = md5(uniqid(time()));
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    
    // Email body
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $plainText . "\r\n\r\n";
    
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--$boundary--\r\n";
    
    try {
        // Send the email
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        // Log the result
        $logMessage = sprintf(
            "[%s] Email %s to %s - %s\n",
            date('Y-m-d H:i:s'),
            $result ? 'SENT' : 'FAILED',
            $to,
            $result ? '' : 'Error: ' . error_get_last()['message']
        );
        
        if ($result) {
            @file_put_contents($successLog, $logMessage, FILE_APPEND);
        } else {
            @file_put_contents($errorLog, $logMessage, FILE_APPEND);
        }
        
        return $result;
        
    } catch (Exception $e) {
        $errorMsg = "[EXCEPTION] Failed to send email to $to: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n";
        @file_put_contents($errorLog, $errorMsg, FILE_APPEND);
        return false;
    }
}

/**
 * Configure PHP to use SMTP for sending emails
 * Call this function at the beginning of your script
 */
function configure_smtp() {
    // SMTP configuration - adjust these values according to your SMTP server
    ini_set('SMTP', 'smtp.gmail.com');
    ini_set('smtp_port', 587);
    ini_set('sendmail_from', 'noreply@blooddonation.com');
    
    // For Gmail SMTP with authentication, you might need to use a library like PHPMailer
    // as PHP's mail() function doesn't support SMTP authentication directly
}

// Uncomment the line below to configure SMTP when this file is included
// configure_smtp();
?>
