<?php
define('INCLUDES_PATH', true);
session_start();
require_once 'includes/db.php';
require_once 'includes/mail_helper.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $error = 'Please enter your email address.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $error = 'If that email is registered, a reset link will be sent.';
        } else {
            // Generate token and expiry
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
            $stmt->execute([$token, $expires, $user['id']]);
            // Send email
            $resetLink = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset-password.php?token=$token";
            $subject = 'Password Reset Request';
            $message = "<p>Hello " . htmlspecialchars($user['name']) . ",</p>" .
                "<p>We received a request to reset your password. Click the link below to set a new password:</p>" .
                "<p><a href='$resetLink'>$resetLink</a></p>" .
                "<p>If you did not request this, you can ignore this email.</p>";
            send_confirmation_email($email, $subject, $message, $user['name']);
            $success = 'If that email is registered, a reset link will be sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - Blood Donation System</title>
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
          <h3>Forgot Password</h3>
        </div>
        <div class="card-body">
          <?php if ($success): ?>
            <div class="alert alert-success text-center"><?php echo $success; ?></div>
          <?php elseif ($error): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
          <?php endif; ?>
          <form action="" method="POST" autocomplete="on">
            <div class="mb-3">
              <label for="email" class="form-label">Enter your registered email address</label>
              <input type="email" class="form-control" id="email" name="email" required autofocus>
            </div>
            <button type="submit" class="btn btn-danger w-100">Send Reset Link</button>
          </form>
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