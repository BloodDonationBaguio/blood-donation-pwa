<?php
// Deprecated modern admin dashboard
// Redirect all traffic to the legacy root-level admin dashboard
session_start();
require_once dirname(__DIR__) . '/includes/admin_auth.php';
requireAdminLogin();

header('Location: /admin.php');
exit();
