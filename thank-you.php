<?php
// Start the session to access session variables if needed
session_start();

// Get the reference number from the URL
$ref = isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Registering - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .thank-you-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
            text-align: center;
        }
        .success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .reference-number {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
            margin: 20px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: inline-block;
        }
        .btn-return {
            margin-top: 20px;
            padding: 10px 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="thank-you-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="mb-4">Thank You for Registering!</h1>
            <p class="lead">Your registration as a blood donor has been received successfully.</p>
            
            <?php if (!empty($ref)): ?>
                <p>Your reference number is:</p>
                <div class="reference-number"><?php echo $ref; ?></div>
                <p>Please keep this number for your records.</p>
            <?php endif; ?>
            
            <p>We appreciate your willingness to help save lives through blood donation.</p>
            
            <a href="index.php" class="btn btn-danger btn-return">
                <i class="fas fa-home me-2"></i>Return to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
