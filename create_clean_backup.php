<?php
// Create a clean backup by copying only essential files
$sourceDir = __DIR__;
$backupDir = dirname(__DIR__) . '/blood-donation-pwa-clean-backup';

// Create backup directory
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Files and directories to include
$includePatterns = [
    '*.php',
    '*.html',
    '*.css',
    '*.js',
    '*.json',
    '*.md',
    '*.txt',
    '*.sql',
    '*.htaccess',
    'admin/',
    'assets/',
    'config/',
    'css/',
    'docs/',
    'includes/',
    'logs/',
    'sql/',
    'scripts/',
    'backup/'
];

// Files and directories to exclude
$excludePatterns = [
    'backup_20250930_232703/',
    '*.backup',
    'test_*.php',
    'debug_*.php',
    'temp_*.php',
    '*.tmp',
    '.git/',
    '.vscode/',
    'node_modules/'
];

function shouldExclude($file, $excludePatterns) {
    foreach ($excludePatterns as $pattern) {
        if (fnmatch($pattern, $file) || strpos($file, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

function copyDirectory($src, $dst, $excludePatterns) {
    if (!file_exists($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;
            
            if (shouldExclude($file, $excludePatterns)) {
                echo "Excluding: $file\n";
                continue;
            }
            
            if (is_dir($srcFile)) {
                copyDirectory($srcFile, $dstFile, $excludePatterns);
            } else {
                copy($srcFile, $dstFile);
                echo "Copied: $file\n";
            }
        }
    }
    closedir($dir);
}

echo "Creating clean backup...\n";
copyDirectory($sourceDir, $backupDir, $excludePatterns);

// Create backup info file
$backupInfo = [
    'created_at' => date('Y-m-d H:i:s'),
    'source_directory' => $sourceDir,
    'backup_directory' => $backupDir,
    'excluded_items' => $excludePatterns,
    'total_files' => count(glob($backupDir . '/**/*', GLOB_BRACE))
];

file_put_contents($backupDir . '/backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));

echo "Clean backup created successfully at: $backupDir\n";
echo "Backup info saved to: backup_info.json\n";
?>
