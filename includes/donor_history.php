<?php
/**
 * Donor History Tracking System
 * Tracks comprehensive donation history for donor profiles
 */

function createDonorHistoryTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS donor_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        donor_id INT NOT NULL,
        donation_date DATE NOT NULL,
        blood_type VARCHAR(10) NOT NULL,
        units_donated DECIMAL(3,1) DEFAULT 1.0,
        donation_center VARCHAR(100),
        phlebotomist VARCHAR(100),
        notes TEXT,
        status ENUM('completed', 'deferred', 'rejected') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
        INDEX idx_donor_id (donor_id),
        INDEX idx_donation_date (donation_date),
        INDEX idx_status (status)
    )";
    
    $pdo->exec($sql);
}

function addDonationRecord($pdo, $donorId, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO donor_history (
                donor_id, donation_date, blood_type, units_donated,
                donation_center, phlebotomist, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $donorId,
            $data['donation_date'] ?? date('Y-m-d'),
            $data['blood_type'],
            $data['units_donated'] ?? 1.0,
            $data['donation_center'] ?? 'Philippine Red Cross Baguio',
            $data['phlebotomist'] ?? null,
            $data['notes'] ?? null,
            $data['status'] ?? 'completed'
        ]);
        
        // Add blood unit to inventory
        require_once __DIR__ . '/blood_inventory.php';
        addBloodUnit($pdo, $donorId, $data['blood_type'], $data['donation_date']);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error adding donation record: " . $e->getMessage());
        return false;
    }
}

function getDonorHistory($pdo, $donorId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                dh.*,
                d.full_name,
                d.reference_code
            FROM donor_history dh
            JOIN donors d ON dh.donor_id = d.id
            WHERE dh.donor_id = ?
            ORDER BY dh.donation_date DESC
        ");
        $stmt->execute([$donorId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting donor history: " . $e->getMessage());
        return false;
    }
}

function getDonorStatistics($pdo, $donorId) {
    try {
        // Total donations
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_donations,
                   SUM(units_donated) as total_units,
                   MAX(donation_date) as last_donation,
                   MIN(donation_date) as first_donation
            FROM donor_history 
            WHERE donor_id = ? AND status = 'completed'
        ");
        $stmt->execute([$donorId]);
        $stats = $stmt->fetch();
        
        // Blood type distribution
        $stmt = $pdo->prepare("
            SELECT blood_type, COUNT(*) as count
            FROM donor_history 
            WHERE donor_id = ? AND status = 'completed'
            GROUP BY blood_type
        ");
        $stmt->execute([$donorId]);
        $bloodTypes = $stmt->fetchAll();
        
        // Monthly donation trend
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(donation_date, '%Y-%m') as month,
                   COUNT(*) as donations
            FROM donor_history 
            WHERE donor_id = ? AND status = 'completed'
            GROUP BY DATE_FORMAT(donation_date, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        $stmt->execute([$donorId]);
        $monthlyTrend = $stmt->fetchAll();
        
        // Eligibility check
        $lastDonation = $stats['last_donation'] ?? null;
        $eligibleForNext = false;
        $daysUntilEligible = 0;
        
        if ($lastDonation) {
            $nextEligibleDate = date('Y-m-d', strtotime($lastDonation . ' + 3 months'));
            $today = date('Y-m-d');
            $eligibleForNext = $today >= $nextEligibleDate;
            $daysUntilEligible = max(0, (strtotime($nextEligibleDate) - strtotime($today)) / (60 * 60 * 24));
        }
        
        return [
            'stats' => $stats,
            'blood_types' => $bloodTypes,
            'monthly_trend' => $monthlyTrend,
            'eligible_for_next' => $eligibleForNext,
            'days_until_eligible' => $daysUntilEligible
        ];
    } catch (Exception $e) {
        error_log("Error getting donor statistics: " . $e->getMessage());
        return false;
    }
}

function isEligibleToDonate($pdo, $donorId) {
    try {
        // Check last donation date
        $stmt = $pdo->prepare("
            SELECT MAX(donation_date) as last_donation
            FROM donor_history 
            WHERE donor_id = ? AND status = 'completed'
        ");
        $stmt->execute([$donorId]);
        $result = $stmt->fetch();
        
        if (!$result['last_donation']) {
            return true; // First time donor
        }
        
        // Check if 3 months have passed
        $nextEligibleDate = date('Y-m-d', strtotime($result['last_donation'] . ' + 3 months'));
        return date('Y-m-d') >= $nextEligibleDate;
    } catch (Exception $e) {
        error_log("Error checking donation eligibility: " . $e->getMessage());
        return false;
    }
}

function getDonationCertificate($pdo, $donorId, $donationId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                dh.*,
                d.full_name,
                d.reference_code,
                d.blood_type
            FROM donor_history dh
            JOIN donors d ON dh.donor_id = d.id
            WHERE dh.id = ? AND dh.donor_id = ?
        ");
        $stmt->execute([$donationId, $donorId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting donation certificate: " . $e->getMessage());
        return false;
    }
}
?> 