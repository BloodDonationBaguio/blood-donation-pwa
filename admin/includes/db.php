<?php
/**
 * Database Connection - SQLite
 */

try {
    // Create database directory if it doesn't exist
    $dbDir = dirname(__DIR__, 2) . '/database';
    if (!file_exists($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    $dbFile = $dbDir . '/blood_system.db';
    
    $dsn = "sqlite:" . $dbFile;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    $pdo = new PDO($dsn, null, null, $options);
    
    // Enable foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON');
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

/**
 * Helper function to get donor count
 */
function getDonorCount() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
}

/**
 * Helper function to get pending request count
 */
function getPendingRequestCount() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn();
}
?>
