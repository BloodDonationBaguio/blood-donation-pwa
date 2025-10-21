<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session and include required files
session_start();
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/mail_helper.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'debug' => []
];

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('CSRF token validation failed');
    }

    // Validate required fields
    $required = ['donor_id', 'subject', 'message'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $donorId = (int)$_POST['donor_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // Get donor information
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM donors WHERE id = ?");
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$donor) {
        throw new Exception('Donor not found');
    }

    // Prepare email content
    $to = $donor['email'];
    $toName = $donor['full_name'];
    
    // Add a nice HTML template to the message
    $htmlMessage = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #d32f2f; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .footer { margin-top: 20px; padding: 10px; text-align: center; font-size: 12px; color: #777; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Blood Donation Center</h2>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($toName) . ",</p>
                <div>" . nl2br(htmlspecialchars($message)) . "</div>
                <p>Thank you for your support!</p>
                <p>Best regards,<br>The Blood Donation Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the Blood Donation System.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Send the email
    $mailSent = sendEmail(
        $to,
        $subject,
        $htmlMessage,
        [
            'toName' => $toName,
            'isHTML' => true,
            'altBody' => strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "\n\n", ""], $message))
        ]
    );

    if ($mailSent) {
        // Log the message in the database
        $stmt = $pdo->prepare("
            INSERT INTO donor_messages 
            (donor_id, subject, message, sent_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$donorId, $subject, $message]);

        $response['success'] = true;
        $response['message'] = 'Message sent successfully';
    } else {
        throw new Exception('Failed to send email');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400); // Bad Request
    
    // Log the error
    error_log('Send donor message error: ' . $e->getMessage());
    if (isset($e->getTraceAsString)) {
        error_log('Trace: ' . $e->getTraceAsString());
    }
}

// Output the JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
