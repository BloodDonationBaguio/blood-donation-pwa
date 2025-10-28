<?php
// includes/session_manager.php - Enhanced session management with Remember Me functionality

// Include session configuration first
require_once __DIR__ . '/session_config.php';

// Load the main db.php from project root
require_once __DIR__ . '/../db.php';

function getDbDriver() {
    try {
        global $pdo;
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Exception $e) {
        return 'mysql';
    }
}

/**
 * Check if user is logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user
 */
function getCurrentUser() {
    if (!isUserLoggedIn()) {
        return null;
    }
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users_new WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Login user with optional remember me
 */
function loginUser($user, $rememberMe = false) {
    global $pdo;
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // If remember me is checked, create a persistent token
    if ($rememberMe) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        try {
            // Store remember token in database (MySQL and PostgreSQL compatible upsert)
            $driver = getDbDriver();
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare("
                    INSERT INTO user_remember_tokens (user_id, token, expires_at) 
                    VALUES (?, ?, ?)
                    ON CONFLICT (user_id) DO UPDATE SET 
                        token = EXCLUDED.token, 
                        expires_at = EXCLUDED.expires_at
                ");
                $stmt->execute([$user['id'], $token, $expires]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO user_remember_tokens (user_id, token, expires_at) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
                ");
                $stmt->execute([$user['id'], $token, $expires, $token, $expires]);
            }
            
            // Set remember me cookie
            setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
            $_SESSION['remember_me'] = true;
            
        } catch (Exception $e) {
            // If remember token fails, just log the user in normally
            error_log("Remember me token creation failed: " . $e->getMessage());
        }
    }
    
    // Update last login time
    try {
        $stmt = $pdo->prepare("UPDATE users_new SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Check and restore session from remember me token
 */
function checkRememberMeToken() {
    if (isUserLoggedIn()) {
        return true; // Already logged in
    }
    
    if (!isset($_COOKIE['remember_token'])) {
        return false; // No remember token
    }
    
    global $pdo;
    try {
        // Check if remember token is valid
        $stmt = $pdo->prepare("
            SELECT u.*, rt.token 
            FROM users_new u 
            JOIN user_remember_tokens rt ON u.id = rt.user_id 
            WHERE rt.token = ? AND rt.expires_at > NOW()
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Restore session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['remember_me'] = true;
            
            return true;
        } else {
            // Invalid or expired token, remove cookie
            setcookie('remember_token', '', time() - 3600, '/');
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Remember me token check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Logout user and clean up tokens
 */
function logoutUser() {
    global $pdo;
    
    $userId = $_SESSION['user_id'] ?? null;
    
    // Remove remember me token from database
    if ($userId) {
        try {
            $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to remove remember token: " . $e->getMessage());
        }
    }
    
    // Remove remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Clear all session data
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
 * Update user activity timestamp
 */
function updateUserActivity() {
    if (isUserLoggedIn()) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Check if session has expired (2 hours of inactivity without remember me)
 */
function checkSessionTimeout() {
    if (!isUserLoggedIn()) {
        return false;
    }
    
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $isRemembered = $_SESSION['remember_me'] ?? false;
    
    // If remember me is active, don't timeout
    if ($isRemembered) {
        updateUserActivity();
        return true;
    }
    
    // Check for 2 hour timeout
    if (time() - $lastActivity > 7200) { // 2 hours
        logoutUser();
        return false;
    }
    
    updateUserActivity();
    return true;
}

/**
 * Require user login - redirect if not logged in
 */
function requireUserLogin($redirectTo = 'login.php') {
    checkRememberMeToken(); // Check for remember me token first
    
    if (!isUserLoggedIn() || !checkSessionTimeout()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Create remember tokens table if it doesn't exist
 */
function createRememberTokensTable() {
    global $pdo;
    try {
        // Ensure users_new table exists (minimal schema) for FK to work
        $driver = getDbDriver();
        if ($driver === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS users_new (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role VARCHAR(32) DEFAULT 'user',
                status VARCHAR(32) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS users_new (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('user','admin','super_admin') DEFAULT 'user',
                status ENUM('active','inactive','suspended') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if ($driver === 'pgsql') {
            $sql = "
                CREATE TABLE IF NOT EXISTS user_remember_tokens (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL UNIQUE,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users_new(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS idx_expires ON user_remember_tokens (expires_at);
            ";
            $pdo->exec($sql);
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS user_remember_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users_new(id) ON DELETE CASCADE,
                    INDEX idx_token (token),
                    INDEX idx_user_id (user_id),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $pdo->exec($sql);
        }
        return true;
    } catch (Exception $e) {
        error_log("Failed to create remember tokens table: " . $e->getMessage());
        return false;
    }
}

// Initialize remember tokens table
createRememberTokensTable();

// Auto-check remember me token on every page load
checkRememberMeToken();
?>