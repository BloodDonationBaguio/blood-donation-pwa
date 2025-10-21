<?php
/**
 * Admin Dashboard - Blood Donation System
 */

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Secure session
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true
]);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['admin_username'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/admin.php';
    header("Location: admin_login.php");
    exit();
}

// Additional security: Verify admin still exists and is active
try {
    require_once(__DIR__ . "/db.php");
    $stmt = $pdo->prepare("SELECT id, username, is_active FROM admin_users WHERE username = ? AND is_active = 1");
    $stmt->execute([$_SESSION['admin_username']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Admin no longer exists or is inactive, destroy session
        session_destroy();
        header("Location: admin-login.php?error=session_expired");
        exit();
    }
} catch (Exception $e) {
    // Database error, destroy session for security
    session_destroy();
    header("Location: admin-login.php?error=database_error");
    exit();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    // Generate a secure random token
    $token = hash('sha256', uniqid('csrf_', true) . microtime(true) . session_id());
    $_SESSION['csrf_token'] = $token;
}

// Initialize variables
$error = '';
$success = '';
$validTabs = ['dashboard', 'donor-list', 'manage-pages', 'update-contact', 'pending-donors', 'donor-details', 'donor-matching', 'audit-log', 'blood-inventory', 'settings', 'help'];
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], $validTabs) 
    ? $_GET['tab'] 
    : 'dashboard';

// Handle success messages
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        try {
            // Get current admin user
            $stmt = $pdo->prepare("SELECT id, password, password_hash FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$_SESSION['admin_username']]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                $error = "Admin user not found.";
            } else {
                // Check current password (try both password fields for compatibility)
                $currentPasswordValid = false;
                if (!empty($admin['password_hash']) && password_verify($currentPassword, $admin['password_hash'])) {
                    $currentPasswordValid = true;
                } elseif (!empty($admin['password']) && $admin['password'] === $currentPassword) {
                    $currentPasswordValid = true;
                }
                
                if (!$currentPasswordValid) {
                    $error = "Current password is incorrect.";
                } else {
                    // Update password
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE admin_users SET password_hash = ?, password = '', updated_at = NOW() WHERE id = ?");
                    
                    if ($updateStmt->execute([$newPasswordHash, $admin['id']])) {
                        $success = "Password changed successfully!";
                        // Log the password change
                        error_log("Admin password changed for user: " . $_SESSION['admin_username']);
                    } else {
                        $error = "Failed to update password. Please try again.";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred while changing password: " . $e->getMessage();
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

// Database connection
try {
    require_once(__DIR__ . "/db.php");
    require_once(__DIR__ . "/includes/enhanced_donor_management.php");
    
    // Define constant to allow access to includes
    define('INCLUDES_PATH', true);
    
    // Handle admin actions before any output
    require_once __DIR__ . '/includes/admin_actions.php';
    
    // Handle donor update action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_donor') {
        $donorId = (int)$_POST['donor_id'];
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $bloodType = $_POST['blood_type'];
        $status = $_POST['status'];
        
        // Get original donor data for logging
        $originalStmt = $pdo->prepare('SELECT * FROM donors_new WHERE id = ?');
        $originalStmt->execute([$donorId]);
        $originalDonor = $originalStmt->fetch();
        
        if ($originalDonor) {
            // Update donor information
            $updateStmt = $pdo->prepare('
                UPDATE donors_new 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, blood_type = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ');
            
            if ($updateStmt->execute([$firstName, $lastName, $email, $phone, $bloodType, $status, $donorId])) {
                // Log the changes
                $changes = [];
                if ($originalDonor['first_name'] !== $firstName) $changes[] = "First name: {$originalDonor['first_name']} → $firstName";
                if ($originalDonor['last_name'] !== $lastName) $changes[] = "Last name: {$originalDonor['last_name']} → $lastName";
                if ($originalDonor['email'] !== $email) $changes[] = "Email: {$originalDonor['email']} → $email";
                if ($originalDonor['phone'] !== $phone) $changes[] = "Phone: {$originalDonor['phone']} → $phone";
                if ($originalDonor['blood_type'] !== $bloodType) $changes[] = "Blood type: {$originalDonor['blood_type']} → $bloodType";
                if ($originalDonor['status'] !== $status) $changes[] = "Status: {$originalDonor['status']} → $status";
                
                $changeLog = implode(', ', $changes);
                logAdminAction($pdo, 'donor_updated', 'donors_new', $donorId, "Donor information updated: $changeLog");
                
                header('Location: ?tab=donor-list&success=Donor information updated successfully.');
                exit();
            } else {
                $error = 'Failed to update donor information.';
            }
        } else {
            $error = 'Donor not found.';
        }
    }
    
    // Handle donor management actions
    if (isset($_GET['approve_donor'])) {
        $id = (int)$_GET['approve_donor'];
        $stmt = $pdo->prepare('UPDATE donors_new SET status = "approved" WHERE id = ?');
        $stmt->execute([$id]);
        $donor = $pdo->query('SELECT * FROM donors_new WHERE id = ' . $id)->fetch();
        if ($donor && $donor['email']) {
            require_once __DIR__ . '/includes/mail_helper.php';
            $subject = "Your Donor Application Approved [Ref: {$donor['reference_code']}]";
            $message = "<p>Dear {$donor['first_name']} {$donor['last_name']},</p>
            <p>Your blood donation application (Ref: <b>{$donor['reference_code']}</b>) has been <b>approved</b>!</p>
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>You can visit the Red Cross center from 8:00 AM to 5:00 PM</li>
                <li>Bring your ID and this reference number: <b>{$donor['reference_code']}</b></li>
                <li>Complete your donation process</li>
            </ul>
            <p>Status: <b>Approved</b></p>
            <p>Thank you for your willingness to donate blood!</p>";
            send_confirmation_email($donor['email'], $subject, $message, $donor['first_name'] . ' ' . $donor['last_name']);
        }
        
        // Log the action
        logAdminAction($pdo, 'donor_approved', 'donors_new', $id, "Donor approved and email sent");
        
                    header('Location: ?tab=pending-donors&success=Donor was approved successfully.');
        exit();
    }

    if (isset($_GET['reject_donor'])) {
        $id = (int)$_GET['reject_donor'];
        $reason = $_GET['reason'] ?? 'Eligibility criteria not met';
        $stmt = $pdo->prepare('UPDATE donors_new SET status = "rejected", rejection_reason = ? WHERE id = ?');
        $stmt->execute([$reason, $id]);
        $donor = $pdo->query('SELECT * FROM donors_new WHERE id = ' . $id)->fetch();
        if ($donor && $donor['email']) {
            require_once __DIR__ . '/includes/mail_helper.php';
            $subject = "Your Donor Application Update [ID: {$donor['id']}]";
            $message = "<p>Dear {$donor['first_name']} {$donor['last_name']},</p>
            <p>Your blood donation application (ID: <b>{$donor['id']}</b>) has been reviewed.</p>
            <p><strong>Status:</strong> <b>Not Approved</b></p>
            <p><strong>Reason:</strong> {$reason}</p>
            <p>If you have any questions, please contact us.</p>";
            send_confirmation_email($donor['email'], $subject, $message, $donor['first_name'] . ' ' . $donor['last_name']);
        }
        
        // Log the action
        logAdminAction($pdo, 'donor_rejected', 'donors_new', $id, "Donor rejected with reason: $reason");
        
                    header('Location: ?tab=pending-donors&success=Donor was rejected successfully.');
        exit();
    }

    if (isset($_GET['mark_served'])) {
        $id = (int)$_GET['mark_served'];
        try {
            // Check if served_date column exists, if not use a different approach
            $stmt = $pdo->prepare('UPDATE donors_new SET status = "served" WHERE id = ?');
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Try to update served_date if column exists
                try {
                    $dateStmt = $pdo->prepare('UPDATE donors_new SET served_date = NOW() WHERE id = ?');
                    $dateStmt->execute([$id]);
                } catch (PDOException $e) {
                    // Column might not exist, that's okay
                    error_log("served_date column might not exist: " . $e->getMessage());
                }
                
                // Log the action
                logAdminAction($pdo, 'donor_marked_served', 'donors_new', $id, "Donor marked as served");
                
                header('Location: ?tab=donor-list&success=Donor was marked as served successfully.');
                exit();
            } else {
                header('Location: ?tab=donor-list&error=Failed to update donor status.');
                exit();
            }
        } catch (PDOException $e) {
            error_log("Error marking donor as served: " . $e->getMessage());
            header('Location: ?tab=donor-list&error=Database error occurred.');
            exit();
        }
    }

    if (isset($_GET['mark_unserved'])) {
        $id = (int)$_GET['mark_unserved'];
        $reason = $_GET['reason'] ?? 'No show';
        try {
            // Update status first
            $stmt = $pdo->prepare('UPDATE donors_new SET status = "unserved" WHERE id = ?');
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Try to update unserved_reason if column exists
                try {
                    $reasonStmt = $pdo->prepare('UPDATE donors_new SET unserved_reason = ? WHERE id = ?');
                    $reasonStmt->execute([$reason, $id]);
                } catch (PDOException $e) {
                    // Column might not exist, that's okay
                    error_log("unserved_reason column might not exist: " . $e->getMessage());
                }
                
                // Log the action
                logAdminAction($pdo, 'donor_marked_unserved', 'donors_new', $id, "Donor marked as unserved with reason: $reason");
                
                header('Location: ?tab=donor-list&success=Donor was marked as unserved successfully.');
                exit();
            } else {
                header('Location: ?tab=donor-list&error=Failed to update donor status.');
                exit();
            }
        } catch (PDOException $e) {
            error_log("Error marking donor as unserved: " . $e->getMessage());
            header('Location: ?tab=donor-list&error=Database error occurred.');
            exit();
        }
    }

    if (isset($_GET['delete_donor'])) {
        $id = (int)$_GET['delete_donor'];
        
        try {
            // Get donor info before deletion for logging
            $stmt = $pdo->prepare('SELECT * FROM donors_new WHERE id = ?');
            $stmt->execute([$id]);
            $donor = $stmt->fetch();
        // Check medical screening status
        $medicalScreeningStatus = "Not Completed";
        if (!empty($donor)) {
            $medStmt = $pdo->prepare("SELECT screening_data, all_questions_answered FROM donor_medical_screening_simple WHERE donor_id = ?");
            $medStmt->execute([$donor['id']]);
            $medicalData = $medStmt->fetch();
            
            if ($medicalData && !empty($medicalData['screening_data'])) {
                $medicalScreeningStatus = $medicalData['all_questions_answered'] ? "Completed" : "Partially Completed";
            }
        }
        
            
            if ($donor) {
                // Store donor info before deletion
                $donorName = $donor['first_name'] . ' ' . $donor['last_name'];
                $donorReference = $donor['id'] ?? 'N/A';
                
                // Delete related records (ignore errors if tables don't exist)
                try {
                    $pdo->prepare('DELETE FROM donor_medical_screening_simple WHERE donor_id = ?')->execute([$id]);
                } catch (PDOException $e) {}
                try {
                    $pdo->prepare('DELETE FROM donor_medical_screening_simple WHERE donor_id = ?')->execute([$id]);
                } catch (PDOException $e) {}
                
                try {
                    $pdo->prepare('DELETE FROM donor_messages WHERE donor_id = ?')->execute([$id]);
                } catch (PDOException $e) {}
                
                try {
                    $pdo->prepare('DELETE FROM donor_matching WHERE donor_id = ?')->execute([$id]);
                } catch (PDOException $e) {}
                
                // Delete the donor
                $stmt = $pdo->prepare('DELETE FROM donors_new WHERE id = ?');
                $stmt->execute([$id]);
                
                // Log the action (ignore errors)
                try {
                    logAdminAction($pdo, 'donor_deleted', 'donors_new', $id, "Donor deleted: {$donorName} ({$donorReference})");
                } catch (Exception $e) {}
                
                // Always show success if donor was found
                header('Location: ?tab=donor-list&success=Donor was deleted successfully.');
                exit();
            } else {
                header('Location: ?tab=donor-list&error=Donor with ID ' . $id . ' was not found in the database.');
                exit();
            }
        } catch (Exception $e) {
            header('Location: ?tab=donor-list&error=Error occurred while deleting donor: ' . $e->getMessage());
            exit();
        }
    }

    
    // Get counts for dashboard
    $donorCount = $pdo->query("SELECT COUNT(*) FROM donors_new")->fetchColumn();
    $pendingDonorCount = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'pending'")->fetchColumn();
    $approvedDonorCount = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'approved'")->fetchColumn();
    $servedDonorCount = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'served'")->fetchColumn();
    
    // Enhanced blood inventory analytics
    try {
        // Blood type distribution for approved and served donors
        $stmt = $pdo->query("SELECT blood_type, COUNT(*) as count FROM donors_new WHERE status IN ('approved', 'served') GROUP BY blood_type ORDER BY count DESC");
        $bloodInventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        // Monthly trends
        // Build last 12 months labels
        $months = [];
        $cursor = new DateTime('first day of this month');
        for ($i = 11; $i >= 0; $i--) {
            $m = (clone $cursor)->modify("-{$i} months");
            $key = $m->format('Y-m');
            $months[$key] = [
                'label' => $m->format('M Y'),
                'registrations' => 0,
                'donations' => 0
            ];
        }

        // Registrations per month (donors created)
        $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as c FROM donors_new WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($months[$row['ym']])) {
                $months[$row['ym']]['registrations'] = (int)$row['c'];
            }
        }

        // Donations per month: prefer donors_new.last_donation_date if available, fallback to blood_inventory.collection_date
        try {
            $hasLastDonation = $pdo->query("SHOW COLUMNS FROM donors_new LIKE 'last_donation_date'")->fetch();
        } catch (Exception $e) { $hasLastDonation = false; }

        if ($hasLastDonation) {
            $stmt = $pdo->query("SELECT DATE_FORMAT(last_donation_date, '%Y-%m') as ym, COUNT(*) as c FROM donors_new WHERE status='served' AND last_donation_date IS NOT NULL AND last_donation_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($months[$row['ym']])) {
                    $months[$row['ym']]['donations'] = (int)$row['c'];
                }
            }
        } else {
            // Fallback: count blood units collected per month
            $stmt = $pdo->query("SELECT DATE_FORMAT(collection_date, '%Y-%m') as ym, COUNT(*) as c FROM blood_inventory WHERE collection_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($months[$row['ym']])) {
                    $months[$row['ym']]['donations'] = (int)$row['c'];
                }
            }
        }

        // Flatten for the view
        $monthlyLabels = array_column($months, 'label');
        $monthlyRegistrations = array_column($months, 'registrations');
        $monthlyDonations = array_column($months, 'donations');
        
        // Status distribution
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM donors_new GROUP BY status");
        $donorStatusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent activity
        $recentActivity = $pdo->query("
                    SELECT 'donor' as type, CONCAT(d.first_name, ' ', d.last_name) as name, d.status, d.created_at, d.reference_code as reference
        FROM donors_new d
        WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $bloodInventory = [];
        $bloodRequests = [];
        $monthlyDonors = [];
        $monthlyRequests = [];
        $donorStatusDistribution = [];
        $requestStatusDistribution = [];
        $recentActivity = [];
    }
    
    // Get recent records
    $recentDonors = $pdo->query("SELECT * FROM donors_new ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
    // Fetch donors and requests for tabs
    $donors = [];
    $requests = [];
    $pendingDonors = [];
    
    if ($activeTab === 'donor-list') {
        $search = trim($_GET['donor_search'] ?? '');
        $statusFilter = $_GET['status_filter'] ?? '';
        $bloodTypeFilter = $_GET['blood_type_filter'] ?? '';
        
        // Pagination parameters
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }
        $offset = ($page - 1) * $perPage;
        
        $sql = 'SELECT d.* FROM donors_new d WHERE 1=1';
        $params = [];
        
        if ($search) {
            $sql .= ' AND (CONCAT(d.first_name, " ", d.last_name) LIKE ? OR d.email LIKE ? OR d.phone LIKE ? OR d.reference_code LIKE ?)';
            $params = array_merge($params, array_fill(0, 4, "%$search%"));
        }
        
        if ($statusFilter) {
            $sql .= ' AND d.status = ?';
            $params[] = $statusFilter;
        }
        
        if ($bloodTypeFilter) {
            $sql .= ' AND d.blood_type = ?';
            $params[] = $bloodTypeFilter;
        }
        
        // Get total count for pagination
        $countSql = str_replace('SELECT d.*', 'SELECT COUNT(*) as total', $sql);
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $perPage);
        
        // Calculate pagination info
        $startRecord = $offset + 1;
        $endRecord = min($offset + $perPage, $totalRecords);
        
        $sql .= ' ORDER BY d.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $donors = $stmt->fetchAll();
    }
    
    if ($activeTab === 'pending-donors') {
        $search = trim($_GET['donor_search'] ?? '');
        $sql = 'SELECT d.* FROM donors_new d WHERE d.status = "pending"';
        $params = [];
        
        if ($search) {
            $sql .= ' AND (CONCAT(d.first_name, " ", d.last_name) LIKE ? OR d.email LIKE ? OR d.phone LIKE ? OR d.reference_code LIKE ?)';
            $params = array_fill(0, 4, "%$search%");
        }
        
        $sql .= ' ORDER BY d.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pendingDonors = $stmt->fetchAll();
    }
    
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Helper function for pagination URLs
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #f8d7da;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        body { 
            background: #f5f5f5; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar { 
            min-height: 100vh; 
            background: var(--dark-color);
            color: white;
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,.8);
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin: 0.25rem 0.5rem;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,.1);
            color: white;
        }
        .sidebar .nav-link.active { 
            background: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        .card { 
            border: none; 
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,.05);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        .stat-card {
            border-left: 4px solid var(--primary-color);
        }
        .stat-card .card-body {
            padding: 1.25rem;
        }
        .stat-card .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-card .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-top: none;
        }
        .badge {
            padding: 0.4em 0.65em;
            font-weight: 500;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        .badge-approved {
            background-color: #198754;
        }
        .badge-rejected {
            background-color: #dc3545;
        }
        .main-content {
            padding: 1.5rem 0;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        .user-dropdown img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1100;
        }
        .stat-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .main-content {
            overflow-x: hidden;
        }
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            border: none;
        }
        .progress {
            border-radius: 10px;
            background-color: #f8f9fa;
        }
        .progress-bar {
            border-radius: 10px;
        }
        .list-group-item {
            border: none;
            border-bottom: 1px solid #f8f9fa;
            padding: 1rem;
        }
        .list-group-item:last-child {
            border-bottom: none;
        }
        /* Prevent unwanted scrolling */
        body {
            scroll-behavior: smooth;
        }
        .dashboard-container {
            min-height: 100vh;
            overflow-x: hidden;
        }
        /* Chart container improvements */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        canvas {
            max-height: 300px !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar p-0">
                <div class="p-3 text-white border-bottom">
                    <i class="fas fa-heartbeat me-2"></i>Blood Donation
                </div>
                <ul class="nav flex-column mt-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'pending-donors' ? 'active' : '' ?>" href="?tab=pending-donors">
                            <i class="fas fa-clock"></i> Pending Donors
                            <?php if ($pendingDonorCount > 0): ?>
                                <span class="badge bg-warning text-dark ms-2"><?= $pendingDonorCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'donor-list' ? 'active' : '' ?>" href="?tab=donor-list">
                            <i class="fas fa-users"></i> All Donors
                        </a>
                    </li>

        <li class="nav-item">
            <a class="nav-link" href="admin_blood_inventory_modern.php">
                <i class="fas fa-tint"></i> Blood Inventory
            </a>
        </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'audit-log' ? 'active' : '' ?>" href="?tab=audit-log">
                            <i class="fas fa-history"></i> Audit Log
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'help' ? 'active' : '' ?>" href="?tab=help">
                            <i class="fas fa-question-circle"></i> Help & Guide
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>" href="?tab=settings">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="admin_logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 p-4 main-content">
                <div class="dashboard-container">
                    <!-- Admin Role Display -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-info d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-user-shield me-2"></i>
                                    <strong>Logged in as:</strong> 
                                    <span class="badge bg-primary ms-2">
                                        <?php
                                        // Get role from session or database
                                        $adminRole = $_SESSION['admin_role'] ?? 'super_admin';
                                        if ($adminRole === 'admin') {
                                            $adminRole = 'super_admin'; // Convert old 'admin' to 'super_admin'
                                        }
                                        echo ucfirst(str_replace('_', ' ', $adminRole)) . ' Admin';
                                        ?>
                                    </span>
                                </div>
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        <?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($activeTab === 'dashboard'): ?>
                        <h2 class="mb-4">Dashboard Overview</h2>
                        
                        <!-- Stats Cards -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="stat-number"><?= number_format($donorCount) ?></div>
                                                <div class="stat-label">Total Donors</div>
                                            </div>
                                            <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                                <i class="fas fa-users text-danger" style="font-size: 1.5rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="stat-number"><?= number_format($pendingDonorCount) ?></div>
                                                <div class="stat-label">Pending Donors</div>
                                            </div>
                                            <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                                <i class="fas fa-clock text-info" style="font-size: 1.5rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Analytics Row -->
                        <div class="row g-4 mb-4">
                            <!-- Blood Inventory Chart -->
                            <div class="col-lg-8">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Blood Inventory Analysis</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($bloodInventory)): ?>
                                            <div class="chart-container">
                                                <canvas id="bloodInventoryChart"></canvas>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-chart-pie text-muted" style="font-size: 3rem;"></i>
                                                <h6 class="text-muted mt-3">No Blood Inventory Data Available</h6>
                                                <p class="text-muted">Blood inventory data will appear here once donors are approved or served.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status Distribution -->
                            <div class="col-lg-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Status Overview</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <h6>Donor Status</h6>
                                            <div class="progress mb-2" style="height: 20px;">
                                                <div class="progress-bar bg-success" style="width: <?= $donorCount > 0 ? ($approvedDonorCount / $donorCount) * 100 : 0 ?>%">
                                                    <?= $approvedDonorCount ?> Approved
                                                </div>
                                            </div>
                                            <div class="progress mb-2" style="height: 20px;">
                                                <div class="progress-bar bg-warning" style="width: <?= $donorCount > 0 ? ($pendingDonorCount / $donorCount) * 100 : 0 ?>%">
                                                    <?= $pendingDonorCount ?> Pending
                                                </div>
                                            </div>
                                            <div class="progress mb-2" style="height: 20px;">
                                                <div class="progress-bar bg-primary" style="width: <?= $donorCount > 0 ? ($servedDonorCount / $donorCount) * 100 : 0 ?>%">
                                                    <?= $servedDonorCount ?> Served
                                                </div>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity & Trends -->
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-activity me-2"></i>Recent Activity</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($recentActivity)): ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?= htmlspecialchars($activity['name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?= $activity['type'] === 'donor' ? 'Donor Registration' : 'Blood Request' ?> - 
                                                                <?= date('M d, Y H:i', strtotime($activity['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                        <span class="badge bg-<?= $activity['status'] === 'pending' ? 'warning' : ($activity['status'] === 'approved' ? 'success' : 'info') ?>">
                                                            <?= ucfirst($activity['status']) ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-activity text-muted" style="font-size: 3rem;"></i>
                                                <h6 class="text-muted mt-3">No Recent Activity</h6>
                                                <p class="text-muted">Recent activity will appear here once donors and requests are added.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Trends</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="monthlyTrendsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script>
                            // Prevent automatic scrolling
                            document.addEventListener('DOMContentLoaded', function() {
                                // Scroll to top to prevent unwanted scrolling
                                window.scrollTo(0, 0);
                            });
                            
                            // Debug: Check if data is available
                            console.log('Blood Inventory Data:', <?= json_encode($bloodInventory) ?>);
                            console.log('Monthly Donors Data:', <?= json_encode($monthlyDonors) ?>);
                            
                            // Wait for DOM to be fully loaded
                            document.addEventListener('DOMContentLoaded', function() {
                                // Blood Inventory Chart
                                const bloodInventoryCtx = document.getElementById('bloodInventoryChart');
                                if (bloodInventoryCtx && <?= !empty($bloodInventory) ? 'true' : 'false' ?>) {
                                    try {
                                        const bloodInventoryChart = new Chart(bloodInventoryCtx.getContext('2d'), {
                                            type: 'doughnut',
                                            data: {
                                                labels: <?= json_encode(array_column($bloodInventory, 'blood_type')) ?>,
                                                datasets: [{
                                                    data: <?= json_encode(array_column($bloodInventory, 'count')) ?>,
                                                    backgroundColor: [
                                                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                                                        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                                                    ],
                                                    borderWidth: 2,
                                                    borderColor: '#fff'
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: {
                                                    legend: {
                                                        position: 'bottom',
                                                        labels: {
                                                            padding: 20,
                                                            usePointStyle: true
                                                        }
                                                    },
                                                    tooltip: {
                                                        callbacks: {
                                                            label: function(context) {
                                                                return context.label + ': ' + context.parsed + ' donors';
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    } catch (error) {
                                        console.error('Error initializing Blood Inventory Chart:', error);
                                    }
                                } else {
                                    console.log('Blood Inventory Chart not available or no data');
                                }

                                // Monthly Trends Chart
                                const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart');
                                if (monthlyTrendsCtx) {
                                    try {
                                        const monthlyTrendsChart = new Chart(monthlyTrendsCtx.getContext('2d'), {
                                            type: 'line',
                                            data: {
                                                labels: <?= json_encode($monthlyLabels) ?>,
                                                datasets: [{
                                                    label: 'Registrations',
                                                    data: <?= json_encode($monthlyRegistrations) ?>,
                                                    borderColor: '#FF6384',
                                                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                                    tension: 0.4,
                                                    fill: true
                                                }, {
                                                    label: 'Donations',
                                                    data: <?= json_encode($monthlyDonations) ?>,
                                                    borderColor: '#36A2EB',
                                                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                                    tension: 0.4,
                                                    fill: true
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: {
                                                    legend: {
                                                        position: 'top'
                                                    }
                                                },
                                                scales: {
                                                    y: {
                                                        beginAtZero: true,
                                                        ticks: {
                                                            stepSize: 1
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    } catch (error) {
                                        console.error('Error initializing Monthly Trends Chart:', error);
                                    }
                                } else {
                                    console.log('Monthly Trends Chart not available or no data');
                                }
                            });
                        </script>
                        <!-- End Dashboard Tab -->
                    <?php elseif ($activeTab === 'settings'): ?>
                        <!-- Settings Tab -->
                        <div class="row">
                            <div class="col-lg-8">
                                <h2 class="mb-4">Admin Settings</h2>
                                
                                <!-- Password Change Section -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-key me-2"></i>Change Password
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="passwordChangeForm">
                                            <input type="hidden" name="action" value="change_password">
                                            
                                            <div class="row g-3">
                                                <div class="col-md-12">
                                                    <label for="current_password" class="form-label">Current Password</label>
                                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label for="new_password" class="form-label">New Password</label>
                                                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                                                    <div class="form-text">Password must be at least 8 characters long</div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>Change Password
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Account Information Section -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-user me-2"></i>Account Information
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // Get current admin info
                                        $adminStmt = $pdo->prepare("SELECT username, email, full_name, role, last_login, created_at FROM admin_users WHERE username = ?");
                                        $adminStmt->execute([$_SESSION['admin_username']]);
                                        $adminInfo = $adminStmt->fetch();
                                        ?>
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($adminInfo['username']) ?>" readonly>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" value="<?= htmlspecialchars($adminInfo['email']) ?>" readonly>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($adminInfo['full_name']) ?>" readonly>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label">Role</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($adminInfo['role'])) ?>" readonly>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label">Last Login</label>
                                                <input type="text" class="form-control" value="<?= $adminInfo['last_login'] ? date('Y-m-d H:i:s', strtotime($adminInfo['last_login'])) : 'Never' ?>" readonly>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label class="form-label">Account Created</label>
                                                <input type="text" class="form-control" value="<?= date('Y-m-d H:i:s', strtotime($adminInfo['created_at'])) ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <!-- Security Tips -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-shield-alt me-2"></i>Security Tips
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Use a strong password with at least 8 characters
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Include numbers, letters, and special characters
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Don't reuse passwords from other accounts
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Change your password regularly
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Log out when finished
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        // Password change form validation
                        document.getElementById('passwordChangeForm').addEventListener('submit', function(e) {
                            const newPassword = document.getElementById('new_password').value;
                            const confirmPassword = document.getElementById('confirm_password').value;
                            
                            if (newPassword !== confirmPassword) {
                                e.preventDefault();
                                alert('New password and confirmation do not match!');
                                return false;
                            }
                            
                            if (newPassword.length < 8) {
                                e.preventDefault();
                                alert('Password must be at least 8 characters long!');
                                return false;
                            }
                        });
                        </script>
                        <!-- End Settings Tab -->
                    <?php else: ?>
                        <!-- Direct tab content loading -->
                        <?php if ($activeTab === 'donor-list'): ?>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2>Donor Management</h2>
                            </div>
                            
                            <!-- Filter and Search Section -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <input type="hidden" name="tab" value="donor-list">
                                        
                                        <!-- Search Input -->
                                        <div class="col-md-4">
                                            <label for="donor_search" class="form-label">
                                                <i class="fas fa-search me-2"></i>Search Donors
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="donor_search" 
                                                   name="donor_search" 
                                                   value="<?= htmlspecialchars($_GET['donor_search'] ?? '') ?>"
                                                   placeholder="Search by name, email, phone, or reference...">
                                        </div>
                                        
                                        <!-- Status Filter -->
                                        <div class="col-md-3">
                                            <label for="status_filter" class="form-label">
                                                <i class="fas fa-filter me-2"></i>Status Filter
                                            </label>
                                            <select class="form-select" id="status_filter" name="status_filter">
                                                <option value="">All Statuses</option>
                                                <option value="pending" <?= ($_GET['status_filter'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="served" <?= ($_GET['status_filter'] ?? '') === 'served' ? 'selected' : '' ?>>Served</option>
                                                <option value="unserved" <?= ($_GET['status_filter'] ?? '') === 'unserved' ? 'selected' : '' ?>>Unserved</option>
                                                <option value="rejected" <?= ($_GET['status_filter'] ?? '') === 'rejected' ? 'selected' : '' ?>>Deferred</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Blood Type Filter -->
                                        <div class="col-md-3">
                                            <label for="blood_type_filter" class="form-label">
                                                <i class="fas fa-tint me-2"></i>Blood Type
                                            </label>
                                            <select class="form-select" id="blood_type_filter" name="blood_type_filter">
                                                <option value="">All Blood Types</option>
                                                <option value="A+" <?= ($_GET['blood_type_filter'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                                <option value="A-" <?= ($_GET['blood_type_filter'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                                <option value="B+" <?= ($_GET['blood_type_filter'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                                <option value="B-" <?= ($_GET['blood_type_filter'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                                <option value="AB+" <?= ($_GET['blood_type_filter'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                                <option value="AB-" <?= ($_GET['blood_type_filter'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                                <option value="O+" <?= ($_GET['blood_type_filter'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                                <option value="O-" <?= ($_GET['blood_type_filter'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Records per page -->
                                        <div class="col-md-2">
                                            <label for="per_page_filter" class="form-label">
                                                <i class="fas fa-list me-2"></i>Per Page
                                            </label>
                                            <select class="form-select" id="per_page_filter" name="per_page">
                                                <option value="10" <?= ($_GET['per_page'] ?? 20) == 10 ? 'selected' : '' ?>>10</option>
                                                <option value="20" <?= ($_GET['per_page'] ?? 20) == 20 ? 'selected' : '' ?>>20</option>
                                                <option value="50" <?= ($_GET['per_page'] ?? 20) == 50 ? 'selected' : '' ?>>50</option>
                                                <option value="100" <?= ($_GET['per_page'] ?? 20) == 100 ? 'selected' : '' ?>>100</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search me-1"></i>Search
                                                </button>
                                                <a href="?tab=donor-list" class="btn btn-outline-secondary">
                                                    <i class="fas fa-times me-1"></i>Clear
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <!-- Results Summary -->
                                    <?php if (!empty($_GET['donor_search']) || !empty($_GET['status_filter']) || !empty($_GET['blood_type_filter'])): ?>
                                        <div class="mt-3">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Showing <?= count($donors) ?> donor(s) 
                                                <?php if (!empty($_GET['donor_search'])): ?>
                                                    matching "<?= htmlspecialchars($_GET['donor_search']) ?>"
                                                <?php endif; ?>
                                                <?php if (!empty($_GET['status_filter'])): ?>
                                                    with status "<?= ucfirst($_GET['status_filter']) ?>"
                                                <?php endif; ?>
                                                <?php if (!empty($_GET['blood_type_filter'])): ?>
                                                    with blood type "<?= $_GET['blood_type_filter'] ?>"
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Reference</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Age</th>
                                            <th>Sex</th>
                                            <th>Weight</th>
                                            <th>Height</th>
                                            <th>Blood Type</th>
                                            <th>Status</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donors as $donor): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($donor['id']) ?></td>
                                                <td><code><?= htmlspecialchars($donor['reference_code'] ?? 'N/A') ?></code></td>
                                                <td><strong><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></strong></td>
                                                <td><?= htmlspecialchars($donor['email']) ?></td>
                                                <td><?= htmlspecialchars($donor['phone'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['date_of_birth'])) {
                                                        $birthDate = new DateTime($donor['date_of_birth']);
                                                        $today = new DateTime();
                                                        $age = $today->diff($birthDate)->y;
                                                        echo $age . ' years';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['gender'])) {
                                                        echo htmlspecialchars($donor['gender']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['weight'])) {
                                                        echo htmlspecialchars($donor['weight']) . ' kg';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['height'])) {
                                                        echo htmlspecialchars($donor['height']) . ' cm';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td><span class="badge bg-danger"><?= htmlspecialchars($donor['blood_type']) ?></span></td>
                                                <td>
                                                    <span class="badge bg-<?= getDonorStatusColor($donor['status']) ?>">
                                                        <?= getDonorDisplayStatus($donor['status'] ?? 'pending') ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($donor['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="admin_edit_donor.php?id=<?= $donor['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="admin_enhanced_donor_management.php?donor_id=<?= $donor['id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <a href="?tab=donor-list&delete_donor=<?= $donor['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this donor?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination Controls -->
                            <?php if ($totalPages > 1): ?>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <!-- Records per page dropdown -->
                                    <div class="d-flex align-items-center">
                                        <label for="per_page" class="form-label me-2 mb-0">Records per page:</label>
                                        <select id="per_page" class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                                            <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                                            <option value="20" <?= $perPage == 20 ? 'selected' : '' ?>>20</option>
                                            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                                            <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Pagination info -->
                                    <div class="text-muted">
                                        Showing <?= $startRecord ?>-<?= $endRecord ?> of <?= $totalRecords ?> records
                                        (Page <?= $page ?> of <?= $totalPages ?>)
                                    </div>
                                    
                                    <!-- Pagination buttons -->
                                    <nav aria-label="Donor list pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <!-- First page -->
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPaginationUrl(1) ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <!-- Previous page -->
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPaginationUrl($page - 1) ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <!-- Page numbers -->
                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <!-- Next page -->
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPaginationUrl($page + 1) ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            
                                            <!-- Last page -->
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPaginationUrl($totalPages) ?>" aria-label="Last">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                                
                                <script>
                                function changePerPage(perPage) {
                                    const url = new URL(window.location);
                                    url.searchParams.set('per_page', perPage);
                                    url.searchParams.set('page', '1'); // Reset to first page
                                    window.location.href = url.toString();
                                }
                                </script>
                            <?php endif; ?>
                        
                        <?php elseif ($activeTab === 'pending-donors'): ?>
                            <h2 class="mb-4">Pending Donors</h2>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Reference</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Age</th>
                                            <th>Sex</th>
                                            <th>Weight</th>
                                            <th>Height</th>
                                            <th>Blood Type</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingDonors as $donor): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($donor['id']) ?></td>
                                                <td><code><?= htmlspecialchars($donor['reference_code'] ?? 'N/A') ?></code></td>
                                                <td><strong><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></strong></td>
                                                <td><?= htmlspecialchars($donor['email']) ?></td>
                                                <td><?= htmlspecialchars($donor['phone'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['date_of_birth'])) {
                                                        $birthDate = new DateTime($donor['date_of_birth']);
                                                        $today = new DateTime();
                                                        $age = $today->diff($birthDate)->y;
                                                        echo $age . ' years';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['gender'])) {
                                                        echo htmlspecialchars($donor['gender']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['weight'])) {
                                                        echo htmlspecialchars($donor['weight']) . ' kg';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($donor['height'])) {
                                                        echo htmlspecialchars($donor['height']) . ' cm';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td><span class="badge bg-danger"><?= htmlspecialchars($donor['blood_type']) ?></span></td>
                                                <td><?= date('M d, Y', strtotime($donor['created_at'])) ?></td>
                                                <td>
                                                    <a href="?tab=pending-donors&approve_donor=<?= $donor['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this donor?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="?tab=pending-donors&reject_donor=<?= $donor['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this donor?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            </div>
                        
                        <?php elseif ($activeTab === 'audit-log'): ?>
                            <h2 class="mb-4">Admin Audit Log</h2>
                            <div class="card">
                                <div class="card-body">
                                    <?php
                                    try {
                                        $auditLogs = $pdo->query("SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 50")->fetchAll();
                                    } catch (Exception $e) {
                                        $auditLogs = [];
                                    }
                                    ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Admin</th>
                                                    <th>Action</th>
                                                    <th>Table</th>
                                                    <th>Record ID</th>
                                                    <th>Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($auditLogs)): ?>
                                                    <?php foreach ($auditLogs as $log): ?>
                                                        <tr>
                                                            <td><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
                                                            <td><?= htmlspecialchars($log['admin_username'] ?? 'Unknown') ?></td>
                                                            <td><?= htmlspecialchars($log['action'] ?? 'Unknown') ?></td>
                                                            <td><?= htmlspecialchars($log['table_name'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($log['record_id'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="6" class="text-center">No audit logs found</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        
                        <?php elseif ($activeTab === 'blood-inventory'): ?>
                            <h2 class="mb-4">Blood Inventory Management</h2>
                            
                            <!-- Blood Type Filter -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <input type="hidden" name="tab" value="blood-inventory">
                                        <div class="col-md-4">
                                            <label class="form-label">Filter by Blood Type:</label>
                                            <select name="blood_type_filter" class="form-select" onchange="this.form.submit()">
                                                <option value="">All Blood Types</option>
                                                <option value="A+" <?= ($_GET['blood_type_filter'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                                <option value="A-" <?= ($_GET['blood_type_filter'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                                <option value="B+" <?= ($_GET['blood_type_filter'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                                <option value="B-" <?= ($_GET['blood_type_filter'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                                <option value="AB+" <?= ($_GET['blood_type_filter'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                                <option value="AB-" <?= ($_GET['blood_type_filter'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                                <option value="O+" <?= ($_GET['blood_type_filter'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                                <option value="O-" <?= ($_GET['blood_type_filter'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                                                <option value="Unknown" <?= ($_GET['blood_type_filter'] ?? '') === 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                                            </select>
                                        </div>
                                        <?php if (!empty($_GET['blood_type_filter'])): ?>
                                            <div class="col-md-2">
                                                <label class="form-label">&nbsp;</label>
                                                <a href="?tab=blood-inventory" class="btn btn-secondary d-block">Clear Filter</a>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Alert for Critical Stock -->
                            <?php
                            $criticalStock = $pdo->query("
                                SELECT blood_type, COUNT(*) as count 
                                FROM donors_new 
                                WHERE status = 'approved' 
                                GROUP BY blood_type 
                                HAVING count <= 2
                            ")->fetchAll();
                            ?>
                            <?php if (!empty($criticalStock)): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Critical Stock Alert:</strong> The following blood types are running low:
                                    <?php foreach ($criticalStock as $stock): ?>
                                        <span class="badge bg-danger ms-2"><?= $stock['blood_type'] ?> (<?= $stock['count'] ?> units)</span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Current Blood Stock</h5>
                                            <button class="btn btn-sm btn-outline-primary" onclick="refreshInventory()">
                                                <i class="fas fa-sync-alt"></i> Refresh
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            $bloodTypeFilter = $_GET['blood_type_filter'] ?? '';
                                            $inventoryQuery = "
                                                SELECT 
                                                    blood_type,
                                                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as available_units,
                                                    COUNT(CASE WHEN status = 'served' THEN 1 END) as used_units,
                                                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_units
                                                FROM donors_new 
                                            ";
                                            
                                            if (!empty($bloodTypeFilter)) {
                                                $inventoryQuery .= " WHERE blood_type = :blood_type ";
                                            }
                                            
                                            $inventoryQuery .= " GROUP BY blood_type ORDER BY blood_type";
                                            
                                            if (!empty($bloodTypeFilter)) {
                                                $stmt = $pdo->prepare($inventoryQuery);
                                                $stmt->execute(['blood_type' => $bloodTypeFilter]);
                                                $bloodInventory = $stmt->fetchAll();
                                            } else {
                                                $bloodInventory = $pdo->query($inventoryQuery)->fetchAll();
                                            }
                                            ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead class="table-dark">
                                                        <tr>
                                                            <th>Blood Type</th>
                                                            <th>Available Units</th>
                                                            <th>Used Units</th>
                                                            <th>Pending Units</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($bloodInventory as $item): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong class="text-primary"><?= htmlspecialchars($item['blood_type']) ?></strong>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-success fs-6"><?= $item['available_units'] ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-info fs-6"><?= $item['used_units'] ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-warning fs-6"><?= $item['pending_units'] ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    $statusClass = 'success';
                                                                    $statusText = 'Good Stock';
                                                                    if ($item['available_units'] <= 2) {
                                                                        $statusClass = 'danger';
                                                                        $statusText = 'Critical';
                                                                    } elseif ($item['available_units'] <= 5) {
                                                                        $statusClass = 'warning';
                                                                        $statusText = 'Low Stock';
                                                                    }
                                                                    ?>
                                                                    <span class="badge bg-<?= $statusClass ?> fs-6">
                                                                        <?= $statusText ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewDonors('<?= $item['blood_type'] ?>')">
                                                                        <i class="fas fa-users"></i> View Donors
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header"><h5>Inventory Summary</h5></div>
                                        <div class="card-body">
                                            <?php
                                            $totalAvailable = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'approved'")->fetchColumn();
                                            $totalUsed = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'served'")->fetchColumn();
                                            $totalPending = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'pending'")->fetchColumn();
                                            $totalDonors = $pdo->query("SELECT COUNT(*) FROM donors_new")->fetchColumn();
                                            ?>
                                            <div class="row text-center">
                                                <div class="col-6 mb-3">
                                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                                        <h4 class="text-primary mb-1"><?= $totalAvailable ?></h4>
                                                        <small>Available Units</small>
                                                    </div>
                                                </div>
                                                <div class="col-6 mb-3">
                                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                                        <h4 class="text-success mb-1"><?= $totalUsed ?></h4>
                                                        <small>Used Units</small>
                                                    </div>
                                                </div>
                                                <div class="col-6 mb-3">
                                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                                        <h4 class="text-warning mb-1"><?= $totalPending ?></h4>
                                                        <small>Pending Units</small>
                                                    </div>
                                                </div>
                                                <div class="col-6 mb-3">
                                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                                        <h4 class="text-info mb-1"><?= $totalDonors ?></h4>
                                                        <small>Total Donors</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Stock Level Indicators -->
                                            <div class="mt-3">
                                                <h6>Stock Level Indicators:</h6>
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge bg-success me-2">Good</span>
                                                    <small>6+ units available</small>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge bg-warning me-2">Low</span>
                                                    <small>3-5 units available</small>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-danger me-2">Critical</span>
                                                    <small>0-2 units available</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recent Activity -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header"><h5>Recent Inventory Activity</h5></div>
                                        <div class="card-body">
                                            <?php
                                            $recentActivity = $pdo->query("
                                                SELECT 
                                                    d.first_name, d.last_name, d.blood_type, d.status, d.updated_at
                                                FROM donors_new d
                                                WHERE d.status IN ('approved', 'served')
                                                ORDER BY d.updated_at DESC
                                                LIMIT 10
                                            ")->fetchAll();
                                            ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Donor</th>
                                                            <th>Blood Type</th>
                                                            <th>Status</th>
                                                            <th>Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($recentActivity as $activity): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?></td>
                                                                <td><span class="badge bg-danger"><?= $activity['blood_type'] ?></span></td>
                                                                <td>
                                                                    <span class="badge bg-<?= $activity['status'] === 'approved' ? 'success' : 'info' ?>">
                                                                        <?= ucfirst($activity['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= date('M d, Y H:i', strtotime($activity['updated_at'])) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                        <?php elseif ($activeTab === 'help'): ?>
                            <!-- Modern Help & Guide Section -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h2 class="mb-1">Help & Guide</h2>
                                    <p class="text-muted mb-0">Complete system documentation and support resources</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary" onclick="printGuide()">
                                        <i class="fas fa-print me-1"></i>Print Guide
                                    </button>
                                    <button class="btn btn-outline-success" onclick="downloadGuide()">
                                        <i class="fas fa-download me-1"></i>Download PDF
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Welcome Banner -->
                            <div class="alert alert-gradient border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="alert-heading mb-2">
                                            <i class="fas fa-rocket me-2"></i>Welcome to Blood Donation Admin Panel
                                        </h4>
                                        <p class="mb-0">Your comprehensive blood donation management system with modern features and intuitive interface.</p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <i class="fas fa-heart fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quick Stats -->
                            <?php
                            $totalDonors = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'served'")->fetchColumn();
                            $totalBloodUnits = $pdo->query("
                                SELECT COUNT(*) FROM blood_inventory bi
                                INNER JOIN donors_new d ON bi.donor_id = d.id
                                WHERE d.status = 'served'
                            ")->fetchColumn();
                            $pendingDonors = $pdo->query("SELECT COUNT(*) FROM donors_new WHERE status = 'pending'")->fetchColumn();
                            $bloodTypes = $pdo->query("SELECT COUNT(DISTINCT blood_type) FROM donors_new WHERE status = 'served'")->fetchColumn();
                            ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="text-primary mb-2">
                                                <i class="fas fa-users fa-2x"></i>
                                            </div>
                                            <h3 class="text-primary mb-1"><?= $totalDonors ?></h3>
                                            <p class="text-muted mb-0">Served Donors</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="text-success mb-2">
                                                <i class="fas fa-tint fa-2x"></i>
                                            </div>
                                            <h3 class="text-success mb-1"><?= $totalBloodUnits ?></h3>
                                            <p class="text-muted mb-0">Blood Units</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="text-warning mb-2">
                                                <i class="fas fa-clock fa-2x"></i>
                                            </div>
                                            <h3 class="text-warning mb-1"><?= $pendingDonors ?></h3>
                                            <p class="text-muted mb-0">Pending Review</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="text-info mb-2">
                                                <i class="fas fa-vial fa-2x"></i>
                                            </div>
                                            <h3 class="text-info mb-1"><?= $bloodTypes ?></h3>
                                            <p class="text-muted mb-0">Blood Types</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Main Content Tabs -->
                            <div class="row">
                                <div class="col-lg-8">
                                    <!-- Navigation Tabs -->
                                    <ul class="nav nav-pills nav-fill mb-4" id="helpTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="overview-tab" data-bs-toggle="pill" data-bs-target="#overview" type="button" role="tab">
                                                <i class="fas fa-home me-2"></i>Overview
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="donors-tab" data-bs-toggle="pill" data-bs-target="#donors" type="button" role="tab">
                                                <i class="fas fa-users me-2"></i>Donor Management
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="inventory-tab" data-bs-toggle="pill" data-bs-target="#inventory" type="button" role="tab">
                                                <i class="fas fa-tint me-2"></i>Blood Inventory
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="features-tab" data-bs-toggle="pill" data-bs-target="#features" type="button" role="tab">
                                                <i class="fas fa-star me-2"></i>Features
                                            </button>
                                        </li>
                                    </ul>
                                    
                                    <!-- Tab Content -->
                                    <div class="tab-content" id="helpTabContent">
                                        <!-- Overview Tab -->
                                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-body">
                                                    <h4 class="card-title text-primary mb-4">
                                                        <i class="fas fa-info-circle me-2"></i>System Overview
                                                    </h4>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5 class="text-success mb-3">Key Features</h5>
                                                            <div class="list-group list-group-flush">
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                                    <strong>Server-Side Pagination</strong> - Efficient data loading
                                        </div>
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                                    <strong>Real-time Data Matching</strong> - Donors ↔ Blood Units
                                                                </div>
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                                    <strong>Advanced Search & Filters</strong> - Find data quickly
                                                                </div>
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                                    <strong>Status Management</strong> - Track donor journey
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h5 class="text-info mb-3">System Benefits</h5>
                                                            <div class="list-group list-group-flush">
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-arrow-right text-info me-2"></i>
                                                                    <strong>Improved Performance</strong> - Fast page loading
                                                                </div>
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-arrow-right text-info me-2"></i>
                                                                    <strong>Better User Experience</strong> - Intuitive interface
                                                                </div>
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-arrow-right text-info me-2"></i>
                                                                    <strong>Data Integrity</strong> - Perfect donor-inventory matching
                                                                </div>
                                                                <div class="list-group-item border-0 px-0">
                                                                    <i class="fas fa-arrow-right text-info me-2"></i>
                                                                    <strong>Scalable Design</strong> - Handles large datasets
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="alert alert-light border mt-4">
                                                        <h6 class="text-primary mb-2">
                                                            <i class="fas fa-lightbulb me-2"></i>Quick Start
                                                        </h6>
                                                        <p class="mb-0">Start by reviewing pending donors, then check the blood inventory to see the complete donor-to-blood-unit relationship. Use the search and filter options to find specific information quickly.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Donor Management Tab -->
                                        <div class="tab-pane fade" id="donors" role="tabpanel">
                                            <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                                    <h4 class="card-title text-primary mb-4">
                                                        <i class="fas fa-users me-2"></i>Donor Management Guide
                                                    </h4>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="card bg-light border-0 mb-3">
                                                                <div class="card-body">
                                                                    <h5 class="text-success">
                                                                        <i class="fas fa-user-clock me-2"></i>All Donors List
                                                                    </h5>
                                                                    <p class="mb-3">Complete donor database with pagination and advanced filtering.</p>
                                                <ul class="list-unstyled">
                                                                        <li><i class="fas fa-search text-primary me-2"></i>Search by name, email, phone, or reference</li>
                                                                        <li><i class="fas fa-filter text-info me-2"></i>Filter by status and blood type</li>
                                                                        <li><i class="fas fa-list text-success me-2"></i>Choose records per page (10, 20, 50, 100)</li>
                                                                        <li><i class="fas fa-edit text-warning me-2"></i>Edit, view, or delete donor records</li>
                                                </ul>
                                                                    <a href="?tab=donor-list" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-external-link-alt me-1"></i>Go to Donor List
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="card bg-light border-0 mb-3">
                                                                <div class="card-body">
                                                                    <h5 class="text-info">
                                                                        <i class="fas fa-user-check me-2"></i>Donor Status Workflow
                                                                    </h5>
                                                                    <p class="mb-3">Understanding the donor approval process.</p>
                                                                    <div class="timeline">
                                                                        <div class="timeline-item">
                                                                            <span class="badge bg-warning">Pending</span>
                                                                            <span class="ms-2">New application received</span>
                                                                        </div>
                                                                        <div class="timeline-item">
                                                                            <span class="badge bg-info">Served</span>
                                                                            <span class="ms-2">Donation completed</span>
                                                                        </div>
                                                                        <div class="timeline-item">
                                                                            <span class="badge bg-danger">Unserved</span>
                                                                            <span class="ms-2">No-show or rejected</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                            </div>
                                            
                                                    <div class="alert alert-info border-0">
                                                        <h6 class="text-info mb-2">
                                                            <i class="fas fa-info-circle me-2"></i>Data Matching
                                                        </h6>
                                                        <p class="mb-0">All blood units in the inventory are linked to served donors. This ensures data integrity and prevents orphaned records. Each donor can have multiple blood units (1-2 units average).</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Blood Inventory Tab -->
                                        <div class="tab-pane fade" id="inventory" role="tabpanel">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-body">
                                                    <h4 class="card-title text-primary mb-4">
                                                        <i class="fas fa-tint me-2"></i>Blood Inventory Management
                                                    </h4>
                                                    
                                                <div class="row">
                                                    <div class="col-md-6">
                                                            <h5 class="text-success mb-3">Inventory Features</h5>
                                                            <ul class="list-unstyled">
                                                                <li class="mb-2">
                                                                    <i class="fas fa-check text-success me-2"></i>
                                                                    <strong>Real-time Tracking</strong> - Live inventory updates
                                                                </li>
                                                                <li class="mb-2">
                                                                    <i class="fas fa-check text-success me-2"></i>
                                                                    <strong>Donor Linking</strong> - Every unit linked to a served donor
                                                                </li>
                                                                <li class="mb-2">
                                                                    <i class="fas fa-check text-success me-2"></i>
                                                                    <strong>Status Management</strong> - Available, Used, Expired, Quarantined
                                                                </li>
                                                                <li class="mb-2">
                                                                    <i class="fas fa-check text-success me-2"></i>
                                                                    <strong>Pagination</strong> - Efficient browsing of large datasets
                                                                </li>
                                                        </ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                            <h5 class="text-info mb-3">Current Status</h5>
                                                            <div class="row text-center">
                                                                <div class="col-6 mb-2">
                                                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                                                        <h6 class="text-success mb-1"><?= $totalBloodUnits ?></h6>
                                                                        <small>Total Units</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6 mb-2">
                                                                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                                                                        <h6 class="text-primary mb-1"><?= $totalDonors ?></h6>
                                                                        <small>Linked Donors</small>
                                                                    </div>
                                                                </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                                    <div class="alert alert-warning border-0">
                                                        <h6 class="text-warning mb-2">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>Important Note
                                                        </h6>
                                                        <p class="mb-0">The blood inventory only shows units from donors with "served" status. This ensures data accuracy and prevents display of units from incomplete donations.</p>
                                            </div>
                                            
                                                    <a href="admin_blood_inventory_modern.php" class="btn btn-primary">
                                                        <i class="fas fa-external-link-alt me-2"></i>Open Blood Inventory
                                                    </a>
                                                </div>
                                            </div>
                                            </div>
                                            
                                        <!-- Features Tab -->
                                        <div class="tab-pane fade" id="features" role="tabpanel">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-body">
                                                    <h4 class="card-title text-primary mb-4">
                                                        <i class="fas fa-star me-2"></i>System Features & Capabilities
                                                    </h4>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5 class="text-success mb-3">Core Features</h5>
                                                            <div class="feature-item mb-3">
                                                                <div class="d-flex align-items-start">
                                                                    <div class="feature-icon me-3">
                                                                        <i class="fas fa-server text-primary"></i>
                                            </div>
                                                                    <div>
                                                                        <h6 class="mb-1">Server-Side Pagination</h6>
                                                                        <p class="text-muted small mb-0">Efficient data loading with configurable page sizes (10, 20, 50, 100 records)</p>
                                        </div>
                                    </div>
                                </div>
                                
                                                            <div class="feature-item mb-3">
                                                                <div class="d-flex align-items-start">
                                                                    <div class="feature-icon me-3">
                                                                        <i class="fas fa-link text-success"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h6 class="mb-1">Data Integrity</h6>
                                                                        <p class="text-muted small mb-0">Perfect matching between donors and blood units with foreign key constraints</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="feature-item mb-3">
                                                                <div class="d-flex align-items-start">
                                                                    <div class="feature-icon me-3">
                                                                        <i class="fas fa-search text-info"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h6 class="mb-1">Advanced Search</h6>
                                                                        <p class="text-muted small mb-0">Multi-field search with real-time filtering and sorting capabilities</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <h5 class="text-info mb-3">Technical Features</h5>
                                                            <div class="feature-item mb-3">
                                                                <div class="d-flex align-items-start">
                                                                    <div class="feature-icon me-3">
                                                                        <i class="fas fa-mobile-alt text-primary"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h6 class="mb-1">Responsive Design</h6>
                                                                        <p class="text-muted small mb-0">Works perfectly on desktop, tablet, and mobile devices</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="feature-item mb-3">
                                                                <div class="d-flex align-items-start">
                                                                    <div class="feature-icon me-3">
                                                                        <i class="fas fa-shield-alt text-success"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h6 class="mb-1">Security Features</h6>
                                                                        <p class="text-muted small mb-0">CSRF protection, input validation, and secure session management</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="feature-item mb-3">
                                                                <div class="d-flex align-items-start">
                                                                    <div class="feature-icon me-3">
                                                                        <i class="fas fa-chart-line text-warning"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h6 class="mb-1">Analytics & Reporting</h6>
                                                                        <p class="text-muted small mb-0">Real-time statistics and comprehensive reporting capabilities</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sidebar -->
                                <div class="col-lg-4">
                                    <!-- Quick Actions -->
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0">
                                                <i class="fas fa-bolt me-2"></i>Quick Actions
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <a href="?tab=donor-list" class="btn btn-outline-primary">
                                                    <i class="fas fa-users me-2"></i>Manage Donors
                                                </a>
                                                <a href="admin_blood_inventory_modern.php" class="btn btn-outline-success">
                                                    <i class="fas fa-tint me-2"></i>Blood Inventory
                                                </a>
                                                <a href="?tab=pending-donors" class="btn btn-outline-warning">
                                                    <i class="fas fa-user-clock me-2"></i>Pending Review
                                                </a>
                                                <a href="?tab=audit-log" class="btn btn-outline-info">
                                                    <i class="fas fa-history me-2"></i>Audit Log
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- System Status -->
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-header bg-success text-white">
                                            <h5 class="mb-0">
                                                <i class="fas fa-heartbeat me-2"></i>System Health
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col-6 mb-3">
                                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                                        <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                                        <h6 class="text-success mb-1">Online</h6>
                                                        <small class="text-muted">System Status</small>
                                                    </div>
                                                </div>
                                                <div class="col-6 mb-3">
                                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                                        <i class="fas fa-database text-info fa-2x mb-2"></i>
                                                        <h6 class="text-info mb-1">Connected</h6>
                                                        <small class="text-muted">Database</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <!-- Custom Styles -->
                            <style>
                            .alert-gradient {
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            }
                            .feature-item {
                                border-left: 3px solid #e9ecef;
                                padding-left: 15px;
                            }
                            .feature-icon {
                                width: 40px;
                                height: 40px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                background: #f8f9fa;
                                border-radius: 50%;
                            }
                            .timeline-item {
                                padding: 5px 0;
                                border-left: 2px solid #e9ecef;
                                padding-left: 15px;
                                margin-left: 10px;
                            }
                            .timeline-item:last-child {
                                border-left: none;
                            }
                            </style>
                            
                            <!-- JavaScript -->
                            <script>
                            function printGuide() {
                                window.print();
                            }
                            
                            function downloadGuide() {
                                alert('PDF download feature coming soon!');
                            }
                            
                            </script>
                        
                        <?php elseif ($activeTab === 'update-contact'): ?>
                            <?php
                            // Contact Info tab removed
                            header('Location: admin.php?tab=dashboard');
                            exit;
                            ?>
                        
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <h4>Tab Content</h4>
                                <p><strong>Active Tab:</strong> <?= htmlspecialchars($activeTab) ?></p>
                                <p>This tab is under development.</p>
                            </div>
                        <?php endif; ?>

                        <script>
                        function findDonors(bloodType, requestId) {
                            alert('Finding donors with blood type: ' + bloodType + ' for request ID: ' + requestId);
                            // You can implement actual donor matching logic here
                        }
                        
                        function refreshInventory() {
                            location.reload();
                        }
                        
                        function viewDonors(bloodType) {
                            // Redirect to donor list filtered by blood type
                            window.location.href = '?tab=donor-list&blood_type=' + encodeURIComponent(bloodType);
                        }
                        </script>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="toast-container">
        <!-- Toasts will be added here dynamically -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global audit log functions - available for all tabs
        window.clearAuditLogFilters = function() {
            try {
                console.log('clearAuditLogFilters called from global scope');
                
                // Clear all form inputs
                const actionType = document.getElementById('action_type');
                const dateFrom = document.getElementById('date_from');
                const dateTo = document.getElementById('date_to');
                
                console.log('Found elements:', {
                    actionType: actionType ? 'yes' : 'no',
                    dateFrom: dateFrom ? 'yes' : 'no',
                    dateTo: dateTo ? 'yes' : 'no'
                });
                
                if (actionType) actionType.value = '';
                if (dateFrom) dateFrom.value = '';
                if (dateTo) dateTo.value = '';
                
                console.log('Cleared form values');
                
                // Redirect to the audit log tab WITHOUT any filter parameters
                console.log('Redirecting to clean URL without filter parameters');
                window.location.href = 'admin.php?tab=audit-log';
            } catch (error) {
                console.error('Error clearing audit log filters:', error);
                // Fallback: redirect anyway
                window.location.href = 'admin.php?tab=audit-log';
            }
        };
        
        // Simple direct function for clearing filters
        window.clearAuditLogFiltersDirect = function() {
            console.log('clearAuditLogFiltersDirect called');
            try {
                // Clear form inputs
                const actionType = document.getElementById('action_type');
                const dateFrom = document.getElementById('date_from');
                const dateTo = document.getElementById('date_to');
                
                console.log('Found elements for clearing:', {
                    actionType: actionType ? 'yes' : 'no',
                    dateFrom: dateFrom ? 'yes' : 'no',
                    dateTo: dateTo ? 'yes' : 'no'
                });
                
                // Clear each element and log the result
                if (actionType) {
                    actionType.value = '';
                    console.log('Cleared action_type, new value:', actionType.value);
                }
                if (dateFrom) {
                    dateFrom.value = '';
                    console.log('Cleared date_from, new value:', dateFrom.value);
                }
                if (dateTo) {
                    dateTo.value = '';
                    console.log('Cleared date_to, new value:', dateTo.value);
                }
                
                console.log('All form elements cleared successfully');
                console.log('Redirecting to clean URL without filter parameters...');
                
                // Force a hard refresh by adding a timestamp to prevent caching
                const timestamp = new Date().getTime();
                const cleanURL = 'admin.php?tab=audit-log&_t=' + timestamp;
                console.log('Redirecting to:', cleanURL);
                
                // Redirect to the audit log tab WITHOUT any filter parameters
                // This ensures the server-side PHP code doesn't see any filter values
                window.location.href = cleanURL;
            } catch (error) {
                console.error('Error in clearAuditLogFiltersDirect:', error);
                // Just redirect anyway
                console.log('Error occurred, redirecting anyway...');
                window.location.href = 'admin.php?tab=audit-log';
            }
        };
        
        // Test function for debugging
        window.testAuditLogFunction = function() {
            console.log('testAuditLogFunction called');
            
            // Test if we can find the form elements
            const actionType = document.getElementById('action_type');
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            console.log('Form elements found:', {
                actionType: actionType ? 'yes' : 'no',
                dateFrom: dateFrom ? 'yes' : 'no',
                dateTo: dateTo ? 'yes' : 'no'
            });
            
            // Show current values
            if (actionType) {
                console.log('Current action_type value:', actionType.value);
            }
            if (dateFrom) {
                console.log('Current date_from value:', dateFrom.value);
            }
            if (dateTo) {
                console.log('Current date_to value:', dateTo.value);
            }
            
            alert('Test function works! clearAuditLogFiltersDirect is available: ' + (typeof window.clearAuditLogFiltersDirect === 'function') + 
                  '\n\nCurrent form values:' +
                  '\naction_type: ' + (actionType ? actionType.value : 'not found') +
                  '\ndate_from: ' + (dateFrom ? dateFrom.value : 'not found') +
                  '\ndate_to: ' + (dateTo ? dateTo.value : 'not found'));
        };
        
        // Test version that doesn't redirect immediately
        window.testClearFunction = function() {
            console.log('testClearFunction called');
            try {
                // Clear form inputs
                const actionType = document.getElementById('action_type');
                const dateFrom = document.getElementById('date_from');
                const dateTo = document.getElementById('date_to');
                
                console.log('Before clearing - Form values:', {
                    actionType: actionType ? actionType.value : 'not found',
                    dateFrom: dateFrom ? dateFrom.value : 'not found',
                    dateTo: dateTo ? dateTo.value : 'not found'
                });
                
                // Clear each element
                if (actionType) {
                    actionType.value = '';
                }
                if (dateFrom) {
                    dateFrom.value = '';
                }
                if (dateTo) {
                    dateTo.value = '';
                }
                
                console.log('After clearing - Form values:', {
                    actionType: actionType ? actionType.value : 'not found',
                    dateFrom: dateFrom ? dateFrom.value : 'not found',
                    dateTo: dateTo ? dateTo.value : 'not found'
                });
                
                alert('Form cleared! Check console for details. Click OK to redirect to clean URL.');
                window.location.href = 'admin.php?tab=audit-log';
            } catch (error) {
                console.error('Error in testClearFunction:', error);
                alert('Error: ' + error.message);
            }
        };
        
        // Function to force a complete page reload
        window.forceReload = function() {
            console.log('Force reloading page...');
            // Force a complete page reload by changing location and reloading
            window.location.href = 'admin.php?tab=audit-log&_reload=' + new Date().getTime();
        };
        
        // Function to show current URL parameters
        window.showCurrentURLParams = function() {
            const urlParams = new URLSearchParams(window.location.search);
            console.log('Current URL parameters:');
            for (let [key, value] of urlParams) {
                console.log(key + ': ' + value);
            }
            
            alert('Current URL parameters:\n' + 
                  Array.from(urlParams.entries())
                    .map(([key, value]) => key + ': ' + value)
                    .join('\n') || 'No parameters');
        };
        
        window.exportAuditLog = function() {
            try {
                console.log('exportAuditLog called from global scope');
                
                // Get current filter parameters
                const params = new URLSearchParams(window.location.search);
                params.set('export', '1');
                
                // Create download link
                const link = document.createElement('a');
                link.href = window.location.pathname + '?' + params.toString();
                link.download = 'audit_log_' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                console.log('Export link created and clicked');
            } catch (error) {
                console.error('Error exporting audit log:', error);
                alert('Error exporting audit log. Please try again.');
            }
        };
        
        // Ensure functions are available immediately
        console.log('Audit log functions initialized globally');
        console.log('clearAuditLogFilters available:', typeof window.clearAuditLogFilters === 'function');
        console.log('exportAuditLog available:', typeof window.exportAuditLog === 'function');

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Function to show toast notifications
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.role = 'alert';
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.id = toastId;
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast from DOM after it's hidden
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }

        // Show any success/error messages from PHP in toasts
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_GET['success'])): ?>
                showToast('<?= addslashes($_GET['success']) ?>', 'success');
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                showToast('<?= addslashes($_GET['error']) ?>', 'danger');
            <?php endif; ?>
        });

        // Donor Matching Functions
        function findDonors(bloodType, requestId) {
            // Show loading state
            showToast('Searching for compatible donors...', 'info');
            
            // Redirect to donor matching page with request details
            window.location.href = `admin.php?tab=donor-matching&request_id=${requestId}&blood_type=${bloodType}`;
        }

        function viewRequestDetails(requestId) {
            // Show request details in a modal or redirect to details page
            showToast('Loading request details...', 'info');
            
            // For now, just show a simple alert with the request ID
            // In a full implementation, this would open a modal with detailed information
            alert(`Viewing details for Blood Request #${requestId}\n\nThis would show:\n- Patient information\n- Hospital details\n- Contact information\n- Request history\n- Current status`);
        }

        function createDonorMatch(requestId, donorId, matchScore) {
            if (confirm('Create match between this donor and blood request?')) {
                // Send AJAX request to create match
                fetch('admin_actions_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=create_match&request_id=${requestId}&donor_id=${donorId}&match_score=${matchScore}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Donor match created successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Failed to create match: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error creating match. Please try again.', 'danger');
                });
            }
        }

        function updateMatchStatus(matchId, newStatus) {
            if (confirm(`Update match status to "${newStatus}"?`)) {
                fetch('admin_actions_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_match_status&match_id=${matchId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Match status updated successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Failed to update status: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error updating status. Please try again.', 'danger');
                });
            }
        }
    </script>
</body>
</html>

