<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db.php';
echo '<h2>Donor Table Insert Test</h2>';
try {
    $ref = 'TEST-' . strtoupper(substr(md5(uniqid()),0,6));
    $stmt = $pdo->prepare("INSERT INTO donors (first_name, last_name, email, phone, blood_type, date_of_birth, gender, address, city, province, weight, height, reference_code, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)");
    $stmt->execute([
        'Test', 'User', 'testuser@example.com', '1234567890', 'O+', '1990-01-01', 'Male', 'Test Address', 'Test City', 'Test Province', 70, 170, $ref
    ]);
    echo '<span style="color:green;">SUCCESS: Inserted test donor with reference ' . $ref . '</span>';
} catch (Throwable $e) {
    echo '<span style="color:red;">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
