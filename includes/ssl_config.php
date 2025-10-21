<?php
/**
 * SSL/HTTPS Configuration System
 * Production-ready SSL setup with security headers
 */

class SSLConfig {
    private static $config = null;
    
    public static function getConfig() {
        if (self::$config === null) {
            self::$config = self::loadConfig();
        }
        return self::$config;
    }
    
    private static function loadConfig() {
        $configFile = __DIR__ . '/../config/ssl_config.json';
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        } else {
            // Default SSL configuration
            $config = [
                'enabled' => false,
                'certificate_path' => '',
                'private_key_path' => '',
                'ca_bundle_path' => '',
                'force_https' => true,
                'hsts_enabled' => true,
                'hsts_max_age' => 31536000, // 1 year
                'security_headers' => [
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'DENY',
                    'X-XSS-Protection' => '1; mode=block',
                    'Referrer-Policy' => 'strict-origin-when-cross-origin',
                    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
                ],
                'csp_enabled' => true,
                'csp_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';"
            ];
            
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
            $configDir . '/ssl_config.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
    
    public static function enforceHTTPS() {
        $config = self::getConfig();
        
        if (!$config['enabled'] || !$config['force_https']) {
            return;
        }
        
        // Force HTTPS redirect
        if (!self::isHTTPS()) {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit();
        }
    }
    
    public static function isHTTPS() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        );
    }
    
    public static function setSecurityHeaders() {
        $config = self::getConfig();
        
        if (!$config['enabled']) {
            return;
        }
        
        // HSTS Header
        if ($config['hsts_enabled']) {
            header("Strict-Transport-Security: max-age=" . $config['hsts_max_age'] . "; includeSubDomains; preload");
        }
        
        // Content Security Policy
        if ($config['csp_enabled']) {
            header("Content-Security-Policy: " . $config['csp_policy']);
        }
        
        // Security Headers
        foreach ($config['security_headers'] as $header => $value) {
            header("$header: $value");
        }
    }
    
    public static function testSSL() {
        $config = self::getConfig();
        
        if (!$config['enabled']) {
            return ['success' => false, 'message' => 'SSL not enabled'];
        }
        
        $tests = [];
        
        // Test certificate files
        if (!empty($config['certificate_path'])) {
            $tests['certificate'] = file_exists($config['certificate_path']);
        }
        
        if (!empty($config['private_key_path'])) {
            $tests['private_key'] = file_exists($config['private_key_path']);
        }
        
        // Test HTTPS detection
        $tests['https_detection'] = self::isHTTPS();
        
        // Test security headers
        $tests['security_headers'] = self::checkSecurityHeaders();
        
        $allPassed = array_reduce($tests, function($carry, $test) {
            return $carry && $test;
        }, true);
        
        return [
            'success' => $allPassed,
            'tests' => $tests,
            'message' => $allPassed ? 'SSL configuration is valid' : 'SSL configuration has issues'
        ];
    }
    
    private static function checkSecurityHeaders() {
        $headers = headers_list();
        $requiredHeaders = ['Strict-Transport-Security', 'X-Content-Type-Options', 'X-Frame-Options'];
        
        foreach ($requiredHeaders as $header) {
            $found = false;
            foreach ($headers as $sentHeader) {
                if (stripos($sentHeader, $header) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function generateHTAccess() {
        $config = self::getConfig();
        
        $htaccess = "# Blood Donation System - Security Configuration\n\n";
        
        if ($config['enabled'] && $config['force_https']) {
            $htaccess .= "# Force HTTPS\n";
            $htaccess .= "RewriteEngine On\n";
            $htaccess .= "RewriteCond %{HTTPS} off\n";
            $htaccess .= "RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n\n";
        }
        
        $htaccess .= "# Security Headers\n";
        foreach ($config['security_headers'] as $header => $value) {
            $htaccess .= "Header always set $header \"$value\"\n";
        }
        
        if ($config['hsts_enabled']) {
            $htaccess .= "Header always set Strict-Transport-Security \"max-age=" . $config['hsts_max_age'] . "; includeSubDomains; preload\"\n";
        }
        
        if ($config['csp_enabled']) {
            $htaccess .= "Header always set Content-Security-Policy \"" . $config['csp_policy'] . "\"\n";
        }
        
        $htaccess .= "\n# File Protection\n";
        $htaccess .= "<Files \"*.json\">\n";
        $htaccess .= "    Order Allow,Deny\n";
        $htaccess .= "    Deny from all\n";
        $htaccess .= "</Files>\n\n";
        
        $htaccess .= "<Files \"*.log\">\n";
        $htaccess .= "    Order Allow,Deny\n";
        $htaccess .= "    Deny from all\n";
        $htaccess .= "</Files>\n";
        
        return $htaccess;
    }
}
?>
