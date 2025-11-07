<?php
// Admin - Tabs-based original layout using includes/admin-tabs.php
session_start();
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'admin_auth.php';

// Enforce admin authentication
requireAdminLogin();

// Determine active tab from query, default to blood-requests
$activeTab = $_GET['tab'] ?? 'blood-requests';

// Prepare lightweight data containers expected by admin-tabs
$pendingDonors = [];
$donors = [];
$requests = [];

// Make variables available to included file via $GLOBALS
$GLOBALS['activeTab'] = $activeTab;
$GLOBALS['pendingDonors'] = $pendingDonors;
$GLOBALS['donors'] = $donors;
$GLOBALS['requests'] = $requests;

// Render with site header/footer and the admin tabs content
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';

// Simple tabs navbar
echo '<div class="container">';
echo '<ul class="nav nav-tabs mb-3">';
// Dashboard removed from navigation; keep accessible only via direct URL if needed
// echo '<li class="nav-item"><a class="nav-link ' . ($activeTab==='dashboard'?'active':'') . '" href="/admin.php?tab=dashboard">Dashboard</a></li>';
echo '<li class="nav-item"><a class="nav-link ' . ($activeTab==='blood-requests'?'active':'') . '" href="/admin.php?tab=blood-requests">Blood Requests</a></li>';
echo '<li class="nav-item"><a class="nav-link ' . ($activeTab==='add-donor'?'active':'') . '" href="/admin.php?tab=add-donor">Add Donor</a></li>';
echo '<li class="nav-item"><a class="nav-link ' . ($activeTab==='manage-pages'?'active':'') . '" href="/admin.php?tab=manage-pages">Manage Pages</a></li>';
echo '<li class="nav-item"><a class="nav-link ' . ($activeTab==='donor-matching'?'active':'') . '" href="/admin.php?tab=donor-matching">Donor Matching</a></li>';
echo '<li class="nav-item ms-auto"><a class="nav-link" href="/admin_logout.php">Logout</a></li>';
echo '</ul>';

// Include the original tabs-based admin content
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'admin-tabs.php';

echo '</div>';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php';

