<?php
// Simple token-based security
$allowed_token = getenv('SEED_TOKEN');
$provided_token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($allowed_token) || $provided_token !== $allowed_token) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied. Invalid or missing token.';
    exit;
}

echo "Starting production database seed script...<br>";

require_once 'db_production.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Database connection failed. PDO object not available. Please check db_production.php and environment variables.<br>";
    exit(1);
}

echo "Database connection appears to be successful.<br>";

function seedTestDonor($pdo) {
    try {
        if (!tableExists($pdo, 'donors')) {
            echo "Error: 'donors' table does not exist in the database.<br>";
            exit(1);
        }
        echo "'donors' table exists. Checking for test donor...<br>";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE email = :email");
        $stmt->execute([':email' => 'test.user@example.com']);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            echo "Test donor not found. Inserting...<br>";
            $insert_stmt = $pdo->prepare(
                "INSERT INTO donors (first_name, last_name, email, phone, blood_type, status, password, created_at, updated_at) VALUES (:first_name, :last_name, :email, :phone, :blood_type, :status, :password, NOW(), NOW())"
            );
            $insert_stmt->execute([
                ':first_name' => 'Test',
                ':last_name' => 'User',
                ':email' => 'test.user@example.com',
                ':phone' => '1234567890',
                ':blood_type' => 'A+',
                ':status' => 'pending',
                ':password' => password_hash('password', PASSWORD_DEFAULT)
            ]);
            echo "Test donor inserted successfully.<br>";
        } else {
            echo "Test donor already exists. No action needed.<br>";
        }
    } catch (PDOException $e) {
        echo "Database error during seeding: " . $e->getMessage() . "<br>";
        exit(1);
    }
}

seedTestDonor($pdo);

echo "Seed script finished successfully.<br>";

?>