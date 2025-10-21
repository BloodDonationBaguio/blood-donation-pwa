<?php
/**
 * Local Development SSL Configuration
 * Disables SSL for localhost development
 */

// Check if we're on localhost
$isLocalhost = (
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
    strpos($_SERVER['HTTP_HOST'], 'xampp') !== false
);

if ($isLocalhost) {
    // Override SSL config for localhost
    $sslConfig = [
        'enabled' => false,
        'force_https' => false,
        'hsts_enabled' => false,
        'security_headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ]
    ];
    
    // Apply localhost-specific settings
    if (!headers_sent()) {
        foreach ($sslConfig['security_headers'] as $header => $value) {
            header("$header: $value");
        }
    }
}
?>
