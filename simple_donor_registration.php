<?php
// Simplified donor registration - minimal code to avoid 500 errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Simple database connection
        require_once 'db.php';
        
        // Get form data
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bloodType = $_POST['blood_type'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $dob = $_POST['birth_date'] ?? '';
        $address = trim($_POST['address'] ?? '');
        
        // Basic validation
        if (empty($fullName) || empty($email) || empty($phone) || empty($bloodType) || empty($gender) || empty($dob) || empty($address)) {
            throw new Exception('Please fill in all required fields');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        // Check for duplicate email
        $checkStmt = $pdo->prepare("SELECT id FROM donors WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            throw new Exception('This email is already registered');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Split name
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        // Insert donor
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
            'REF-' . $donorId,
            json_encode($medicalData),
            1
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $message = "✅ Registration successful! Your donor ID is: $donorId. We will contact you soon.";
        $success = true;
        
        // Clear form data
        $_POST = [];
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "❌ Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Donor Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .form-container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .btn-register { background: #dc3545; border: none; padding: 12px 30px; font-weight: 600; }
        .btn-register:hover { background: #c82333; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="form-container">
            <h1 class="text-center mb-4">Donor Registration</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Blood Type *</label>
                        <select class="form-select" name="blood_type" required>
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
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gender *</label>
                        <select class="form-select" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date of Birth *</label>
                        <input type="date" class="form-control" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Address *</label>
                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-register text-white">Register as Donor</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
