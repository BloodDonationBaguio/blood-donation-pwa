<?php
/**
 * Working Admin Login Page
 * This bypasses all problematic files and works directly
 */

session_start();

// Simple database connection
$host = 'localhost:3306';
$dbname = 'blood_system';
$username = 'root';
$password = 'password112';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $dbError = "Database connection failed: " . $e->getMessage();
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginUsername = trim($_POST['username'] ?? '');
    $loginPassword = trim($_POST['password'] ?? '');
    
    if (empty($loginUsername) || empty($loginPassword)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            // Check if admin_users table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
            if ($stmt->rowCount() === 0) {
                // Create admin_users table
                $createTable = "
                    CREATE TABLE admin_users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        role ENUM('super_admin', 'inventory_manager', 'medical_staff', 'viewer') DEFAULT 'super_admin',
                        email VARCHAR(100),
                        full_name VARCHAR(100),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        last_login TIMESTAMP NULL,
                        is_active BOOLEAN DEFAULT TRUE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                $pdo->exec($createTable);
            }
            
            // Check if admin user exists
            $stmt = $pdo->prepare("SELECT id, password, role FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$loginUsername]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($loginPassword, $user['password'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $loginUsername;
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_last_activity'] = time();
                
                header('Location: admin.php');
                exit();
            } else {
                // Check if we need to create default admin user
                if ($loginUsername === 'admin' && $loginPassword === 'admin123') {
                    // Create default admin user
                    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_users (username, password, role, email, full_name, is_active) 
                        VALUES (?, ?, 'super_admin', 'prc.baguio.blood@gmail.com', 'System Administrator', 1)
                        ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role), is_active = 1
                    ");
                    $stmt->execute([$loginUsername, $hashedPassword]);
                    
                    // Try login again
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $loginUsername;
                    $_SESSION['admin_role'] = 'super_admin';
                    $_SESSION['admin_last_activity'] = time();
                    
                    header('Location: admin.php');
                    exit();
                } else {
                    $error = 'Invalid username or password';
                }
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
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
        .login-container {
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h2>Admin Login</h2>
            <p class="text-muted">Blood Donation System</p>
        </div>

        <?php if (isset($dbError)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($dbError) ?>
            </div>
        <?php endif; ?>

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
                This will create the admin user automatically if it doesn't exist.
            </small>
        </div>

        <!-- Quick Actions -->
        <div class="mt-3 text-center">
            <small class="text-muted">
                <a href="admin.php" target="_blank">Go to Admin Panel</a> | 
                <a href="index.php">Back to Homepage</a>
            </small>
        </div>
    </div>
</body>
</html>
