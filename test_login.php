<?php
// TEST LOGIN SCRIPT - Shows actual errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

echo "<!DOCTYPE html><html><head><title>Test Login</title></head><body>";
echo "<h1>Test Admin Login</h1><pre>";

try {
    echo "Step 1: Checking admin_users table...\n";
    $stmt = $pdo->query("SELECT * FROM admin_users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "❌ Admin user NOT found!\n";
        echo "\nAll users in database:\n";
        $stmt = $pdo->query("SELECT id, username, email FROM admin_users");
        $users = $stmt->fetchAll();
        foreach ($users as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}\n";
        }
        die();
    }
    
    echo "✅ Admin user found!\n\n";
    echo "Admin Details:\n";
    echo "- ID: {$admin['id']}\n";
    echo "- Username: {$admin['username']}\n";
    echo "- Email: {$admin['email']}\n";
    echo "- Password Hash: " . substr($admin['password'], 0, 30) . "...\n";
    echo "- Hash Length: " . strlen($admin['password']) . " chars\n\n";
    
    echo "Step 2: Testing password verification...\n";
    $test_passwords = [
        'admin123',
        'admin123*',
        'admin 123*'
    ];
    
    foreach ($test_passwords as $test_pass) {
        $result = password_verify($test_pass, $admin['password']);
        $status = $result ? "✅ MATCH!" : "❌ No match";
        echo "- Testing '$test_pass': $status\n";
    }
    
    echo "\nStep 3: Checking password hash validity...\n";
    $info = password_get_info($admin['password']);
    echo "- Algorithm: {$info['algoName']}\n";
    echo "- Is Valid: " . ($info['algo'] > 0 ? "Yes" : "No") . "\n";
    
    if ($info['algo'] == 0) {
        echo "\n❌ PASSWORD IS NOT HASHED!\n";
        echo "The password is stored as plain text: {$admin['password']}\n";
        echo "This is why login fails!\n\n";
        echo "SOLUTION: Run fix_my_password.php to hash it properly\n";
    }
    
    echo "\n=====================================\n";
    echo "Summary:\n";
    echo "- Database: ✅ Working\n";
    echo "- Admin User: ✅ Found\n";
    
    // Check which password works
    $working_pass = null;
    foreach ($test_passwords as $test_pass) {
        if (password_verify($test_pass, $admin['password'])) {
            $working_pass = $test_pass;
            break;
        }
    }
    
    if ($working_pass) {
        echo "- Working Password: ✅ '$working_pass'\n";
        echo "\n✅ YOU CAN LOGIN WITH: $working_pass\n";
    } else {
        echo "- Working Password: ❌ None of the test passwords work\n";
        echo "\n❌ PASSWORD ISSUE DETECTED\n";
    }
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";
echo "<p><a href='admin_login.php'>Try Admin Login</a></p>";
echo "</body></html>";
?>

