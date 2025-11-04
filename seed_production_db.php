<?php
echo "Starting production database seed script...\n";

require_once 'db_production.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Database connection failed. PDO object not available. Please check db_production.php and environment variables.\n";
    exit(1);
}

echo "Database connection appears to be successful.\n";

function seedTestDonor($pdo) {
    try {
        // Use the helper function from db_production.php to check if the table exists
        if (!tableExists($pdo, 'donors')) {
            echo "Error: 'donors' table does not exist in the database.\n";
            exit(1);
        }
        echo "'donors' table exists. Checking for test donor...\n";

        // Check if the test donor already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE email = :email");
        $stmt->execute([':email' => 'test.user@example.com']);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            echo "Test donor not found. Inserting...\n";
            $insert_stmt = $pdo->prepare(
                "INSERT INTO donors (name, email, phone, blood_type, status, password, created_at, updated_at) VALUES (:name, :email, :phone, :blood_type, :status, :password, NOW(), NOW())"
            );
            $insert_stmt->execute([
                ':name' => 'Test User',
                ':email' => 'test.user@example.com',
                ':phone' => '1234567890',
                ':blood_type' => 'A+',
                ':status' => 'pending',
                ':password' => password_hash('password', PASSWORD_DEFAULT)
            ]);
            echo "Test donor inserted successfully.\n";
        } else {
            echo "Test donor already exists. No action needed.\n";
        }
    } catch (PDOException $e) {
        echo "Database error during seeding: " . $e->getMessage() . "\n";
        exit(1);
    }
}

seedTestDonor($pdo);

echo "Seed script finished successfully.\n";

?>