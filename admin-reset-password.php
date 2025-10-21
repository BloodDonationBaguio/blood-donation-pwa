<?php
// admin-reset-password.php
// Password reset page for admin

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
$token = $_GET['token'] ?? '';
$validToken = false;
$adminInfo = null;

// Validate token
if (!empty($token)) {
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
        
        // Check if token is valid and not expired
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, reset_token, reset_token_expiry FROM admin_users WHERE reset_token = ? AND is_active = 1");
        $stmt->execute([$token]);
        $adminInfo = $stmt->fetch();
        
        if ($adminInfo && $adminInfo['reset_token_expiry'] && strtotime($adminInfo['reset_token_expiry']) > time()) {
            $validToken = true;
        } else {
            $error = "Invalid or expired reset token. Please request a new password reset.";
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again later.";
        error_log("Password reset token validation error: " . $e->getMessage());
    }
} else {
    $error = "No reset token provided.";
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password' && $validToken) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        try {
            // Update password and clear reset token
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE admin_users SET password_hash = ?, password = '', reset_token = NULL, reset_token_expiry = NULL, updated_at = NOW() WHERE id = ?");
            
            if ($updateStmt->execute([$newPasswordHash, $adminInfo['id']])) {
                $success = "Password has been reset successfully! You can now login with your new password.";
                $validToken = false; // Hide the form
                error_log("Password reset successful for admin: " . $adminInfo['username']);
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        } catch (Exception $e) {
            $error = "An error occurred while resetting password: " . $e->getMessage();
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Admin</title>
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
        
        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .reset-body {
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
        
        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #17a2b8; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-lock fa-3x mb-3"></i>
            <h2 class="mb-0">Reset Password</h2>
            <p class="mb-0">Enter your new password</p>
        </div>
        
        <div class="reset-body">
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
                    <a href="admin_login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                </div>
            <?php elseif ($validToken && $adminInfo): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Resetting password for: <strong><?= htmlspecialchars($adminInfo['username']) ?></strong>
                </div>
                
                <form method="POST" id="resetForm">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <div class="mb-4">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-key me-2"></i>New Password
                        </label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               minlength="8" required>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="form-text">
                            Password must be at least 8 characters long
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-key me-2"></i>Confirm New Password
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               minlength="8" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Reset Password
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="admin_login.php" class="back-link">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <p>Invalid or expired reset link.</p>
                    <a href="admin-forgot-password.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Request New Reset
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-fair');
            } else if (strength === 4) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all password fields.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>
