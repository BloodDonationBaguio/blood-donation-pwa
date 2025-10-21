<?php
// Create a simple backup by copying essential files only
$sourceDir = __DIR__;
$backupDir = dirname(__DIR__) . '/blood-donation-pwa-backup-final';

// Create backup directory
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Essential directories to copy
$essentialDirs = [
    'admin',
    'assets', 
    'config',
    'css',
    'docs',
    'includes',
    'logs',
    'sql',
    'scripts',
    'backup'
];

// Essential files to copy
$essentialFiles = [
    'index.php',
    'admin.php',
    'admin-login.php',
    'admin_actions_handler.php',
    'db.php',
    'donor-registration.php',
    'donor-profile.php',
    'track-donor.php',
    'login.php',
    'logout.php',
    'signup.php',
    'profile.php',
    'about.php',
    'find-us.php',
    'thank-you.php',
    'success.php',
    'manifest.json',
    '.htaccess'
];

echo "Creating final backup...\n";

// Copy essential directories
foreach ($essentialDirs as $dir) {
    if (is_dir($sourceDir . '/' . $dir)) {
        $destDir = $backupDir . '/' . $dir;
        if (!file_exists($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        // Copy directory contents
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir . '/' . $dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $backupDir . '/' . $dir . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!file_exists($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
        echo "Copied directory: $dir\n";
    }
}

// Copy essential files
foreach ($essentialFiles as $file) {
    if (file_exists($sourceDir . '/' . $file)) {
        copy($sourceDir . '/' . $file, $backupDir . '/' . $file);
        echo "Copied file: $file\n";
    }
}

// Copy all PHP files in root (excluding problematic ones)
$phpFiles = glob($sourceDir . '/*.php');
foreach ($phpFiles as $file) {
    $filename = basename($file);
    if (!in_array($filename, ['create_clean_backup.php', 'create_simple_backup.php', 'backup_database.php'])) {
        copy($file, $backupDir . '/' . $filename);
        echo "Copied PHP file: $filename\n";
    }
}

// Copy all markdown files
$mdFiles = glob($sourceDir . '/*.md');
foreach ($mdFiles as $file) {
    $filename = basename($file);
    copy($file, $backupDir . '/' . $filename);
    echo "Copied MD file: $filename\n";
}

// Create backup info
$backupInfo = [
    'created_at' => date('Y-m-d H:i:s'),
    'source_directory' => $sourceDir,
    'backup_directory' => $backupDir,
    'database_backup' => 'backup/blood_donation_database_backup_2025-10-19_13-51-39.sql',
    'total_files' => count(glob($backupDir . '/**/*', GLOB_BRACE))
];

file_put_contents($backupDir . '/backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));

echo "Final backup created successfully at: $backupDir\n";
echo "Backup info saved to: backup_info.json\n";
?>
