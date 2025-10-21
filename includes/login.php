<?php
// Simple login handler - no complex logic
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simple redirect for now
header('Location: ../admin_login.php?error=1');
exit();
?>