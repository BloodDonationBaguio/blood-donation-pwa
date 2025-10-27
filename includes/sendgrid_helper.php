<?php
// SendGrid API mail helper
require_once __DIR__ . '/../vendor/autoload.php';
use SendGrid\Mail\Mail;

function sendgrid_send_email($to, $subject, $htmlMessage, $toName = '', $from = null, $fromName = null) {
    $apiKey = getenv('SENDGRID_API_KEY') ?: getenv('MAIL_PASS');
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
        return $response->statusCode() >= 200 && $response->statusCode() < 300;
    } catch (Exception $e) {
        error_log('SendGrid API error: ' . $e->getMessage());
        return false;
    }
}
