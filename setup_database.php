<?php
// Database Setup Script for Blood Donation System
// This script creates all necessary tables for the application

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db.php';

echo "<h1>Blood Donation System - Database Setup</h1>";
echo "<p>Setting up database tables...</p>";

try {
    // 1. Create users_new table
    echo "<h2>Creating users_new table...</h2>";
    $sql_users = "
    CREATE TABLE IF NOT EXISTS users_new (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_users);
    echo "✅ users_new table created successfully<br>";
    
    // 2. Create user_remember_tokens table
    echo "<h2>Creating user_remember_tokens table...</h2>";
    $sql_tokens = "
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
    
    $pdo->exec($sql_tokens);
    echo "✅ user_remember_tokens table created successfully<br>";
    
    // 3. Create donors_new table
    echo "<h2>Creating donors_new table...</h2>";
    $sql_donors = "
    CREATE TABLE IF NOT EXISTS donors_new (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255) NOT NULL,
        last_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') NOT NULL,
        date_of_birth DATE NOT NULL,
        gender ENUM('Male', 'Female') NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        weight DECIMAL(5,2) NOT NULL,
        height DECIMAL(5,2) NOT NULL,
        reference_code VARCHAR(20) NOT NULL UNIQUE,
        status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_reference (reference_code),
        INDEX idx_status (status),
        INDEX idx_blood_type (blood_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_donors);
    echo "✅ donors_new table created successfully<br>";
    
    // 4. Create donor_medical_screening_simple table
    echo "<h2>Creating donor_medical_screening_simple table...</h2>";
    $sql_medical = "
    CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
        id INT AUTO_INCREMENT PRIMARY KEY,
        donor_id INT NOT NULL,
        reference_code VARCHAR(20) NOT NULL,
        screening_data JSON NOT NULL,
        all_questions_answered BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (donor_id) REFERENCES donors_new(id) ON DELETE CASCADE,
        INDEX idx_donor_id (donor_id),
        INDEX idx_reference (reference_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_medical);
    echo "✅ donor_medical_screening_simple table created successfully<br>";
    
    // 5. Create notifications table
    echo "<h2>Creating notifications table...</h2>";
    $sql_notifications = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users_new(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_notifications);
    echo "✅ notifications table created successfully<br>";
    
    // 6. Check table structure and add missing columns if needed
    echo "<h2>Checking table structure...</h2>";
    
    // Get current table structure
    $stmt = $pdo->query("DESCRIBE users_new");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    // Add missing columns
    if (!in_array('name', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN name VARCHAR(255) NOT NULL AFTER id");
        echo "✅ Added 'name' column<br>";
    }
    
    if (!in_array('role', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN role ENUM('user', 'admin', 'super_admin') DEFAULT 'user' AFTER password");
        echo "✅ Added 'role' column<br>";
    }
    
    if (!in_array('status', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER role");
        echo "✅ Added 'status' column<br>";
    }
    
    if (!in_array('created_at', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✅ Added 'created_at' column<br>";
    }
    
    if (!in_array('updated_at', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "✅ Added 'updated_at' column<br>";
    }
    
    if (!in_array('last_login', $existingColumns)) {
        $pdo->exec("ALTER TABLE users_new ADD COLUMN last_login TIMESTAMP NULL AFTER updated_at");
        echo "✅ Added 'last_login' column<br>";
    }
    
    // 7. Create a default admin user
    echo "<h2>Creating default admin user...</h2>";
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users_new WHERE email = ?");
    $stmt->execute(['admin@blooddonation.com']);
    
    if ($stmt->rowCount() == 0) {
        // Get the first available status value from the enum
        $stmt = $pdo->query("SHOW COLUMNS FROM users_new LIKE 'status'");
        $statusColumn = $stmt->fetch(PDO::FETCH_ASSOC);
        $statusValues = [];
        if ($statusColumn) {
            preg_match_all("/'([^']+)'/", $statusColumn['Type'], $matches);
            $statusValues = $matches[1];
        }
        $defaultStatus = !empty($statusValues) ? $statusValues[0] : 'approved';
        
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users_new (name, full_name, email, password, role, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'System Administrator',
            'System Administrator',
            'admin@blooddonation.com',
            $admin_password,
            'super_admin',
            $defaultStatus
        ]);
        echo "✅ Default admin user created successfully<br>";
        echo "<p><strong>Admin Login:</strong><br>";
        echo "Email: admin@blooddonation.com<br>";
        echo "Password: admin123</p>";
    } else {
        echo "✅ Admin user already exists<br>";
    }
    
    // 7. Create a test regular user
    echo "<h2>Creating test user...</h2>";
    
    $stmt = $pdo->prepare("SELECT id FROM users_new WHERE email = ?");
    $stmt->execute(['test@example.com']);
    
    if ($stmt->rowCount() == 0) {
        // Get the first available status value from the enum
        $stmt = $pdo->query("SHOW COLUMNS FROM users_new LIKE 'status'");
        $statusColumn = $stmt->fetch(PDO::FETCH_ASSOC);
        $statusValues = [];
        if ($statusColumn) {
            preg_match_all("/'([^']+)'/", $statusColumn['Type'], $matches);
            $statusValues = $matches[1];
        }
        $defaultStatus = !empty($statusValues) ? $statusValues[0] : 'approved';
        
        $user_password = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users_new (name, full_name, email, password, role, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Test User',
            'Test User',
            'test@example.com',
            $user_password,
            'user',
            $defaultStatus
        ]);
        echo "✅ Test user created successfully<br>";
        echo "<p><strong>Test User Login:</strong><br>";
        echo "Email: test@example.com<br>";
        echo "Password: test123</p>";
    } else {
        echo "✅ Test user already exists<br>";
    }
    
    echo "<h2>Database Setup Complete! 🎉</h2>";
    echo "<p>All tables have been created successfully. You can now:</p>";
    echo "<ul>";
    echo "<li><a href='login.php'>Login to the system</a></li>";
    echo "<li><a href='profile.php'>Access your profile</a></li>";
    echo "<li><a href='donor-registration.php'>Register as a donor</a></li>";
    echo "<li><a href='admin.php'>Access admin panel</a></li>";
    echo "</ul>";
    
    echo "<h3>Default Login Credentials:</h3>";
    echo "<p><strong>Admin:</strong> admin@blooddonation.com / admin123</p>";
    echo "<p><strong>Test User:</strong> test@example.com / test123</p>";
    
} catch (PDOException $e) {
    echo "<h2>❌ Database Setup Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection settings in <code>db.php</code></p>";
} catch (Exception $e) {
    echo "<h2>❌ Setup Failed</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
