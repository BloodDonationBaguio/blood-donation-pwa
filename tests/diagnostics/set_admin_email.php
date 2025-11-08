<?php
// Diagnostics: Update admin email address safely
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$result = [
    'ok' => false,
    'error' => null,
    'username' => null,
    'before_email' => null,
    'after_email' => null,
    'rows_affected' => 0,
    'set_active' => false,
    'dry_run' => false,
    'driver' => null,
    'now' => date('c'),
];

try {
    require_once __DIR__ . '/../../includes/db.php';
    if (!isset($pdo)) { throw new Exception('Database connection not initialized'); }

    $result['driver'] = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    $username = isset($_GET['username']) ? trim($_GET['username']) : 'admin';
    $newEmail = isset($_GET['new_email']) ? trim($_GET['new_email']) : '';
    $setActive = isset($_GET['set_active']) ? filter_var($_GET['set_active'], FILTER_VALIDATE_BOOLEAN) : true; // default: ensure active
    $dryRun = isset($_GET['dry_run']) ? filter_var($_GET['dry_run'], FILTER_VALIDATE_BOOLEAN) : false; // default: perform update

    $result['username'] = $username;
    $result['set_active'] = $setActive;
    $result['dry_run'] = $dryRun;

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Provide a valid email via ?new_email=you@example.com');
    }

    // Ensure admin exists
    $sel = $pdo->prepare('SELECT id, username, email, is_active FROM admin_users WHERE username = ? LIMIT 1');
    $sel->execute([$username]);
    $admin = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$admin) { throw new Exception('Admin user not found'); }
    $result['before_email'] = $admin['email'] ?? null;

    if ($dryRun) {
        $result['after_email'] = $newEmail;
        $result['ok'] = true;
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }

    // Perform update (also ensure is_active = 1 if requested)
    if ($setActive) {
        $upd = $pdo->prepare('UPDATE admin_users SET email = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $upd->execute([$newEmail, $admin['id']]);
    } else {
        $upd = $pdo->prepare('UPDATE admin_users SET email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $upd->execute([$newEmail, $admin['id']]);
    }

    $result['rows_affected'] = $upd->rowCount();

    // Read back
    $sel2 = $pdo->prepare('SELECT email, is_active FROM admin_users WHERE id = ?');
    $sel2->execute([$admin['id']]);
    $after = $sel2->fetch(PDO::FETCH_ASSOC);
    $result['after_email'] = $after['email'] ?? null;

    // Log the change
    error_log(sprintf('Admin email updated for username=%s from %s to %s (set_active=%s)',
        $username,
        $result['before_email'] ?? 'NULL',
        $result['after_email'] ?? 'NULL',
        $setActive ? 'true' : 'false'
    ));

    $result['ok'] = true;
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>