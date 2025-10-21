<?php
session_start();
require_once __DIR__ . "/includes/db.php";

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $gender = $_POST["gender"] ?? "";
    $birth_date = $_POST["birth_date"] ?? "";
    $weight = floatval($_POST["weight"] ?? 0);
    $blood_type = $_POST["blood_type"] ?? "";

    // Basic validation
    if (empty($full_name)) $errors[] = "Full name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if (empty($birth_date)) $errors[] = "Date of birth is required";
    if ($weight < 50) $errors[] = "Minimum weight is 50kg";
    if (empty($blood_type)) $errors[] = "Blood type is required";

    if (empty($errors)) {
        try {
            // Generate reference code
            $ref_code = 'DON' . strtoupper(uniqid());
            
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO donors 
                (full_name, email, gender, date_of_birth, weight, blood_type, reference_code, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$full_name, $email, $gender, $birth_date, $weight, $blood_type, $ref_code]);
            
            $success = true;
            $success_message = "Registration successful! Your reference number is: " . $ref_code;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], 
        input[type="email"], 
        input[type="date"], 
        input[type="number"],
        select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #e74c3c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background-color: #c0392b; }
        .error { color: #e74c3c; margin-bottom: 15px; }
        .success { color: #27ae60; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Donor Registration</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" required 
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="gender">Gender:</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male" <?= ($_POST['gender'] ?? '') == 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= ($_POST['gender'] ?? '') == 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= ($_POST['gender'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="birth_date">Date of Birth:</label>
                <input type="date" id="birth_date" name="birth_date" required
                       value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="weight">Weight (kg):</label>
                <input type="number" id="weight" name="weight" min="50" step="0.1" required
                       value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="blood_type">Blood Type:</label>
                <select id="blood_type" name="blood_type" required>
                    <option value="">Select Blood Type</option>
                    <option value="A+" <?= ($_POST['blood_type'] ?? '') == 'A+' ? 'selected' : '' ?>>A+</option>
                    <option value="A-" <?= ($_POST['blood_type'] ?? '') == 'A-' ? 'selected' : '' ?>>A-</option>
                    <option value="B+" <?= ($_POST['blood_type'] ?? '') == 'B+' ? 'selected' : '' ?>>B+</option>
                    <option value="B-" <?= ($_POST['blood_type'] ?? '') == 'B-' ? 'selected' : '' ?>>B-</option>
                    <option value="AB+" <?= ($_POST['blood_type'] ?? '') == 'AB+' ? 'selected' : '' ?>>AB+</option>
                    <option value="AB-" <?= ($_POST['blood_type'] ?? '') == 'AB-' ? 'selected' : '' ?>>AB-</option>
                    <option value="O+" <?= ($_POST['blood_type'] ?? '') == 'O+' ? 'selected' : '' ?>>O+</option>
                    <option value="O-" <?= ($_POST['blood_type'] ?? '') == 'O-' ? 'selected' : '' ?>>O-</option>
                </select>
            </div>

            <button type="submit">Register as Donor</button>
        </form>
    <?php endif; ?>
</body>
</html>
