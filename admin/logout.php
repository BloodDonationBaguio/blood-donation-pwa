<?php
require_once __DIR__ . '/../includes/admin_auth.php';

// Log out the admin
adminLogout();

// Redirect to legacy root-level admin login page with a logout message
header('Location: /admin_login.php?logout=1');
exit();
?>
