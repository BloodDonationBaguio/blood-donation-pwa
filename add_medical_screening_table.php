<?php
// Add missing medical screening table to existing database
require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Add Medical Screening Table</title></head><body>";
echo "<h1>Adding Medical Screening Table</h1><pre>";

try {
    echo "Creating donor_medical_screening_simple table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
        id SERIAL PRIMARY KEY,
        donor_id INTEGER REFERENCES donors(id) ON DELETE CASCADE,
        reference_code VARCHAR(20),
        screening_data JSONB,
        all_questions_answered BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ donor_medical_screening_simple table created\n\n";
    
    echo "=====================================\n";
    echo "✅ MEDICAL SCREENING TABLE ADDED!\n";
    echo "=====================================\n\n";
    echo "Donor registration should now work properly.\n";
    echo "⚠️ Delete this file after setup!\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='donor-registration.php'>Test Donor Registration</a></p>";
echo "</body></html>";
?>
