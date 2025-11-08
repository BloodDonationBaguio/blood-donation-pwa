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
    // Check if donors_new exists, otherwise use donors table
    $donorsTable = 'donors'; // Default to 'donors' table which likely exists
    
    try {
        // For PostgreSQL - check if table exists before querying
        $checkTable = $pdo->prepare("SELECT to_regclass('public.donors_new') AS exists");
        $checkTable->execute();
        $tableResult = $checkTable->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($tableResult['exists'])) {
            $donorsTable = 'donors_new';
        }
    } catch (Exception $e) {
        // If error checking table, stick with default 'donors'
        error_log("Table check error: " . $e->getMessage());
    }
    
    $donations = $pdo->prepare("
        SELECT * FROM $donorsTable 
        WHERE LOWER(email) = LOWER(?) 
        ORDER BY id DESC
    ");
    $donations->execute([$user['email']]);
    $donation_history = $donations->fetchAll();
    
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
    $errorTrace = $e->getTraceAsString();
    
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

// Blood requests feature removed - no longer needed
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
        <!-- Donation History Card -->
        <div class="col-lg-8 col-md-12">
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
</div>

<?php // Profile editing removed per request ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
