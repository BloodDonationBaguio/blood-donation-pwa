<?php
// Database Configuration
define('DB_HOST', 'dpg-d3ugfe3e5dus739m78ng-a');
define('DB_PORT', '5432');
define('DB_NAME', 'blood_system');
define('DB_USER', 'blood_system_user');
define('DB_PASS', 'u4iTxHGbiGRYMflR6nLQPFNG02aL6jTJ'); // Password from Render

// PDO Connection Options
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Create database connection
try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        DB_OPTIONS
    );
} catch (PDOException $e) {
    // Log the actual error for debugging
    error_log("Database connection failed: " . $e->getMessage());
    // Show user-friendly message
    die("Could not connect to the database. Please try again later.");
}

// reCAPTCHA Configuration
// For development, you can disable reCAPTCHA by setting ENABLE_RECAPTCHA to false
// For production, set your actual reCAPTCHA v3 site key and secret key
define('ENABLE_RECAPTCHA', false);
define('RECAPTCHA_SITE_KEY', 'YOUR_ACTUAL_SITE_KEY');
define('RECAPTCHA_SECRET_KEY', 'YOUR_ACTUAL_SECRET_KEY');

// reCAPTCHA score threshold (0.0 to 1.0, where 1.0 is very likely a good interaction)
define('RECAPTCHA_SCORE_THRESHOLD', 0.5);
