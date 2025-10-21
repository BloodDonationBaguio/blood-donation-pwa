<?php
// Backup and optional cleanup utility
// Usage: php -f includes/backup_and_cleanup.php -- db=blood_donation_db dest=C:\\backups\\blood-donation-pwa cleanup=1

ini_set('display_errors', 0);
error_reporting(E_ALL);

function getArg(string $key, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $key.'=') === 0 || strpos($arg, '--'.$key.'=') === 0) {
            $parts = explode('=', $arg, 2);
            return $parts[1] ?? $default;
        }
    }
    return $default;
}

$projectRoot = realpath(__DIR__ . '/..');
$dbName = getArg('db', 'blood_donation_db');
$dbUser = getArg('user', 'root');
$dbPass = getArg('pass', '');
$backupBase = getArg('dest', 'C:\\backups\\blood-donation-pwa');
$doCleanup = in_array('cleanup=1', $argv, true) || in_array('--cleanup=1', $argv, true) || getArg('cleanup') === '1';

$ts = date('Ymd_His');
$backupDir = rtrim($backupBase, "\\/") . DIRECTORY_SEPARATOR . $ts;
if (!is_dir($backupBase) && !@mkdir($backupBase, 0755, true)) {
    fwrite(STDERR, "Failed to create backup base directory: {$backupBase}\n");
    exit(1);
}
if (!@mkdir($backupDir, 0755, true)) {
    fwrite(STDERR, "Failed to create backup directory: {$backupDir}\n");
    exit(1);
}

// 1) Zip project files
$zipPath = $backupDir . DIRECTORY_SEPARATOR . 'files_' . $ts . '.zip';
// Prefer ZipArchive; if unavailable, fall back to naive copy of directory
$zipOk = class_exists('ZipArchive');
if ($zipOk) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        $zipOk = false;
    }
}

$excludeDirs = [
    '.git', 'node_modules', 'vendor', 'storage\logs'
];
// Avoid backing up external backups to keep size down
$excludePatterns = [
    '#(^|\\\/)backup_\d{8}_\d{6}($|\\\/)#i',
];

$rootLen = strlen($projectRoot) + 1;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    $path = $file->getPathname();
    $rel = substr($path, $rootLen);
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    if (isset($parts[0]) && in_array($parts[0], $excludeDirs, true)) { continue; }
    $skip = false;
    foreach ($excludePatterns as $pat) { if (preg_match($pat, $rel)) { $skip = true; break; } }
    if ($skip) { continue; }
    if ($file->isFile()) {
        if ($zipOk) {
            $zip->addFile($path, $rel);
        } else {
            // Fallback: copy files into a mirror directory inside backupDir
            $mirrorBase = $backupDir . DIRECTORY_SEPARATOR . 'files_' . $ts;
            $target = $mirrorBase . DIRECTORY_SEPARATOR . $rel;
            $dir = dirname($target);
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            @copy($path, $target);
        }
    }
}
if ($zipOk) { $zip->close(); }

// 2) DB dump using mysqldump
$dumpPath = $backupDir . DIRECTORY_SEPARATOR . 'db_' . $ts . '.sql';
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$cmd = null;
if (file_exists($mysqldump)) {
    $auth = "-u{$dbUser}" . ($dbPass !== '' ? " -p{$dbPass}" : '');
    $cmd = "\"{$mysqldump}\" {$auth} --host=127.0.0.1 --port=3306 --databases \"{$dbName}\"";
} else {
    // Try PATH fallback
    $cmd = "mysqldump -u{$dbUser}" . ($dbPass !== '' ? " -p{$dbPass}" : '') . " --host=127.0.0.1 --port=3306 --databases \"{$dbName}\"";
}

// Capture stdout to file robustly
$descriptorspec = [
    1 => ['file', $dumpPath, 'w'],
    2 => ['pipe', 'w'],
];
$proc = proc_open($cmd, $descriptorspec, $pipes, $projectRoot);
if (is_resource($proc)) {
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    if ($exitCode !== 0) {
        file_put_contents($dumpPath, "-- mysqldump failed or not found. Error: \n{$stderr}\n", FILE_APPEND);
    }
} else {
    file_put_contents($dumpPath, "-- mysqldump could not be executed.\n", FILE_APPEND);
}

// 3) Identify redundant files (but only delete if cleanup requested)
$candidates = [];
$patterns = [
    '/(^|\\\\)backup_\d{8}_\d{6}(\\\\|$)/i',
];
$fileNameRegex = '/^(add_test|add_more_test|setup_test|seed_test|cleanup_test|QUICK_PRODUCTION_SETUP|final_.*|restore_.*|fix_.*|create_admin|simple_setup|setup_.*|db_.*diagnostic|system_scan)\.php$/i';

$rii2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($rii2 as $item) {
    $path = $item->getPathname();
    $rel = substr($path, $rootLen);
    // Skip core directories
    if (strpos($rel, 'includes' . DIRECTORY_SEPARATOR . 'phpmailer') === 0) { continue; }
    if ($item->isDir()) {
        foreach ($patterns as $pat) {
            if (preg_match($pat, $path)) { $candidates[] = ['type' => 'dir', 'path' => $path]; break; }
        }
    } else {
        if (preg_match($fileNameRegex, basename($path))) {
            $candidates[] = ['type' => 'file', 'path' => $path];
        }
    }
}

$removed = [];
if ($doCleanup) {
    foreach ($candidates as $c) {
        $path = $c['path'];
        // Safety: do not delete current admin.php, includes, or db files, or anything under backup destination
        if (strpos($path, $backupBase) === 0) { continue; }
        if ($c['type'] === 'dir') {
            // Only remove directories named backup_YYYYMMDD_HHMMSS at project root level
            $base = basename($path);
            if (preg_match('/^backup_\d{8}_\d{6}$/', $base)) {
                // Recursively delete
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($it as $f) {
                    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
                }
                if (@rmdir($path)) { $removed[] = $path; }
            }
        } else {
            @unlink($path) && $removed[] = $path;
        }
    }
}

$report = [
    'backup_dir' => $backupDir,
    'zip' => $zipPath,
    'sql_dump' => $dumpPath,
    'cleanup_performed' => $doCleanup,
    'removed' => $removed,
    'kept_project_root' => $projectRoot
];

header('Content-Type: application/json');
echo json_encode($report, JSON_PRETTY_PRINT);
?>


