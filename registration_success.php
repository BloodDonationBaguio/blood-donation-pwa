<?php
// Start session
session_start();

// Include database connection
require_once __DIR__ . '/includes/db.php';

// Check if reference number is provided
$reference = $_GET['ref'] ?? '';

if (empty($reference)) {
    header('Location: index.php');
    exit;
}

// Try to get donor information directly from donors_new table
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(first_name, ' ', last_name) as full_name, 
        status, 
        created_at,
        blood_type,
        email,
        phone
    FROM donors 
    WHERE reference_code = ?
");
$stmt->execute([$reference]);
$donor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donor) {
    // If not found, don't redirect immediately - show a message
    $notFound = true;
}

// Format the creation date if donor found
if (!empty($donor)) {
    $createdAt = new DateTime($donor['created_at']);
    $formattedDate = $createdAt->format('F j, Y \a\t g:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-card {
            max-width: 600px;
            margin: 2rem auto;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .success-body {
            padding: 2rem;
            background: white;
        }
        .reference-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
            margin: 1rem 0;
            padding: 1rem;
            background-color: #e8f5e9;
            border-radius: 5px;
            text-align: center;
        }
        .steps {
            margin: 2rem 0;
            padding: 0;
            list-style: none;
        }
        .steps li {
            margin-bottom: 1.5rem;
            padding-left: 2.5rem;
            position: relative;
        }
        .steps li:before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 0;
            color: #28a745;
        }
        .btn-home {
            background: linear-gradient(135deg, #d62d20 0%, #a91b12 100%);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            border-radius: 50px;
            margin-top: 1rem;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <div class="container py-5">
        <div class="success-card">
            <div class="success-header">
                <i class="fas fa-check-circle fa-4x mb-3"></i>
                <h2>Registration Submitted Successfully!</h2>
                <p class="mb-0">Thank you for your willingness to donate blood and save lives.</p>
            </div>
            
            <div class="success-body">
                <?php if (!empty($donor)): ?>
                <p>Hello <strong><?php echo htmlspecialchars($donor['full_name']); ?></strong>,</p>
                <p>Your donor registration has been received on <strong><?php echo $formattedDate; ?></strong> and is currently being reviewed by our team.</p>
                <?php else: ?>
                <p>Your donor registration has been received and is currently being reviewed by our team.</p>
                <?php endif; ?>
                
                <div class="reference-number">
                    Your Reference Number: <span id="refNumber"><?php echo htmlspecialchars($reference); ?></span>
                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard()" data-bs-toggle="tooltip" data-bs-placement="top" title="Copy to clipboard">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
                
                <h5 class="mt-4 mb-3">What Happens Next?</h5>
                <ol class="steps">
                    <li><strong>Review Process</strong>: Our medical team will review your information to ensure donor eligibility.</li>
                    <li><strong>Email Notification</strong>: You will receive an email with the next steps once your application is reviewed.</li>
                </ol>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Need to check your status?</strong> Use the reference number above to track your application status on our website.
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-home text-white">
                        <i class="fas fa-home me-2"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Blood Donation System</h5>
                    <p class="mb-0">Saving lives through blood donation.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Copy reference number to clipboard
    function copyToClipboard() {
        const refNumber = document.getElementById('refNumber').textContent;
        navigator.clipboard.writeText(refNumber).then(function() {
            // Show success message
            const tooltip = bootstrap.Tooltip.getInstance(event.target);
            const originalTitle = event.target.getAttribute('data-bs-original-title');
            
            event.target.setAttribute('data-bs-original-title', 'Copied!');
            tooltip.show();
            
            // Revert back after 2 seconds
            setTimeout(function() {
                event.target.setAttribute('data-bs-original-title', originalTitle);
                tooltip.hide();
            }, 2000);
        });
    }
    </script>
</body>
</html>
