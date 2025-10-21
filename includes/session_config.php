<?php
// Session configuration - must be included before any output
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings for better security and persistence
    if (!headers_sent()) {
        ini_set('session.cookie_lifetime', 86400 * 30); // 30 days
        ini_set('session.gc_maxlifetime', 86400 * 30);   // 30 days
        ini_set('session.cookie_httponly', 1);           // HTTP only cookies
        ini_set('session.use_strict_mode', 1);           // Strict mode
        session_start();
    } else {
        // If headers are already sent, just start session without configuration
        @session_start();
    }
}
?>
