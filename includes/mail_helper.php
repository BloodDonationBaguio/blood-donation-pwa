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
    $is_debug_script = in_array(basename($_SERVER['SCRIPT_FILENAME']), ['debug_email.php', 'test_mail_debug.php']);
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
require_once __DIR__ . '/sendgrid_helper.php';
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
    
    // Use SendGrid Web API for all outgoing mail
    return sendgrid_send_email($to, $subject, $htmlMessage, $toName);
}
