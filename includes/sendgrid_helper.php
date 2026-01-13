<?php
// Set timezone to Baguio, Philippines
require_once __DIR__ . '/../config/timezone.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function sendgrid_send_email($to, $subject, $htmlMessage, $toName = '', $from = null, $fromName = null) {
    $apiKey = getenv('SENDGRID_API_KEY') ?: getenv('MAIL_PASS');
    if (empty($apiKey)) {
        return false;
    }
    $from = $from ?: (getenv('MAIL_FROM') ?: 'prc.baguio.blood@gmail.com');
    $fromName = $fromName ?: 'Blood Donation System';

    if (class_exists('SendGrid\Mail\Mail')) {
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($from, $fromName);
        $email->setSubject($subject);
        $email->addTo($to, $toName ?: $to);
        $email->addContent('text/html', $htmlMessage);
        $sendgrid = new \SendGrid($apiKey);
        try {
            $response = $sendgrid->send($email);
            return $response->statusCode() >= 200 && $response->statusCode() < 300;
        } catch (Exception $e) {
            return false;
        }
    }

    $payload = [
        'personalizations' => [[
            'to' => [['email' => $to, 'name' => ($toName ?: $to)]],
            'subject' => $subject,
        ]],
        'from' => ['email' => $from, 'name' => $fromName],
        'content' => [[
            'type' => 'text/html',
            'value' => $htmlMessage,
        ]],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
