<?php
// RESET ADMIN PASSWORD SCRIPT - Delete after use
require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Reset Admin Password</title></head><body>";
echo "<h1>Reset Admin Password</h1><pre>";

try {
    // Delete existing admin user
    echo "Removing old admin user...\n";
    $pdo->exec("DELETE FROM admin_users WHERE username = 'admin'");
    echo "✓ Old admin removed\n\n";
    
    // Create fresh admin user
    echo "Creating new admin user...\n";
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, full_name, role) 
                          VALUES (?, ?, ?, ?, 'super_admin')");
    $stmt->execute(['admin', $password, 'admin@blooddonation.com', 'System Administrator']);
    
    echo "✓ Admin user created\n\n";
    echo "=====================================\n";
    echo "✅ PASSWORD RESET COMPLETE!\n";
    echo "=====================================\n\n";
    echo "Login Credentials:\n";
    echo "- Username: admin\n";
    echo "- Password: admin123\n\n";
    echo "Password Hash: " . $password . "\n\n";
    
    // Verify it works
    echo "Testing password verification...\n";
    if (password_verify('admin123', $password)) {
        echo "✅ Password verification WORKS!\n";
    } else {
        echo "❌ Password verification FAILED!\n";
    }
    
    echo "\n⚠️ DELETE THIS FILE NOW!\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='admin_login.php'>Go to Admin Login</a></p>";
echo "</body></html>";
?>

