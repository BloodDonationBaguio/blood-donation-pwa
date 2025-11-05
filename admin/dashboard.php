<?php
// Admin Dashboard (include-based layout)
// Ensures auth, sets active page, and renders header/page/footer
session_start();
require_once dirname(__DIR__) . '/includes/admin_auth.php';
requireAdminLogin();

// Used by header to highlight active menu item
$page = 'dashboard';

// Load admin DB helpers before rendering header (for badge counts, etc.)
require_once __DIR__ . '/includes/db.php';

// Render standard admin layout and page content
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/pages/dashboard.php';
require_once __DIR__ . '/includes/footer.php';
