<?php
// Database Configuration
$dbHost = getenv('DB_HOST');
$dbPort = 5432; // Default PostgreSQL port
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

try {
    $pdo = new PDO("pgsql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// reCAPTCHA Configuration
// For development, you can disable reCAPTCHA by setting ENABLE_RECAPTCHA to false
// For production, set your actual reCAPTCHA v3 site key and secret key
define('ENABLE_RECAPTCHA', false);
define('RECAPTCHA_SITE_KEY', 'YOUR_ACTUAL_SITE_KEY');
define('RECAPTCHA_SECRET_KEY', 'YOUR_ACTUAL_SECRET_KEY');

// reCAPTCHA score threshold (0.0 to 1.0, where 1.0 is very likely a good interaction)
define('RECAPTCHA_SCORE_THRESHOLD', 0.5);
