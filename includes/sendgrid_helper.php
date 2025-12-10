<?php
// SendGrid API mail helper
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    use SendGrid\Mail\Mail;
}

function sendgrid_send_email($to, $subject, $htmlMessage, $toName = '', $from = null, $fromName = null) {
    // Ensure SendGrid library is available
    if (!class_exists('SendGrid\Mail\Mail')) {
        error_log('SendGrid library not loaded');
        return false;
    }
    $apiKey = getenv('SENDGRID_API_KEY') ?: getenv('MAIL_PASS');
    if (empty($apiKey)) {
        error_log('SendGrid API key missing');
        return false;
    }
    $from = $from ?: (getenv('MAIL_FROM') ?: 'prc.baguio.blood@gmail.com');
    $fromName = $fromName ?: 'Blood Donation System';
    $email = new Mail();
    $email->setFrom($from, $fromName);
    $email->setSubject($subject);
    $email->addTo($to, $toName ?: $to);
    $email->addContent("text/html", $htmlMessage);
    $sendgrid = new \SendGrid($apiKey);
    try {
        $response = $sendgrid->send($email);
        $success = $response->statusCode() >= 200 && $response->statusCode() < 300;
        error_log('SendGrid API response: ' . $response->statusCode());
        return $success;
    } catch (Exception $e) {
        error_log('SendGrid API error: ' . $e->getMessage());
        return false;
    }
}
