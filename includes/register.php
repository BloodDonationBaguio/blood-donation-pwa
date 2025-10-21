<?php
// Start output buffering
ob_start();

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Ensure logs directory exists
if (!file_exists(dirname(__DIR__) . '/logs')) {
    mkdir(dirname(__DIR__) . '/logs', 0755, true);
}

// Function to clean output buffer and send JSON response
function cleanAndSendJson($success, $message = '', $data = []) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();

    // Set the response code
    http_response_code($success ? 200 : 400);

    // Prepare the response
    $response = [
        'success' => $success,
        'message' => $message
    ];

    // Add additional data if provided
    if (is_array($data) && !empty($data)) {
        $response = array_merge($response, $data);
    }

    // Send JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // End output buffering and flush
    if (ob_get_level() > 0) {
        ob_end_flush();
    }

    exit();
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method");
    }

    require_once(__DIR__ . '/../db.php');

    // Get and sanitize input
    $full_name = trim($_POST["full_name"] ?? '');
    $phone = trim($_POST["phone"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $blood_type = trim($_POST["blood_type"] ?? '');
    $city = trim($_POST["city"] ?? '');
    $age = intval($_POST["age"] ?? 0);
    $gender = trim($_POST["gender"] ?? '');
    $address = trim($_POST["address"] ?? '');
    $postal_code = trim($_POST["postal_code"] ?? '');
    $donated_before = $_POST["donated_before"] ?? '';
    $last_donation_date = ($donated_before === "yes" && !empty($_POST["last_donation_date"]))
        ? $_POST["last_donation_date"]
        : null;

    // Validate required fields
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($blood_type)) $errors[] = "Blood type is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($postal_code)) $errors[] = "Postal code is required";
    if ($age < 18) $errors[] = "You must be at least 18 years old to register";

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        cleanAndSendJson(false, implode("\n", $errors));
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        $isNewUser = false;
        $ref_code = 'DON' . strtoupper(uniqid());

        // Check if email already exists in users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // User exists, use existing user ID
            $user_id = $existingUser['id'];
            $isNewUser = false;
            $message = "Thank you for donating again! Your new donation has been recorded.";
        } else {
            // Create new user account
            $isNewUser = true;
            $temp_password = 'donor' . rand(1000, 9999);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users 
                (name, email, password, phone, address, blood_type, role) 
                VALUES (?, ?, ?, ?, ?, ?, 'donor')
            ");
            if (!$stmt->execute([$full_name, $email, $hashed_password, $phone, $city, $blood_type])) {
                throw new Exception("Failed to create user account");
            }
            $user_id = $pdo->lastInsertId();
            $message = "Thank you for registering as a donor!";
        }

        // Create new donor record
        $stmt = $pdo->prepare("
            INSERT INTO donors 
            (user_id, full_name, email, phone, date_of_birth, gender, 
             address, city, postal_code, blood_type, reference_code, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'donor')
        ");

        $date_of_birth = date('Y-m-d', strtotime("-$age years"));

        if (!$stmt->execute([
            $user_id,
            $full_name,
            $email,
            $phone,
            $date_of_birth,
            $gender,
            $address,
            $city,
            $postal_code,
            $blood_type,
            $ref_code
        ])) {
            throw new Exception("Failed to create donor record: " . implode(" - ", $stmt->errorInfo()));
        }

        // Commit transaction
        $pdo->commit();

        // Return success response
        cleanAndSendJson(
            true,
            $message,
            [
                'redirect' => 'thank-you.php?ref=' . urlencode($ref_code),
                'reference_code' => $ref_code,
                'is_new_user' => $isNewUser
            ]
        );

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    cleanAndSendJson(false, "An error occurred: " . $e->getMessage());
}
