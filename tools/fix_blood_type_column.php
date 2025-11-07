<?php
// Fix script: widen donors.blood_type to VARCHAR(10) for Postgres/MySQL
// Access restriction: localhost only
$allowed = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed, true)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain');
echo "Starting blood_type width check...\n";

try {
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $isPostgres = ($driver === 'pgsql');
    $isSqlite = ($driver === 'sqlite');

    if ($isSqlite) {
        echo "SQLite detected; width constraints not enforced in the same way. No action taken.\n";
        exit;
    }

    // Read current length
    if ($isPostgres) {
        $stmt = $pdo->prepare("SELECT character_maximum_length FROM information_schema.columns WHERE table_name = 'donors' AND column_name = 'blood_type' ORDER BY character_maximum_length NULLS LAST LIMIT 1");
    } else {
        // MySQL/MariaDB
        $stmt = $pdo->prepare("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donors' AND COLUMN_NAME = 'blood_type' LIMIT 1");
    }
    $stmt->execute();
    $len = $stmt->fetchColumn();

    echo "Current donors.blood_type length: " . ($len === false ? 'unknown' : $len) . "\n";

    if ($len !== false && (int)$len >= 10) {
        echo "Width already sufficient (>=10). No change.\n";
    } else {
        if ($isPostgres) {
            $pdo->exec("ALTER TABLE donors ALTER COLUMN blood_type TYPE VARCHAR(10)");
        } else {
            $pdo->exec("ALTER TABLE donors MODIFY blood_type VARCHAR(10)");
        }
        echo "Increased donors.blood_type to VARCHAR(10).\n";
    }

    // Quick write test to ensure updates won't fail (no data changed)
    $pdo->query('SELECT blood_type FROM donors LIMIT 1');
    echo "Read test OK.\n";
    $stmt = $pdo->prepare('UPDATE donors SET blood_type = blood_type WHERE 1=0');
    $stmt->execute();
    echo "Write test OK.\n";

    echo "Done.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>