<?php
// Scan for duplicate files and unused components
$projectDir = __DIR__;
$duplicates = [];
$unusedFiles = [];
$fileHashes = [];

echo "Scanning for duplicate files and unused components...\n\n";

// Get all files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $filePath = $file->getPathname();
        $relativePath = str_replace($projectDir . DIRECTORY_SEPARATOR, '', $filePath);
        
        // Skip backup directories and temporary files
        if (strpos($relativePath, 'backup') !== false || 
            strpos($relativePath, '.tmp') !== false ||
            strpos($relativePath, 'test_') !== false ||
            strpos($relativePath, 'debug_') !== false) {
            continue;
        }
        
        $fileHash = md5_file($filePath);
        
        if (isset($fileHashes[$fileHash])) {
            $duplicates[] = [
                'original' => $fileHashes[$fileHash],
                'duplicate' => $relativePath,
                'size' => filesize($filePath)
            ];
        } else {
            $fileHashes[$fileHash] = $relativePath;
        }
    }
}

// Identify potentially unused files
$unusedPatterns = [
    'backup' => 'Backup files',
    'test_' => 'Test files',
    'debug_' => 'Debug files',
    'temp_' => 'Temporary files',
    '.backup' => 'Backup files',
    '_old' => 'Old version files',
    '_broken' => 'Broken files',
    '_new' => 'Alternative versions',
    'demo_' => 'Demo files',
    'sample_' => 'Sample files'
];

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($projectDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        
        foreach ($unusedPatterns as $pattern => $description) {
            if (strpos($relativePath, $pattern) !== false) {
                $unusedFiles[] = [
                    'file' => $relativePath,
                    'reason' => $description,
                    'size' => filesize($file->getPathname())
                ];
                break;
            }
        }
    }
}

// Report duplicates
echo "=== DUPLICATE FILES ===\n";
if (empty($duplicates)) {
    echo "No duplicate files found.\n\n";
} else {
    foreach ($duplicates as $dup) {
        echo "Duplicate: {$dup['duplicate']}\n";
        echo "  Original: {$dup['original']}\n";
        echo "  Size: " . number_format($dup['size'] / 1024, 2) . " KB\n\n";
    }
}

// Report unused files
echo "=== POTENTIALLY UNUSED FILES ===\n";
if (empty($unusedFiles)) {
    echo "No unused files found.\n\n";
} else {
    $totalSize = 0;
    foreach ($unusedFiles as $file) {
        echo "File: {$file['file']}\n";
        echo "  Reason: {$file['reason']}\n";
        echo "  Size: " . number_format($file['size'] / 1024, 2) . " KB\n\n";
        $totalSize += $file['size'];
    }
    echo "Total unused files size: " . number_format($totalSize / 1024, 2) . " KB\n\n";
}

// Save report
$report = [
    'scan_date' => date('Y-m-d H:i:s'),
    'duplicates' => $duplicates,
    'unused_files' => $unusedFiles,
    'total_duplicates' => count($duplicates),
    'total_unused' => count($unusedFiles)
];

file_put_contents('duplicate_scan_report.json', json_encode($report, JSON_PRETTY_PRINT));
echo "Scan report saved to: duplicate_scan_report.json\n";
?>
