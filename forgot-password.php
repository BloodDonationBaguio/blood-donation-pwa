<?php
define('INCLUDES_PATH', true);
session_start();
require_once 'includes/db.php';
require_once 'includes/mail_helper.php';

$success = '';
$error = '';

// Ensure required columns exist on users_new to prevent SQL errors
try {
    if (isset($pdo)) {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [];
        if (function_exists('getTableStructure')) {
            $struct = getTableStructure($pdo, 'users_new');
            foreach ($struct as $col) {
                if (isset($col['Field'])) { $columns[] = strtolower($col['Field']); }
                elseif (isset($col['column_name'])) { $columns[] = strtolower($col['column_name']); }
                elseif (isset($col['name'])) { $columns[] = strtolower($col['name']); }
            }
        }
        $hasResetToken   = in_array('reset_token', $columns, true);
        $hasResetExpires = in_array('reset_token_expires', $columns, true);

        if (!$hasResetToken) {
            if ($driver === 'mysql') { $pdo->exec("ALTER TABLE users_new ADD COLUMN reset_token VARCHAR(255) NULL"); }
            else { $pdo->exec("ALTER TABLE users_new ADD COLUMN reset_token TEXT"); }
        }
        if (!$hasResetExpires) {
            if ($driver === 'mysql') { $pdo->exec("ALTER TABLE users_new ADD COLUMN reset_token_expires DATETIME NULL"); }
            else { $pdo->exec("ALTER TABLE users_new ADD COLUMN reset_token_expires TEXT"); }
        }
    }
} catch (Exception $schemaEx) {
    error_log('users_new schema ensure (forgot-password) failed: ' . $schemaEx->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $error = 'Please enter your email address.';
    } else {
        try {
            // Check if user exists (case-insensitive)
            $stmt = $pdo->prepare('SELECT id, name, email FROM users_new WHERE LOWER(email) = LOWER(?)');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                // Do not reveal whether the email exists
                $error = 'If that email is registered, a reset link will be sent.';
            } else {
                // Generate token and expiry
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                $stmt = $pdo->prepare('UPDATE users_new SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
                $stmt->execute([$token, $expires, $user['id']]);
                // Send email
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $resetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset-password.php?token=$token";
                $subject = 'Password Reset Request';
                $message = "<p>Hello " . htmlspecialchars($user['name']) . ",</p>" .
                    "<p>We received a request to reset your password. Click the link below to set a new password:</p>" .
                    "<p><a href='$resetLink'>$resetLink</a></p>" .
                    "<p>If you did not request this, you can ignore this email.</p>";
                send_confirmation_email($user['email'], $subject, $message, $user['name']);
                $success = 'If that email is registered, a reset link will be sent.';
            }
        } catch (Exception $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $error = 'An error occurred while processing your request. Please try again later.';
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