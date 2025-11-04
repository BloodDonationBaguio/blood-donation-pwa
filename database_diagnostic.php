<?php
// database_diagnostic.php
// Enforce strict types and error reporting
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone and include database configuration
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Manila');
}
require_once __DIR__ . '/db.php';

// --- Persistent Check Mode ---
// This mode ensures a specific test user exists, adding it if it's missing.
// This is useful for environments where the database might be ephemeral.

try {
    // Check if the test donor already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE email = ?");
    $testEmail = 'testuser@example.com';
    $stmt->execute([$testEmail]);
    $exists = $stmt->fetchColumn();

    if ($exists == 0) {
        echo "Test donor not found. Inserting...\n";
        // Insert the test donor if it doesn't exist
        $insertStmt = $pdo->prepare(
            "INSERT INTO donors (first_name, last_name, email, phone, blood_type, status) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insertStmt->execute([
            'Test',
            'User',
            $testEmail,
            '1234567890',
            'Unknown',
            'pending'
        ]);
        echo "Test donor with 'Unknown' blood type inserted successfully.\n";
    } else {
        echo "Test donor already exists. No action taken.\n";
    }

} catch (PDOException $e) {
    die("Database error during persistent check: " . $e->getMessage() . "\n");
}

echo "Database diagnostic complete.\n";
?>
