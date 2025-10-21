<?php
/**
 * System File Cleanup Analysis
 * Identifies unnecessary files that can be safely deleted
 */

echo "🧹 SYSTEM FILE CLEANUP ANALYSIS\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$essentialFiles = [
    // Core system files
    'index.php', 'db.php', 'navbar.php',
    
    // User-facing pages
    'login.php', 'signup.php', 'dashboard.php', 'profile.php',
    'donor-registration.php', 'logout.php',
    
    // Admin pages
    'admin.php', 'admin-login.php', 'admin_blood_inventory.php', 
    'admin_enhanced_donor_management.php',
    
    // API endpoints
    'get_blood_unit.php', 'get_medical_screening.php',
    'simple_ajax_donor_details.php', 'mark_notification.php',
    
    // Includes (essential functionality)
    'includes/BloodInventoryManager.php',
    'includes/enhanced_donor_management.php',
    'includes/mail.php',
    'includes/session_manager.php',
    'includes/email_queue.php',
    
    // Admin includes
    'admin/includes/admin_auth.php',
    
    // SQL files
    'sql/blood_inventory_migration.sql',
    'sql/blood_inventory_simple.sql',
    
    // Configuration
    '.htaccess',
    
    // Documentation (keep important ones)
    'BLOOD_INVENTORY_SYSTEM.md',
    'REAL_DONOR_SYSTEM_SUMMARY.md',
    'SYSTEM_STATUS_REPORT.md'
];

$testFiles = [
    // Test and debug files
    'test.php', 'debug.php', 'test_connection.php',
    'test_db.php', 'test_email.php', 'test_session.php',
    
    // Migration and setup files (temporary)
    'migrate_add_reset_token.php',
    'create_tables.php', 'setup.php',
    
    // Backup and temporary files
    'backup.php', 'temp.php', 'tmp.php',
    
    // Old/unused files
    'old_index.php', 'index_old.php',
    'admin_old.php', 'old_admin.php'
];

$temporaryFiles = [
    // Log files
    '*.log', 'error.log', 'access.log',
    
    // Cache files
    '*.cache', 'cache/*',
    
    // Temporary uploads
    'uploads/temp/*', 'temp/*',
    
    // Session files
    'sessions/*', 'tmp/*'
];

function scanDirectory($dir = '.', $level = 0) {
    $files = [];
    $indent = str_repeat('  ', $level);
    
    if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $fullPath = $dir . '/' . $file;
                $relativePath = ($dir == '.') ? $file : $fullPath;
                
                if (is_dir($fullPath)) {
                    echo $indent . "📁 $file/\n";
                    $files = array_merge($files, scanDirectory($fullPath, $level + 1));
                } else {
                    $size = filesize($fullPath);
                    $sizeStr = formatBytes($size);
                    $modified = date('Y-m-d H:i', filemtime($fullPath));
                    echo $indent . "📄 $file ($sizeStr) - $modified\n";
                    
                    $files[] = [
                        'path' => $relativePath,
                        'name' => $file,
                        'size' => $size,
                        'modified' => filemtime($fullPath),
                        'dir' => $dir
                    ];
                }
            }
        }
        closedir($handle);
    }
    
    return $files;
}

function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return round($size, 1) . ' ' . $units[$unit];
}

function analyzeFile($file, $essentialFiles, $testFiles) {
    $path = $file['path'];
    $name = $file['name'];
    
    // Check if essential
    if (in_array($path, $essentialFiles)) {
        return 'essential';
    }
    
    // Check if test file
    if (in_array($name, $testFiles)) {
        return 'test';
    }
    
    // Pattern matching for unnecessary files
    $unnecessaryPatterns = [
        '/^test_.*\.php$/' => 'test',
        '/^debug.*\.php$/' => 'debug',
        '/^temp.*\.php$/' => 'temporary',
        '/^backup.*\.php$/' => 'backup',
        '/^old_.*\.php$/' => 'old',
        '/.*_old\.php$/' => 'old',
        '/.*_backup\.php$/' => 'backup',
        '/.*_test\.php$/' => 'test',
        '/^check_.*\.php$/' => 'diagnostic',
        '/^verify_.*\.php$/' => 'diagnostic',
        '/^analyze_.*\.php$/' => 'diagnostic',
        '/^scan_.*\.php$/' => 'diagnostic',
        '/^cleanup_.*\.php$/' => 'diagnostic',
        '/\.log$/' => 'log',
        '/\.tmp$/' => 'temporary',
        '/\.bak$/' => 'backup',
        '/~$/' => 'temporary',
        '/\.swp$/' => 'temporary'
    ];
    
    foreach ($unnecessaryPatterns as $pattern => $type) {
        if (preg_match($pattern, $name)) {
            return $type;
        }
    }
    
    // Check file content for test/debug indicators
    if (pathinfo($name, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($path);
        if (strpos($content, 'test_') === 0 || 
            strpos($content, 'debug') !== false ||
            strpos($content, 'TODO: DELETE') !== false ||
            strpos($content, 'TEMPORARY') !== false) {
            return 'suspicious';
        }
    }
    
    return 'unknown';
}

echo "CURRENT DIRECTORY STRUCTURE:\n";
echo str_repeat("-", 50) . "\n";

$allFiles = scanDirectory();

echo "\n\nFILE ANALYSIS:\n";
echo str_repeat("=", 50) . "\n";

$categories = [
    'essential' => [],
    'test' => [],
    'debug' => [],
    'temporary' => [],
    'backup' => [],
    'old' => [],
    'diagnostic' => [],
    'log' => [],
    'suspicious' => [],
    'unknown' => []
];

$totalSize = 0;
$deletableSize = 0;

foreach ($allFiles as $file) {
    $category = analyzeFile($file, $essentialFiles, $testFiles);
    $categories[$category][] = $file;
    $totalSize += $file['size'];
    
    if ($category !== 'essential' && $category !== 'unknown') {
        $deletableSize += $file['size'];
    }
}

foreach ($categories as $category => $files) {
    if (empty($files)) continue;
    
    $categorySize = array_sum(array_column($files, 'size'));
    $count = count($files);
    
    echo "\n" . strtoupper($category) . " FILES ($count files, " . formatBytes($categorySize) . "):\n";
    echo str_repeat("-", 30) . "\n";
    
    foreach ($files as $file) {
        $sizeStr = formatBytes($file['size']);
        $status = ($category === 'essential') ? '✅ KEEP' : '🗑️ DELETE';
        echo "$status {$file['path']} ($sizeStr)\n";
    }
}

echo "\n\nCLEANUP SUMMARY:\n";
echo str_repeat("=", 50) . "\n";
echo "Total files: " . count($allFiles) . "\n";
echo "Total size: " . formatBytes($totalSize) . "\n";
echo "Essential files: " . count($categories['essential']) . "\n";
echo "Deletable files: " . (count($allFiles) - count($categories['essential']) - count($categories['unknown'])) . "\n";
echo "Space to free: " . formatBytes($deletableSize) . "\n";
echo "Unknown files (manual review): " . count($categories['unknown']) . "\n";

echo "\n\nRECOMMENDED ACTIONS:\n";
echo str_repeat("-", 30) . "\n";
echo "✅ SAFE TO DELETE:\n";
foreach (['test', 'debug', 'temporary', 'backup', 'old', 'diagnostic', 'log'] as $cat) {
    if (!empty($categories[$cat])) {
        echo "   • " . count($categories[$cat]) . " $cat files\n";
    }
}

if (!empty($categories['suspicious'])) {
    echo "\n⚠️ REVIEW BEFORE DELETING:\n";
    foreach ($categories['suspicious'] as $file) {
        echo "   • {$file['path']}\n";
    }
}

if (!empty($categories['unknown'])) {
    echo "\n❓ MANUAL REVIEW NEEDED:\n";
    foreach ($categories['unknown'] as $file) {
        echo "   • {$file['path']}\n";
    }
}

echo "\nAnalysis completed at: " . date('Y-m-d H:i:s') . "\n";
?>
