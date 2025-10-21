<?php
// Start output buffering
ob_start();

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Ensure logs directory exists
if (!file_exists(dirname(__DIR__) . '/logs')) {
    mkdir(dirname(__DIR__) . '/logs', 0755, true);
}

// Function to clean output buffer and send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
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
        
        // Check if email already exists in users table
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
            $temp_password = substr(md5(uniqid(rand(), true)), 0, 8); // Generate a random password
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, address, blood_type, role) VALUES (?, ?, ?, ?, ?, ?, 'donor')");
            if (!$stmt->execute([$full_name, $email, $hashed_password, $phone, $city, $blood_type])) {
                throw new Exception("Failed to create user account");
            }
            $user_id = $pdo->lastInsertId();
            $message = "Thank you for registering as a donor! Your temporary password is: " . $temp_password;
        }
        
        // Create new donor record
        $stmt = $pdo->prepare("INSERT INTO donors (user_id, blood_type, last_donation_date, status) VALUES (?, ?, ?, 'active')");
        if (!$stmt->execute([$user_id, $blood_type, $last_donation_date])) {
            throw new Exception("Failed to create donor record");
        }
        
        // Generate reference code
        $ref_code = 'DON' . str_pad($pdo->lastInsertId(), 5, '0', STR_PAD_LEFT);
        
        // Send welcome email if this is a new user
        if ($isNewUser) {
            // Include the simple mail function
            $mailHelperPath = __DIR__ . '/simple_mail.php';
            if (!file_exists($mailHelperPath)) {
                throw new Exception("Mail helper file not found");
            }
            require_once($mailHelperPath);
            
            if (!function_exists('send_simple_email')) {
                throw new Exception("Email sending function not available");
            }
            
            // Prepare email content
            $subject = "Welcome to Blood Donation System";
            $htmlMessage = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>Welcome to Blood Donation System</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #d9230f; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background-color: #f9f9f9; }
                        .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                        .button {
                            display: inline-block;
                            padding: 10px 20px;
                            margin: 20px 0;
                            background-color: #d9230f;
                            color: white !important;
                            text-decoration: none;
                            border-radius: 4px;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Welcome to Blood Donation System</h1>
                        </div>
                        <div class='content'>
                            <p>Dear $full_name,</p>
                            <p>Thank you for registering as a blood donor. Your account has been successfully created.</p>
                            <p>Your donor reference code is: <strong>$ref_code</strong></p>
                            <p>Your temporary password is: <strong>$temp_password</strong></p>
                            <p>Please log in and change your password after your first login.</p>
                            <p>You can now log in to your account using your email and password to update your profile and check your donation history.</p>
                            <p>Thank you for your willingness to save lives!</p>
                            <p>Best regards,<br>Blood Donation System Team</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>";
            
            // Send the email
            if (!send_simple_email($email, $subject, $htmlMessage, $full_name)) {
                throw new Exception("Failed to send welcome email. Please try again later.");
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        sendJsonResponse(
            true, 
            "Thank you for registering as a blood donor! Your reference number is: " . $ref_code,
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
    // Log the error
    error_log("Registration error: " . $e->getMessage());
    
    // Return error response
    sendJsonResponse(false, "An error occurred during registration. Please try again later.");
}
