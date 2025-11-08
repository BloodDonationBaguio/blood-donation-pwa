<?php
// Direct include of includes/admin-tabs.php to isolate rendering
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/utils.php';
t_section('Admin Tabs Direct Render (pending-donors)');

$activeTab = 'pending-donors';
$pendingDonors = [];

// Try donors_new first, then donors; use simple pending condition
try {
    $pendingDonors = $pdo->query("SELECT * FROM donors_new WHERE status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review') LIMIT 10")->fetchAll();
} catch (Throwable $e) {
    // fallback
}
if (empty($pendingDonors)) {
    try {
        $pendingDonors = $pdo->query("SELECT * FROM donors WHERE status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review') LIMIT 10")->fetchAll();
    } catch (Throwable $e) {
        // fallback
    }
}

// If still empty, provide stub donor to validate markup path
if (empty($pendingDonors)) {
    $pendingDonors = [
        [
            'id' => 0,
            'reference' => 'STUB-REF',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => 'N/A',
            'age' => 0,
            'sex' => 'N/A',
            'weight' => 0,
            'height' => 0,
            'blood_type' => 'Unknown',
            'created_at' => date('Y-m-d H:i:s'),
        ]
    ];
}

// Render include
$_GET['donor_search'] = '';
ob_start();
include __DIR__ . '/../includes/admin-tabs.php';
$html = ob_get_clean();

$len = strlen($html);
t_pass("admin-tabs.php html length=$len");
t_assert(stripos($html, 'Pending Donor Applications') !== false, 'Contains Pending Donor Applications header');

echo $t_output;
echo "<hr><pre>" . htmlspecialchars(substr($html, 0, 2000)) . "</pre>";
?>