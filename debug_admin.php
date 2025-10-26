<?php
// DEBUG SCRIPT - Delete after fixing
require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Admin Debug</title></head><body>";
echo "<h1>Admin User Debug</h1><pre>";

try {
    // Check admin_users table
    $stmt = $pdo->query("SELECT * FROM admin_users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✅ Admin user found!\n\n";
        echo "User Details:\n";
        echo "- ID: " . $admin['id'] . "\n";
        echo "- Username: " . $admin['username'] . "\n";
        echo "- Email: " . $admin['email'] . "\n";
        echo "- Full Name: " . $admin['full_name'] . "\n";
        echo "- Role: " . $admin['role'] . "\n";
        echo "- Password Hash: " . substr($admin['password'], 0, 20) . "...\n";
        echo "- Hash Length: " . strlen($admin['password']) . " chars\n\n";
        
        // Test password verification
        echo "Password Tests:\n";
        $test_passwords = ['admin123', 'admin123*', 'admin 123*'];
        
        foreach ($test_passwords as $test_pass) {
            $result = password_verify($test_pass, $admin['password']);
            $status = $result ? "✅ MATCH" : "❌ NO MATCH";
            echo "- Testing '$test_pass': $status\n";
        }
        
        echo "\n";
        echo "Expected Password Hash for 'admin123':\n";
        echo password_hash('admin123', PASSWORD_DEFAULT) . "\n\n";
        
        echo "Actual Password Hash in DB:\n";
        echo $admin['password'] . "\n\n";
        
        // Check if hash is valid
        $info = password_get_info($admin['password']);
        echo "Hash Info:\n";
        echo "- Algorithm: " . $info['algoName'] . "\n";
        echo "- Valid Hash: " . ($info['algo'] > 0 ? "Yes" : "No") . "\n";
        
    } else {
        echo "❌ Admin user NOT found!\n";
        echo "Available users:\n";
        $stmt = $pdo->query("SELECT username FROM admin_users");
        $users = $stmt->fetchAll();
        foreach ($users as $user) {
            echo "- " . $user['username'] . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='admin_login.php'>Back to Login</a></p>";
echo "</body></html>";
?>

