<?php
// Diagnostics: Try writing a reset token for a given admin email
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$result = [
    'ok' => false,
    'error' => null,
    'email' => null,
    'updated' => false,
    'token' => null,
    'expiry' => null,
    'reset_link' => null,
    'mail_attempted' => false,
    'mail_sent' => false,
    'now' => date('c'),
];

require_once __DIR__ . '/../../includes/db.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$dryRun = isset($_GET['dry_run']) ? filter_var($_GET['dry_run'], FILTER_VALIDATE_BOOLEAN) : true; // default: do not update
$doSend = isset($_GET['send']) ? filter_var($_GET['send'], FILTER_VALIDATE_BOOLEAN) : false; // default: do not send

$result['email'] = $email;

try {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Provide a valid email via ?email=you@example.com');
    }
    if (!isset($pdo)) { throw new Exception('Database connection not initialized'); }

    // Ensure required columns exist
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [];
        try {
            if ($driver === 'mysql') {
                foreach ($pdo->query('DESCRIBE `admin_users`') as $row) { $columns[] = strtolower($row['Field']); }
            } elseif ($driver === 'pgsql') {
                $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'admin_users' ORDER BY ordinal_position");
                $stmt->execute();
                foreach ($stmt as $row) { $columns[] = strtolower($row['column_name']); }
            } else {
                foreach ($pdo->query('PRAGMA table_info(admin_users)') as $row) { $columns[] = strtolower($row['name']); }
            }
        } catch (Exception $ignore) {}

        if (!in_array('reset_token', $columns, true)) {
            $pdo->exec($driver === 'mysql' ? 'ALTER TABLE admin_users ADD COLUMN reset_token VARCHAR(255) NULL' : 'ALTER TABLE admin_users ADD COLUMN reset_token TEXT');
        }
        if (!in_array('reset_token_expiry', $columns, true)) {
            $pdo->exec($driver === 'mysql' ? 'ALTER TABLE admin_users ADD COLUMN reset_token_expiry DATETIME NULL' : 'ALTER TABLE admin_users ADD COLUMN reset_token_expiry TEXT');
        }
        if (!in_array('is_active', $columns, true)) {
            if ($driver === 'mysql') { $pdo->exec('ALTER TABLE admin_users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); }
            elseif ($driver === 'pgsql') { $pdo->exec('ALTER TABLE admin_users ADD COLUMN is_active INT NOT NULL DEFAULT 1'); }
            else { $pdo->exec('ALTER TABLE admin_users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1'); }
        }
    } catch (Exception $schemaEx) {
        // Proceed; we will still try update to surface errors
    }

    // Lookup admin by email (case-insensitive)
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE LOWER(email) = LOWER(?)');
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) { throw new Exception('Admin not found for that email'); }
    if (isset($admin['is_active']) && (int)$admin['is_active'] !== 1) { throw new Exception('Admin is not active'); }

    // Generate token and expiry
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $result['token'] = $token;
    $result['expiry'] = $expiry;

    // Build canonical reset link (based on current host)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    $path = '/admin-reset-password.php';
    $result['reset_link'] = sprintf('%s://%s%s?token=%s', $scheme, $host, $path, $token);

    if (!$dryRun) {
        $update = $pdo->prepare('UPDATE admin_users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?');
        $result['updated'] = $update->execute([$token, $expiry, $admin['id']]);
    }

    // Optional mail send
    if ($doSend) {
        // Bypass direct access guard in mail_helper by defining INCLUDES_PATH
        if (!defined('INCLUDES_PATH')) { define('INCLUDES_PATH', __DIR__ . '/../../includes'); }
        if (file_exists(__DIR__ . '/../../includes/mail.php')) { require_once __DIR__ . '/../../includes/mail.php'; }
        
        $result['mail_attempted'] = true;
        $subject = 'Admin Password Reset';
        $body = '<p>You requested a password reset.</p><p>Reset Link: <a href="' . htmlspecialchars($result['reset_link']) . '">Reset Password</a></p>';
        if (function_exists('send_confirmation_email')) {
            $result['mail_sent'] = (bool)send_confirmation_email($email, $subject, $body);
        }
    }

    $result['ok'] = true;
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>