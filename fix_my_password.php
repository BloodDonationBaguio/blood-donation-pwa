<?php
// FIX PASSWORD SCRIPT - Sets password to admin123*
require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Fix Password</title></head><body>";
echo "<h1>Fix Admin Password</h1><pre>";

try {
    // Set password to admin123* (with proper hash)
    echo "Fixing admin password...\n";
    $password = password_hash('admin123*', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$password]);
    
    echo "✓ Password updated\n\n";
    echo "=====================================\n";
    echo "✅ PASSWORD FIXED!\n";
    echo "=====================================\n\n";
    echo "Login Credentials:\n";
    echo "- Username: admin\n";
    echo "- Password: admin123*\n\n";
    
    // Verify it works
    echo "Testing password verification...\n";
    if (password_verify('admin123*', $password)) {
        echo "✅ Password 'admin123*' verification WORKS!\n";
    } else {
        echo "❌ Password verification FAILED!\n";
    }
    
    // Check what's in database
    echo "\nVerifying database...\n";
    $stmt = $pdo->query("SELECT username, email FROM admin_users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    if ($admin) {
        echo "✅ Admin user exists: " . $admin['username'] . " (" . $admin['email'] . ")\n";
    }
    
    echo "\n⚠️ DELETE THIS FILE NOW!\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='admin_login.php'>Go to Admin Login</a></p>";
echo "</body></html>";
?>

