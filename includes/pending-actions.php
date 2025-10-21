<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 1);

// Set up error logging
$logFile = __DIR__.'/pending_actions_debug.log';
ini_set('error_log', $logFile);

// Ensure logs directory exists
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Function to log debug information
function log_debug($message, $data = null) {
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    if ($data !== null) {
        $logMessage .= 'Data: ' . print_r($data, true) . "\n";
    }
    
    // Log to both the specific log file and PHP's error log
    $logFile = $GLOBALS['logFile'];
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    error_log($logMessage);
    
    // Also log to the general error log in the logs directory
    $generalLog = $GLOBALS['logDir'] . '/error.log';
    file_put_contents($generalLog, $logMessage, FILE_APPEND);
}

// Log the request with detailed information
log_debug('Script started', [
    'POST' => $_POST,
    'GET' => $_GET,
    'FILES' => $_FILES,
    'SERVER' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
        'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'N/A',
        'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A'
    ]
]);

// Function to send JSON response
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    
    // Ensure output is clean before sending JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode($data);
    exit;
}

// Include database connection
if (!@include_once(__DIR__.'/../db.php')) {
    throw new Exception('Failed to include db.php');
}

// Verify database connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new Exception('Database connection not properly initialized');
}

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
        $id = intval($_POST['request_id']);
        if (isset($_POST['approve_request'])) {
        // Get form data
        $admin_note = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';
        $appointment_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
        $appointment_time = !empty($_POST['appointment_time']) ? $_POST['appointment_time'] : null;
        
        // Update request with appointment information
        $stmt = $pdo->prepare("UPDATE requests 
                              SET status='fulfilled', 
                                  note=?, 
                                  appointment_date=?, 
                                  appointment_time=?,
                                  updated_at=NOW() 
                              WHERE id=?");
        $stmt->execute([$admin_note, $appointment_date, $appointment_time, $id]);
        // Fetch user info
        $info = $pdo->prepare("SELECT * FROM requests WHERE id=?");
        $info->execute([$id]);
        $req = $info->fetch();
        if ($req) {
            // Send email if email exists
            if (!empty($req['email'])) {
                require_once('mail_helper.php');
                $ref_code = 'REQ-' . str_pad($id, 5, '0', STR_PAD_LEFT);
                $appointmentDate = !empty($_POST['appointment_date']) ? date('F j, Y', strtotime($_POST['appointment_date'])) : '';
                $appointmentTime = !empty($_POST['appointment_time']) ? $_POST['appointment_time'] : '';
                
                $subject = "Blood Donation Appointment Scheduled - $ref_code";
                $msg = "<div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>";
                $msg .= "<h2 style='color: #d9230f;'>Your Blood Donation Appointment</h2>"
                     . "<p>Dear " . htmlspecialchars($req['full_name']) . ",</p>"
                     . "<p>Your blood request (Reference: <b>$ref_code</b>) has been approved.</p>";
                
                if (!empty($appointmentDate) && !empty($appointmentTime)) {
                    $msg .= "<div style='background-color: #f8f9fa; border-left: 4px solid #d9230f; padding: 15px; margin: 15px 0;'>"
                         . "<h3 style='margin-top: 0; color: #d9230f;'><i class='far fa-calendar-alt'></i> Your Appointment Details</h3>"
                         . "<p><strong>Date:</strong> $appointmentDate</p>"
                         . "<p><strong>Time:</strong> $appointmentTime</p>"
                         . "<p><strong>Location:</strong> Red Cross Baguio Office</p>"
                         . "</div>";
                }
                
                if (!empty($admin_note)) {
                    $msg .= "<div style='background-color: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 4px;'>"
                         . "<p style='margin: 0;'><strong>Additional Instructions:</strong><br>" . nl2br(htmlspecialchars($admin_note)) . "</p>"
                         . "</div>";
                }
                
                $msg .= "<h3 style='color: #d9230f;'>What to Bring:</h3>"
                      . "<ul>"
                      . "<li>Valid ID (passport, driver's license, or any government-issued ID)</li>"
                      . "<li>Your reference number: <b>$ref_code</b></li>"
                      . "<li>List of current medications (if any)</li>"
                      . "</ul>";
                      
                $msg .= "<div style='background-color: #f0f7ff; padding: 15px; margin: 15px 0; border-radius: 4px;'>"
                     . "<p style='margin: 0;'><strong>Note:</strong> Please arrive 10 minutes before your scheduled time. If you need to reschedule, please contact us at least 24 hours in advance.</p>"
                     . "</div>";
                     
                $msg .= "<p>Thank you for choosing to donate blood. Your contribution will help save lives.</p>";
                $msg .= "</div>";
                send_confirmation_email($req['email'], $subject, $msg);
            }
            // Insert notification if user_id exists
            if (!empty($req['user_id'])) {
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif->execute([$req['user_id'], 'Your blood request (Ref: REQ-'.str_pad($id, 5, '0', STR_PAD_LEFT).') has been approved.']);
            }
        }
        $tab = isset($_POST['tab']) ? $_POST['tab'] : 'pending-requests';
        
        if ($isAjax) {
            send_json_response([
                'success' => true,
                'message' => 'Request approved successfully!',
                'action' => 'approve',
                'request_id' => $id
            ]);
        } else {
            header('Location: ../admin-dashboard.php?tab=' . urlencode($tab) . '&success=1');
            exit();
        }
    }
    
    if (isset($_POST['reject_request'])) {
        // Mark as rejected and add admin note
        $admin_note = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';
        $stmt = $pdo->prepare("UPDATE requests SET status='rejected', note=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$admin_note, $id]);
        
        // Fetch user info
        $info = $pdo->prepare("SELECT * FROM requests WHERE id=?");
        $info->execute([$id]);
        $req = $info->fetch();
        
        if ($req) {
            // Send email if email exists
            if (!empty($req['email'])) {
                require_once('mail_helper.php');
                $ref_code = 'REQ-' . str_pad($id, 5, '0', STR_PAD_LEFT);
                $subject = "Update on Your Blood Request - $ref_code";
                $msg = "<div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>"
                     . "<h2 style='color: #d9230f;'>Update on Your Blood Request</h2>"
                     . "<p>Dear " . htmlspecialchars($req['full_name']) . ",</p>"
                     . "<p>We regret to inform you that your blood request (Reference: <b>$ref_code</b>) has been declined.</p>";
                
                if (!empty($admin_note)) {
                    $msg .= "<div style='background-color: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 4px;'>"
                         . "<p style='margin: 0;'><strong>Reason for Declination:</strong><br>" . nl2br(htmlspecialchars($admin_note)) . "</p>"
                         . "</div>";
                }
                
                $msg .= "<h3 style='color: #d9230f;'>Next Steps:</h3>"
                      . "<ul>"
                      . "<li><strong>Reference Number:</strong> Please keep this for your records: <b>$ref_code</b></li>"
                      . "<li>If you believe this was a mistake or have additional information to provide, please don't hesitate to contact us.</li>"
                      . "<li>For any questions or to submit a new request, please visit our office or contact us directly.</li>"
                      . "</ul>"
                      . "<p>We appreciate your understanding and encourage you to consider future donation opportunities.</p>"
                      . "<p>Thank you for considering blood donation.</p>"
                      . "<p>Sincerely,<br>The Red Cross Baguio Team</p>"
                      . "</div>";
                send_confirmation_email($req['email'], $subject, $msg);
            }
            
            // Insert notification if user_id exists
            if (!empty($req['user_id'])) {
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif->execute([$req['user_id'], 'Your blood request (Ref: REQ-'.str_pad($id, 5, '0', STR_PAD_LEFT).') has been rejected.']);
            }
        }
        
        $tab = isset($_POST['tab']) ? $_POST['tab'] : 'blood-requests';
        
        if ($isAjax) {
            send_json_response([
                'success' => true,
                'message' => 'Request rejected successfully!',
                'action' => 'reject',
                'request_id' => $id
            ]);
        } else {
            header('Location: ../admin-dashboard.php?tab=' . urlencode($tab) . '&rejected=1');
            exit();
        }
        
        // Prepare success response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // AJAX request - return JSON
            // AJAX request - return JSON
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Action completed successfully',
                'redirect' => '../admin-dashboard.php?tab=' . urlencode($tab) . '&success=1'
            ]);
        } else {
            // Regular form submission - redirect
            header('Location: ../admin-dashboard.php?tab=' . urlencode($tab) . '&success=1');
        }
        exit();
    }
    
    // If we get here, it means no valid action was taken
    throw new Exception('No valid action specified');
}
} catch (Exception $e) {
    log_debug('Error in pending-actions.php', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Return error response
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // AJAX request - return JSON
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ]);
    } else {
        // Regular form submission - show error
        header('Location: ../admin-dashboard.php?tab=blood-requests&error=' . urlencode('An error occurred: ' . $e->getMessage()));
    }
    exit();
}
