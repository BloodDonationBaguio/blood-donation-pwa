<?php
// Plain-text fingerprint endpoint to verify live code state
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$resp = [];

// Basic environment info
$resp['env'] = 'render_docker_apache_php';
$resp['php_version'] = PHP_VERSION;
$resp['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
$resp['request_time'] = date('c');

// Files of interest
$files = [
    'admin.php',
    'includes/admin-tabs.php',
    '.htaccess',
    'Dockerfile',
    'render.yaml',
    'db.php',
    'db_production.php'
];

foreach ($files as $f) {
    $info = [
        'exists' => file_exists($f),
    ];
    if ($info['exists']) {
        $info['size'] = filesize($f);
        $info['mtime'] = @date('c', filemtime($f));
        $info['sha1'] = @sha1_file($f);
    }
    $resp['file'][$f] = $info;
}

// Content markers in admin.php to verify Help & Guide changes
$adminContent = @file_get_contents('admin.php') ?: '';
$markers = [
    'help_section_header' => 'Modern Help & Guide Section',
    'action_center_block' => 'Action Center Quick Actions',
    'action_center_tab'   => 'data-bs-target="#action-center"',
    'help_tabs_id'        => 'id="helpTabs"',
];
foreach ($markers as $key => $needle) {
    $resp['marker'][$key] = ($adminContent && strpos($adminContent, $needle) !== false) ? 'present' : 'missing';
}

// Optional: git commit id if .git is available (may not exist in container)
$gitHead = null;
if (is_dir('.git') && file_exists('.git/HEAD')) {
    $head = trim(@file_get_contents('.git/HEAD'));
    if (strpos($head, 'ref:') === 0) {
        $ref = trim(substr($head, 5));
        $gitHead = @file_get_contents(".git/" . $ref);
    } else {
        $gitHead = $head;
    }
}
$resp['git_head'] = $gitHead ? trim($gitHead) : 'not_available';

// Output
echo "=== Code Fingerprint ===\n";
foreach ($resp as $section => $value) {
    if (is_array($value)) {
        echo "[$section]\n";
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                echo "$k:" . json_encode($v, JSON_UNESCAPED_SLASHES) . "\n";
            } else {
                echo "$k: $v\n";
            }
        }
    } else {
        echo "$section: $value\n";
    }
}
echo "\n";
?>