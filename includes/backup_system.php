<?php
/**
 * Automated Backup System
 * Production-ready backup with multiple storage options
 */

class BackupSystem {
    private $config;
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->config = $this->loadConfig();
    }
    
    private function loadConfig() {
        $configFile = __DIR__ . '/../config/backup_config.json';
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        } else {
            // Default backup configuration
            $config = [
                'enabled' => true,
                'schedule' => [
                    'daily' => '02:00',
                    'weekly' => 'sunday',
                    'monthly' => '1st'
                ],
                'retention' => [
                    'daily' => 7,
                    'weekly' => 4,
                    'monthly' => 12
                ],
                'storage' => [
                    'local' => [
                        'enabled' => true,
                        'path' => '../backups/',
                        'max_size' => '1GB'
                    ],
                    'cloud' => [
                        'enabled' => false,
                        'provider' => 'aws_s3',
                        'bucket' => '',
                        'region' => '',
                        'access_key' => '',
                        'secret_key' => ''
                    ]
                ],
                'compression' => true,
                'encryption' => false,
                'notifications' => [
                    'enabled' => true,
                    'email' => 'admin@blooddonation.com',
                    'on_success' => false,
                    'on_failure' => true
                ]
            ];
            
            $this->saveConfig($config);
        }
        
        return $config;
    }
    
    public function saveConfig($config) {
        $configDir = __DIR__ . '/../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        file_put_contents(
            $configDir . '/backup_config.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
    
    public function createBackup($type = 'manual') {
        if (!$this->config['enabled']) {
            return ['success' => false, 'message' => 'Backup system disabled'];
        }
        
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = "backup_{$type}_{$timestamp}";
            
            // Create backup directory
            $backupDir = $this->config['storage']['local']['path'] . $backupName;
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            // Database backup
            $dbBackup = $this->backupDatabase($backupDir);
            if (!$dbBackup['success']) {
                throw new Exception($dbBackup['message']);
            }
            
            // Files backup
            $filesBackup = $this->backupFiles($backupDir);
            if (!$filesBackup['success']) {
                throw new Exception($filesBackup['message']);
            }
            
            // Create backup manifest
            $manifest = [
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => $type,
                'database' => $dbBackup['file'],
                'files' => $filesBackup['files'],
                'size' => $this->getDirectorySize($backupDir),
                'compression' => $this->config['compression'],
                'encryption' => $this->config['encryption']
            ];
            
            file_put_contents(
                $backupDir . '/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT)
            );
            
            // Compress if enabled
            if ($this->config['compression']) {
                $this->compressBackup($backupDir);
            }
            
            // Upload to cloud if enabled
            if ($this->config['storage']['cloud']['enabled']) {
                $this->uploadToCloud($backupDir, $backupName);
            }
            
            // Clean old backups
            $this->cleanOldBackups();
            
            // Log backup
            $this->logBackup($backupName, 'success', $manifest['size']);
            
            return [
                'success' => true,
                'backup_name' => $backupName,
                'size' => $manifest['size'],
                'message' => 'Backup created successfully'
            ];
            
        } catch (Exception $e) {
            $this->logBackup($backupName ?? 'unknown', 'error', 0, $e->getMessage());
            
            if ($this->config['notifications']['enabled'] && $this->config['notifications']['on_failure']) {
                $this->sendNotification('Backup Failed', $e->getMessage());
            }
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function backupDatabase($backupDir) {
        try {
            $dbFile = $backupDir . '/database.sql';
            
            // Get database configuration
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $username = $_ENV['DB_USERNAME'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';
            $database = $_ENV['DB_NAME'] ?? 'blood_system';
            
            // Create mysqldump command
            $command = "mysqldump -h $host -u $username -p$password $database > $dbFile";
            
            // Execute backup
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('Database backup failed: ' . implode("\n", $output));
            }
            
            if (!file_exists($dbFile) || filesize($dbFile) === 0) {
                throw new Exception('Database backup file is empty or missing');
            }
            
            return ['success' => true, 'file' => 'database.sql'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function backupFiles($backupDir) {
        try {
            $filesDir = $backupDir . '/files';
            mkdir($filesDir, 0755, true);
            
            // Files to backup (exclude unnecessary files)
            $excludePatterns = [
                '/backups/',
                '/logs/',
                '/tmp/',
                '/cache/',
                '*.log',
                '*.tmp',
                '*.cache'
            ];
            
            $filesToBackup = [
                'index.php',
                'admin.php',
                'donor-registration.php',
                'includes/',
                'config/',
                '.htaccess'
            ];
            
            $backedUpFiles = [];
            
            foreach ($filesToBackup as $item) {
                $sourcePath = __DIR__ . '/../' . $item;
                $destPath = $filesDir . '/' . $item;
                
                if (is_file($sourcePath)) {
                    $destDir = dirname($destPath);
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    copy($sourcePath, $destPath);
                    $backedUpFiles[] = $item;
                } elseif (is_dir($sourcePath)) {
                    $this->copyDirectory($sourcePath, $destPath, $excludePatterns);
                    $backedUpFiles[] = $item;
                }
            }
            
            return ['success' => true, 'files' => $backedUpFiles];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function copyDirectory($source, $dest, $excludePatterns = []) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = str_replace($source . '/', '', $item->getPathname());
            
            // Check if file should be excluded
            $exclude = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $relativePath)) {
                    $exclude = true;
                    break;
                }
            }
            
            if ($exclude) continue;
            
            $destPath = $dest . '/' . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($item->getPathname(), $destPath);
            }
        }
    }
    
    private function compressBackup($backupDir) {
        $zipFile = $backupDir . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Cannot create ZIP file');
        }
        
        $this->addDirectoryToZip($zip, $backupDir, basename($backupDir));
        $zip->close();
        
        // Remove original directory
        $this->removeDirectory($backupDir);
        
        return $zipFile;
    }
    
    private function addDirectoryToZip($zip, $dir, $zipDir = '') {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $zipDir . '/' . substr($filePath, strlen($dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }
    }
    
    private function getDirectorySize($dir) {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
    
    private function cleanOldBackups() {
        $backupPath = $this->config['storage']['local']['path'];
        $retention = $this->config['retention'];
        
        $backups = glob($backupPath . 'backup_*');
        $backupsByType = ['daily' => [], 'weekly' => [], 'monthly' => []];
        
        foreach ($backups as $backup) {
            $backupName = basename($backup);
            if (preg_match('/backup_(daily|weekly|monthly)_(\d{4}-\d{2}-\d{2})/', $backupName, $matches)) {
                $type = $matches[1];
                $date = $matches[2];
                $backupsByType[$type][] = ['path' => $backup, 'date' => $date];
            }
        }
        
        foreach ($backupsByType as $type => $backups) {
            if (count($backups) > $retention[$type]) {
                // Sort by date (oldest first)
                usort($backups, function($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });
                
                // Remove oldest backups
                $toRemove = count($backups) - $retention[$type];
                for ($i = 0; $i < $toRemove; $i++) {
                    $this->removeDirectory($backups[$i]['path']);
                }
            }
        }
    }
    
    private function logBackup($backupName, $status, $size, $error = null) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'backup_name' => $backupName,
            'status' => $status,
            'size' => $size,
            'error' => $error
        ];
        
        $logFile = __DIR__ . '/../logs/backup.log';
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function sendNotification($subject, $message) {
        if (!$this->config['notifications']['enabled']) {
            return;
        }
        
        $email = $this->config['notifications']['email'];
        $body = "Backup System Notification\n\n$subject\n\n$message";
        
        // Use the advanced mail system
        require_once __DIR__ . '/advanced_mail.php';
        $mailer = new AdvancedMail();
        $mailer->sendEmail($email, $subject, $body, false);
    }
    
    public function getBackupStats() {
        $logFile = __DIR__ . '/../logs/backup.log';
        if (!file_exists($logFile)) {
            return ['total' => 0, 'success' => 0, 'failed' => 0, 'total_size' => 0];
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'total_size' => 0];
        
        foreach ($logs as $log) {
            $entry = json_decode($log, true);
            if ($entry) {
                $stats['total']++;
                if ($entry['status'] === 'success') {
                    $stats['success']++;
                    $stats['total_size'] += $entry['size'];
                } else {
                    $stats['failed']++;
                }
            }
        }
        
        return $stats;
    }
    
    public function restoreBackup($backupName) {
        $backupPath = $this->config['storage']['local']['path'] . $backupName;
        
        if (!is_dir($backupPath) && !file_exists($backupPath . '.zip')) {
            return ['success' => false, 'message' => 'Backup not found'];
        }
        
        try {
            // Extract if compressed
            if (file_exists($backupPath . '.zip')) {
                $zip = new ZipArchive();
                if ($zip->open($backupPath . '.zip') === TRUE) {
                    $zip->extractTo($backupPath);
                    $zip->close();
                }
            }
            
            // Restore database
            $dbFile = $backupPath . '/database.sql';
            if (file_exists($dbFile)) {
                $this->restoreDatabase($dbFile);
            }
            
            // Restore files
            $filesDir = $backupPath . '/files';
            if (is_dir($filesDir)) {
                $this->restoreFiles($filesDir);
            }
            
            return ['success' => true, 'message' => 'Backup restored successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function restoreDatabase($dbFile) {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_NAME'] ?? 'blood_system';
        
        $command = "mysql -h $host -u $username -p$password $database < $dbFile";
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception('Database restore failed: ' . implode("\n", $output));
        }
    }
    
    private function restoreFiles($filesDir) {
        $this->copyDirectory($filesDir, __DIR__ . '/../');
    }
}
?>
