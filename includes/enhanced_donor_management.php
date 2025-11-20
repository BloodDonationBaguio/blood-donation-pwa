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
        // Check if status column exists (PostgreSQL syntax)
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'donors' AND column_name = 'status'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            // Create a standard status column used across the app
            $pdo->exec("ALTER TABLE donors ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
        }
    } catch (Exception $e) {
        // Don't break the page; log for troubleshooting
        error_log("ensureDonorStatusColumnExists error: " . $e->getMessage());
    }
}

// Ensure extended donor metadata columns exist
function ensureDonorExtendedColumns($pdo) {
    try {
        $requiredColumns = [
            'rejection_reason'   => 'TEXT',
            'unserved_reason'    => 'TEXT',
            'served_date'        => 'TIMESTAMP NULL',
            'last_donation_date' => 'TIMESTAMP NULL'
        ];

        if (!empty($requiredColumns)) {
            $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
            $stmt = $pdo->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'donors' AND column_name IN ($placeholders)"
            );
            $stmt->execute(array_keys($requiredColumns));
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $existing = $columns ? array_flip(array_map('strtolower', $columns)) : [];

            foreach ($requiredColumns as $column => $type) {
                if (!isset($existing[strtolower($column)])) {
                    $pdo->exec("ALTER TABLE donors ADD COLUMN $column $type");
                }
            }
        }
    } catch (Exception $e) {
        error_log("ensureDonorExtendedColumns error: " . $e->getMessage());
    }
}

// Ensure donor_notes table exists (dialect-aware)
function ensureDonorNotesTableExists($pdo) {
    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? '');

        if ($driver === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS donor_notes (
                id SERIAL PRIMARY KEY,
                donor_id INTEGER NOT NULL,
                note TEXT NOT NULL,
                created_by VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
        } elseif ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS donor_notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donor_id INTEGER NOT NULL,
                note TEXT NOT NULL,
                created_by TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
        } else {
            // MySQL / MariaDB
            $sql = "CREATE TABLE IF NOT EXISTS donor_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                donor_id INT NOT NULL,
                note TEXT NOT NULL,
                created_by VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_donor_id (donor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
        error_log("ensureDonorNotesTableExists error: " . $e->getMessage());
    }
}

// Ensure donations_new table exists (dialect-aware)
function ensureDonationsNewTableExists($pdo) {
    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? '');

        if ($driver === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS donations_new (
                id SERIAL PRIMARY KEY,
                donor_id INT NOT NULL,
                donation_date DATE NOT NULL,
                blood_type VARCHAR(10),
                units_donated INT DEFAULT 1,
                status VARCHAR(20) DEFAULT 'scheduled',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
        } elseif ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS donations_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donor_id INTEGER NOT NULL,
                donation_date DATE NOT NULL,
                blood_type TEXT,
                units_donated INTEGER DEFAULT 1,
                status TEXT DEFAULT 'scheduled',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
        } else {
            // MySQL / MariaDB
            $sql = "CREATE TABLE IF NOT EXISTS donations_new (
                id INT AUTO_INCREMENT PRIMARY KEY,
                donor_id INT NOT NULL,
                donation_date DATE NOT NULL,
                blood_type VARCHAR(10),
                units_donated INT DEFAULT 1,
                status ENUM('scheduled','completed','cancelled') DEFAULT 'scheduled',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_donor_id (donor_id),
                INDEX idx_donation_date (donation_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
        error_log("ensureDonationsNewTableExists error: " . $e->getMessage());
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
            FROM donors d
            LEFT JOIN donor_medical_screening_simple ms ON d.id = ms.donor_id
            WHERE d.id = ?
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
            FROM donors d
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
        // Ensure audit log table exists BEFORE starting transaction
        // (CREATE TABLE auto-commits and would break our transaction)
        ensureAuditLogTableExists($pdo);
        
        
        
        // Get current donor details
        $stmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donor) {
            throw new Exception("Donor not found");
        }
        
        // Update donor status; when served, also set served_date
        if ($newStatus === 'served') {
            $stmt = $pdo->prepare("UPDATE donors SET status = 'served', served_date = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$donorId]);
        } else {
            $stmt = $pdo->prepare("UPDATE donors SET status = ? WHERE id = ?");
            $result = $stmt->execute([$newStatus, $donorId]);
        }
        
        // Add status change note and email donor with the note
        if (!empty($notes)) {
            // Only insert note if donor_notes table exists
            try {
                $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$donorId, $notes, $adminId]);
            } catch (PDOException $e) {
                // donor_notes table might not exist, log but continue
                error_log("donor_notes insert failed: " . $e->getMessage());
            }

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
        
        if ($newStatus === 'served') {
            $donationDate = date('Y-m-d');
            $bloodType = $donor['blood_type'];
            if (strlen($bloodType) > 10) {
                $bloodType = substr($bloodType, 0, 10);
                error_log("WARNING: Blood type too long for donor $donorId: " . $donor['blood_type']);
            }
            $stmt = $pdo->prepare("INSERT INTO donations_new (donor_id, donation_date, blood_type, status, created_at) VALUES (?, ?, ?, 'completed', CURRENT_TIMESTAMP)");
            $stmt->execute([$donorId, $donationDate, $bloodType]);
            require_once __DIR__ . '/BloodInventoryManagerComplete.php';
            $inventoryManager = new BloodInventoryManagerComplete($pdo);
            $inventoryManager->addBloodUnit([
                'donor_id' => $donorId,
                'collection_date' => $donationDate,
                'collection_site' => 'Main Center',
                'storage_location' => 'Storage A'
            ]);
        }

        // Log admin action
        logAdminAction($pdo, 'donor_status_updated', 'donors', $donorId, "Status changed from {$donor['status']} to: $newStatus");
        
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

        
        if ($newStatus === 'served') {
            $donationDate = date('Y-m-d');
            require_once __DIR__ . '/BloodInventoryManagerComplete.php';
            $inventoryManager = new BloodInventoryManagerComplete($pdo);
            $inventoryManager->addBloodUnit([
                'donor_id' => $donorId,
                'collection_date' => $donationDate,
                'collection_site' => 'Main Center',
                'storage_location' => 'Storage A'
            ]);
        }
        return true;
        
    } catch (Exception $e) {
        
        error_log("Error updating donor status: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        // Store the error message so it can be retrieved by the caller
        if (!isset($GLOBALS['last_donor_error'])) {
            $GLOBALS['last_donor_error'] = $e->getMessage();
        }
        return false;
    }
}

// Approve donor (admin controlled)
function approveDonor($pdo, $donorId, $adminId = null) {
    try {
        // Ensure audit log table exists BEFORE starting transaction
        ensureAuditLogTableExists($pdo);
        
        $pdo->beginTransaction();
        
        // Get donor details for email
        $donor = getDonorDetails($pdo, $donorId);
        
        if (!$donor) {
            error_log("Donor not found in approveDonor: " . $donorId);
            throw new Exception("Donor not found");
        }
        
        // Update donor status
        $stmt = $pdo->prepare("UPDATE donors SET status = 'approved' WHERE id = ?");
        $stmt->execute([$donorId]);

        if ($donor && !empty($donor['email']) && !preg_match('/@example\.com$/', $donor['email'])) {
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
        logAdminAction($pdo, 'donor_approved', 'donors', $donorId, "Donor approved and email sent");
        
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
        // Ensure audit log table exists BEFORE starting transaction
        ensureAuditLogTableExists($pdo);
        
        $pdo->beginTransaction();
        
        // Get donor details for email
        $donor = getDonorDetails($pdo, $donorId);
        
        if (!$donor) {
            throw new Exception("Donor not found");
        }
        
        // Update donor status
        $stmt = $pdo->prepare("UPDATE donors SET status = 'unserved' WHERE id = ?");
        $stmt->execute([$donorId]);
        
        // Add note about unserved reason
        if (!empty($reason) || !empty($customNote)) {
            // Ensure donor_notes table exists before inserting
            ensureDonorNotesTableExists($pdo);
            $note = "Marked as unserved. Reason: " . $reason;
            if (!empty($customNote)) {
                $note .= " - " . $customNote;
            }
            
            $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
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
        logAdminAction($pdo, 'donor_unserved', 'donors', $donorId, "Donor marked as unserved. Reason: $reason");
        
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

// Mark donor as served (PostgreSQL-compatible: inserts into blood_inventory directly)
function markDonorServed($pdo, $donorId, $donationDate = null, $adminId = null) {
    if (!file_exists(__DIR__ . '/../logs')) {
        @mkdir(__DIR__ . '/../logs', 0755, true);
    }
    $logFile = __DIR__ . '/../logs/donor_served.log';
    $logPrefix = "[" . date('Y-m-d H:i:s') . "] markDonorServed donorId=$donorId ";

    try {
        // Self-heal critical tables including blood_inventory
        try {
            // Skip self-heal during automated tests to avoid redirects/HTML output
            if (!defined('TEST_MODE') || TEST_MODE !== true) {
                require_once __DIR__ . '/../database_self_heal.php';
            }
        } catch (Throwable $e) {
            error_log('database_self_heal include failed: ' . $e->getMessage());
        }

        // Ensure audit log table exists BEFORE starting transaction
        ensureAuditLogTableExists($pdo);

        $pdo->beginTransaction();

        // Fetch donor
        $donor = getDonorDetails($pdo, $donorId);
        if (!$donor) {
            throw new Exception("Donor not found: $donorId");
        }

        // Update donor status and served date
        $servedDate = $donationDate ?: date('Y-m-d');
        $update = $pdo->prepare("UPDATE donors SET status = 'served', served_date = ? WHERE id = ?");
        $update->execute([$servedDate, $donorId]);

        // Generate unique unit ID
        $bloodType = strtoupper(trim((string)$donor['blood_type']));
        if ($bloodType === '') {
            // Fallback to unknown blood type to avoid empty unit id
            $bloodType = 'UNK';
        }
        $unitId = 'UNIT-' . $bloodType . '-' . date('Ymd', strtotime($servedDate)) . '-' . str_pad((string)$donorId, 5, '0', STR_PAD_LEFT);

        // Calculate expiry date (35 days from collection)
        $expiryDate = date('Y-m-d', strtotime($servedDate . ' +35 days'));

        // Insert donation record (completed)
        try {
            $stmt = $pdo->prepare("INSERT INTO donations_new (donor_id, donation_date, blood_type, status, created_at) VALUES (?, ?, ?, 'completed', CURRENT_TIMESTAMP)");
            $stmt->execute([$donorId, $servedDate, substr($bloodType, 0, 10)]);
        } catch (Throwable $e) {
            // Continue even if donations_new insert fails; log for follow-up
            error_log('donations_new insert failed: ' . $e->getMessage());
        }

        // Insert into blood_inventory (PostgreSQL ON CONFLICT safe)
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertedUnitId = null;
        if ($driver === 'pgsql') {
            $sql = "INSERT INTO blood_inventory (
                        unit_id, donor_id, blood_type, collection_date, expiry_date, status, volume_ml, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, 'Available', 450, CURRENT_TIMESTAMP
                    ) ON CONFLICT (unit_id) DO NOTHING RETURNING unit_id";
            $inventoryStmt = $pdo->prepare($sql);
            $inventoryStmt->execute([$unitId, $donorId, substr($bloodType, 0, 10), $servedDate, $expiryDate]);
            $insertedUnitId = $inventoryStmt->fetchColumn();
        } else {
            // MySQL/MariaDB fallback
            $sql = "INSERT IGNORE INTO blood_inventory (
                        unit_id, donor_id, blood_type, collection_date, expiry_date, status, volume_ml
                    ) VALUES (
                        ?, ?, ?, ?, ?, 'Available', 450
                    )";
            $inventoryStmt = $pdo->prepare($sql);
            $inventoryStmt->execute([$unitId, $donorId, substr($bloodType, 0, 10), $servedDate, $expiryDate]);
            $insertedUnitId = $unitId; // If ignored, treat as existing
            // Resilience: ensure row exists; if not, attempt direct upsert-style insert
            try {
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM blood_inventory WHERE unit_id = ?');
                $checkStmt->execute([$unitId]);
                $exists = (int)$checkStmt->fetchColumn() > 0;
                if (!$exists) {
                    // Fallback insert without IGNORE (handles edge cases where IGNORE suppresses insertion)
                    $sql2 = "INSERT INTO blood_inventory (
                                unit_id, donor_id, blood_type, collection_date, expiry_date, status, volume_ml
                            ) VALUES (
                                ?, ?, ?, ?, ?, 'available', 450
                            ) ON DUPLICATE KEY UPDATE unit_id = unit_id";
                    $stmt2 = $pdo->prepare($sql2);
                    $stmt2->execute([$unitId, $donorId, substr($bloodType, 0, 10), $servedDate, $expiryDate]);
                }
            } catch (Throwable $ensureE) {
                // Non-fatal: log and proceed; transaction will still commit for served state
                error_log('blood_inventory ensure insert fallback failed: ' . $ensureE->getMessage());
            }
        }

        // Send served email if available
        if (!empty($donor['email'])) {
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
        logAdminAction($pdo, 'donor_served', 'donors', $donorId, "Donor marked as served; unit $unitId");

        $pdo->commit();

        @file_put_contents($logFile, $logPrefix . "SUCCESS unit=$unitId\n", FILE_APPEND);

        return [
            'success' => true,
            'unit_id' => $unitId,
            'donor_id' => $donorId,
            'blood_type' => $bloodType,
            'inserted' => (bool)$insertedUnitId
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @file_put_contents($logFile, $logPrefix . 'ERROR ' . $e->getMessage() . "\n", FILE_APPEND);
        error_log("ERROR marking donor served: " . $e->getMessage());
        error_log($e->getTraceAsString());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Bulk: mark multiple donors served (helper)
function markMultipleDonorsServed($pdo, $donorIds, $donationDate = null) {
    $results = [ 'success' => [], 'failed' => [] ];
    foreach ((array)$donorIds as $id) {
        $r = markDonorServed($pdo, (int)$id, $donationDate);
        if (is_array($r) && !empty($r['success'])) {
            $results['success'][] = $r;
        } else {
            $results['failed'][] = [ 'donor_id' => (int)$id, 'error' => is_array($r) ? ($r['error'] ?? 'Unknown error') : 'Operation failed' ];
        }
    }
    return $results;
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
    ensureDonorNotesTableExists($pdo);
    try {
        // Ensure donor_notes table exists before inserting
        ensureDonorNotesTableExists($pdo);
        $stmt = $pdo->prepare("INSERT INTO donor_notes (donor_id, note, created_by, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
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
        // Make sure donors.status and extended metadata exist (required by the UI and queries)
        ensureDonorStatusColumnExists($pdo);
        ensureDonorExtendedColumns($pdo);

        // Ensure supporting tables exist in a driver-safe way
        ensureDonorNotesTableExists($pdo);
        ensureDonationsNewTableExists($pdo);
        
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
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM donors");
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Status breakdown
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM donors GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Blood type breakdown
        $stmt = $pdo->query("SELECT blood_type, COUNT(*) as count FROM donors GROUP BY blood_type");
        $stats['by_blood_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent donors (last 7 days) - PostgreSQL syntax
        $stmt = $pdo->query("SELECT COUNT(*) as recent FROM donors WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '7 days'");
        $stats['recent'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
        
        // Served donors count
        $stmt = $pdo->query("SELECT COUNT(*) as served FROM donors WHERE status = 'served'");
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

// Backfill served_date for already-served donors using latest completed donation
function backfillServedDates($pdo) {
    try {
        // Set served_date to the latest completed donation_date if missing
        $sql = "UPDATE donors d
                JOIN (
                  SELECT donor_id, MAX(donation_date) AS latest_date
                  FROM donations_new
                  WHERE status = 'completed'
                  GROUP BY donor_id
                ) dn ON dn.donor_id = d.id
                SET d.served_date = dn.latest_date
                WHERE d.status = 'served' AND d.served_date IS NULL";
        $pdo->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log('Error backfilling served_date: ' . $e->getMessage());
        return false;
    }
}

// Backfill donation dates for legacy served donors using safe fallbacks
// - If a served donor lacks served_date but has last_donation_date, use it
// - If dates are missing, derive from blood_inventory.collection_date
// - Ensure last_donation_date is also populated when served_date exists
function backfillDonationDatesFallbacks($pdo) {
    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'mysql');

        // 1) served_date <- last_donation_date when served_date is missing
        try {
            $pdo->exec("UPDATE donors SET served_date = last_donation_date WHERE status = 'served' AND served_date IS NULL AND last_donation_date IS NOT NULL");
        } catch (Exception $e) {
            // Non-blocking
            error_log('Fallback A failed: ' . $e->getMessage());
        }

        // 2) served_date <- latest blood_inventory.collection_date when still missing
        try {
            if ($driver === 'pgsql') {
                $sql = "UPDATE donors d
                        SET served_date = bi.latest_date
                        FROM (
                          SELECT donor_id, MAX(collection_date) AS latest_date
                          FROM blood_inventory
                          WHERE donor_id IS NOT NULL
                          GROUP BY donor_id
                        ) bi
                        WHERE bi.donor_id = d.id AND d.status = 'served' AND d.served_date IS NULL";
                $pdo->exec($sql);
            } else {
                $sql = "UPDATE donors d
                        JOIN (
                          SELECT donor_id, MAX(collection_date) AS latest_date
                          FROM blood_inventory
                          WHERE donor_id IS NOT NULL
                          GROUP BY donor_id
                        ) bi ON bi.donor_id = d.id
                        SET d.served_date = bi.latest_date
                        WHERE d.status = 'served' AND d.served_date IS NULL";
                $pdo->exec($sql);
            }
        } catch (Exception $e) {
            error_log('Fallback B failed: ' . $e->getMessage());
        }

        // 3) last_donation_date <- served_date when missing
        try {
            $pdo->exec("UPDATE donors SET last_donation_date = served_date WHERE last_donation_date IS NULL AND served_date IS NOT NULL");
        } catch (Exception $e) {
            error_log('Fallback C failed: ' . $e->getMessage());
        }

        // 4) last_donation_date <- latest blood_inventory.collection_date when still missing
        try {
            if ($driver === 'pgsql') {
                $sql = "UPDATE donors d
                        SET last_donation_date = bi.latest_date
                        FROM (
                          SELECT donor_id, MAX(collection_date) AS latest_date
                          FROM blood_inventory
                          WHERE donor_id IS NOT NULL
                          GROUP BY donor_id
                        ) bi
                        WHERE bi.donor_id = d.id AND d.last_donation_date IS NULL";
                $pdo->exec($sql);
            } else {
                $sql = "UPDATE donors d
                        JOIN (
                          SELECT donor_id, MAX(collection_date) AS latest_date
                          FROM blood_inventory
                          WHERE donor_id IS NOT NULL
                          GROUP BY donor_id
                        ) bi ON bi.donor_id = d.id
                        SET d.last_donation_date = bi.latest_date
                        WHERE d.last_donation_date IS NULL";
                $pdo->exec($sql);
            }
        } catch (Exception $e) {
            error_log('Fallback D failed: ' . $e->getMessage());
        }

        return true;
    } catch (Exception $e) {
        error_log('Error in backfillDonationDatesFallbacks: ' . $e->getMessage());
        return false;
    }
}
?>
