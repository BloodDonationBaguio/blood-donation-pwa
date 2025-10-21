<?php
/**
 * Create Admin User Script
 * This will create an admin user in the database
 */

echo "<h2>👤 Create Admin User</h2>";

try {
    require_once 'db.php';
    echo "<p style='color: green;'>✅ Database connected</p>";
    
    // Check if admin_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() === 0) {
        echo "<p style='color: red;'>❌ admin_users table not found. Creating it...</p>";
        
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
        echo "<p style='color: green;'>✅ admin_users table created</p>";
    } else {
        echo "<p style='color: green;'>✅ admin_users table exists</p>";
    }
    
    // Create default admin user
    $username = 'admin';
    $password = 'admin123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ Admin user already exists. Updating password...</p>";
        
        // Update existing user
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, role = 'super_admin', is_active = 1 WHERE username = ?");
        $stmt->execute([$hashedPassword, $username]);
        echo "<p style='color: green;'>✅ Admin user password updated</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Creating new admin user...</p>";
        
        // Insert new admin user
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (username, password, role, email, full_name, is_active) 
            VALUES (?, ?, 'super_admin', ?, ?, 1)
        ");
        $stmt->execute([$username, $hashedPassword, 'admin@example.com', 'System Administrator']);
        echo "<p style='color: green;'>✅ Admin user created successfully</p>";
    }
    
    // Verify the user was created
    $stmt = $pdo->prepare("SELECT username, role, is_active FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<h3>✅ Admin User Created Successfully!</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr><th style='padding: 10px; background: #f0f0f0;'>Property</th><th style='padding: 10px; background: #f0f0f0;'>Value</th></tr>";
        echo "<tr><td style='padding: 10px;'>Username</td><td style='padding: 10px;'>" . htmlspecialchars($user['username']) . "</td></tr>";
        echo "<tr><td style='padding: 10px;'>Role</td><td style='padding: 10px;'>" . htmlspecialchars($user['role']) . "</td></tr>";
        echo "<tr><td style='padding: 10px;'>Status</td><td style='padding: 10px;'>" . ($user['is_active'] ? 'Active' : 'Inactive') . "</td></tr>";
        echo "<tr><td style='padding: 10px;'>Password</td><td style='padding: 10px;'>admin123</td></tr>";
        echo "</table>";
        
        echo "<h3>🎉 Login Credentials:</h3>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "</div>";
        
        echo "<h3>🚀 Next Steps:</h3>";
        echo "<ol>";
        echo "<li><a href='admin_login_fixed.php' target='_blank'>Try Login</a></li>";
        echo "<li><a href='admin.php' target='_blank'>Go to Admin Panel</a></li>";
        echo "<li><a href='test_admin_login.php'>Test Login Again</a></li>";
        echo "</ol>";
        
    } else {
        echo "<p style='color: red;'>❌ Failed to create admin user</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
