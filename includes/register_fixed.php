<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Ensure logs directory exists
if (!file_exists(dirname(__DIR__) . '/logs')) {
    mkdir(dirname(__DIR__) . '/logs', 0755, true);
}

function sendJsonResponse($success, $message = '', $data = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method");
    }

    require_once(__DIR__ . '/../db.php');

    // Get and validate input
    $required = ['full_name', 'phone', 'email', 'blood_type', 'city', 'age', 'donated_before'];
    $input = [];
    foreach ($required as $field) {
        $input[$field] = trim($_POST[$field] ?? '');
        if (empty($input[$field]) && $field !== 'donated_before') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (intval($input['age']) < 18) {
        $errors[] = "You must be at least 18 years old";
    }
    
    if (!empty($errors)) {
        sendJsonResponse(false, implode("\n", $errors));
    }

    $pdo->beginTransaction();
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $user_id = $user['id'];
            $message = "Thank you for donating again!";
        } else {
            // Create user without role
            $temp_password = 'donor' . rand(1000, 9999);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, address, blood_type) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['full_name'], 
                $input['email'], 
                $hashed_password, 
                $input['phone'], 
                $input['city'], 
                $input['blood_type']
            ]);
            
            $user_id = $pdo->lastInsertId();
            $message = "Thank you for registering! Your temporary password is: " . $temp_password;
        }
        
        // Create donor record
        $stmt = $pdo->prepare("INSERT INTO donors (user_id, blood_type, is_available) VALUES (?, ?, 1)");
        $stmt->execute([$user_id, $input['blood_type']]);
        
        $pdo->commit();
        sendJsonResponse(true, $message, ['redirect' => 'thank-you.php']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendJsonResponse(false, "An error occurred. Please try again later.");
}
