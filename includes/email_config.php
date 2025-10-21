<?php
/**
 * Advanced Email Configuration System
 * Production-ready SMTP configuration with multiple providers
 */

class EmailConfig {
    private static $config = null;
    
    public static function getConfig() {
        if (self::$config === null) {
            self::$config = self::loadConfig();
        }
        return self::$config;
    }
    
    private static function loadConfig() {
        $configFile = __DIR__ . '/../config/email_config.json';
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        } else {
            // Default configuration
            $config = [
                'provider' => 'smtp',
                'smtp' => [
                    'host' => 'smtp.gmail.com',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => '',
                    'password' => '',
                    'from_email' => 'prc.baguio.blood@gmail.com',
                    'from_name' => 'Philippine Red Cross - Baguio Chapter'
                ],
                'sendgrid' => [
                    'api_key' => '',
                    'from_email' => 'prc.baguio.blood@gmail.com',
                    'from_name' => 'Philippine Red Cross - Baguio Chapter'
                ],
                'mailgun' => [
                    'api_key' => '',
                    'domain' => '',
                    'from_email' => 'prc.baguio.blood@gmail.com',
                    'from_name' => 'Philippine Red Cross - Baguio Chapter'
                ],
                'backup_providers' => ['sendgrid', 'mailgun'],
                'retry_attempts' => 3,
                'timeout' => 30
            ];
            
            // Save default config
            self::saveConfig($config);
        }
        
        return $config;
    }
    
    public static function saveConfig($config) {
        $configDir = __DIR__ . '/../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        file_put_contents(
            $configDir . '/email_config.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
    
    public static function testConnection() {
        $config = self::getConfig();
        
        try {
            $mailer = self::createMailer();
            $mailer->isSMTP();
            $mailer->Host = $config['smtp']['host'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['smtp']['username'];
            $mailer->Password = $config['smtp']['password'];
            $mailer->SMTPSecure = $config['smtp']['encryption'];
            $mailer->Port = $config['smtp']['port'];
            $mailer->Timeout = $config['timeout'];
            
            $mailer->smtpConnect();
            $mailer->smtpClose();
            
            return ['success' => true, 'message' => 'SMTP connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public static function createMailer() {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        return new PHPMailer\PHPMailer\PHPMailer(true);
    }
}
?>
