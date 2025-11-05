<?php
// Modern dashboard entry point: redirect into router with page=dashboard
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
requireAdminLogin();

header('Location: /admin/?page=dashboard');
exit();
