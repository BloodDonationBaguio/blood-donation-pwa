<?php
// TEST ADMIN LOGIN - Shows what's happening
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Admin Login Test</h1><pre>";

// Test 1: Database connection
echo "1. Testing database connection...\n";
try {
    require_once 'db.php';
    echo "✅ Database connected\n\n";
} catch (Exception $e) {
    echo "❌ Database failed: " . $e->getMessage() . "\n";
    die();
}

// Test 2: Check if POST works
echo "2. Check if this is a POST request...\n";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "✅ POST request detected\n";
    echo "Username: " . ($_POST['username'] ?? 'NOT SET') . "\n";
    echo "Password: " . (isset($_POST['password']) ? '[PROVIDED]' : 'NOT SET') . "\n\n";
    
    // Test 3: Check credentials
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        echo "3. Looking up admin user...\n";
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                echo "✅ User found!\n";
                echo "   ID: {$admin['id']}\n";
                echo "   Username: {$admin['username']}\n";
                echo "   Password hash: " . substr($admin['password'], 0, 20) . "...\n\n";
                
                echo "4. Testing password...\n";
                $passwordValid = password_verify($password, $admin['password']);
                
                if ($passwordValid) {
                    echo "✅ PASSWORD CORRECT!\n\n";
                    echo "5. Testing session...\n";
                    session_start();
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    echo "✅ Session set\n\n";
                    
                    echo "6. Testing redirect...\n";
                    echo "Would redirect to: admin.php\n";
                    echo "<a href='admin.php'>Click here to go to admin.php</a>\n";
                } else {
                    echo "❌ PASSWORD INCORRECT\n";
                }
            } else {
                echo "❌ User not found\n";
            }
        } catch (Exception $e) {
            echo "❌ Database error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Username or password empty\n";
    }
} else {
    echo "❌ Not a POST request (showing form below)\n\n";
}

echo "</pre>";

// Show test form
?>
<form method="POST" action="">
    <h2>Test Login Form</h2>
    <div>
        <label>Username:</label><br>
        <input type="text" name="username" value="admin" required><br><br>
    </div>
    <div>
        <label>Password:</label><br>
        <input type="password" name="password" placeholder="Enter password" required><br><br>
    </div>
    <button type="submit">Test Login</button>
</form>

<hr>
<p><a href="admin-login.php">Back to Admin Login</a></p>

