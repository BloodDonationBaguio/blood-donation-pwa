<?php
// track-donor.php
require_once 'db.php';
$ref = '';
$status = '';
$error = '';
$donor = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref = trim($_POST['reference']);
    if ($ref) {
        // Check reference_code field
        $stmt = $pdo->prepare('SELECT * FROM donors_new WHERE reference_code = ?');
        $stmt->execute([$ref]);
        $donor = $stmt->fetch();
        
        if (!$donor) {
            // Check if this might be a blood request reference
            $stmt = $pdo->prepare('SELECT * FROM blood_requests WHERE reference_code = ?');
            $stmt->execute([$ref]);
            $request = $stmt->fetch();
            
            if ($request) {
                $error = 'This reference number belongs to a blood request, not a donor registration. Please use the <a href="track-request.php">Blood Request Tracking</a> page instead.';
            } else {
                $error = 'No donor registration found with this reference number. Please check your reference number and try again.';
            }
        } else {
            $status = ucfirst($donor['status'] ?? 'pending');
            
            // Get user details if available
            $user = null;
            if (!empty($donor['user_id'])) {
                $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $stmt->execute([$donor['user_id']]);
                $user = $stmt->fetch();
            }
        }
    } else {
        $error = 'Please enter a reference number.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Donor Registration - Red Cross Baguio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .status-pending { color: #ffc107; font-weight: 500; }
        .status-approved { color: #198754; font-weight: 500; }
        .status-rejected { color: #dc3545; font-weight: 500; }
        .status-served { color: #0dcaf0; font-weight: 500; }
        .status-unserved { color: #fd7e14; font-weight: 500; }
        .info-card { 
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
        .tracking-icon {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="tracking-icon">
                            <i class="bi bi-heart-pulse"></i>
                        </div>
                        <h2 class="h4 fw-bold mb-2">Track Donor Registration</h2>
                        <p class="text-muted">Enter your reference number to check the status of your donor registration</p>
                    </div>
                    
                    <form method="POST" class="mb-4">
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control form-control-lg rounded-start-pill px-4" 
                                   name="reference" 
                                   placeholder="Enter reference number" 
                                   value="<?= htmlspecialchars($ref) ?>" 
                                   required>
                            <button type="submit" class="btn btn-primary px-4 rounded-end-pill">
                                <i class="bi bi-search me-2"></i> Track
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-3">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php elseif ($donor): ?>
                        <div class="info-card">
                            <h5 class="fw-bold mb-3">
                                Registration Status: 
                                <span class="status-<?= strtolower($status) ?>">
                                    <?= $status ?>
                                    <?php if ($status === 'Pending'): ?>
                                        <i class="bi bi-hourglass-split"></i>
                                    <?php elseif ($status === 'Approved'): ?>
                                        <i class="bi bi-check-circle-fill"></i>
                                    <?php elseif ($status === 'Rejected'): ?>
                                        <i class="bi bi-x-circle-fill"></i>
                                    <?php elseif ($status === 'Served'): ?>
                                        <i class="bi bi-heart-fill"></i>
                                    <?php elseif ($status === 'Unserved'): ?>
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    <?php endif; ?>
                                </span>
                            </h5>
                            
                            <?php if ($status === 'Pending'): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Your application is being processed.</strong> Our medical team is reviewing your information. You will receive an email notification once the review is complete.
                                </div>
                            <?php elseif ($status === 'Approved'): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>Your application has been approved.</strong> You can visit Red Cross chapter from 8:00 AM to 5:00 PM to complete your donation. Please bring your ID and this reference number.
                                </div>
                            <?php elseif ($status === 'Rejected'): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-x-circle me-2"></i>
                                    <strong>Application Status: Not Approved</strong>
                                    <?php if (!empty($donor['rejection_reason'])): ?>
                                        <br><strong>Reason:</strong> <?= htmlspecialchars($donor['rejection_reason']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($status === 'Served'): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-heart me-2"></i>
                                    <strong>Thank you for your donation!</strong> Your blood donation has been completed successfully.
                                </div>
                            <?php elseif ($status === 'Unserved'): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Donation Status: Unserved</strong>
                                    <?php if (!empty($donor['unserved_reason'])): ?>
                                        <br><strong>Reason:</strong> <?= htmlspecialchars($donor['unserved_reason']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Reference Number:</strong><br>
                                        <?= htmlspecialchars($donor['reference_code'] ?? $donor['reference_number'] ?? $donor['reference'] ?? 'N/A') ?>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Name:</strong><br>
                                        <?= htmlspecialchars($donor['full_name']) ?>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Blood Type:</strong><br>
                                        <?= htmlspecialchars($donor['blood_type']) ?: 'Not specified' ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Registration Date:</strong><br>
                                        <?= date('F j, Y', strtotime($donor['created_at'])) ?>
                                    </p>
                                    <?php if ($user && !empty($user['email'])): ?>
                                    <p class="mb-2">
                                        <strong>Email:</strong><br>
                                        <?= htmlspecialchars($user['email']) ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php if (!empty($donor['city'])): ?>
                                    <p class="mb-2">
                                        <strong>Location:</strong><br>
                                        <?= htmlspecialchars($donor['city']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($donor['note'])): ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        <div>
                                            <strong>Note from Admin:</strong>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($donor['note'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4 pt-3 border-top text-center">
                                <p class="text-muted small mb-0">
                                    For any questions, please contact us at 
                                    <a href="mailto:info@redcrossbaguio.org">info@redcrossbaguio.org</a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-outline-primary px-4 rounded-pill">
                                <i class="bi bi-house-door me-2"></i>Back to Home
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
