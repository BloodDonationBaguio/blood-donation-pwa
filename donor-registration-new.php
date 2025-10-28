<?php
define('INCLUDES_PATH', true);
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/pg_compat.php";

function generateReferenceNumber() {
    return strtoupper('DNR-' . substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

function isEligibleAge($birthDate) {
    $age = date_diff(date_create($birthDate), date_create('today'))->y;
    return ($age >= 18 && $age <= 65);
}

// Initialize variables
$errors = [];
$success = false;
$refNumber = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bloodType = $_POST['blood_type'] ?? '';
    $dob = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = 'City of Baguio';
    $province = 'Benguet';
    $weight = $_POST['weight'] ?? '';
    $height = $_POST['height'] ?? '';
    
    // Simple validation
    if (empty($fullName)) $errors[] = "Full name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($phone)) $errors[] = "Phone is required.";
    if (empty($bloodType)) $errors[] = "Blood type is required.";
    if (empty($dob)) $errors[] = "Date of birth is required.";
    if (empty($gender)) $errors[] = "Gender is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($weight) || $weight < 50) $errors[] = "Weight must be at least 50kg.";
    if (empty($height)) $errors[] = "Height is required.";
    
    // Age validation
    if (!empty($dob) && !isEligibleAge($dob)) {
        $errors[] = "You must be between 18 and 65 years old to donate blood.";
    }
    
    // If no errors, process registration
    if (empty($errors)) {
        try {
            // Generate reference number
            $refNumber = generateReferenceNumber();
            
            // Split full name
            $nameParts = explode(' ', trim($fullName), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            // Check which table to use
            $donorsTable = 'donors';
            if (tableExists($pdo, 'donors_new')) {
                $donorsTable = 'donors_new';
            }
            
            // Insert donor information
            $stmt = $pdo->prepare("
                INSERT INTO " . $donorsTable . " (
                    first_name, last_name, email, phone, blood_type, date_of_birth, 
                    gender, address, city, province, weight, height, reference_code, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', " . mysql_now() . ")
            ");
            
            $stmt->execute([
                $firstName, $lastName, $email, $phone, $bloodType, $dob, 
                $gender, $address, $city, $province, $weight, $height, $refNumber
            ]);
            
            // Success - redirect to success page
            $_SESSION['registration_ref'] = $refNumber;
            header('Location: registration_success.php?ref=' . urlencode($refNumber));
            exit();
            
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .required:after {
            content: " *";
            color: #e74c3c;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container form-container">
        <h1 class="text-center mb-4">Donor Registration</h1>
        
        <!-- Error messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5>Please fix the following errors:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="POST" action="">
            <div class="row g-3">
                <!-- Full Name -->
                <div class="col-md-6">
                    <label for="full_name" class="form-label required">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                
                <!-- Email -->
                <div class="col-md-6">
                    <label for="email" class="form-label required">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                
                <!-- Phone -->
                <div class="col-md-6">
                    <label for="phone" class="form-label required">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
                
                <!-- Blood Type -->
                <div class="col-md-6">
                    <label for="blood_type" class="form-label required">Blood Type</label>
                    <select class="form-select" id="blood_type" name="blood_type" required>
                        <option value="">Select Blood Type</option>
                        <option value="A+" <?= ($_POST['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= ($_POST['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= ($_POST['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= ($_POST['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                        <option value="AB+" <?= ($_POST['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= ($_POST['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                        <option value="O+" <?= ($_POST['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= ($_POST['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                        <option value="Unknown" <?= ($_POST['blood_type'] ?? '') === 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                    </select>
                </div>
                
                <!-- Date of Birth -->
                <div class="col-md-6">
                    <label for="birth_date" class="form-label required">Date of Birth</label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                           value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" required>
                </div>
                
                <!-- Gender -->
                <div class="col-md-6">
                    <label for="gender" class="form-label required">Gender</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                
                <!-- Address -->
                <div class="col-md-6">
                    <label for="address" class="form-label required">Address</label>
                    <input type="text" class="form-control" id="address" name="address" 
                           value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
                </div>
                
                <!-- Weight -->
                <div class="col-md-6">
                    <label for="weight" class="form-label required">Weight (kg)</label>
                    <input type="number" class="form-control" id="weight" name="weight" min="50" step="0.1"
                           value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>" required>
                </div>
                
                <!-- Height -->
                <div class="col-md-6">
                    <label for="height" class="form-label required">Height (cm)</label>
                    <input type="number" class="form-control" id="height" name="height" min="100" max="250" step="0.1"
                           value="<?= htmlspecialchars($_POST['height'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Register as Donor</button>
                <a href="index.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
