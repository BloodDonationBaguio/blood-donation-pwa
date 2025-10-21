<?php
require_once(__DIR__.'/settings.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Contact Info
    if (isset($_POST['update_contact'])) {
        $settings = get_site_settings();
        $settings['contact']['phone'] = trim($_POST['phone']);
        $settings['contact']['email'] = trim($_POST['email']);
        $settings['contact']['address'] = trim($_POST['address']);
        $settings['contact']['facebook'] = trim($_POST['facebook']);
        save_site_settings($settings);
        header('Location: ../admin-dashboard.php?tab=update-contact&success=1');
        exit();
    }
    // Update Page Content
    if (isset($_POST['update_page'])) {
        $settings = get_site_settings();
        $page = $_POST['page'];
        $content = trim($_POST['content']);
        $settings['pages'][$page] = $content;
        save_site_settings($settings);
        header('Location: ../admin-dashboard.php?tab=manage-pages&success=1');
        exit();
    }
}
?>
