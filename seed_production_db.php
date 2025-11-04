<?php
require_once 'db_production.php';

function seedTestDonor() {
    global $pdo;
    try {
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
            echo "Test donor already exists.\n";
        }
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

seedTestDonor();
?>