<?php
require_once __DIR__ . '/includes/session_manager.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$isAdmin = !empty($_SESSION['admin_username']);
header('Content-Type: application/json');
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
}
$flushed = false;
try {
    if (function_exists('opcache_reset')) { $flushed = opcache_reset(); }
} catch (Throwable $e) { /* ignore */ }
echo json_encode(['ok'=>true,'flushed'=>$flushed,'ts'=>date('Ymd-His')]);
exit;