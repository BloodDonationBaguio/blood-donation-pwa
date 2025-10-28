<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/session_manager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!$name || !$email || !$password) {
        header('Location: signup.php?error=empty');
        exit();
    }
    try {
        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users_new WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            header('Location: signup.php?error=exists');
            exit();
        }
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users_new (name, email, password) VALUES (?, ?, ?)');
        if ($stmt->execute([$name, $email, $password_hash])) {
            $userId = $pdo->lastInsertId();
            
            // Get the newly created user
            $stmt = $pdo->prepare('SELECT * FROM users_new WHERE id = ?');
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch();
            
            // Login the user automatically (with remember me for convenience)
            loginUser($newUser, true);
            
            header('Location: dashboard.php?signup=success');
            exit();
        } else {
            header('Location: signup.php?error=server');
            exit();
        }
    } catch (Exception $e) {
        header('Location: signup.php?error=exception');
        exit();
    }
}
header('Location: signup.php');
exit();
