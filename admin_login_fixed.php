<?php
/**
 * Fixed Admin Login Page
 * Proper login handling with database connection
 */

session_start();

// Include database connection
require_once 'db.php';
require_once 'includes/admin_auth.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: admin.php');
    exit();
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Try to login
        if (adminLogin($username, $password)) {
            header('Location: admin.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Check if database is working
$dbStatus = 'Unknown';
$dbError = '';

try {
    $stmt = $pdo->query("SELECT 1");
    $dbStatus = 'Connected';
} catch (Exception $e) {
    $dbStatus = 'Error';
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
            margin: 2rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .login-header h2 {
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
        }
        .credentials-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid #dc3545;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-connected {
            background-color: #d1f2eb;
            color: #0f5132;
        }
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h2>Admin Login</h2>
            <p class="text-muted">Blood Donation System</p>
        </div>

        <!-- Database Status -->
        <div class="mb-3">
            <small class="text-muted">Database Status: 
                <span class="status-badge <?= $dbStatus === 'Connected' ? 'status-connected' : 'status-error' ?>">
                    <?= $dbStatus ?>
                </span>
            </small>
            <?php if ($dbError): ?>
                <br><small class="text-danger">Error: <?= htmlspecialchars($dbError) ?></small>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="fas fa-user me-2"></i>Username
                </label>
                <input type="text" 
                       class="form-control" 
                       id="username" 
                       name="username" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-2"></i>Password
                </label>
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       required>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </form>

        <!-- Default Credentials Info -->
        <div class="credentials-info">
            <h6><i class="fas fa-info-circle me-2"></i>Default Credentials</h6>
            <p class="mb-1"><strong>Username:</strong> admin</p>
            <p class="mb-1"><strong>Password:</strong> admin123</p>
            <small class="text-muted">
                If login fails, try running 
                <a href="setup_super_admin.php" target="_blank">setup_super_admin.php</a>
            </small>
        </div>

        <!-- Quick Actions -->
        <div class="mt-3 text-center">
            <small class="text-muted">
                <a href="setup_database_complete.php" target="_blank">Setup Database</a> | 
                <a href="database_diagnostic.php" target="_blank">Diagnostic</a>
            </small>
        </div>
    </div>
</body>
</html>
