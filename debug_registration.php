<?php
// Debug donor registration issues
require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Registration Debug</title></head><body>";
echo "<h1>Donor Registration Debug</h1><pre>";

try {
    echo "=== DATABASE CONNECTION TEST ===\n";
    echo "✅ Database connected successfully\n";
    echo "PostgreSQL Version: " . $pdo->query('SELECT version()')->fetchColumn() . "\n\n";
    
    echo "=== TABLE EXISTENCE CHECK ===\n";
    
    // Check donors table
    $donorsExists = $pdo->query("SELECT to_regclass('public.donors')")->fetchColumn();
    echo $donorsExists ? "✅ donors table exists\n" : "❌ donors table missing\n";
    
    // Check medical screening table
    $medicalExists = $pdo->query("SELECT to_regclass('public.donor_medical_screening_simple')")->fetchColumn();
    echo $medicalExists ? "✅ donor_medical_screening_simple table exists\n" : "❌ donor_medical_screening_simple table missing\n";
    
    // Check admin_users table
    $adminExists = $pdo->query("SELECT to_regclass('public.admin_users')")->fetchColumn();
    echo $adminExists ? "✅ admin_users table exists\n" : "❌ admin_users table missing\n";
    
    echo "\n=== TABLE STRUCTURE CHECK ===\n";
    
    if ($donorsExists) {
        echo "Donors table columns:\n";
        $columns = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'donors' ORDER BY ordinal_position")->fetchAll();
        foreach ($columns as $col) {
            echo "  - {$col['column_name']} ({$col['data_type']})\n";
        }
    }
    
    if ($medicalExists) {
        echo "\nMedical screening table columns:\n";
        $columns = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'donor_medical_screening_simple' ORDER BY ordinal_position")->fetchAll();
        foreach ($columns as $col) {
            echo "  - {$col['column_name']} ({$col['data_type']})\n";
        }
    }
    
    echo "\n=== TEST DATA INSERT ===\n";
    
    if ($donorsExists && $medicalExists) {
        echo "Testing donor insert...\n";
        
        // Test insert into donors table
        $testStmt = $pdo->prepare("
            INSERT INTO donors (
                first_name, last_name, email, phone, blood_type, date_of_birth, 
                gender, address, city, province, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
        ");
        
        $testData = [
            'Test', 'User', 'test@example.com', '1234567890', 'O+', '1990-01-01',
            'Male', 'Test Address', 'City of Baguio', 'Benguet'
        ];
        
        try {
            $testStmt->execute($testData);
            $testDonorId = $pdo->lastInsertId();
            echo "✅ Test donor inserted successfully (ID: $testDonorId)\n";
            
            // Test medical screening insert
            echo "Testing medical screening insert...\n";
            $medicalStmt = $pdo->prepare("
                INSERT INTO donor_medical_screening_simple 
                (donor_id, reference_code, screening_data, all_questions_answered) 
                VALUES (?, ?, ?, ?)
            ");
            
            $medicalData = [
                $testDonorId,
                'TEST-123456',
                json_encode(['q1' => 'no', 'q2' => 'no']),
                1
            ];
            
            $medicalStmt->execute($medicalData);
            echo "✅ Test medical screening inserted successfully\n";
            
            // Clean up test data
            $pdo->prepare("DELETE FROM donor_medical_screening_simple WHERE donor_id = ?")->execute([$testDonorId]);
            $pdo->prepare("DELETE FROM donors WHERE id = ?")->execute([$testDonorId]);
            echo "✅ Test data cleaned up\n";
            
        } catch (Exception $e) {
            echo "❌ Test insert failed: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== EMAIL SYSTEM CHECK ===\n";
    if (function_exists('send_confirmation_email')) {
        echo "✅ send_confirmation_email function exists\n";
    } else {
        echo "❌ send_confirmation_email function missing\n";
    }
    
    echo "\n=== LOG DIRECTORY CHECK ===\n";
    $logDir = __DIR__ . '/logs';
    if (is_dir($logDir)) {
        echo "✅ Logs directory exists\n";
        echo "Writable: " . (is_writable($logDir) ? "Yes" : "No") . "\n";
    } else {
        echo "❌ Logs directory missing\n";
    }
    
    echo "\n=====================================\n";
    echo "✅ DEBUG COMPLETE\n";
    echo "=====================================\n";
    
    if (!$medicalExists) {
        echo "\n🚨 ISSUE FOUND: Medical screening table is missing!\n";
        echo "Please run: https://blooddonationbaguio.com/add_medical_screening_table.php\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='add_medical_screening_table.php'>Create Missing Table</a></p>";
echo "<p><a href='donor-registration.php'>Try Registration Again</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
echo "</body></html>";
?>
