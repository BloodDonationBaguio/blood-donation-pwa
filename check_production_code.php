<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

header('Content-Type: text/plain');
echo "=== PRODUCTION CODE CHECK ===\n\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

$files = [
    'admin.php',
    'donor-registration.php',
    'process_manual_donor.php',
    'includes/medical_section.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "$file:\n";
        echo "  Last Modified: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
        echo "  Size: " . filesize($file) . " bytes\n";
        $content = @file_get_contents($file) ?: '';
        if ($file === 'admin.php') {
            echo "  Contains 'manual-register': " . (strpos($content, 'manual-register') !== false ? 'YES' : 'NO') . "\n";
            echo "  Contains medical_section include: " . (strpos($content, 'includes/medical_section.php') !== false ? 'YES' : 'NO') . "\n";
        }
        echo "\n";
    } else {
        echo "$file: NOT FOUND\n\n";
    }
}

echo "OPcache Status:\n";
if (function_exists('opcache_get_status')) {
    $status = @opcache_get_status();
    if ($status) {
        echo "  Enabled: " . ($status['opcache_enabled'] ? 'YES' : 'NO') . "\n";
        echo "  Cached scripts: " . (isset($status['scripts']) ? count($status['scripts']) : 0) . "\n";
    } else {
        echo "  Status unavailable\n";
    }
} else {
    echo "  OPcache not available\n";
}
?>