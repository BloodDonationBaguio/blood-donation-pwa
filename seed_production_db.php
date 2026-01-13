<?php
echo "Starting production database seed script...\n";

require_once 'db_production.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Database connection failed. PDO object not available. Please check db_production.php and environment variables.\n";
    exit(1);
}

echo "Database connection appears to be successful.\n";

require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';
$inventoryManager = new BloodInventoryManagerComplete($pdo);
$backfill = $inventoryManager->backfillMissingUnits(500);
echo "Inventory backfill result: " . json_encode($backfill) . "\n";

function seedTestDonor($pdo) {
    try {
        // Use the helper function from db_production.php to check if the table exists
        if (!tableExists($pdo, 'donors')) {
            echo "Note: 'donors' table not found; skipping test donor seed.\n";
            return;
        }
        echo "'donors' table exists. Checking for test donor...\n";

        // Check if the test donor already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE email = :email");
        $stmt->execute([':email' => 'test.user@example.com']);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            echo "Test donor not found. Inserting...\n";
            $insert_stmt = $pdo->prepare(
                "INSERT INTO donors (first_name, last_name, email, phone, blood_type, status, created_at, updated_at) VALUES (:first_name, :last_name, :email, :phone, :blood_type, :status, NOW(), NOW())"
            );
            $insert_stmt->execute([
                ':first_name' => 'Test',
                ':last_name' => 'User',
                ':email' => 'test.user@example.com',
                ':phone' => '1234567890',
                ':blood_type' => 'A+',
                ':status' => 'pending'
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