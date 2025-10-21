<?php
define('INCLUDES_PATH', true);
session_start();
require_once 'includes/db.php';

$token = $_GET['token'] ?? '';
$showForm = false;
$success = '';
$error = '';

if ($token) {
    $stmt = $pdo->prepare('SELECT id, reset_token_expires FROM users WHERE reset_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user && strtotime($user['reset_token_expires']) > time()) {
        $showForm = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (!$password || !$confirm) {
                $error = 'Please fill in all fields.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
                $stmt->execute([$hash, $user['id']]);
                $success = 'Your password has been reset. You can now <a href="login.php">login</a>.';
                $showForm = false;
            }
        }
    } else {
        $error = 'Invalid or expired reset link.';
    }
} else {
    $error = 'Invalid or expired reset link.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - Blood Donation System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow">
        <div class="card-header bg-danger text-white text-center">
          <h3>Reset Password</h3>
        </div>
        <div class="card-body">
          <?php if ($success): ?>
            <div class="alert alert-success text-center"><?php echo $success; ?></div>
          <?php elseif ($error): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
          <?php endif; ?>
          <?php if ($showForm): ?>
          <form action="" method="POST" autocomplete="on">
            <div class="mb-3">
              <label for="password" class="form-label">New Password</label>
              <input type="password" class="form-control" id="password" name="password" required minlength="6">
            </div>
            <div class="mb-3">
              <label for="confirm_password" class="form-label">Confirm New Password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="btn btn-danger w-100">Reset Password</button>
          </form>
          <?php endif; ?>
          <div class="mt-3 text-center">
            <a href="login.php">Back to Login</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 