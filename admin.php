<?php
// Admin entry now routes to the modern admin dashboard
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
requireAdminLogin();

header('Location: /admin/?page=dashboard');
exit();
