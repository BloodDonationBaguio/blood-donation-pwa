<?php
// admin-forgot-password.php
// Forgot password functionality for admin

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost:3306');
define('DB_NAME', 'blood_system');
define('DB_USER', 'root');
define('DB_PASS', 'password112');

// Start session
session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit();
}

$error = '';
$success = '';

// Process forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;port=3306;dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, username, email, full_name FROM admin_users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                $error = "No admin account found with that email address.";
            } else {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $expiryTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token in database
                $updateStmt = $pdo->prepare("UPDATE admin_users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $updateStmt->execute([$resetToken, $expiryTime, $admin['id']]);
                
                // Send reset email
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/admin-reset-password.php?token=" . $resetToken;
                
                $subject = "Password Reset Request - Blood Donation System";
                $message = "
                    <h2>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($admin['full_name']) . ",</p>
                    <p>You have requested to reset your password for the Blood Donation System admin account.</p>
                    <p><strong>Username:</strong> " . htmlspecialchars($admin['username']) . "</p>
                    <p>Click the link below to reset your password:</p>
                    <p><a href='$resetLink' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Reset Password</a></p>
                    <p><strong>This link will expire in 1 hour.</strong></p>
                    <p>If you did not request this password reset, please ignore this email.</p>
                    <p>Best regards,<br>Blood Donation System Team</p>
                ";
                
                // Send email
                require_once __DIR__ . '/includes/mail.php';
                if (function_exists('send_confirmation_email')) {
                    $emailSent = send_confirmation_email($email, $subject, $message, $admin['full_name']);
                    if ($emailSent) {
                        $success = "Password reset instructions have been sent to your email address.";
                    } else {
                        $error = "Failed to send reset email. Please try again later.";
                    }
                } else {
                    $error = "Email system not available. Please contact system administrator.";
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Forgot password error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 50%, #a71e2a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .forgot-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .forgot-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .forgot-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 16px;
        }
        
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .back-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            color: #c82333;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <i class="fas fa-key fa-3x mb-3"></i>
            <h2 class="mb-0">Forgot Password?</h2>
            <p class="mb-0">Enter your email to reset your password</p>
        </div>
        
        <div class="forgot-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="admin_login.php" class="back-link">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" id="forgotForm">
                    <input type="hidden" name="action" value="forgot_password">
                    
                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        <div class="form-text">
                            Enter the email address associated with your admin account
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="admin_login.php" class="back-link">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address.');
                return false;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
    </script>
</body>
</html>
