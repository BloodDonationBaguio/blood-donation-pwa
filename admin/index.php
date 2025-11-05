<?php
// Modern Admin Router
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
requireAdminLogin();

// Ensure DB available to pages
require_once __DIR__ . '/includes/db.php';

// Determine page to render
$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['page']) : 'dashboard';
$allowedPages = [
    'dashboard',
    'dashboard_new',
    'donors',
    'requests',
    'settings',
];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

// Render common header
require_once __DIR__ . '/includes/header.php';

// Render requested page content
$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (file_exists($pageFile)) {
    require $pageFile;
} else {
    echo '<div class="container mt-4"><div class="alert alert-warning">Page not found.</div></div>';
}

// Render common footer
require_once __DIR__ . '/includes/footer.php';
?>
