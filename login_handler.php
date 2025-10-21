<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('db.php');
require_once('includes/session_manager.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $rememberMe = isset($_POST["remember_me"]) && $_POST["remember_me"] == "1";

    $stmt = $pdo->prepare("SELECT * FROM users_new WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Use the new session manager to login
        loginUser($user, $rememberMe);
        
        // Check if there was a redirect URL stored
        $redirectUrl = $_SESSION['redirect_after_login'] ?? 'dashboard.php?login=success';
        unset($_SESSION['redirect_after_login']);
        
        header("Location: $redirectUrl");
        exit();
    } else {
        header("Location: login.php?error=invalid");
        exit();
    }
}
header("Location: login.php");
exit();
