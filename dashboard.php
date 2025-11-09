<?php
// Root-level donor Dashboard page
// Implements logged-in user dashboard with profile and donation history

// Disable error display to prevent output before headers
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Include necessary files (root-level)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/session_manager.php';
require_once __DIR__ . '/pg_compat.php'; // PostgreSQL compatibility layer

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

    // Fetch user donation history by email with robust donors_new fallback
    // Prefer donors_new when available; otherwise fall back to donors
    $donorsTable = 'donors_new';
    try {
        // Attempt a lightweight query to confirm donors_new exists
        $pdo->query("SELECT 1 FROM donors_new LIMIT 1");
    } catch (Exception $e) {
        $donorsTable = 'donors';
    }

    $donations = $pdo->prepare("
        SELECT id, blood_type, status, created_at, updated_at
        FROM $donorsTable 
        WHERE LOWER(email) = LOWER(?) 
        ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
    ");
    $donations->execute([$user['email']]);
    $donation_history = $donations->fetchAll();

    // Compute next eligibility based on the last completed donation (90 days rule)
    $lastCompletedDate = null;
    foreach ($donation_history as $row) {
        $st = strtolower($row['status'] ?? '');
        if (in_array($st, ['completed', 'served'], true)) {
            $lastCompletedDate = $row['updated_at'] ?? $row['created_at'] ?? null;
            break; // Already ordered by latest first
        }
    }
    $daysRemaining = null;
    $nextEligibleDate = null;
    if ($lastCompletedDate) {
        $baseTs = strtotime($lastCompletedDate);
        $nextEligibleDate = date('Y-m-d', strtotime('+90 days', $baseTs));
        $today = date('Y-m-d');
        $diffDays = floor((strtotime($today) - $baseTs) / (60 * 60 * 24));
        $daysRemaining = max(0, 90 - $diffDays);
    }

} catch (Exception $e) {
    // Log the error but don't redirect to prevent loops
    $errorMessage = $e->getMessage();
    error_log("Dashboard error: " . $errorMessage);

    // Send proper content type header
    header('Content-Type: text/html; charset=UTF-8');

    // Get error details for debugging
    $errorCode = $e->getCode();
    $errorFile = basename($e->getFile());
    $errorLine = $e->getLine();

    // Create a user-friendly message but include technical details
    $userMessage = "We're experiencing technical difficulties with our database connection.";

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
                max-width: 800px;
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
            .error-details {
                margin-top: 20px;
                text-align: left;
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #ddd;
                font-family: monospace;
                font-size: 14px;
                overflow-x: auto;
            }
            .error-details pre {
                margin-bottom: 0;
                white-space: pre-wrap;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='error-container'>
                <h2 class='mb-4'>System Error</h2>
                <p class='mb-4'>{$userMessage}</p>
                <div class='error-details'>
                    <h5>Error Details (for technical support):</h5>
                    <p><strong>Error:</strong> " . htmlspecialchars($errorMessage) . "</p>
                    <p><strong>Code:</strong> {$errorCode}</p>
                    <p><strong>Location:</strong> {$errorFile} (line {$errorLine})</p>
                    <p><strong>Database Type:</strong> " . (defined('DB_TYPE') ? DB_TYPE : 'Unknown') . "</p>
                </div>
                <div class='mt-4'>
                    <a href='logout.php' class='btn btn-primary'>Logout and Try Again</a>
                </div>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
    <link rel="manifest" href="manifest.json">
    <title>History</title>
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
        .eligibility-card { border-left: 4px solid #dc3545; transition: all 0.3s ease; margin-bottom: 20px; }
        .eligibility-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .testimonial-card { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

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
        <!-- Donation History Card -->
        <div class="col-lg-8 col-md-12">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-center align-items-center mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="background:#f5f6fa;width:56px;height:56px;">
                        <i class="bi bi-droplet-fill fs-2 text-info"></i>
                    </span>
                </div>
                <h5 class="fw-semibold mb-2 text-center">Donation History</h5>
                <div class="container mt-5">
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="#dc3545" class="bi bi-droplet-fill" viewBox="0 0 16 16">
                                            <path d="M8 16a6 6 0 0 0 6-6c0-1.655-1.122-2.904-2.432-4.362C10.254 4.176 8.75 2.503 8 0 7.25 2.503 5.746 4.176 4.432 5.638 3.122 7.096 2 8.345 2 10a6 6 0 0 0 6 6zM6.646 4.646c-.376.377-1.272 1.489-2.093 2.677A4.488 4.488 0 0 1 4 10c0 .341.028.67.082.981l3.558-3.558.08-.08.08-.08-.002-.002.002.002.08-.08.08-.08 3.558-3.558A4.488 4.488 0 0 1 12 10c0-.341-.028-.67-.082-.981l-3.558 3.558-.08.08-.08.08.002.002-.002-.002-.08.08-.08.08-3.558 3.558z"/>
                                        </svg>
                                        <h2 class="mt-3">Donation History</h2>
                                    </div>
                                
                                    <?php if ($lastCompletedDate): ?>
                                        <div class="alert alert-info">
                                            You can donate again after <strong><?php echo htmlspecialchars($nextEligibleDate); ?></strong> (<?php echo $daysRemaining; ?> days remaining).
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-secondary">
                                            No completed donations yet. Once completed, you can donate again after 90 days.
                                        </div>
                                    <?php endif; ?>
                                
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
                                                        <td><?= date('M d, Y', strtotime($don['updated_at'] ?? $don['created_at'])) ?></td>
                                                        <td><?= htmlspecialchars($don['blood_type']) ?></td>
                                                        <?php 
                                                            $status = strtolower($don['status'] ?? '');
                                                            $label = 'Donation Completed';
                                                            $badge = 'bg-success';
                                                            if ($status === 'completed' || $status === 'served') {
                                                                $label = 'Donation Completed';
                                                                $badge = 'bg-success';
                                                            } elseif ($status === 'approved') {
                                                                $label = 'Approved';
                                                                $badge = 'bg-info';
                                                            } elseif ($status === 'pending') {
                                                                $label = 'Pending';
                                                                $badge = 'bg-warning';
                                                            } elseif ($status === 'rejected') {
                                                                $label = 'Rejected';
                                                                $badge = 'bg-danger';
                                                            } else {
                                                                $label = 'Donation Completed'; // fallback to requested wording
                                                                $badge = 'bg-success';
                                                            }
                                                        ?>
                                                        <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
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
                </div>

</div>

<?php /* Profile editing removed per request; page now shows history only. */ ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>