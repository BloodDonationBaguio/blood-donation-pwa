<?php
/**
 * Admin Settings Page
 * Update admin username and password
 */

session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require_once 'includes/db.php';
require_once 'includes/admin_auth.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_credentials') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($currentPassword)) {
            $error = 'Current password is required';
        } elseif (empty($newUsername)) {
            $error = 'New username is required';
        } elseif (strlen($newUsername) < 3) {
            $error = 'Username must be at least 3 characters long';
        } elseif (empty($newPassword)) {
            $error = 'New password is required';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT id, username, password FROM admin_users WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$admin || !password_verify($currentPassword, $admin['password'])) {
                    $error = 'Current password is incorrect';
                } else {
                    // Check if new username already exists (if different)
                    if ($newUsername !== $admin['username']) {
                        $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
                        $checkStmt->execute([$newUsername, $_SESSION['admin_id']]);
                        if ($checkStmt->fetch()) {
                            $error = 'Username already exists';
                        }
                    }
                    
                    if (empty($error)) {
                        // Update credentials
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updateStmt = $pdo->prepare("
                            UPDATE admin_users 
                            SET username = ?, password = ? 
                            WHERE id = ?
                        ");
                        
                        if ($updateStmt->execute([$newUsername, $hashedPassword, $_SESSION['admin_id']])) {
                            // Update session
                            $_SESSION['admin_username'] = $newUsername;
                            $success = 'Credentials updated successfully!';
                        } else {
                            $error = 'Failed to update credentials';
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get current admin info
$currentAdmin = null;
try {
    $stmt = $pdo->prepare("SELECT username, created_at, last_login FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $currentAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load admin information';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .settings-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin: 2rem 0;
        }
        .settings-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }
        .settings-header h2 {
            color: #2d3748;
            font-weight: 700;
        }
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        .btn-update {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
            color: white;
        }
        .btn-back {
            background: #6c757d;
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .info-value {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="settings-container">
                    <div class="settings-header">
                        <h2><i class="fas fa-cog me-2"></i>Admin Settings</h2>
                        <p class="text-muted">Update your admin credentials and account information</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Current Admin Info -->
                    <?php if ($currentAdmin): ?>
                    <div class="info-card">
                        <h5 class="mb-3"><i class="fas fa-user me-2"></i>Current Account Information</h5>
                        <div class="info-item">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?= htmlspecialchars($currentAdmin['username']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Role:</span>
                            <span class="info-value"><?= ucfirst(str_replace('_', ' ', $_SESSION['admin_role'] ?? 'super_admin')) ?> Admin</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Account Created:</span>
                            <span class="info-value"><?= date('M d, Y', strtotime($currentAdmin['created_at'])) ?></span>
                        </div>
                        <?php if ($currentAdmin['last_login']): ?>
                        <div class="info-item">
                            <span class="info-label">Last Login:</span>
                            <span class="info-value"><?= date('M d, Y H:i', strtotime($currentAdmin['last_login'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Update Credentials Form -->
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_credentials">
                        
                        <h5 class="mb-3"><i class="fas fa-key me-2"></i>Update Credentials</h5>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <div class="invalid-feedback">Please enter your current password.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_username" class="form-label">New Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_username" name="new_username" 
                                   value="<?= htmlspecialchars($currentAdmin['username'] ?? '') ?>" required>
                            <div class="invalid-feedback">Please enter a new username (at least 3 characters).</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="invalid-feedback">Please enter a new password (at least 6 characters).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">Please confirm your new password.</div>
                        </div>
                        
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-update">
                                <i class="fas fa-save me-2"></i>Update Credentials
                            </button>
                            <a href="admin.php" class="btn btn-back">
                                <i class="fas fa-arrow-left me-2"></i>Back to Admin
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
