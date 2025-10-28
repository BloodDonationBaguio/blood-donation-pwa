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
		@mkdir($logDir, 0755, true);
	}
	
	// Read configuration from environment
	$mailHost   = getenv('MAIL_HOST') ?: 'smtp.sendgrid.net';
	$mailUser   = getenv('MAIL_USER') ?: 'apikey'; // SendGrid requires literal 'apikey'
	$mailPass   = getenv('MAIL_PASS') ?: '';
	$mailPort   = (int)(getenv('MAIL_PORT') ?: 587);
	$mailSecure = strtolower(getenv('MAIL_SECURE') ?: 'tls');
	$fromEmail  = getenv('MAIL_FROM') ?: 'prc.baguio.blood@gmail.com';
	$fromName   = getenv('MAIL_FROM_NAME') ?: 'Blood Donation System';
	
	$useSmtp = !empty($mailPass);
	
	// Attempt SMTP first (preferred)
	if ($useSmtp) {
		try {
			$mail->isSMTP();
			$mail->Host = $mailHost;
			$mail->SMTPAuth = true;
			$mail->Username = $mailUser;
			$mail->Password = $mailPass;
			$mail->Port = $mailPort;
			$mail->SMTPKeepAlive = true;
			$mail->Timeout = 30;
			$mail->Mailer = 'smtp';
			if ($mailSecure === 'tls') {
				$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			} elseif ($mailSecure === 'ssl') {
				$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
			}
			$mail->SMTPOptions = [
				'ssl' => [
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true,
				],
			];
			// Debug to file
			if (is_writable($logDir)) {
				$mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
				$mail->Debugoutput = function($str) use ($debugLog) {
					@file_put_contents($debugLog, date('Y-m-d H:i:s') . ' - ' . trim($str) . "\n", FILE_APPEND);
				};
			}
			
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
			
			$result = $mail->send();
			$logMessage = sprintf('[%s] SMTP %s to %s\n', date('Y-m-d H:i:s'), $result ? 'SENT' : 'FAILED', $to);
			@file_put_contents($result ? $successLog : $errorLog, $logMessage, FILE_APPEND);
			return $result;
		} catch (\Exception $e) {
			@file_put_contents($errorLog, '[' . date('Y-m-d H:i:s') . "] SMTP ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
			// fall through to SendGrid API
		}
	}
	
	// Fallback: SendGrid Web API (requires SENDGRID_API_KEY env)
	return sendgrid_send_email($to, $subject, $htmlMessage, $toName, $fromEmail, $fromName);
}
