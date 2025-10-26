<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use the main database configuration
require_once 'db.php';

// Start session
session_start();

// Check if already logged in - but only redirect if we have a valid session
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['admin_username'])) {
    // Verify the session is still valid by checking the database
    try {
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$_SESSION['admin_username']]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            header('Location: admin.php');
            exit();
        } else {
            // Invalid session, clear it
            session_destroy();
            session_start();
        }
    } catch (Exception $e) {
        // Database error, clear session and continue to login
        session_destroy();
        session_start();
    }
}

// Process login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            // Use the $pdo connection from db.php
            // Check admin credentials in admin_users table
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Check password using both password_hash (new) and password (old) for compatibility
                $passwordValid = false;
                
                // First try password_hash (new method)
                if (!empty($admin['password_hash']) && password_verify($password, $admin['password_hash'])) {
                    $passwordValid = true;
                }
                // Fallback to old password field for compatibility
                elseif (!empty($admin['password']) && $admin['password'] === $password) {
                    $passwordValid = true;
                }
                
                if ($passwordValid) {
                    // Set session variables
                    session_regenerate_id(true); // Prevent session fixation
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    // Update last login time
                    $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$admin['id']]);
                    
                    // Redirect to admin dashboard
                    header('Location: admin.php');
                    exit();
                } else {
                    $error = 'Invalid password';
                }
            } else {
                $error = 'Invalid username';
            }
            
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
            error_log("Admin login error: " . $e->getMessage());
            error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - Blood Donation System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      var alerts = document.querySelectorAll('.alert');
      alerts.forEach(function(alert) {
        setTimeout(function() {
          var bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }, 5000);
      });
    });
  </script>
</head>
<body>
  <style>
    body {
      min-height: 100vh;
      background: #0a232b;
      font-family: 'Segoe UI', Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    .split-container {
      display: flex;
      min-height: 100vh;
    }
    .split-left {
      background: #0a232b;
      color: #fff;
      flex: 1.2;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
    }
    .split-left img {
      width: 90px;
      margin-bottom: 24px;
      border-radius: 50%;
      background: #fff;
      padding: 8px;
      box-shadow: 0 4px 18px rgba(0,0,0,.12);
    }
    .split-left h2 {
      font-weight: 700;
      letter-spacing: 1px;
      margin-bottom: 10px;
    }
    .split-left p {
      color: #b3c6d1;
      font-size: 1.1rem;
      margin-bottom: 0;
    }
    .split-right {
      flex: 1.5;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8f9fa;
    }
    .login-container {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.13);
      padding: 40px 32px 32px 32px;
      width: 100%;
      max-width: 380px;
      min-width: 300px;
      margin: 32px 0;
    }
    .login-container .card-title {
      font-size: 2rem;
      font-weight: 700;
      color: #c82333;
      margin-bottom: 8px;
    }
    .login-container .card-subtitle {
      color: #6c757d;
      font-size: 1.03rem;
      margin-bottom: 28px;
    }
    .login-container .form-label {
      font-weight: 500;
      color: #333;
    }
    .login-container .btn-danger {
      background: #c82333;
      border: none;
      font-weight: 600;
      font-size: 1.1rem;
      border-radius: 8px;
      margin-top: 8px;
      box-shadow: 0 2px 8px rgba(200,35,51,0.10);
      transition: background 0.2s;
    }
    .login-container .btn-danger:hover {
      background: #a71d2a;
    }
    .forgot-link {
      display: block;
      text-align: right;
      margin-top: 10px;
      color: #c82333;
      font-size: 0.98rem;
      text-decoration: none;
    }
    .forgot-link:hover {
      text-decoration: underline;
      color: #a71d2a;
    }
    @media (max-width: 900px) {
      .split-container { flex-direction: column; }
      .split-left, .split-right { flex: unset; min-height: 240px; }
      .split-left { padding: 32px 8px; }
      .split-right { padding: 24px 0; }
    }
  </style>
  <div class="split-container">
    <div class="split-left">
      <img src="https://benguetredcross.com/wp-content/uploads/2023/03/Logo_Philippine_Red_Cross-1536x1536.png" alt="Red Cross Logo">
      <h2>Philippine Red Cross</h2>
      <p>Blood Donation Admin System</p>
    </div>
    <div class="split-right">
      <div class="login-container">
        <h1 class="card-title">Welcome Back</h1>
        <p class="card-subtitle">Please sign in to continue</p>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert">
          <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form class="login-form" method="POST" action="">
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
          </div>
          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
          </div>
          <button type="submit" class="btn btn-danger w-100">Sign In</button>
        </form>
        
        <div class="text-center mt-4">
          <a href="admin-forgot-password.php" class="text-primary">Forgot Password?</a>
        </div>
        
        <div class="text-center mt-2">
          <a href="admin_logout.php" class="text-muted">Clear Session / Logout</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
