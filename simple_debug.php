<?php
// Simple debug script - no redirects, just output
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Simple Registration Debug</h1>";

try {
    require_once 'db.php';
    echo "<p style='color: green;'>✅ Database connected successfully</p>";
    
    // Check if medical screening table exists
    $result = $pdo->query("SELECT to_regclass('public.donor_medical_screening_simple')");
    $tableExists = $result->fetchColumn();
    
    if ($tableExists) {
        echo "<p style='color: green;'>✅ Medical screening table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Medical screening table MISSING!</p>";
        echo "<p>This is likely why registration is stuck.</p>";
        
        // Try to create it
        echo "<p>Attempting to create the table...</p>";
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
                id SERIAL PRIMARY KEY,
                donor_id INTEGER REFERENCES donors(id) ON DELETE CASCADE,
                reference_code VARCHAR(20),
                screening_data JSONB,
                all_questions_answered BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<p style='color: green;'>✅ Table created successfully!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Failed to create table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check donors table
    $result = $pdo->query("SELECT to_regclass('public.donors')");
    $donorsExists = $result->fetchColumn();
    
    if ($donorsExists) {
        echo "<p style='color: green;'>✅ Donors table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Donors table missing</p>";
    }
    
    // Test a simple insert
    if ($tableExists && $donorsExists) {
        echo "<p>Testing database insert...</p>";
        try {
            $stmt = $pdo->prepare("INSERT INTO donors (first_name, last_name, email, phone, blood_type, date_of_birth, gender, address, city, province, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)");
            $stmt->execute(['Test', 'User', 'test@example.com', '1234567890', 'O+', '1990-01-01', 'Male', 'Test Address', 'City of Baguio', 'Benguet']);
            $testId = $pdo->lastInsertId();
            echo "<p style='color: green;'>✅ Test insert successful (ID: $testId)</p>";
            
            // Clean up
            $pdo->prepare("DELETE FROM donors WHERE id = ?")->execute([$testId]);
            echo "<p style='color: green;'>✅ Test data cleaned up</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Test insert failed: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='donor-registration.php'>Try Registration Again</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
