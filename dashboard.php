<?php
// Disable error display to prevent output before headers
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Include necessary files
require_once 'db.php';
require_once 'includes/session_manager.php';
require_once 'pg_compat.php'; // Add PostgreSQL compatibility layer

// Require user to be logged in
requireUserLogin();

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: login.php?error=not_logged_in');
    exit();
}

try {
    // Fetch user info - use CAST for PostgreSQL compatibility with numeric IDs
    $stmt = $pdo->prepare('SELECT * FROM users_new WHERE id = CAST(? AS INTEGER)');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Clear session but don't redirect to prevent loops
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // Use JavaScript to clear cookies and redirect
        echo "<script>
            document.cookie.split(';').forEach(function(c) {
                document.cookie = c.trim().split('=')[0] + '=;' + 'expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/';
            });
            window.location.href = 'login.php?error=session_cleared';
        </script>";
        exit();
    }
    
    // Fetch user donation history with PostgreSQL compatible query
    // Use LOWER for case-insensitive email comparison in PostgreSQL
    $donations = $pdo->prepare('
        SELECT * FROM donors_new 
        WHERE LOWER(email) = LOWER(?) 
        ORDER BY id DESC
    ');
    $donations->execute([$user['email']]);
    $donation_history = $donations->fetchAll();
    
} catch (Exception $e) {
    // Log the error but don't redirect to prevent loops
    error_log("Dashboard error: " . $e->getMessage());
    
    // Send proper content type header
    header('Content-Type: text/html; charset=UTF-8');
    
    // Display a complete HTML page with user-friendly error message
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>System Error - Blood Donation System</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding-top: 50px; 
                background-color: #f8f9fa;
            }
            .error-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
            }
            .btn-primary {
                background-color: #dc3545;
                border-color: #dc3545;
            }
            .btn-primary:hover {
                background-color: #c82333;
                border-color: #bd2130;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='error-container'>
                <h2 class='mb-4'>System Error</h2>
                <p class='mb-4'>We're experiencing technical difficulties with our database connection. Please try again later.</p>
                <a href='logout.php' class='btn btn-primary'>Logout and Try Again</a>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

// Blood requests feature removed - no longer needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Donation Dashboard - Philippine Red Cross Baguio Chapter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('https://www.redcross.org.ph/wp-content/uploads/2021/06/blood-donation.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            margin-bottom: 50px;
        }
        .eligibility-card {
            border-left: 4px solid #dc3545;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .eligibility-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .testimonial-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center">
        <h1 class="display-4 fw-bold mb-4">Welcome, <?= htmlspecialchars($user['name']) ?>!</h1>
        <p class="lead mb-5">Your contributions help save lives. Thank you for being a part of our life-saving mission.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="donor-registration.php" class="btn btn-danger btn-lg px-4">Donate Now</a>
            <a href="track.php" class="btn btn-outline-light btn-lg px-4">Track Application</a>
        </div>
    </div>
</section>

<!-- Welcome Message -->
<div class="container">
    <?php if (isset($_GET['login']) || isset($_GET['signup'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Welcome, <?= htmlspecialchars($user['name']) ?>!</strong> You are now logged in.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
</div>

<!-- Main Content -->
<div class="container py-5">
    <div class="row justify-content-center g-4">
        <!-- Profile Card -->
        <div class="col-lg-6 col-md-12">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-center align-items-center mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="background:#f5f6fa;width:56px;height:56px;">
                        <i class="bi bi-person-circle fs-2 text-primary"></i>
                    </span>
                </div>
                <h5 class="fw-semibold mb-3 text-center">Profile</h5>
                <div class="mb-2"><b>Name:</b> <?= htmlspecialchars($user['name']) ?></div>
                <div class="mb-2"><b>Email:</b> <?= htmlspecialchars($user['email']) ?></div>
                <div class="mb-3"><b>Joined:</b> <?= htmlspecialchars($user['created_at']) ?></div>
                <button class="btn btn-outline-primary w-100 rounded-pill" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="bi bi-pencil-square me-1"></i> Edit Profile
                </button>
            </div>
        </div>

        <!-- Donation History Card -->
        <div class="col-lg-6 col-md-12">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-center align-items-center mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="background:#f5f6fa;width:56px;height:56px;">
                        <i class="bi bi-droplet-fill fs-2 text-info"></i>
                    </span>
                </div>
                <h5 class="fw-semibold mb-3 text-center">Donation History</h5>
                <?php if (isset($donation_history) && is_array($donation_history) && count($donation_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Date</th>
                                    <th>Blood Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donation_history as $don): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($don['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($don['blood_type']) ?></td>
                                        <td><span class="badge bg-success">Registered</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center">No donation records found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="post" action="dashboard.php">
              <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label for="editName" class="form-label">Full Name</label>
                  <input type="text" class="form-control" id="editName" name="edit_name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="mb-3">
                  <label for="editEmail" class="form-label">Email</label>
                  <input type="email" class="form-control" id="editEmail" name="edit_email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="mb-3">
                  <label for="editPassword" class="form-label">New Password</label>
                  <input type="password" class="form-control" id="editPassword" name="edit_password" placeholder="Leave blank to keep current password">
                </div>
                <div class="mb-3">
                  <label for="currentPassword" class="form-label">Current Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                  <div class="form-text">Enter your current password to save changes.</div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
    </div>
</div>

<?php
// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_name'], $_POST['edit_email'], $_POST['current_password'])) {
  $edit_name = trim($_POST['edit_name']);
  $edit_email = trim($_POST['edit_email']);
  $edit_password = $_POST['edit_password'];
  $current_password = $_POST['current_password'];
  $error = '';
  $success = '';
  // Check current password
  if (!password_verify($current_password, $user['password'])) {
    $error = 'Current password is incorrect.';
  } else {
    $update_query = 'UPDATE users_new SET name = ?, email = ?';
    $params = [$edit_name, $edit_email];
    if (!empty($edit_password)) {
      $update_query .= ', password = ?';
      $params[] = password_hash($edit_password, PASSWORD_DEFAULT);
    }
    $update_query .= ' WHERE id = ?';
    $params[] = $user_id;
    $update = $pdo->prepare($update_query);
    if ($update->execute($params)) {
      $success = 'Profile updated successfully!';
      $_SESSION['user_name'] = $edit_name;
      $_SESSION['user_email'] = $edit_email;
      // Refresh user data
      $stmt = $pdo->prepare('SELECT * FROM users_new WHERE id = ?');
      $stmt->execute([$user_id]);
      $user = $stmt->fetch();
    } else {
      $error = 'Failed to update profile.';
    }
  }
  if ($error) {
    echo '<div class="alert alert-danger mt-3">' . htmlspecialchars($error) . '</div>';
  } elseif ($success) {
    echo '<div class="alert alert-success mt-3">' . htmlspecialchars($success) . '</div>';
  }
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
