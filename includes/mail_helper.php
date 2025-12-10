<?php
require_once __DIR__ . '/phpmailer_loader.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/email_queue_helper.php';
// Enable error reporting for debugging (log only, no display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Force email runtime timezone to Asia/Manila to ensure correct headers/content
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Manila');
}

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
 * Falls back to PHP native mail, then SendGrid API, then local queue.
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlMessage HTML formatted message
 * @param string $toName Optional recipient name
 * @return bool True if email was sent successfully, false otherwise
 */
function send_confirmation_email($to, $subject, $htmlMessage, $toName = '') {
	// Set up logging
	$logDir = __DIR__ . '/../logs';
	$errorLog = $logDir . '/email_errors.log';
	$successLog = $logDir . '/email_success.log';
	if (!file_exists($logDir)) {
		@mkdir($logDir, 0755, true);
	}
	
	// Read configuration from environment
	$fromEmail  = getenv('MAIL_FROM') ?: 'prc.baguio.blood@gmail.com';
	$fromName   = getenv('MAIL_FROM_NAME') ?: 'Blood Donation System';
	
	// Primary: SendGrid API
	if (file_exists(__DIR__ . '/sendgrid_helper.php') && function_exists('sendgrid_send_email')) {
		try {
			$sgSuccess = sendgrid_send_email($to, $subject, $htmlMessage, $toName, $fromEmail, $fromName);
			if ($sgSuccess) {
				@file_put_contents($successLog, date('Y-m-d H:i:s') . " - SendGrid API SENT to $to\n", FILE_APPEND);
				return true;
			} else {
				@file_put_contents($errorLog, date('Y-m-d H:i:s') . " - SendGrid API FAILED to $to\n", FILE_APPEND);
			}
		} catch (Exception $e) {
			@file_put_contents($errorLog, date('Y-m-d H:i:s') . " - SendGrid API Exception to $to: " . $e->getMessage() . "\n", FILE_APPEND);
		}
	}
	
	// Fallback: SMTP
	$mailHost   = getenv('MAIL_HOST') ?: 'smtp.sendgrid.net';
	$mailUser   = getenv('MAIL_USER') ?: 'apikey';
	$mailPass   = getenv('MAIL_PASS') ?: '';
	$mailPort   = (int)(getenv('MAIL_PORT') ?: 587);
	$mailSecure = strtolower(getenv('MAIL_SECURE') ?: 'tls');
	
	if (!empty($mailPass)) {
		try {
			$mail = new PHPMailer\PHPMailer\PHPMailer(true);
			$mail->isSMTP();
			$mail->Timeout = 5;
			$mail->Host = $mailHost;
			$mail->SMTPAuth = true;
			$mail->Username = $mailUser;
			$mail->Password = $mailPass;
			$mail->Port = $mailPort;
			$mail->SMTPSecure = $mailSecure === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
			$mail->SMTPOptions = [
				'ssl' => [
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true,
				],
			];
			$mail->setFrom($fromEmail, $fromName);
			if (!empty($toName)) {
				$mail->addAddress($to, $toName);
			} else {
				$mail->addAddress($to);
			}
			$mail->addReplyTo($fromEmail, $fromName);
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body = $htmlMessage;
			$mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlMessage));
			
			if ($mail->send()) {
				@file_put_contents($successLog, date('Y-m-d H:i:s') . " - SMTP SENT to $to\n", FILE_APPEND);
				return true;
			}
		} catch (Exception $e) {
			@file_put_contents($errorLog, date('Y-m-d H:i:s') . " - SMTP Exception to $to: " . $e->getMessage() . "\n", FILE_APPEND);
		}
	}
	
	// Fallback to PHP native mail
	$headers = "From: $fromName <$fromEmail>\r\n";
	$headers .= "Reply-To: $fromName <$fromEmail>\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
	$plainBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlMessage));
	$subjectPlain = html_entity_decode($subject, ENT_QUOTES, 'UTF-8');
	
	$success = mail($to, $subjectPlain, $plainBody, $headers);
	@file_put_contents($success ? $successLog : $errorLog, date('Y-m-d H:i:s') . " - PHP mail " . ($success ? 'SENT' : 'FAILED') . " to $to\n", FILE_APPEND);
	if ($success) {
		return true;
	}
	
	// Final fallback: queue to local file
	$queued = queue_email($to, $subject, $htmlMessage, $toName);
	if ($queued) {
		@file_put_contents($errorLog, date('Y-m-d H:i:s') . " - QUEUED to file for $to\n", FILE_APPEND);
		return true; // treat as success so registration continues
	}
	
	return false;
}
