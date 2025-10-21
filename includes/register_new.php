<?php
// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Ensure logs directory exists
if (!file_exists(dirname(__DIR__) . '/logs')) {
    mkdir(dirname(__DIR__) . '/logs', 0755, true);
}

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method");
    }

    // Include database connection
    require_once(__DIR__ . '/../db.php');

    // Get and sanitize input
    $full_name = trim($_POST["full_name"] ?? '');
    $phone = trim($_POST["phone"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $blood_type = trim($_POST["blood_type"] ?? '');
    $city = trim($_POST["city"] ?? '');
    $age = intval($_POST["age"] ?? 0);
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
    if ($age < 18) $errors[] = "You must be at least 18 years old to register";
    if (empty($donated_before)) $errors[] = "Please specify if you've donated before";
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        sendJsonResponse(false, implode("\n", $errors));
    }

    // Start transaction
    $pdo->beginTransaction();
    
    try {
        $isNewUser = false;
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // User exists, get the existing user ID
            $user_id = $existingUser['id'];
            $message = "Thank you for donating again! Your new donation has been recorded.";
        } else {
            // Create new user account
            $isNewUser = true;
            $temp_password = 'donor' . rand(1000, 9999);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, address, blood_type) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt->execute([$full_name, $email, $hashed_password, $phone, $city, $blood_type])) {
                throw new Exception("Failed to create user account");
            }
            $user_id = $pdo->lastInsertId();
            $message = "Thank you for registering as a donor! Your temporary password is: " . $temp_password;
        }
        
        // Create new donor record (allow multiple donations per user)
        $stmt = $pdo->prepare("INSERT INTO donors (user_id, blood_type, last_donation_date, is_available) VALUES (?, ?, ?, ?)");
        $is_available = 1; // Default to available
        if (!$stmt->execute([$user_id, $blood_type, $last_donation_date, $is_available])) {
            throw new Exception("Failed to create donor record");
        }
        
        // Generate reference code
        $ref_code = 'DON' . str_pad($pdo->lastInsertId(), 5, '0', STR_PAD_LEFT);
        
        // Send confirmation email if this is a new user
        if ($isNewUser) {
            $to = $email;
            $subject = "Your Blood Donation Account";
            $email_message = "Hello " . $full_name . ",\n\n";
            $email_message .= "Thank you for registering as a blood donor. Here are your login details:\n";
            $email_message .= "Email: " . $email . "\n";
            $email_message .= "Password: " . $temp_password . "\n\n";
            $email_message .= "Please login to update your profile and check your donation status.\n\n";
            $email_message .= "Thank you for your support!\n";
            $headers = "From: noreply@blooddonation.org";
            
            // Send email (suppress errors with @)
            @mail($to, $subject, $email_message, $headers);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        sendJsonResponse(true, $message, [
            'reference_code' => $ref_code,
            'is_new_user' => $isNewUser,
            'redirect' => 'thank-you.php'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Registration error: " . $e->getMessage());
    
    // Return error response
    sendJsonResponse(false, "An error occurred during registration. Please try again later.");
}
