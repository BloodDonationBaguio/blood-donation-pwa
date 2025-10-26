<?php
// Debug the actual registration process
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Registration Process Debug</h1>";

// Test the exact same process as donor-registration.php
try {
    require_once 'db.php';
    echo "<p style='color: green;'>✅ Database connected</p>";
    
    // Test the exact insert that donor-registration.php uses
    echo "<h3>Testing Donor Insert Process:</h3>";
    
    $pdo->beginTransaction();
    echo "<p>✅ Transaction started</p>";
    
    // Test data (same as registration form)
    $testData = [
        'Test', 'User', 'test@example.com', '1234567890', 'O+', '1990-01-01',
        'Male', 'Test Address', 'City of Baguio', 'Benguet'
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO donors (
            first_name, last_name, email, phone, blood_type, date_of_birth, 
            gender, address, city, province, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute($testData);
    $donorId = $pdo->lastInsertId();
    echo "<p>✅ Donor inserted (ID: $donorId)</p>";
    
    // Test medical screening insert
    echo "<h3>Testing Medical Screening Insert:</h3>";
    
    $medicalData = [
        'q1' => 'no',
        'q2' => 'no',
        'q3' => 'no'
    ];
    
    $medicalStmt = $pdo->prepare("
        INSERT INTO donor_medical_screening_simple 
        (donor_id, reference_code, screening_data, all_questions_answered) 
        VALUES (?, ?, ?, ?)
    ");
    
    $medicalStmt->execute([
        $donorId,
        'TEST-123456',
        json_encode($medicalData),
        1
    ]);
    echo "<p>✅ Medical screening inserted</p>";
    
    // Test email function
    echo "<h3>Testing Email Function:</h3>";
    
    if (function_exists('send_confirmation_email')) {
        echo "<p>✅ send_confirmation_email function exists</p>";
        
        // Test email (but don't actually send)
        echo "<p>📧 Email function is available (not sending test email to avoid spam)</p>";
    } else {
        echo "<p style='color: red;'>❌ send_confirmation_email function missing</p>";
    }
    
    // Commit transaction
    $pdo->commit();
    echo "<p>✅ Transaction committed successfully</p>";
    
    // Clean up
    $pdo->prepare("DELETE FROM donor_medical_screening_simple WHERE donor_id = ?")->execute([$donorId]);
    $pdo->prepare("DELETE FROM donors WHERE id = ?")->execute([$donorId]);
    echo "<p>✅ Test data cleaned up</p>";
    
    echo "<hr>";
    echo "<h3>🎉 All Tests Passed!</h3>";
    echo "<p>The registration process should work. The issue might be:</p>";
    echo "<ul>";
    echo "<li>JavaScript form validation preventing submission</li>";
    echo "<li>CAPTCHA issues</li>";
    echo "<li>Form data not being sent properly</li>";
    echo "<li>Session issues</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "<p>🔄 Transaction rolled back</p>";
    }
}

echo "<hr>";
echo "<p><a href='donor-registration.php'>Try Registration Again</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
