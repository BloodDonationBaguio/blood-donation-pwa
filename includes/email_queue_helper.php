<?php
/**
 * Email Queue Helper - Fallback when SMTP and mail() fail
 */

function queue_email($to, $subject, $htmlMessage, $toName = '') {
    $queueDir = __DIR__ . '/../email_queue';
    if (!file_exists($queueDir)) {
        @mkdir($queueDir, 0755, true);
    }
    $filename = $queueDir . '/email_' . date('Y-m-d_H-i-s_') . uniqid() . '.json';
    $payload = [
        'to' => $to,
        'to_name' => $toName,
        'subject' => $subject,
        'html' => $htmlMessage,
        'plain' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlMessage)),
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT));
    error_log("Email queued to file: $filename");
    return true;
}

function get_queued_emails() {
    $queueDir = __DIR__ . '/../email_queue';
    $emails = [];
    if (is_dir($queueDir)) {
        foreach (glob($queueDir . '/*.json') as $file) {
            $emails[] = json_decode(file_get_contents($file), true) + ['file' => basename($file)];
        }
    }
    return $emails;
}

function delete_queued_email($filename) {
    $queueDir = __DIR__ . '/../email_queue';
    $filepath = $queueDir . '/' . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}
?>
