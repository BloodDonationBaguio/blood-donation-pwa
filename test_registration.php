<?php
// Simple test registration form - no JavaScript, no CAPTCHA
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once 'db.php';
        
        // Simple validation
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bloodType = $_POST['blood_type'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $dob = $_POST['birth_date'] ?? '';
        $address = trim($_POST['address'] ?? '');
        
        if (empty($fullName) || empty($email) || empty($phone) || empty($bloodType) || empty($gender) || empty($dob) || empty($address)) {
            throw new Exception('Please fill in all required fields');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert donor
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO donors (
                first_name, last_name, email, phone, blood_type, date_of_birth, 
                gender, address, city, province, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $firstName, $lastName, $email, $phone, $bloodType, $dob, 
            $gender, $address, 'City of Baguio', 'Benguet'
        ]);
        
        $donorId = $pdo->lastInsertId();
        
        // Insert simple medical screening
        $medicalData = ['test' => 'yes'];
        $medicalStmt = $pdo->prepare("
            INSERT INTO donor_medical_screening_simple 
            (donor_id, reference_code, screening_data, all_questions_answered) 
            VALUES (?, ?, ?, ?)
        ");
        
        $medicalStmt->execute([
            $donorId,
            'TEST-' . $donorId,
            json_encode($medicalData),
            1
        ]);
        
        // Commit
        $pdo->commit();
        
        $message = "✅ Registration successful! Donor ID: $donorId";
        $success = true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "❌ Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Registration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #c82333; }
        .message { padding: 10px; margin: 20px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <h1>Test Registration Form</h1>
    <p>This is a simplified registration form to test if the backend works without JavaScript or CAPTCHA.</p>
    
    <?php if ($message): ?>
        <div class="message <?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
            <label>Phone *</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
            <label>Blood Type *</label>
            <select name="blood_type" required>
                <option value="">Select Blood Type</option>
                <option value="A+" <?= ($_POST['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                <option value="A-" <?= ($_POST['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                <option value="B+" <?= ($_POST['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                <option value="B-" <?= ($_POST['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                <option value="AB+" <?= ($_POST['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                <option value="AB-" <?= ($_POST['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                <option value="O+" <?= ($_POST['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                <option value="O-" <?= ($_POST['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Gender *</label>
            <select name="gender" required>
                <option value="">Select Gender</option>
                <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Date of Birth *</label>
            <input type="date" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
            <label>Address *</label>
            <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
        </div>
        
        <button type="submit">Test Registration</button>
    </form>
    
    <hr>
    <p><a href="donor-registration.php">Go to Full Registration Form</a></p>
    <p><a href="index.php">Back to Home</a></p>
</body>
</html>
