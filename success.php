<?php
/**
 * Success Page for Blood Request Submission
 */

$reference = $_GET['ref'] ?? '';
$type = $_GET['type'] ?? 'request';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted Successfully - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        .success-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .reference-code {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: #dc3545;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="text-success mb-3">Request Submitted Successfully!</h2>
            
            <?php if (!empty($reference)): ?>
                <p class="mb-3">Your blood request has been submitted successfully. Please save your reference number:</p>
                <div class="reference-code">
                    <?= htmlspecialchars($reference) ?>
                </div>
                <p class="text-muted">Use this reference number to track your request status.</p>
            <?php else: ?>
                <p class="mb-3">Your blood request has been submitted successfully!</p>
                <p class="text-muted">Our team will review your request and contact you soon.</p>
            <?php endif; ?>
            
            <div class="mt-4">
                <h5>What's Next?</h5>
                <ul class="list-unstyled text-start">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Our team will review your request</li>
                    <li class="mb-2"><i class="fas fa-phone text-info me-2"></i>You may be contacted for additional information</li>
                    <li class="mb-2"><i class="fas fa-users text-warning me-2"></i>We'll find matching donors in your area</li>
                    <li class="mb-2"><i class="fas fa-bell text-primary me-2"></i>You'll be notified when a donor is found</li>
                </ul>
            </div>
            
            <div class="mt-4 d-grid gap-2">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>Return to Home
                </a>
                <?php if (!empty($reference)): ?>
                    <a href="track-request.php?ref=<?= urlencode($reference) ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-search me-2"></i>Track Request
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 text-muted small">
                <p>For urgent requests, please contact us directly at:<br>
                <strong>Phone:</strong> +63 74 442 7065<br>
                <strong>Email:</strong> baguio@redcross.org.ph</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>