<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');
    $stmt->execute([$notif_id, $user_id]);
}
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
header('Location: ' . $redirect);
exit();
