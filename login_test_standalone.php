<?php
// STANDALONE LOGIN TEST - NO INCLUDES, NO HEADERS
// Start session FIRST before any output
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Manual database connection
$database_url = getenv('DATABASE_URL');
if (!$database_url) {
    die("DATABASE_URL not set!");
}

$db = parse_url($database_url);
$pdo = new PDO(
    "pgsql:host={$db['host']};port=" . ($db['port'] ?? 5432) . ";dbname=" . ltrim($db['path'], '/'),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>LOGIN TEST</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f0f0f0; }
        pre { background: white; padding: 15px; border: 2px solid #333; }
        form { background: white; padding: 20px; border: 2px solid green; margin-top: 20px; }
        input { padding: 8px; margin: 5px 0; width: 200px; }
        button { padding: 10px 20px; background: #dc3545; color: white; border: none; cursor: pointer; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 STANDALONE LOGIN TEST</h1>
    
    <pre><?php
    
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "=== POST REQUEST RECEIVED ===\n\n";
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "Username entered: " . htmlspecialchars($username) . "\n";
    echo "Password entered: " . (empty($password) ? '[EMPTY]' : '[PROVIDED]') . "\n\n";
    
    if (empty($username) || empty($password)) {
        echo "<span class='error'>❌ Username or password is empty!</span>\n";
    } else {
        try {
            echo "Looking up user in database...\n";
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                echo "<span class='success'>✅ User found!</span>\n";
                echo "  - ID: {$admin['id']}\n";
                echo "  - Username: {$admin['username']}\n";
                echo "  - Email: {$admin['email']}\n";
                echo "  - Password hash: " . substr($admin['password'], 0, 25) . "...\n\n";
                
                echo "Testing password verification...\n";
                if (password_verify($password, $admin['password'])) {
                    echo "<span class='success'>✅ PASSWORD IS CORRECT!</span>\n\n";
                    
                    // Set session variables (session already started at top)
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    echo "Session variables set:\n";
                    echo "  - admin_logged_in: " . ($_SESSION['admin_logged_in'] ? 'true' : 'false') . "\n";
                    echo "  - admin_id: {$_SESSION['admin_id']}\n";
                    echo "  - admin_username: {$_SESSION['admin_username']}\n\n";
                    
                    echo "<span class='success'>✅ LOGIN SUCCESSFUL!</span>\n\n";
                    echo "=== MANUAL NEXT STEPS ===\n";
                    echo "1. <a href='admin.php' style='color: green; font-weight: bold;'>Click here to go to admin.php</a>\n";
                    echo "2. Or manually visit: https://blooddonationbaguio.com/admin.php\n";
                    
                } else {
                    echo "<span class='error'>❌ PASSWORD IS WRONG!</span>\n\n";
                    echo "The password you entered does NOT match the hash in the database.\n";
                    echo "\nTested passwords against hash:\n";
                    $test_passes = ['admin123', 'admin123*', 'admin 123*'];
                    foreach ($test_passes as $tp) {
                        $match = password_verify($tp, $admin['password']) ? '✅ WORKS' : '❌ no';
                        echo "  - '$tp': $match\n";
                    }
                }
            } else {
                echo "<span class='error'>❌ User '$username' NOT FOUND in database!</span>\n\n";
                
                echo "Users in database:\n";
                $stmt = $pdo->query("SELECT username, email FROM admin_users");
                $users = $stmt->fetchAll();
                foreach ($users as $u) {
                    echo "  - {$u['username']} ({$u['email']})\n";
                }
            }
            
        } catch (PDOException $e) {
            echo "<span class='error'>❌ DATABASE ERROR:</span>\n";
            echo $e->getMessage() . "\n";
        }
    }
    
} else {
    echo "=== READY TO TEST LOGIN ===\n\n";
    echo "Fill out the form below and click 'TEST LOGIN'\n";
}

    ?></pre>
    
    <form method="POST" action="">
        <h2>🔐 Test Login Form</h2>
        <div>
            <label><strong>Username:</strong></label><br>
            <input type="text" name="username" value="admin" required>
        </div>
        <div>
            <label><strong>Password:</strong></label><br>
            <input type="password" name="password" placeholder="Enter password here" required>
        </div>
        <br>
        <button type="submit">🚀 TEST LOGIN</button>
    </form>
    
    <hr>
    <p><a href="admin-login.php">← Back to real admin login</a></p>
</body>
</html>
<?php
?>

