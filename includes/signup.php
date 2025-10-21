<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once(__DIR__ . '/../db.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!$name || !$email || !$password) {
        header('Location: ../signup.html?error=empty');
        exit();
    }
    try {
        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users_new WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            header('Location: ../signup.html?error=exists');
            exit();
        }
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users_new (name, email, password) VALUES (?, ?, ?)');
        if ($stmt->execute([$name, $email, $password_hash])) {
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            header('Location: ../dashboard.php?signup=success');
            exit();
        } else {
            header('Location: ../signup.html?error=server');
            exit();
        }
    } catch (Exception $e) {
        header('Location: ../signup.html?error=exception');
        exit();
    }
}
header('Location: ../signup.html');
exit();
