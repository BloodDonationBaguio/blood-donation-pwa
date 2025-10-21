<?php
/**
 * Email Queue System
 * 
 * This file handles queuing and sending emails in the background
 * to prevent delays in form submission.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Add an email to the queue
 */
function add_to_email_queue($to, $subject, $message, $toName = '') {
    $queueFile = __DIR__ . '/../email_queue.txt';
    $queueItem = json_encode([
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
        'toName' => $toName,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Append to queue file
    file_put_contents($queueFile, $queueItem . "\n", FILE_APPEND);
    
    // Log the queue addition
    file_put_contents('email_queue_log.txt', 
        date('Y-m-d H:i:s') . " - Added to queue: $to - $subject\n", 
        FILE_APPEND
    );
}

/**
 * Process the email queue (to be called from a cron job or background process)
 */
function process_email_queue() {
    $queueFile = __DIR__ . '/../email_queue.txt';
    $processingFile = __DIR__ . '/../email_queue_processing.txt';
    $errorFile = __DIR__ . '/../email_queue_errors.txt';
    $maxEmails = 10; // Process max 10 emails at a time
    
    // Rotate the queue file if it exists
    if (file_exists($queueFile)) {
        rename($queueFile, $processingFile);
    } else {
        // No emails in queue
        return 0;
    }
    
    $emails = file($processingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $processed = 0;
    $errors = [];
    
    foreach ($emails as $index => $emailJson) {
        if ($processed >= $maxEmails) {
            break;
        }
        
        $email = json_decode($emailJson, true);
        if (!$email) {
            $errors[] = "Invalid email data: $emailJson";
            continue;
        }
        
        // Send the email using the original function
        $result = sendEmailDirectly(
            $email['to'],
            $email['subject'],
            $email['message'],
            $email['toName']
        );
        
        if ($result) {
            $processed++;
        } else {
            $errors[] = "Failed to send to: " . $email['to'] . " - " . $email['subject'];
        }
        
        // Remove processed email
        unset($emails[$index]);
    }
    
    // Save any remaining emails back to the queue
    if (!empty($emails)) {
        file_put_contents($queueFile, implode("\n", $emails) . "\n");
    }
    
    // Log any errors
    if (!empty($errors)) {
        file_put_contents($errorFile, 
            date('Y-m-d H:i:s') . " - Errors:\n" . 
            implode("\n", $errors) . "\n\n", 
            FILE_APPEND
        );
    }
    
    // Clean up
    @unlink($processingFile);
    
    return $processed;
}

/**
 * Direct email sending function (bypasses queue)
 */
function sendEmailDirectly($to, $subject, $message, $toName = '') {
    global $mail; // Use the existing mailer instance if available
    
    if (!isset($mail)) {
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        
        $mail = new PHPMailer(true);
    }
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'prc.baguio.blood@gmail.com';
        $mail->Password = 'anoi yppm telm vmfy'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        
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
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        
        // Send the email
        $result = $mail->send();
        
        // Log the email sending
        file_put_contents('email_sent.log', 
            date('Y-m-d H:i:s') . " - Sent to $to - $subject\n", 
            FILE_APPEND
        );
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        file_put_contents('email_errors.log', 
            date('Y-m-d H:i:s') . " - Failed to send to $to - " . $e->getMessage() . "\n", 
            FILE_APPEND
        );
        return false;
    }
}

// Process queue if this file is called directly
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'email_queue.php') {
    $processed = process_email_queue();
    echo "Processed $processed emails.\n";
}
?>
