<?php
/**
 * Send Donor Message System
 * Handles sending various types of messages to donors
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/advanced_mail.php';

// Set content type for JSON responses
header('Content-Type: application/json');

/**
 * Send a message to a donor
 */
function sendDonorMessage($donorId, $messageType, $subject, $message, $adminId = null) {
    global $pdo;
    
    try {
        // Get donor information
        $stmt = $pdo->prepare("SELECT * FROM donors_new WHERE id = ?");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donor) {
            return ['success' => false, 'message' => 'Donor not found'];
        }
        
        // Get admin information if provided
        $adminName = 'System Administrator';
        if ($adminId) {
            $adminStmt = $pdo->prepare("SELECT full_name FROM admin_users WHERE id = ?");
            $adminStmt->execute([$adminId]);
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            if ($admin) {
                $adminName = $admin['full_name'];
            }
        }
        
        // Prepare email content
        $emailContent = generateEmailTemplate($messageType, $donor, $message, $adminName);
        
        // Send email using advanced mail system
        $mailer = new AdvancedMail();
        $result = $mailer->sendEmail(
            $donor['email'],
            $subject,
            $emailContent,
            true // isHTML
        );
        
        if ($result['success']) {
            // Log the message in database
            $logStmt = $pdo->prepare("
                INSERT INTO donor_messages (donor_id, message_type, subject, message, admin_id, sent_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([$donorId, $messageType, $subject, $message, $adminId]);
            
            return ['success' => true, 'message' => 'Message sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email: ' . $result['message']];
        }
        
    } catch (Exception $e) {
        error_log("Send donor message error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error occurred'];
    }
}

/**
 * Generate email template based on message type
 */
function generateEmailTemplate($messageType, $donor, $message, $adminName) {
    $donorName = $donor['first_name'] . ' ' . $donor['last_name'];
    $referenceCode = $donor['reference_code'];
    
    switch ($messageType) {
        case 'welcome':
            return generateWelcomeEmail($donorName, $referenceCode, $message);
            
        case 'status_update':
            return generateStatusUpdateEmail($donorName, $referenceCode, $message);
            
        case 'appointment_reminder':
            return generateAppointmentReminderEmail($donorName, $referenceCode, $message);
            
        case 'thank_you':
            return generateThankYouEmail($donorName, $referenceCode, $message);
            
        case 'general':
        default:
            return generateGeneralEmail($donorName, $referenceCode, $message, $adminName);
    }
}

/**
 * Generate welcome email template
 */
function generateWelcomeEmail($donorName, $referenceCode, $message) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Welcome to Blood Donation Program</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .highlight { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to Blood Donation Program</h1>
                <p>Philippine Red Cross - Baguio Chapter</p>
            </div>
            <div class='content'>
                <h2>Dear $donorName,</h2>
                <p>Welcome to our blood donation program! We're excited to have you join our community of life-savers.</p>
                
                <div class='highlight'>
                    <strong>Your Reference Code:</strong> $referenceCode<br>
                    <strong>Important:</strong> Please keep this reference code safe. You'll need it to track your application status.
                </div>
                
                <p>$message</p>
                
                <p>Thank you for your commitment to saving lives through blood donation.</p>
                
                <p>Best regards,<br>
                Philippine Red Cross - Baguio Chapter Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the Blood Donation System.</p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Generate status update email template
 */
function generateStatusUpdateEmail($donorName, $referenceCode, $message) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Application Status Update</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .status-box { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0; }
        </style>
    </head>
    <body>
                    <div class='container'>
                        <div class='header'>
                <h1>Application Status Update</h1>
                <p>Philippine Red Cross - Baguio Chapter</p>
                        </div>
                        <div class='content'>
                <h2>Dear $donorName,</h2>
                <p>We have an update regarding your blood donation application.</p>
                
                <div class='status-box'>
                    <strong>Reference Code:</strong> $referenceCode<br>
                    <strong>Status Update:</strong><br>
                    $message
                </div>
                
                <p>You can track your application status from 8:00 AM to 5:00 PM using your reference code.</p>
                
                <p>Thank you for your patience and commitment to blood donation.</p>
                
                <p>Best regards,<br>
                Philippine Red Cross - Baguio Chapter Team</p>
                        </div>
                        <div class='footer'>
                <p>This is an automated message from the Blood Donation System.</p>
            </div>
                        </div>
    </body>
    </html>";
}

/**
 * Generate appointment reminder email template
 */
function generateAppointmentReminderEmail($donorName, $referenceCode, $message) {
                return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Appointment Reminder</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .reminder-box { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0; }
        </style>
    </head>
    <body>
                    <div class='container'>
                        <div class='header'>
                <h1>Appointment Reminder</h1>
                <p>Philippine Red Cross - Baguio Chapter</p>
                        </div>
                        <div class='content'>
                <h2>Dear $donorName,</h2>
                <p>This is a friendly reminder about your upcoming blood donation appointment.</p>
                
                <div class='reminder-box'>
                    <strong>Reference Code:</strong> $referenceCode<br>
                    <strong>Appointment Details:</strong><br>
                    $message
                </div>
                
                <p>Please arrive 15 minutes before your scheduled time and bring a valid ID.</p>
                
                <p>Thank you for your commitment to saving lives!</p>
                
                <p>Best regards,<br>
                Philippine Red Cross - Baguio Chapter Team</p>
                        </div>
                        <div class='footer'>
                <p>This is an automated message from the Blood Donation System.</p>
                        </div>
                    </div>
    </body>
    </html>";
}

/**
 * Generate thank you email template
 */
function generateThankYouEmail($donorName, $referenceCode, $message) {
                return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Thank You for Your Donation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .thank-you-box { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0; text-align: center; }
        </style>
    </head>
    <body>
                    <div class='container'>
                        <div class='header'>
                <h1>Thank You for Your Donation!</h1>
                <p>Philippine Red Cross - Baguio Chapter</p>
                        </div>
                        <div class='content'>
                <h2>Dear $donorName,</h2>
                
                <div class='thank-you-box'>
                    <h3>🎉 Thank You! 🎉</h3>
                    <p>Your blood donation has made a real difference in someone's life.</p>
                </div>
                
                <p><strong>Reference Code:</strong> $referenceCode</p>
                
                <p>$message</p>
                
                <p>Your selfless act of donating blood helps save lives and brings hope to patients and their families.</p>
                
                <p>We look forward to seeing you again for your next donation!</p>
                
                <p>With heartfelt gratitude,<br>
                Philippine Red Cross - Baguio Chapter Team</p>
                        </div>
                        <div class='footer'>
                <p>This is an automated message from the Blood Donation System.</p>
                        </div>
                    </div>
    </body>
    </html>";
}

/**
 * Generate general email template
 */
function generateGeneralEmail($donorName, $referenceCode, $message, $adminName) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Message from Blood Donation System</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .message-box { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Message from Blood Donation System</h1>
                <p>Philippine Red Cross - Baguio Chapter</p>
            </div>
            <div class='content'>
                <h2>Dear $donorName,</h2>
                
                <div class='message-box'>
                    <p>$message</p>
                </div>
                
                <p><strong>Reference Code:</strong> $referenceCode</p>
                
                <p>If you have any questions, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>
                $adminName<br>
                Philippine Red Cross - Baguio Chapter</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the Blood Donation System.</p>
            </div>
        </div>
    </body>
    </html>";
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($_POST['action']) {
        case 'send_message':
            if (isset($_POST['donor_id'], $_POST['message_type'], $_POST['subject'], $_POST['message'])) {
                $adminId = $_SESSION['admin_id'] ?? null;
                $response = sendDonorMessage(
                    $_POST['donor_id'],
                    $_POST['message_type'],
                    $_POST['subject'],
                    $_POST['message'],
                    $adminId
                );
            } else {
                $response = ['success' => false, 'message' => 'Missing required parameters'];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
    }
    
    echo json_encode($response);
    exit;
}

// If accessed directly, return error
http_response_code(403);
echo json_encode(['success' => false, 'message' => 'Access denied']);
exit;
?>