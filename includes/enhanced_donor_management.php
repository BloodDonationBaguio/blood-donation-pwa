<?php
// Mark this include context as internal to bypass direct-access guard in mail helper
if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', true);
}
/**
 * Enhanced Donor Management System
 * Provides comprehensive donor approval and status management
 */

require_once __DIR__ . '/admin_actions.php';

// Ensure critical columns exist on first load (idempotent)
function ensureDonorStatusColumnExists($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM donors_new LIKE 'status'");
        if (!$stmt->fetch()) {
            // Create a standard status column used across the app
            $pdo->exec("ALTER TABLE donors_new ADD COLUMN status ENUM('pending','approved','served','unserved','rejected') DEFAULT 'pending'");
        }
    } catch (Exception $e) {
        // Don't break the page; log for troubleshooting
        error_log("ensureDonorStatusColumnExists error: " . $e->getMessage());
    }
}

// Get medical screening status
function getMedicalScreeningStatus($screeningData, $allQuestionsAnswered) {
    if (!$screeningData) return "Not Completed";
    return $allQuestionsAnswered ? "Completed" : "Partially Completed";
}

// Get donor details with medical screening
function getDonorDetails($pdo, $donorId) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, ms.screening_data, ms.all_questions_answered, ms.created_at as screening_date
            FROM donors_new d
            LEFT JOIN donor_medical_screening_simple ms ON d.id = ms.donor_id
            WHERE d.id = ?
              AND d.email NOT LIKE 'test_%' 
              AND d.email NOT LIKE '%@example.com'
              AND d.first_name != 'Test'
              AND d.last_name != 'User'
              AND (d.reference_code NOT LIKE 'TEST-%' OR d.reference_code IS NULL)
        ");
        $stmt->execute([$donorId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting donor details: " . $e->getMessage());
        return null;
    }
}

// Get all donors with status filtering
function getDonorsList($pdo, $status = null, $limit = 50) {
    try {
        $query = "
            SELECT d.*, ms.screening_data, ms.all_questions_answered, ms.created_at as screening_date
            FROM donors_new d
            LEFT JOIN donor_medical_screening_simple ms ON d.id = ms.donor_id
            WHERE d.email NOT LIKE 'test_%' 
              AND d.email NOT LIKE '%@example.com'
              AND d.first_name != 'Test'
              AND d.last_name != 'User'
              AND (d.reference_code NOT LIKE 'TEST-%' OR d.reference_code IS NULL)
        ";
        
        $params = [];
        if ($status) {
            $query .= " AND d.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY d.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add medical screening status to each donor
        foreach ($donors as &$donor) {
            $donor['medical_screening_status'] = getMedicalScreeningStatus($donor['screening_data'], $donor['all_questions_answered']);
        }
        
        return $donors;
    } catch (Exception $e) {
        error_log("Error getting donors list: " . $e->getMessage());
        return [];
    }
}

// Update donor status (admin controlled)
function updateDonorStatus($pdo, $donorId, $newStatus, $notes = '', $adminId = null) {
    try {
        $pdo->beginTransaction();
        
        // Get current donor details
        $stmt = $pdo->prepare("SELECT * FROM donors_new WHERE id = ?");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donor) {
            throw new Exception("Donor not found");
        }
        
        // Update donor status
        $stmt = $pdo->prepare("UPDATE donors_new SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newStatus, $donorId]);
        
        // Add status change note and email donor with the note
        if (!empty($notes)) {
            $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$donorId, $notes, $adminId]);

            // Email donor with the remark
            if (!empty($donor['email'])) {
                require_once __DIR__ . '/mail_helper.php';
                $subject = "Update on Your Donor Application (" . ($donor['reference_code'] ?? 'No Ref') . ")";
                $message = "<h3>Application Update</h3>"
                    . "<p>Dear " . htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) . ",</p>"
                    . "<p>Your status has been updated to: <strong>" . ucfirst($newStatus) . "</strong>.</p>"
                    . "<p><strong>Remarks from admin:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>"
                    . "<p>Thank you for your participation in our blood donation program.</p>";
                @send_confirmation_email($donor['email'], $subject, $message);
            }
        }
        
        // If served via generic status update, also create donation record
        if ($newStatus === 'served') {
            $donationDate = date('Y-m-d');
            $stmt = $pdo->prepare("INSERT INTO donations_new (donor_id, donation_date, blood_type, donation_status, created_at) VALUES (?, ?, ?, 'completed', NOW())");
            $stmt->execute([$donorId, $donationDate, $donor['blood_type']]);
        }

        // Log admin action
        logAdminAction($pdo, 'donor_status_updated', 'donors_new', $donorId, "Status changed from {$donor['status']} to: $newStatus");
        
        // Send status-change email templates (always notify)
        if (!empty($donor['email'])) {
            require_once __DIR__ . '/mail_helper.php';
            if ($newStatus === 'served') {
                $subject = "Thank You for Donating Blood – Philippine Red Cross Baguio Chapter";
                $message = "<p>Dear {$donor['first_name']} {$donor['last_name']},</p>"
                    . "<p>On behalf of the Philippine Red Cross – Baguio Chapter, we sincerely thank you for your blood donation. Your generosity helps save lives and supports patients in our community who are in urgent need.</p>"
                    . "<h3>After Donation – Please Remember:</h3>"
                    . "<ul>"
                    . "<li>Rest for at least 10–15 minutes and drink fluids.</li>"
                    . "<li>Avoid heavy lifting or strenuous activity for the next 24 hours.</li>"
                    . "<li>Keep the bandage on for 3–4 hours and avoid getting it wet.</li>"
                    . "</ul>"
                    . "<p>You may donate blood again after 90 days (3 months). We will be happy to welcome you back when you are eligible.</p>"
                    . (!empty($notes) ? ("<p><strong>Additional Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>") : '')
                    . "<p>With gratitude,<br>Philippine Red Cross – Baguio Chapter</p>";
                @send_confirmation_email($donor['email'], $subject, $message);
            } elseif ($newStatus === 'approved') {
                $subject = "Blood Donation Application Approved - Reference: {$donor['reference_code']}";
                $message = "<h2>Your Application is Approved</h2><p>Dear {$donor['first_name']} {$donor['last_name']}, your application was approved. You may visit our center at your convenience.</p>"
                    . (!empty($notes) ? ("<p><strong>Remarks:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>") : '');
                @send_confirmation_email($donor['email'], $subject, $message);
            } elseif ($newStatus === 'unserved') {
                $subject = "Blood Donation Not Completed – Philippine Red Cross Baguio Chapter";
                $message = "<p>Dear {$donor['first_name']} {$donor['last_name']},</p>"
                    . "<p>We noticed that your recent blood donation appointment with the Philippine Red Cross – Baguio Chapter was not completed.</p>"
                    . "<h3>If you still wish to donate:</h3>"
                    . "<ul>"
                    . "<li>You may reschedule at your convenience.</li>"
                    . "<li>Make sure to have enough rest, drink water, and eat a healthy meal before donating.</li>"
                    . "<li>Bring a valid ID when you come to the blood center.</li>"
                    . "</ul>"
                    . "<p>Your donation will help patients in need. We look forward to seeing you soon.</p>"
                    . (!empty($notes) ? ("<p><strong>Additional Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>") : '')
                    . "<p>Sincerely,<br>Philippine Red Cross – Baguio Chapter</p>";
                @send_confirmation_email($donor['email'], $subject, $message);
            } elseif ($newStatus === 'rejected') {
                $subject = "Important Update on Your Blood Donation Eligibility – Philippine Red Cross Baguio Chapter";
                $message = "<p>Dear {$donor['first_name']} {$donor['last_name']},</p>"
                    . "<p>Thank you for your willingness to donate blood with the Philippine Red Cross – Baguio Chapter. After reviewing your screening results, you have been temporarily deferred from donating at this time.</p>"
                    . "<h3>What this means:</h3>"
                    . "<ul>"
                    . "<li>You cannot donate today for safety reasons.</li>"
                    . "<li>The reason may be due to recent travel, medication, low hemoglobin, or other medical factors.</li>"
                    . "<li>You may be eligible to donate again after the deferral period.</li>"
                    . "</ul>"
                    . "<p>Please contact our blood center to confirm the date when you can return. Your health and the safety of patients always come first.</p>"
                    . "<p>We truly appreciate your commitment to saving lives, and we hope to welcome you again when you are eligible.</p>"
                    . (!empty($notes) ? ("<p><strong>Additional Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>") : '')
                    . "<p>Sincerely,<br>Philippine Red Cross – Baguio Chapter</p>";
                @send_confirmation_email($donor['email'], $subject, $message);
            }
        }

        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating donor status: " . $e->getMessage());
        return false;
    }
}

// Approve donor (admin controlled)
function approveDonor($pdo, $donorId, $adminId = null) {
    try {
        $pdo->beginTransaction();
        
        // Get donor details for email
        $donor = getDonorDetails($pdo, $donorId);
        
        if (!$donor) {
            throw new Exception("Donor not found");
        }
        
        // Update donor status
        $stmt = $pdo->prepare("UPDATE donors_new SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$donorId]);
        
        if ($donor && !empty($donor['email'])) {
            // Send approval email
            require_once __DIR__ . '/mail_helper.php';
            
            $subject = "Blood Donation Application Approved - Reference: {$donor['reference_code']}";
            $message = "
                <h2>🎉 Your Blood Donation Application is Approved!</h2>
                <p>Dear {$donor['first_name']} {$donor['last_name']},</p>
                
                <p>Great news! Your blood donation application has been <strong>approved</strong>.</p>
                
                <div style='background:#f8f9fa; padding:15px; border-radius:5px; margin:15px 0;'>
                    <h3>Application Details:</h3>
                    <p><strong>Reference Code:</strong> {$donor['reference_code']}</p>
                    <p><strong>Blood Type:</strong> {$donor['blood_type']}</p>
                    <p><strong>Approval Date:</strong> " . date('F j, Y') . "</p>
                </div>
                
                <h3>✅ What's Next?</h3>
                <p><strong>You can visit the Red Cross center from 8:00 AM to 5:00 PM!</strong></p>
                <ul>
                    <li>Bring your valid ID (driver's license, passport, etc.)</li>
                    <li>Mention your reference code: <strong>{$donor['reference_code']}</strong></li>
                    <li>Complete the donation process</li>
                    <li>Our staff will guide you through the entire process</li>
                </ul>
                
                <h3>📍 Location & Contact:</h3>
                <p><strong>Red Cross Baguio Chapter</strong><br>
                Address: [Your Address Here]<br>
                Phone: +63 74 442 7065<br>
                Email: baguio@redcross.org.ph</p>
                
                <h3>⏰ Operating Hours:</h3>
                <p>Monday - Friday: 8:00 AM - 5:00 PM<br>
                Saturday: 8:00 AM - 12:00 PM</p>
                
                <p><strong>Thank you for your willingness to save lives!</strong></p>
                
                <p>Best regards,<br>
                Red Cross Baguio Chapter</p>
            ";
            
            send_confirmation_email($donor['email'], $subject, $message);
        }
        
        // Log admin action
        logAdminAction($pdo, 'donor_approved', 'donors_new', $donorId, "Donor approved and email sent");
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error approving donor: " . $e->getMessage());
        return false;
    }
}

// Mark donor as unserved (admin controlled)
function markDonorUnserved($pdo, $donorId, $reason, $customNote = '', $adminId = null) {
    try {
        $pdo->beginTransaction();
        
        // Get donor details for email
        $donor = getDonorDetails($pdo, $donorId);
        
        if (!$donor) {
            throw new Exception("Donor not found");
        }
        
        // Update donor status
        $stmt = $pdo->prepare("UPDATE donors_new SET status = 'unserved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$donorId]);
        
        // Add note about unserved reason
        if (!empty($reason) || !empty($customNote)) {
            $note = "Marked as unserved. Reason: " . $reason;
            if (!empty($customNote)) {
                $note .= " - " . $customNote;
            }
            
            $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$donorId, $note, $adminId]);
        }
        
        if ($donor && !empty($donor['email'])) {
            // Send unserved email
            require_once __DIR__ . '/mail_helper.php';
            
            $subject = "Blood Donation Not Completed – Philippine Red Cross Baguio Chapter";
            $message = "<p>Dear {$donor['first_name']} {$donor['last_name']},</p>"
                . "<p>We noticed that your recent blood donation appointment with the Philippine Red Cross – Baguio Chapter was not completed.</p>"
                . "<h3>If you still wish to donate:</h3>"
                . "<ul>"
                . "<li>You may reschedule at your convenience.</li>"
                . "<li>Make sure to have enough rest, drink water, and eat a healthy meal before donating.</li>"
                . "<li>Bring a valid ID when you come to the blood center.</li>"
                . "</ul>"
                . "<p>Your donation will help patients in need. We look forward to seeing you soon.</p>"
                . (!empty($customNote) ? ("<p><strong>Additional Notes:</strong><br>" . htmlspecialchars($customNote) . "</p>") : '')
                . "<p>Sincerely,<br>Philippine Red Cross – Baguio Chapter</p>";
            
            send_confirmation_email($donor['email'], $subject, $message);
        }
        
        // Log admin action
        logAdminAction($pdo, 'donor_unserved', 'donors_new', $donorId, "Donor marked as unserved. Reason: $reason");
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error marking donor as unserved: " . $e->getMessage());
        return false;
    }
}

// Mark donor as served (admin controlled)
function markDonorServed($pdo, $donorId, $donationDate = null, $adminId = null) {
    // Log to a file for debugging
    if (!file_exists(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0755, true);
    }
    $logFile = __DIR__ . '/../logs/donor_served.log';
    $logMessage = "[" . date('Y-m-d H:i:s') . "] markDonorServed called with donorId: $donorId\n";
    
    try {
        $pdo->beginTransaction();
        
        // First, modify the status column to include 'served' if it doesn't already
        try {
            $pdo->exec("ALTER TABLE donors_new MODIFY COLUMN status ENUM('pending','approved','served','rejected','suspended') DEFAULT 'pending'");
            $logMessage .= "Updated status column to include 'served'\n";
        } catch (Exception $e) {
            // Column might not exist or already updated
            $logMessage .= "Status column update: " . $e->getMessage() . "\n";
        }
        
        // Log current tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $logMessage .= "Available tables: " . implode(", ", $tables) . "\n";
        
        // Always use donors_new table
        $tableName = 'donors_new';
        $logMessage .= "Using table: $tableName\n";
        
        // Check columns in the table
        $columns = $pdo->query("SHOW COLUMNS FROM $tableName")->fetchAll(PDO::FETCH_COLUMN);
        $logMessage .= "Columns in $tableName: " . implode(", ", $columns) . "\n";
        
        // Ensure status column exists in the table and includes 'served' in the ENUM
        if (!in_array('status', $columns)) {
            $logMessage .= "Adding status column to $tableName\n";
            $pdo->exec("ALTER TABLE $tableName ADD COLUMN status ENUM('pending','approved','served','rejected','suspended') DEFAULT 'pending'");
        } else {
            // Check if 'served' is in the ENUM
            $columnInfo = $pdo->query("SHOW COLUMNS FROM $tableName WHERE Field = 'status'")->fetch(PDO::FETCH_ASSOC);
            if (strpos($columnInfo['Type'], "'served'") === false) {
                $logMessage .= "Updating status column to include 'served'\n";
                $pdo->exec("ALTER TABLE $tableName MODIFY COLUMN status ENUM('pending','approved','served','rejected','suspended') DEFAULT 'pending'");
            }
        }
        
        // Log the update
        $logMessage .= "Updating donor $donorId status to 'served'\n";
        
        // Update donor status to 'served'
        $stmt = $pdo->prepare("UPDATE $tableName SET status = 'served', updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$donorId]);
        $logMessage .= "Update result: " . ($result ? "success" : "failed") . "\n";
        
        if (!$result) {
            throw new Exception("Failed to update donor status");
        }
        
        // Get donor details for email
        $donor = getDonorDetails($pdo, $donorId);
        
        if (!$donor) {
            throw new Exception("Donor not found");
        }
        
        // Log the update
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Add donation record
        $donationDate = $donationDate ?: date('Y-m-d');
        // Note: donations_new table column is named `donation_status`, not `status`
        $stmt = $pdo->prepare("INSERT INTO donations_new (donor_id, donation_date, blood_type, donation_status, created_at) VALUES (?, ?, ?, 'completed', NOW())");
        $stmt->execute([$donorId, $donationDate, $donor['blood_type']]);
        
        if ($donor && !empty($donor['email'])) {
            // Send served confirmation email
            require_once __DIR__ . '/mail_helper.php';
            
            $subject = "Thank You for Donating Blood – Philippine Red Cross Baguio Chapter";
            $message = "<p>Dear {$donor['first_name']} {$donor['last_name']},</p>"
                . "<p>On behalf of the Philippine Red Cross – Baguio Chapter, we sincerely thank you for your blood donation. Your generosity helps save lives and supports patients in our community who are in urgent need.</p>"
                . "<h3>After Donation – Please Remember:</h3>"
                . "<ul>"
                . "<li>Rest for at least 10–15 minutes and drink fluids.</li>"
                . "<li>Avoid heavy lifting or strenuous activity for the next 24 hours.</li>"
                . "<li>Keep the bandage on for 3–4 hours and avoid getting it wet.</li>"
                . "</ul>"
                . "<p>You may donate blood again after 90 days (3 months). We will be happy to welcome you back when you are eligible.</p>"
                . "<p>With gratitude,<br>Philippine Red Cross – Baguio Chapter</p>";
            
            send_confirmation_email($donor['email'], $subject, $message);
        }
        
        // Log admin action
        logAdminAction($pdo, 'donor_served', 'donors_new', $donorId, "Donor marked as served after donation");
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error marking donor as served: " . $e->getMessage());
        return false;
    }
}

// Get donor notes
function getDonorNotes($pdo, $donorId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM donor_notes WHERE donor_id = ? ORDER BY created_at DESC");
        $stmt->execute([$donorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting donor notes: " . $e->getMessage());
        return [];
    }
}

// Add note to donor
function addDonorNote($pdo, $donorId, $note, $adminId = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$donorId, $note, $adminId]);
        return true;
    } catch (Exception $e) {
        error_log("Error adding donor note: " . $e->getMessage());
        return false;
    }
}

// Create donor management tables
function createDonorManagementTables($pdo) {
    try {
        // Make sure donors_new.status exists (required by the UI and queries)
        ensureDonorStatusColumnExists($pdo);
        
        // Create donor_notes table
        $pdo->exec("CREATE TABLE IF NOT EXISTS donor_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            donor_id INT NOT NULL,
            note TEXT NOT NULL,
            created_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_donor_id (donor_id)
        )");
        
        // Create donations_new table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS donations_new (
            id INT AUTO_INCREMENT PRIMARY KEY,
            donor_id INT NOT NULL,
            donation_date DATE NOT NULL,
            blood_type VARCHAR(5),
            units_donated INT DEFAULT 1,
            status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_donor_id (donor_id),
            INDEX idx_donation_date (donation_date)
        )");
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating donor management tables: " . $e->getMessage());
        return false;
    }
}

// Get donor statistics
function getDonorStatistics($pdo) {
    try {
        $stats = [];
        
        // Total donors
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM donors_new");
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Status breakdown
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM donors_new GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Blood type breakdown
        $stmt = $pdo->query("SELECT blood_type, COUNT(*) as count FROM donors_new GROUP BY blood_type");
        $stats['by_blood_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent donors (last 7 days)
        $stmt = $pdo->query("SELECT COUNT(*) as recent FROM donors_new WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
        
        // Served donors count
        $stmt = $pdo->query("SELECT COUNT(*) as served FROM donors_new WHERE status = 'served'");
        $stats['served'] = $stmt->fetch(PDO::FETCH_ASSOC)['served'];
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting donor statistics: " . $e->getMessage());
        return [];
    }
}

// Get predefined unserved reasons
function getUnservedReasons() {
    return [
        'medical_condition' => 'Medical condition not suitable for donation',
        'recent_travel' => 'Recent travel to restricted areas',
        'medication' => 'Currently taking medications that prevent donation',
        'low_hemoglobin' => 'Low hemoglobin levels',
        'recent_surgery' => 'Recent surgery or medical procedure',
        'pregnancy' => 'Currently pregnant or recently gave birth',
        'age_restriction' => 'Age outside acceptable range',
        'weight_requirement' => 'Weight below minimum requirement',
        'other' => 'Other reason'
    ];
}

// Get available status options for donors
function getDonorStatusOptions() {
    return [
        'pending' => 'Pending - Awaiting review',
        'approved' => 'Approved - Can visit from 8:00 AM to 5:00 PM',
        'served' => 'Served - Donation completed',
        'unserved' => 'Unserved - Not eligible',
        // Rename label shown to admins from "Rejected" to "Temporary Deferred"
        'rejected' => 'Temporary Deferred - Postpone donation temporarily'
    ];
}

// Get status color for display
function getDonorStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'approved' => 'success',
        'served' => 'info',
        'unserved' => 'danger',
        'rejected' => 'danger'
    ];
    
    return $colors[$status] ?? 'secondary';
}

// Get display status (converts internal status to user-friendly display)
function getDonorDisplayStatus($status) {
    $displayStatuses = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'served' => 'Served',
        'unserved' => 'Unserved',
        'rejected' => 'Deferred'
    ];
    
    return $displayStatuses[$status] ?? ucfirst($status);
}
?> 