<?php
header('Content-Type: text/plain');
echo "=== WHOAMI ===\n\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a') . "\n";
echo "SCRIPT_FILENAME (this): " . ($_SERVER['SCRIPT_FILENAME'] ?? 'n/a') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'n/a') . "\n";
echo "CWD: " . __DIR__ . "\n\n";
$files = [
  'admin.php' => __DIR__ . '/admin.php',
  'includes/admin/tabs/manual-register.php' => __DIR__ . '/includes/admin/tabs/manual-register.php',
  'includes/admin/tabs/add-donor.php' => __DIR__ . '/includes/admin/tabs/add-donor.php',
  'donor-registration.php' => __DIR__ . '/donor-registration.php',
  'process_manual_donor.php' => __DIR__ . '/process_manual_donor.php',
  'includes/medical_section.php' => __DIR__ . '/includes/medical_section.php'
];
foreach ($files as $label => $path) {
  $real = realpath($path);
  echo "$label:\n";
  echo "  Path: $path\n";
  echo "  Realpath: " . ($real ?: 'NOT FOUND') . "\n";
  if ($real && file_exists($real)) {
    echo "  Last Modified: " . date('Y-m-d H:i:s', filemtime($real)) . "\n";
    echo "  Size: " . filesize($real) . " bytes\n";
  }
  echo "\n";
}
echo "OPcache:\n";
if (function_exists('opcache_get_status')) {
  $status = @opcache_get_status();
  echo "  Enabled: " . (($status && $status['opcache_enabled']) ? 'YES' : 'NO') . "\n";
  echo "  Cached scripts: " . (($status && isset($status['scripts'])) ? count($status['scripts']) : 0) . "\n";
} else {
  echo "  Not available\n";
}
echo "\n";