<?php
// Set timezone to Baguio, Philippines
require_once __DIR__ . '/config/timezone.php';

define('INCLUDES_PATH', true);
session_start();
require_once 'includes/db.php';
require_once 'includes/mail_helper.php';

$success = '';
$error = '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Lightweight diagnostics: visit this page with ?diag=1 to check runtime status
if (isset($_GET['diag']) && $_GET['diag'] == '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forgot Password Diagnostics\n";
    echo "PHP version: " . phpversion() . "\n";
    try {
        if (!isset($pdo)) {
            echo "PDO: NOT INITIALIZED\n";
        } else {
            echo "PDO: initialized\n";
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            echo "Driver: " . $driver . "\n";
            // Basic connectivity
            try {
                $pdo->query('SELECT 1');
                echo "Connectivity: OK (SELECT 1)\n";
            } catch (Exception $exSel) {
                echo "Connectivity error: " . $exSel->getMessage() . "\n";
            }
            // Check users_new table and key columns
            try {
                $exists = function_exists('tableExists') ? tableExists($pdo, 'users_new') : false;
                echo "users_new exists: " . ($exists ? 'YES' : 'NO') . "\n";
                if ($exists) {
                    $one = $pdo->query('SELECT id, name, email FROM users_new LIMIT 1')->fetch();
                    echo "sample row: " . json_encode($one) . "\n";
                    // Columns snapshot
                    $cols = [];
                    if (function_exists('getTableStructure')) {
                        foreach (getTableStructure($pdo, 'users_new') as $c) {
                            if (isset($c['Field'])) { $cols[] = strtolower($c['Field']); }
                            elseif (isset($c['column_name'])) { $cols[] = strtolower($c['column_name']); }
                            elseif (isset($c['name'])) { $cols[] = strtolower($c['name']); }
                        }
                    }
                    echo "columns: " . implode(', ', $cols) . "\n";
                }
            } catch (Exception $exTbl) {
                echo "users_new check error: " . $exTbl->getMessage() . "\n";
            }
        }
    } catch (Exception $ex) {
        echo "Diagnostics exception: " . $ex->getMessage() . "\n";
    }
    echo "\nTip: Mail logs are in logs/mail_debug.log and logs/email_errors.log\n";
    exit;
}

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
    error_log('users_new schema ensure (__zip_restore forgot-password) failed: ' . $schemaEx->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $error = 'Please enter your email address.';
    } else {
        // Check if user exists (case-insensitive) on users_new
        try {
            $stmt = $pdo->prepare('SELECT id, name, email FROM users_new WHERE LOWER(email) = LOWER(?)');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                // Explicitly inform when email is not registered
                $error = 'The entered email is not registered.';
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
                send_confirmation_email($user['email'] ?? $email, $subject, $message, $user['name']);
                $success = 'A reset link has been sent to your email.';
            }
        } catch (Exception $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $error = 'An error occurred while processing your request. Please try again later.';
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => (bool)$success,
            'message' => $success ?: $error,
        ]);
        exit;
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
  <style>
    .btn-loading{
      position: relative;
      pointer-events: none;
      opacity: .85;
    }
    .btn-loading .spinner-border{ width: 1rem; height: 1rem; border-width: .2rem; margin-right: .5rem; }
  </style>
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
          <form id="forgotForm" action="" method="POST" autocomplete="on">
            <div class="mb-3">
              <label for="email" class="form-label">Enter your registered email address</label>
              <input type="email" class="form-control" id="email" name="email" required autofocus>
            </div>
            <button id="submitBtn" type="submit" class="btn btn-danger w-100">
              <span class="btn-text">Send Reset Link</span>
            </button>
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
<script>
  (function(){
    const form = document.getElementById('forgotForm');
    const btn  = document.getElementById('submitBtn');
    const btnText = btn.querySelector('.btn-text');
    form?.addEventListener('submit', async function(e){
      e.preventDefault();
      const email = document.getElementById('email').value.trim();
      if(!email){ return; }

      // loading state
      btn.classList.add('btn-loading');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ email })
        });
        const data = await res.json().catch(()=>({ success:false, message:'Unexpected response' }));

        // Show message
        const container = document.querySelector('.card-body');
        if (container){
          const alert = document.createElement('div');
          alert.className = 'alert text-center ' + (data.success ? 'alert-success' : 'alert-danger');
          alert.textContent = data.message || (data.success ? 'A reset link has been sent to your email.' : 'An error occurred.');
          // Replace any existing alerts
          container.querySelectorAll('.alert').forEach(el=>el.remove());
          container.prepend(alert);
        }
      } catch(err) {
        console.error(err);
      } finally {
        // restore button state
        btn.disabled = false;
        btn.classList.remove('btn-loading');
        btn.innerHTML = '<span class="btn-text">Send Reset Link</span>';
      }
    });
  })();
</script>
</body>
</html>