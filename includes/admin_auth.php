<?php
// Admin Authentication Functions

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default admin credentials (change these in production!)
define('DEFAULT_ADMIN_USERNAME', 'admin');
define('DEFAULT_ADMIN_PASSWORD', 'admin123'); // Change this in production

/**
 * Check if user is logged in as admin
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require admin login
 * Redirects to login page if not logged in
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /blood-donation-pwa/admin_login.php');
        exit();
    }
}

/**
 * Attempt to log in with username and password
 * 
 * @param string $username
 * @param string $password
 * @return bool True if login successful, false otherwise
 */
function adminLogin($username, $password) {
    try {
        // Connect to database
        require_once __DIR__ . '/db.php';
        
        // Check if admin_users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
        if ($stmt->rowCount() === 0) {
            // Fallback to default credentials if no database table
            if ($username === DEFAULT_ADMIN_USERNAME && $password === DEFAULT_ADMIN_PASSWORD) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_role'] = 'super_admin';
                $_SESSION['admin_last_activity'] = time();
                return true;
            }
            return false;
        }
        
        // Query database for user
        $stmt = $pdo->prepare("
            SELECT id, username, password, role, is_active 
            FROM admin_users 
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_last_activity'] = time();
            
            // Debug: Log the role being set
            error_log("Admin login - Role set to: " . $user['role']);
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return true;
        }
        
        // Fallback to default credentials for backward compatibility
        if ($username === DEFAULT_ADMIN_USERNAME && $password === DEFAULT_ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_role'] = 'super_admin';
            $_SESSION['admin_last_activity'] = time();
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        
        // Fallback to default credentials
        if ($username === DEFAULT_ADMIN_USERNAME && $password === DEFAULT_ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_role'] = 'super_admin';
            $_SESSION['admin_last_activity'] = time();
            return true;
        }
        
        return false;
    }
}

/**
 * Log out the admin user
 */
function adminLogout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Check for session timeout (30 minutes of inactivity)
 */
function checkAdminSessionTimeout() {
    $timeout = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $timeout) {
        // Last request was more than 30 minutes ago
        adminLogout();
        header('Location: /blood-donation-pwa/admin-login.php?timeout=1');
        exit();
    }
    
    // Update last activity time stamp
    $_SESSION['admin_last_activity'] = time();
}

// Check for session timeout on admin pages
if (isAdminLoggedIn()) {
    checkAdminSessionTimeout();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Verify CSRF token
 * 
 * @param string $token The token to verify
 * @return bool True if token is valid, false otherwise
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token for forms
 * 
 * @return string The CSRF token
 */
function getCsrfToken() {
    return $_SESSION['csrf_token'];
}
?>
