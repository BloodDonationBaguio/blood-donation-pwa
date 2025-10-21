<?php
require_once __DIR__ . '/../includes/admin_auth.php';

// Log out the admin
adminLogout();

// Redirect to admin login page with a logout message
header('Location: /blood-donation-pwa/admin_login.php?logout=1');
exit();
?>
