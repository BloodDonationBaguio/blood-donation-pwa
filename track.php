<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
require_once 'db.php';

$error = '';
$donor = null;
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['reference'])) {
        $ref = trim($_POST['reference']);
        
        // Check if it's a donor reference
        $stmt = $pdo->prepare('SELECT * FROM donors_new WHERE reference_code = ?');
        $stmt->execute([$ref]);
        $donor = $stmt->fetch();
        
        if ($donor) {
            $type = 'donor';
        } else {
            $error = 'No record found with this reference number. Please check your reference number and try again.';
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
    <title>Track Application - Red Cross Baguio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .tracking-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 40px 0;
        }
        
        .tracking-header {
            background: white;
            color: #333;
            padding: 50px 40px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .tracking-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }
        
        .tracking-content {
            padding: 40px;
            background: white;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }
        
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-top: 20px;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-served {
            background: #cfe2ff;
            color: #084298;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-unserved {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-deferred {
            background: #f8d7da;
            color: #721c24;
        }
        
        .type-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            margin: 0;
        }
        
        .info-value {
            color: #212529;
            margin: 0;
            text-align: right;
        }
        
        @media (max-width: 768px) {
            .tracking-container {
                margin: 20px 0;
                border-radius: 15px;
            }
            
            .tracking-header {
                padding: 30px 20px;
            }
            
            .tracking-content {
                padding: 30px 20px;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .info-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="tracking-container">
                <div class="tracking-header">
                    <div class="text-center mb-4">
                        <div style="background: linear-gradient(135deg, #dc3545, #ff6b6b); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 8px 25px rgba(220,53,69,0.3);">
                            <span style="color: white; font-size: 36px; font-weight: bold;">♥</span>
                        </div>
                        <h2 class="h4 fw-bold mb-2" style="color: #dc3545; font-size: 2rem;">Track Your Application</h2>
                        <p class="text-muted">Enter your reference number to check the status of your donor registration</p>
                    </div>
                    
                    <form method="POST" class="mb-4">
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   name="reference" 
                                   placeholder="Enter your reference number"
                                   value="<?= htmlspecialchars($_POST['reference'] ?? '') ?>"
                                   required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search me-2"></i>Track
                            </button>
                        </div>
                    </form>
                </div>
                    
                <div class="tracking-content">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php elseif ($donor): ?>
                        <!-- Donor Information Display -->
                        <div class="info-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="fw-bold mb-0">
                                    Donor Status: 
                                    <?php 
                                    $statusClass = 'warning';
                                    $statusText = ucfirst($donor['status'] ?? 'pending');
                                    
                                    switch(strtolower($donor['status'] ?? 'pending')) {
                                        case 'approved':
                                            $statusClass = 'success';
                                            $statusText = 'Approved';
                                            break;
                                        case 'served':
                                            $statusClass = 'success';
                                            $statusText = 'Served';
                                            break;
                                        case 'unserved':
                                            $statusClass = 'danger';
                                            $statusText = 'Unserved';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'danger';
                                            $statusText = 'Deferred';
                                            break;
                                        case 'pending':
                                            $statusClass = 'warning';
                                            $statusText = 'Pending';
                                            break;
                                        default:
                                            $statusClass = 'secondary';
                                            $statusText = ucfirst($donor['status'] ?? 'Unknown');
                                    }
                                    ?>
                                    <span class="status-<?= strtolower($donor['status'] ?? 'pending') ?>">
                                        <?= htmlspecialchars($statusText) ?>
                                    </span>
                                </h5>
                                <span class="badge bg-success type-badge">Donor Registration</span>
                            </div>
                            
                            <?php if (strtolower($donor['status'] ?? 'pending') === 'pending'): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Your application is being reviewed.</strong> Our team is processing your registration and will contact you soon.
                                </div>
                            <?php elseif (strtolower($donor['status'] ?? 'pending') === 'approved'): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Your application has been approved!</strong> You can now visit the Red Cross Baguio Chapter from <strong>8:00 AM to 5:00 PM</strong> to complete your blood donation. Please bring a valid ID and your reference number.
                                </div>
                            <?php elseif (strtolower($donor['status'] ?? 'pending') === 'served'): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-heart me-2"></i>
                                    <strong>Thank you for your donation!</strong> Your blood donation has been completed successfully. You are now an active blood donor. Thank you for helping save lives!
                                </div>
                            <?php elseif (strtolower($donor['status'] ?? 'pending') === 'rejected'): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-times-circle me-2"></i>
                                    <strong>Your application has been deferred.</strong> 
                                    <?php if (!empty($donor['remarks'])): ?>
                                        <br><strong>Reason:</strong> <?= htmlspecialchars($donor['remarks']) ?>
                                    <?php endif; ?>
                                    <br>Please contact us for more information or reapply when eligible.
                                </div>
                            <?php elseif (strtolower($donor['status'] ?? 'pending') === 'unserved'): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>Your application is temporarily unserved.</strong> 
                                    <?php if (!empty($donor['remarks'])): ?>
                                        <br><strong>Reason:</strong> <?= htmlspecialchars($donor['remarks']) ?>
                                    <?php endif; ?>
                                    <br>Please contact us for more information.
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <p class="info-label">Reference Number:</p>
                                        <p class="info-value"><?= htmlspecialchars($donor['reference_code'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="info-row">
                                        <p class="info-label">Full Name:</p>
                                        <p class="info-value"><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></p>
                                    </div>
                                    <div class="info-row">
                                        <p class="info-label">Blood Type:</p>
                                        <p class="info-value"><?= htmlspecialchars($donor['blood_type'] ?? 'Not specified') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <p class="info-label">Registration Date:</p>
                                        <p class="info-value"><?= date('F j, Y', strtotime($donor['created_at'])) ?></p>
                                    </div>
                                    <div class="info-row">
                                        <p class="info-label">Email:</p>
                                        <p class="info-value"><?= htmlspecialchars($donor['email']) ?></p>
                                    </div>
                                    <div class="info-row">
                                        <p class="info-label">Phone:</p>
                                        <p class="info-value"><?= htmlspecialchars($donor['phone'] ?? 'Not provided') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($donor): ?>
                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-outline-primary px-4 rounded-pill">
                                <i class="fas fa-home me-2"></i>Back to Home
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