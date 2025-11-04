<?php
require_once __DIR__ . '/db.php';

echo "Database Diagnostic Report\n";
echo "===========================\n\n";

try {
    // Get the database driver
    echo "1. Database Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";

    // Check for donors_new table
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='donors_new'");
    if ($stmt->fetch() !== false) {
        echo "2. Table 'donors_new' exists.\n";
    } else {
        echo "2. Table 'donors_new' does NOT exist.\n";
    }

    // Check for donors table
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='donors'");
    if ($stmt->fetch() !== false) {
        echo "3. Table 'donors' exists.\n";
    } else {
        echo "3. Table 'donors' does NOT exist.\n";
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
