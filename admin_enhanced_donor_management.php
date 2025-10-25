<?php
/**
 * Enhanced Donor Management Admin Interface
 * Comprehensive donor approval and status management system
 */

require_once 'db.php';
require_once __DIR__ . '/admin/includes/admin_auth.php';
require_once 'includes/enhanced_donor_management.php';
require_once 'includes/admin_actions.php';

// Create necessary tables
createDonorManagementTables($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'bulk_approve':
            // CSRF check
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorIds = $_POST['donor_ids'] ?? [];
            if (is_string($donorIds)) {
                $decoded = json_decode($donorIds, true);
                if (is_array($decoded)) { $donorIds = $decoded; }
            }
            $successCount = 0;
            
            foreach ($donorIds as $donorId) {
                if (approveDonor($pdo, (int)$donorId)) {
                    $successCount++;
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Approved $successCount donors successfully"]);
            exit;
            
        case 'bulk_unserved':
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorIds = $_POST['donor_ids'] ?? [];
            if (is_string($donorIds)) {
                $decoded = json_decode($donorIds, true);
                if (is_array($decoded)) { $donorIds = $decoded; }
            }
            $reason = $_POST['reason'];
            $customNote = $_POST['custom_note'] ?? '';
            $successCount = 0;
            
            foreach ($donorIds as $donorId) {
                if (markDonorUnserved($pdo, (int)$donorId, $reason, $customNote)) {
                    $successCount++;
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Marked $successCount donors as unserved"]);
            exit;
            
        case 'bulk_served':
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorIds = $_POST['donor_ids'] ?? [];
            if (is_string($donorIds)) {
                $decoded = json_decode($donorIds, true);
                if (is_array($decoded)) { $donorIds = $decoded; }
            }
            $donationDate = $_POST['donation_date'] ?? date('Y-m-d');
            $successCount = 0;
            
            foreach ($donorIds as $donorId) {
                if (markDonorServed($pdo, (int)$donorId, $donationDate)) {
                    $successCount++;
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Marked $successCount donors as served"]);
            exit;
            
        case 'bulk_communication':
            $donorIds = $_POST['donor_ids'] ?? [];
            $subject = $_POST['subject'];
            $message = $_POST['message'];
            $successCount = 0;
            
            foreach ($donorIds as $donorId) {
                $donor = getDonorDetails($pdo, (int)$donorId);
                if ($donor && !empty($donor['email'])) {
                    require_once 'includes/mail_helper.php';
                    if (send_confirmation_email($donor['email'], $subject, $message)) {
                        $successCount++;
                    }
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Sent communication to $successCount donors"]);
            exit;
            
        case 'approve_donor':
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorId = (int)$_POST['donor_id'];
            
            if (approveDonor($pdo, $donorId)) {
                echo json_encode(['success' => true, 'message' => 'Donor approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve donor']);
            }
            exit;
            
        case 'mark_unserved':
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorId = (int)$_POST['donor_id'];
            $reason = $_POST['reason'];
            $customNote = $_POST['custom_note'] ?? '';
            try {
                if (markDonorUnserved($pdo, $donorId, $reason, $customNote)) {
                    echo json_encode(['success' => true, 'message' => 'Donor marked as unserved']);
                } else {
                    $ei = $pdo->errorInfo();
                    $err = isset($ei[2]) ? $ei[2] : 'Unknown database error';
                    echo json_encode(['success' => false, 'message' => 'Failed to mark donor as unserved', 'error' => $err]);
                }
            } catch (Throwable $e) {
                error_log('mark_unserved error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to mark donor as unserved', 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'mark_served':
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorId = (int)$_POST['donor_id'];
            $donationDate = $_POST['donation_date'] ?? date('Y-m-d');
            try {
                if (markDonorServed($pdo, $donorId, $donationDate)) {
                    echo json_encode(['success' => true, 'message' => 'Donor marked as served']);
                } else {
                    $ei = $pdo->errorInfo();
                    $err = isset($ei[2]) ? $ei[2] : 'Unknown database error';
                    echo json_encode(['success' => false, 'message' => 'Failed to mark donor as served', 'error' => $err]);
                }
            } catch (Throwable $e) {
                error_log('mark_served error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to mark donor as served', 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'update_donor_status':
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorId = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : 0;
            $newStatus = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';

            if (!$donorId || empty($newStatus)) {
                echo json_encode(['success' => false, 'message' => 'Missing donor_id or status']);
                exit;
            }

            try {
                // Clear any previous error
                unset($GLOBALS['last_donor_error']);
                
                $ok = updateDonorStatus($pdo, $donorId, $newStatus, $notes);
                if ($ok) {
                    echo json_encode(['success' => true, 'message' => 'Donor status updated successfully']);
                } else {
                    // Check if there's a detailed error message from the function
                    $detailedError = $GLOBALS['last_donor_error'] ?? null;
                    
                    // If no detailed error, check PDO error
                    if (!$detailedError) {
                        $ei = $pdo->errorInfo();
                        $detailedError = isset($ei[2]) ? $ei[2] : 'Unknown database error';
                    }
                    
                    error_log('update_donor_status failed: ' . $detailedError);
                    echo json_encode(['success' => false, 'message' => 'Failed to update donor status', 'error' => $detailedError]);
                }
            } catch (Throwable $e) {
                error_log('update_donor_status error: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                echo json_encode(['success' => false, 'message' => 'Failed to update donor status', 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'add_note':
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
                exit;
            }
            $donorId = (int)$_POST['donor_id'];
            $note = $_POST['note'];
            
            if (addDonorNote($pdo, $donorId, $note)) {
                echo json_encode(['success' => true, 'message' => 'Note added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add note']);
            }
            exit;
    }
}



// Get donor details for modal (for backward compatibility)
$donorId = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;
$donor = null;
$notes = [];

if ($donorId > 0) {
    $donor = getDonorDetails($pdo, $donorId);
    $notes = getDonorNotes($pdo, $donorId);
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$bloodTypeFilter = $_GET['blood_type'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Get donors list with filters
$donors = getDonorsList($pdo, $statusFilter);
$stats = getDonorStatistics($pdo);
$unservedReasons = getUnservedReasons();

// Apply additional filters
if ($bloodTypeFilter) {
    $donors = array_filter($donors, fn($d) => $d['blood_type'] === $bloodTypeFilter);
}
if ($searchTerm) {
    $donors = array_filter($donors, fn($d) => 
        stripos($d['first_name'] . ' ' . $d['last_name'], $searchTerm) !== false ||
        stripos($d['email'], $searchTerm) !== false ||
        stripos($d['reference_code'], $searchTerm) !== false
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Donor Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        .modal-xl {
            max-width: 90%;
        }
        .donor-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .note-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .medical-section {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .bulk-actions {
            background: #fff3cd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .checkbox-column {
            width: 40px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="admin.php?tab=donor-list" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin
                    </a>
                </div>
                
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status Filter</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="served" <?= $statusFilter === 'served' ? 'selected' : '' ?>>Served</option>
                                <option value="unserved" <?= $statusFilter === 'unserved' ? 'selected' : '' ?>>Unserved</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Blood Type</label>
                            <select name="blood_type" class="form-select" onchange="this.form.submit()">
                                <option value="">All Blood Types</option>
                                <option value="A+" <?= $bloodTypeFilter === 'A+' ? 'selected' : '' ?>>A+</option>
                                <option value="A-" <?= $bloodTypeFilter === 'A-' ? 'selected' : '' ?>>A-</option>
                                <option value="B+" <?= $bloodTypeFilter === 'B+' ? 'selected' : '' ?>>B+</option>
                                <option value="B-" <?= $bloodTypeFilter === 'B-' ? 'selected' : '' ?>>B-</option>
                                <option value="AB+" <?= $bloodTypeFilter === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                <option value="AB-" <?= $bloodTypeFilter === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                <option value="O+" <?= $bloodTypeFilter === 'O+' ? 'selected' : '' ?>>O+</option>
                                <option value="O-" <?= $bloodTypeFilter === 'O-' ? 'selected' : '' ?>>O-</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, email, or reference" value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                                <a href="admin_enhanced_donor_management.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions" style="display: none;">
                    <h6><i class="fas fa-tasks me-2"></i>Bulk Actions</h6>
                    <div class="row g-2">
                        <div class="col-md-2">
                            <button class="btn btn-success btn-sm" onclick="bulkApprove()">
                                <i class="fas fa-check me-1"></i>Approve Selected
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-danger btn-sm" onclick="bulkUnserved()">
                                <i class="fas fa-times me-1"></i>Mark Unserved
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-info btn-sm" onclick="bulkServed()">
                                <i class="fas fa-heart me-1"></i>Mark Served
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-sm" onclick="bulkCommunication()">
                                <i class="fas fa-envelope me-1"></i>Send Message
                            </button>
                        </div>
                        <div class="col-md-2">
                            <span id="selectedCount" class="badge bg-secondary">0 selected</span>
                        </div>
                    </div>
                </div>
                
                <!-- Donors Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Donor Applications - Admin Controlled Workflow</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th class="checkbox-column">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th>ID</th>
                                        <th>Reference</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Blood Type</th>
                                        <th>Status</th>
                                        <th>Medical Screening</th>
                                        <th>Registration Date</th>
                                        <th>Admin Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donors as $donor): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="donor-checkbox" value="<?= $donor['id'] ?>" onchange="updateBulkActions()">
                                        </td>
                                        <td><?= $donor['id'] ?></td>
                                        <td><code><?= htmlspecialchars($donor['reference_code'] ?? 'N/A') ?></code></td>
                                        <td><strong><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($donor['email'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($donor['phone'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-danger"><?= htmlspecialchars($donor['blood_type']) ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= getDonorStatusColor($donor['status']) ?> status-badge">
                                                <?= getDonorDisplayStatus($donor['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $screeningStatus = $donor['medical_screening_status'] ?? 'Not Completed';
                                            $screeningClass = $screeningStatus === 'Completed' ? 'success' : ($screeningStatus === 'Partially Completed' ? 'warning' : 'secondary');
                                            ?>
                                            <span class="badge bg-<?= $screeningClass ?> status-badge">
                                                <?= $screeningStatus ?>
                                            </span>
                                            <?php if ($screeningStatus === 'Completed'): ?>
                                                <br><small>
                                                    <button class="btn btn-sm btn-outline-primary mt-1" onclick="viewMedicalScreening(<?= $donor['id'] ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($donor['created_at'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-primary" onclick="viewDonor(<?= $donor['id'] ?>)">
                                                    <i class="fas fa-info-circle"></i> View More Information
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="updateDonorStatus(<?= $donor['id'] ?>, '<?= $donor['status'] ?>')">
                                                    <i class="fas fa-edit"></i> Update Status
                                                </button>
                                                <?php if ($donor['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveDonor(<?= $donor['id'] ?>)">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="markUnserved(<?= $donor['id'] ?>)">
                                                        <i class="fas fa-times"></i> Unserved
                                                    </button>
                                                <?php elseif ($donor['status'] === 'approved'): ?>
                                                    
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-secondary" onclick="addNote(<?= $donor['id'] ?>)">
                                                    <i class="fas fa-sticky-note"></i> Note
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Donor Details Modal -->
    <div class="modal fade" id="donorModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Donor Information - Complete Profile Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="donorModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading donor details...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading donor information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approve Donor Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Donor - Verification Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Verification Checklist:</strong>
                    </div>
                    <ul>
                        <li>✅ Personal information is complete and accurate</li>
                        <li>✅ Contact details are valid</li>
                        <li>✅ Medical questionnaire has been reviewed</li>
                        <li>✅ No disqualifying medical conditions</li>
                        <li>✅ Blood type is confirmed</li>
                    </ul>
                    <p><strong>This action will:</strong></p>
                    <ul>
                        <li>Change status to "Approved"</li>
                        <li>Send approval email with visit instructions</li>
                        <li>Allow donor to visit Red Cross from 8:00 AM to 5:00 PM</li>
                        <li>Log this action in audit trail</li>
                    </ul>
                    <input type="hidden" id="approveDonorId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmApprove()">
                        <i class="fas fa-check me-2"></i>Approve Donor
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mark Unserved Modal -->
    <div class="modal fade" id="unservedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Unserved - Reason Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="unservedForm">
                        <input type="hidden" id="unservedDonorId" name="donor_id">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Reason for Unserved</label>
                            <select class="form-select" id="unservedReason" name="reason" required>
                                <option value="">Select a reason</option>
                                <?php foreach ($unservedReasons as $key => $reason): ?>
                                    <option value="<?= $key ?>"><?= $reason ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes (Optional)</label>
                            <textarea class="form-control" id="unservedCustomNote" name="custom_note" rows="3" placeholder="Add any additional notes..."></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>This action will:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Change status to "Unserved"</li>
                                <li>Send email with reason to donor</li>
                                <li>Log this action in audit trail</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmUnserved()">
                        <i class="fas fa-times me-2"></i>Mark as Unserved
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mark Served Modal -->
    <div class="modal fade" id="servedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Served - Donation Completed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="servedForm">
                        <input type="hidden" id="servedDonorId" name="donor_id">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Donation Date</label>
                            <input type="date" class="form-control" id="donationDate" name="donation_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-heart me-2"></i>
                            <strong>This action will:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Change status to "Served"</li>
                                <li>Record the donation in database</li>
                                <li>Send thank you email to donor</li>
                                <li>Log this action in audit trail</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="confirmServed()">
                        <i class="fas fa-heart me-2"></i>Mark as Served
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Donor Status Modal -->
    <div class="modal fade" id="donorStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Donor Status - Admin Control</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="donorStatusForm">
                        <input type="hidden" id="donorStatusId" name="donor_id">
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <input type="text" class="form-control" id="currentDonorStatus" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" id="newDonorStatus" name="status" required>
                                <option value="">Select new status</option>
                                <?php 
                                $donorStatusOptions = getDonorStatusOptions();
                                foreach ($donorStatusOptions as $key => $description): 
                                ?>
                                    <option value="<?= $key ?>"><?= $description ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="donorStatusNotes" name="notes" rows="3" placeholder="Add notes about this status change..."></textarea>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnUpdateStatus" onclick="confirmDonorStatusUpdate()">
                        <i class="fas fa-edit me-2"></i>Update Status
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Communication Modal -->
    <div class="modal fade" id="bulkCommunicationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Bulk Communication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="bulkCommunicationForm">
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" id="bulkSubject" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="bulkMessage" name="message" rows="5" required></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This message will be sent to all selected donors.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmBulkCommunication()">
                        <i class="fas fa-envelope me-2"></i>Send Message
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Note Modal -->
    <div class="modal fade" id="noteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Internal Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="noteForm">
                        <input type="hidden" id="noteDonorId" name="donor_id">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" id="noteText" name="note" rows="4" placeholder="Enter internal note..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmAddNote()">
                        <i class="fas fa-sticky-note me-2"></i>Add Note
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Medical Screening Modal -->
    <div class="modal fade" id="medicalScreeningModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Medical Screening Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="medicalScreeningModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading screening details...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading medical screening information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Helper: POST with AJAX header and safe JSON parsing
        function postJson(url, formData) {
            return fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(async (res) => {
                const text = await res.text();
                try { return JSON.parse(text); }
                catch (e) { throw new Error(text || 'Invalid server response'); }
            });
        }
        // Bulk actions functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.donor-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.donor-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkActions.style.display = 'block';
                selectedCount.textContent = `${checkboxes.length} selected`;
            } else {
                bulkActions.style.display = 'none';
            }
        }
        
        function getSelectedDonorIds() {
            const checkboxes = document.querySelectorAll('.donor-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
        
        // Bulk actions
        function bulkApprove() {
            const donorIds = getSelectedDonorIds();
            if (donorIds.length === 0) {
                alert('Please select donors to approve');
                return;
            }
            
            if (confirm(`Approve ${donorIds.length} selected donors?`)) {
                const formData = new FormData();
                formData.append('action', 'bulk_approve');
                formData.append('donor_ids', JSON.stringify(donorIds));
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                
                postJson('admin_enhanced_donor_management.php', formData)
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Failed to approve donors: ' + data.message);
                    }
                });
            }
        }
        
        function bulkUnserved() {
            const donorIds = getSelectedDonorIds();
            if (donorIds.length === 0) {
                alert('Please select donors to mark as unserved');
                return;
            }
            
            const reason = prompt('Enter reason for unserved:');
            if (reason) {
                const customNote = prompt('Additional notes (optional):');
                const formData = new FormData();
                formData.append('action', 'bulk_unserved');
                formData.append('donor_ids', JSON.stringify(donorIds));
                formData.append('reason', reason);
                formData.append('custom_note', customNote || '');
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                
                postJson('admin_enhanced_donor_management.php', formData)
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Failed to mark donors as unserved: ' + data.message);
                    }
                });
            }
        }
        
        function bulkServed() {
            const donorIds = getSelectedDonorIds();
            if (donorIds.length === 0) {
                alert('Please select donors to mark as served');
                return;
            }
            
            const donationDate = prompt('Enter donation date (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
            if (donationDate) {
                const formData = new FormData();
                formData.append('action', 'bulk_served');
                formData.append('donor_ids', JSON.stringify(donorIds));
                formData.append('donation_date', donationDate);
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                
                postJson('admin_enhanced_donor_management.php', formData)
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Failed to mark donors as served: ' + data.message);
                    }
                });
            }
        }
        
        function bulkCommunication() {
            const donorIds = getSelectedDonorIds();
            if (donorIds.length === 0) {
                alert('Please select donors to send message to');
                return;
            }
            
            document.getElementById('bulkSubject').value = '';
            document.getElementById('bulkMessage').value = '';
            new bootstrap.Modal(document.getElementById('bulkCommunicationModal')).show();
        }
        
        function confirmBulkCommunication() {
            const formData = new FormData(document.getElementById('bulkCommunicationForm'));
            formData.append('action', 'bulk_communication');
            formData.append('donor_ids', JSON.stringify(getSelectedDonorIds()));
            
            postJson('admin_enhanced_donor_management.php', formData)
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('bulkCommunicationModal')).hide();
                    location.reload();
                } else {
                    alert('Failed to send communication: ' + data.message);
                }
            });
        }
        
        // View donor details
        function viewDonor(donorId) {
            // Show loading in modal
            document.getElementById('donorModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;
            
            // Show the modal first
            new bootstrap.Modal(document.getElementById('donorModal')).show();
            
            // Fetch donor details
            fetch(`simple_ajax_donor_details.php?action=get_donor_details&donor_id=${donorId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('donorModalBody').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('donorModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading donor details. Please try again.
                        </div>`;
                });
        }
        
        // Approve donor
        function approveDonor(donorId) {
            document.getElementById('approveDonorId').value = donorId;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
        
        function confirmApprove() {
            const donorId = document.getElementById('approveDonorId').value;
            const formData = new FormData();
            formData.append('action', 'approve_donor');
            formData.append('donor_id', donorId);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            
            postJson('admin_enhanced_donor_management.php', formData)
            .then(data => {
                if (data.success) {
                    alert('Donor approved successfully!');
                    location.reload();
                } else {
                    alert('Failed to approve donor: ' + data.message);
                }
            });
        }
        
        // Mark unserved
        function markUnserved(donorId) {
            document.getElementById('unservedDonorId').value = donorId;
            new bootstrap.Modal(document.getElementById('unservedModal')).show();
        }
        
        function confirmUnserved() {
            const formData = new FormData(document.getElementById('unservedForm'));
            formData.append('action', 'mark_unserved');
            
            postJson('admin_enhanced_donor_management.php', formData)
            .then(data => {
                if (data.success) {
                    alert('Donor marked as unserved!');
                    location.reload();
                } else {
                    alert('Failed to mark donor as unserved: ' + data.message);
                }
            });
        }
        
        // Mark served
        function markServed(donorId) {
            document.getElementById('servedDonorId').value = donorId;
            new bootstrap.Modal(document.getElementById('servedModal')).show();
        }
        
        function setButtonLoading(btn, isLoading, loadingText) {
            if (!btn) return;
            if (isLoading) {
                btn.dataset.originalText = btn.innerHTML;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${loadingText || 'Processing...'}`;
                btn.disabled = true;
            } else {
                btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
                btn.disabled = false;
            }
        }

        function confirmServed() {
            const formData = new FormData(document.getElementById('servedForm'));
            formData.append('action', 'mark_served');
            
            const btn = document.querySelector('#servedModal .btn-info');
            setButtonLoading(btn, true, 'Marking served...');

            fetch('admin_enhanced_donor_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Donor marked as served!');
                    location.reload();
                } else {
                    alert('Failed to mark donor as served: ' + (data.message || '') + (data.error ? '\nDetails: ' + data.error : ''));
                }
            })
            .finally(() => {
                setButtonLoading(btn, false);
            });
        }
        
        // Update donor status
        function updateDonorStatus(donorId, currentStatus) {
            document.getElementById('donorStatusId').value = donorId;
            document.getElementById('currentDonorStatus').value = currentStatus;
            new bootstrap.Modal(document.getElementById('donorStatusModal')).show();
        }
        
        function confirmDonorStatusUpdate() {
            const form = document.getElementById('donorStatusForm');
            const formData = new FormData(form);
            formData.append('action', 'update_donor_status');
            // Ensure donor_id, status, notes are present
            if (!formData.get('donor_id')) {
                formData.set('donor_id', document.getElementById('donorStatusId').value);
            }
            
            const btn = document.getElementById('btnUpdateStatus');
            setButtonLoading(btn, true, 'Updating...');

            fetch('admin_enhanced_donor_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Donor status updated successfully!');
                    location.reload();
                } else {
                    alert('Failed to update donor status: ' + (data.message || '') + (data.error ? '\nDetails: ' + data.error : ''));
                }
            })
            .finally(() => {
                setButtonLoading(btn, false);
            });
        }
        
        // Add note
        function addNote(donorId) {
            document.getElementById('noteDonorId').value = donorId;
            new bootstrap.Modal(document.getElementById('noteModal')).show();
        }
        
        function confirmAddNote() {
            const formData = new FormData(document.getElementById('noteForm'));
            formData.append('action', 'add_note');
            
            fetch('admin_enhanced_donor_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Note added successfully!');
                    // Clear the textarea after successful submission
                    document.getElementById('noteText').value = '';
                    bootstrap.Modal.getInstance(document.getElementById('noteModal')).hide();
                    // Optionally refresh the page to show the new note
                    // location.reload();
                } else {
                    alert('Failed to add note: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding note: ' + error.message);
            });
        }
        
        // View medical screening details
        function viewMedicalScreening(donorId) {
            // Show loading in modal
            document.getElementById('medicalScreeningModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading medical screening information...</p>
                </div>`;
            
            // Show the modal first
            new bootstrap.Modal(document.getElementById('medicalScreeningModal')).show();
            
            // Fetch medical screening details
            fetch(`get_medical_screening.php?donor_id=${donorId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    let html = `
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5><i class="fas fa-user me-2"></i>Donor Information</h5>
                                <p><strong>Name:</strong> ${data.donor.name}</p>
                                <p><strong>Reference:</strong> <code>${data.donor.reference_code}</code></p>
                                <p><strong>Gender:</strong> ${data.donor.gender || 'Not specified'}</p>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-clipboard-check me-2"></i>Screening Summary</h5>
                                <p><strong>Status:</strong> <span class="badge bg-${data.screening.completed ? 'success' : 'warning'}">${data.screening.completed ? 'Completed' : 'Incomplete'}</span></p>
                                <p><strong>Date:</strong> ${new Date(data.screening.date).toLocaleDateString()}</p>
                                <p><strong>Summary:</strong> Safe: ${data.summary.no_count} | Risk: ${data.summary.yes_count} | Not Answered: ${data.summary.not_answered}</p>
                            </div>
                        </div>`;
                    
                    if (data.summary.yes_count > 0) {
                        html += `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Medical Review Required:</strong> This donor has ${data.summary.yes_count} positive responses that require medical review.
                            </div>`;
                    } else {
                        html += `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Medical Screening Passed:</strong> All responses are negative or not applicable.
                            </div>`;
                    }
                    
                    html += `<div class="accordion" id="screeningAccordion">`;
                    
                    data.questions.forEach((section, sectionIndex) => {
                        const sectionId = `section-${sectionIndex}`;
                        html += `
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-${sectionId}">
                                    <button class="accordion-button ${sectionIndex === 0 ? '' : 'collapsed'}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${sectionId}">
                                        <i class="fas fa-heartbeat me-2"></i>${section.title}
                                    </button>
                                </h2>
                                <div id="collapse-${sectionId}" class="accordion-collapse collapse ${sectionIndex === 0 ? 'show' : ''}" data-bs-parent="#screeningAccordion">
                                    <div class="accordion-body">`;
                        
                        section.questions.forEach(q => {
                            const answerClass = q.answer === 'yes' ? 'border-danger bg-light' : (q.answer === 'no' ? 'border-success bg-light' : 'border-secondary');
                            const answerIcon = q.answer === 'yes' ? '<i class="fas fa-times-circle text-danger me-1"></i>' : (q.answer === 'no' ? '<i class="fas fa-check-circle text-success me-1"></i>' : '<i class="fas fa-question-circle text-muted me-1"></i>');
                            
                            html += `
                                <div class="mb-3 p-3 border rounded ${answerClass}">
                                    <div class="fw-bold mb-2">${q.question}</div>
                                    <div>${answerIcon}<strong>Answer:</strong> ${q.answer.charAt(0).toUpperCase() + q.answer.slice(1)}</div>
                                </div>`;
                        });
                        
                        html += `
                                    </div>
                                </div>
                            </div>`;
                    });
                    
                    html += `</div>`;
                    
                    document.getElementById('medicalScreeningModalBody').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('medicalScreeningModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading medical screening details: ${error.message}
                        </div>`;
                });
        }
    </script>
</body>
</html> 