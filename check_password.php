<?php
// SUPER SIMPLE PASSWORD CHECK - NO INCLUDES!
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Manual database connection
$database_url = getenv('DATABASE_URL');

if (!$database_url) {
    die("NO DATABASE_URL!");
}

$db = parse_url($database_url);
$host = $db['host'];
$port = isset($db['port']) ? $db['port'] : 5432;
$dbname = ltrim($db['path'], '/');
$user = $db['user'];
$pass = $db['pass'];

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<h1>Password Check</h1><pre>";
    
    $stmt = $pdo->query("SELECT * FROM admin_users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "ADMIN USER NOT FOUND!\n";
        die();
    }
    
    echo "Admin found!\n\n";
    echo "Password hash: " . substr($admin['password'], 0, 30) . "...\n\n";
    
    echo "Testing passwords:\n";
    
    $passwords = ['admin123', 'admin123*', 'admin 123*'];
    foreach ($passwords as $pw) {
        $match = password_verify($pw, $admin['password']);
        echo "- '$pw': " . ($match ? "WORKS!" : "no") . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>

