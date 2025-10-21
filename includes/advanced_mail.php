<?php
/**
 * Advanced Email System with Multiple Providers
 * Production-ready email delivery with failover support
 */

require_once __DIR__ . '/email_config.php';

class AdvancedMail {
    private $config;
    private $primaryProvider;
    private $backupProviders;
    
    public function __construct() {
        $this->config = EmailConfig::getConfig();
        $this->primaryProvider = $this->config['provider'];
        $this->backupProviders = $this->config['backup_providers'] ?? [];
    }
    
    public function sendEmail($to, $subject, $body, $isHTML = true, $attachments = []) {
        $providers = array_merge([$this->primaryProvider], $this->backupProviders);
        
        foreach ($providers as $provider) {
            try {
                $result = $this->sendWithProvider($provider, $to, $subject, $body, $isHTML, $attachments);
                if ($result['success']) {
                    $this->logEmail($to, $subject, $provider, 'success');
                    return $result;
                }
            } catch (Exception $e) {
                $this->logEmail($to, $subject, $provider, 'error', $e->getMessage());
                continue;
            }
        }
        
        return ['success' => false, 'message' => 'All email providers failed'];
    }
    
    private function sendWithProvider($provider, $to, $subject, $body, $isHTML, $attachments = []) {
        switch ($provider) {
            case 'smtp':
                return $this->sendWithSMTP($to, $subject, $body, $isHTML, $attachments);
            case 'sendgrid':
                return $this->sendWithSendGrid($to, $subject, $body, $isHTML, $attachments);
            case 'mailgun':
                return $this->sendWithMailgun($to, $subject, $body, $isHTML, $attachments);
            default:
                throw new Exception("Unknown email provider: $provider");
        }
    }
    
    private function sendWithSMTP($to, $subject, $body, $isHTML, $attachments = []) {
        $mailer = EmailConfig::createMailer();
        $smtpConfig = $this->config['smtp'];
        
        $mailer->isSMTP();
        $mailer->Host = $smtpConfig['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $smtpConfig['username'];
        $mailer->Password = $smtpConfig['password'];
        $mailer->SMTPSecure = $smtpConfig['encryption'];
        $mailer->Port = $smtpConfig['port'];
        $mailer->Timeout = $this->config['timeout'];
        
        $mailer->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        
        if ($isHTML) {
            $mailer->isHTML(true);
            $mailer->Body = $body;
        } else {
            $mailer->isHTML(false);
            $mailer->Body = strip_tags($body);
        }
        
        // Add attachments
        foreach ($attachments as $attachment) {
            $mailer->addAttachment($attachment['path'], $attachment['name']);
        }
        
        $mailer->send();
        return ['success' => true, 'provider' => 'smtp'];
    }
    
    private function sendWithSendGrid($to, $subject, $body, $isHTML, $attachments = []) {
        $sendgridConfig = $this->config['sendgrid'];
        
        if (empty($sendgridConfig['api_key'])) {
            throw new Exception('SendGrid API key not configured');
        }
        
        $data = [
            'personalizations' => [
                [
                    'to' => [['email' => $to]],
                    'subject' => $subject
                ]
            ],
            'from' => [
                'email' => $sendgridConfig['from_email'],
                'name' => $sendgridConfig['from_name']
            ],
            'content' => [
                [
                    'type' => $isHTML ? 'text/html' : 'text/plain',
                    'value' => $body
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $sendgridConfig['api_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'provider' => 'sendgrid'];
        } else {
            throw new Exception("SendGrid API error: HTTP $httpCode - $response");
        }
    }
    
    private function sendWithMailgun($to, $subject, $body, $isHTML, $attachments = []) {
        $mailgunConfig = $this->config['mailgun'];
        
        if (empty($mailgunConfig['api_key']) || empty($mailgunConfig['domain'])) {
            throw new Exception('Mailgun API key or domain not configured');
        }
        
        $data = [
            'from' => $mailgunConfig['from_name'] . ' <' . $mailgunConfig['from_email'] . '>',
            'to' => $to,
            'subject' => $subject,
            'html' => $isHTML ? $body : null,
            'text' => $isHTML ? strip_tags($body) : $body
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/{$mailgunConfig['domain']}/messages");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERPWD, "api:{$mailgunConfig['api_key']}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'provider' => 'mailgun'];
        } else {
            throw new Exception("Mailgun API error: HTTP $httpCode - $response");
        }
    }
    
    private function logEmail($to, $subject, $provider, $status, $error = null) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to,
            'subject' => $subject,
            'provider' => $provider,
            'status' => $status,
            'error' => $error
        ];
        
        $logFile = __DIR__ . '/../logs/email_delivery.log';
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    public function getDeliveryStats() {
        $logFile = __DIR__ . '/../logs/email_delivery.log';
        if (!file_exists($logFile)) {
            return ['total' => 0, 'success' => 0, 'failed' => 0, 'providers' => []];
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'providers' => []];
        
        foreach ($logs as $log) {
            $entry = json_decode($log, true);
            if ($entry) {
                $stats['total']++;
                if ($entry['status'] === 'success') {
                    $stats['success']++;
                } else {
                    $stats['failed']++;
                }
                
                $provider = $entry['provider'];
                if (!isset($stats['providers'][$provider])) {
                    $stats['providers'][$provider] = ['success' => 0, 'failed' => 0];
                }
                $stats['providers'][$provider][$entry['status']]++;
            }
        }
        
        return $stats;
    }
}
?>
