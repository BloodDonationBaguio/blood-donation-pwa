<?php
// Debug file to check what files exist on the server
header('Content-Type: application/json');

$files_to_check = [
    'test_simple.php',
    'test_stress.php',
    'test_system_load.php', 
    'test_database_comprehensive.php',
    'test_simple_features.php',
    'health.php',
    'connection_diagnostic.php'
];

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_path' => __DIR__,
    'file_status' => []
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    $results['file_status'][$file] = [
        'exists' => file_exists($full_path),
        'readable' => is_readable($full_path),
        'size' => file_exists($full_path) ? filesize($full_path) : 0,
        'full_path' => $full_path
    ];
}

// List all PHP files in directory
$php_files = glob(__DIR__ . '/*.php');
$results['all_php_files'] = array_map('basename', $php_files);

// Check .htaccess
$htaccess_path = __DIR__ . '/.htaccess';
$results['htaccess'] = [
    'exists' => file_exists($htaccess_path),
    'readable' => is_readable($htaccess_path),
    'content_preview' => file_exists($htaccess_path) ? substr(file_get_contents($htaccess_path), 0, 500) : null
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
