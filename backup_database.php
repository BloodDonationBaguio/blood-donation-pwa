<?php
// Database backup script
require_once 'db.php';

try {
    // Create backup directory
    $backupDir = __DIR__ . '/backup';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Get database name
    $dbName = DB_NAME;
    $backupFile = $backupDir . '/blood_donation_database_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Create mysqldump command
    $command = "C:\\xampp\\mysql\\bin\\mysqldump.exe --user=" . DB_USER . " --password=" . DB_PASS . " --host=localhost --port=3306 " . $dbName . " > " . $backupFile;
    
    // Execute backup
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0) {
        echo "Database backup created successfully: " . $backupFile . "\n";
        
        // Get file size
        $fileSize = filesize($backupFile);
        echo "Backup file size: " . number_format($fileSize / 1024, 2) . " KB\n";
        
        // List all tables in backup
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables backed up: " . implode(', ', $tables) . "\n";
        
    } else {
        echo "Database backup failed. Return code: " . $returnVar . "\n";
        echo "Output: " . implode("\n", $output) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error creating database backup: " . $e->getMessage() . "\n";
}
?>
