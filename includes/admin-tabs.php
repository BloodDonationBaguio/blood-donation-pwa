<?php
// Get variables from global scope
$activeTab = $GLOBALS['activeTab'] ?? 'dashboard';
$donors = $GLOBALS['donors'] ?? [];
$pendingDonors = $GLOBALS['pendingDonors'] ?? [];
$requests = $GLOBALS['requests'] ?? [];

// Debug: Check if activeTab is defined
if (!isset($activeTab)) {
    echo '<div class="alert alert-danger">Error: $activeTab variable is not defined!</div>';
    $activeTab = 'dashboard'; // Fallback
}

// Debug: Show current active tab
echo "<!-- Debug: Active tab is: $activeTab -->";

// Add Donor Tab
if ($activeTab === 'add-donor'): ?>
    <div class="tab-pane fade show active" id="add-donor" role="tabpanel">
        <h2 class="mb-4">Add New Donor</h2>
        <form action="add-donor.php" method="POST" class="needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required>
                </div>
                <div class="col-md-6">
                    <label for="blood_type" class="form-label">Blood Type</label>
                    <select class="form-select" id="blood_type" name="blood_type" required>
                        <option value="">Select Blood Type</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Add Donor</button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>





<!-- Manage Pages Tab -->
<?php if ($activeTab === 'manage-pages'): ?>
    <div class="tab-pane fade show active" id="manage-pages" role="tabpanel">
        <h2 class="mb-4">Manage Website Pages</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Home Page</h5>
                        <p class="card-text">Update the main landing page content.</p>
                        <a href="edit-page.php?page=home" class="btn btn-primary">Edit</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">About Us</h5>
                        <p class="card-text">Update the about us page content.</p>
                        <a href="edit-page.php?page=about" class="btn btn-primary">Edit</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Contact Us</h5>
                        <p class="card-text">Update contact information and form.</p>
                        <a href="edit-page.php?page=contact" class="btn btn-primary">Edit</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

        <?php else: ?>
            <!-- Show pending blood requests -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Pending Blood Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $pendingRequests = $pdo->query("SELECT * FROM blood_requests WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll();
                            ?>
                            
                            <?php if (!empty($pendingRequests)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Patient Name</th>
                                                <th>Blood Type</th>
                                                <th>Desired Date</th>
                                                <th>City</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingRequests as $req): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($req['patient_name']) ?></td>
                                                    <td><span class="badge bg-danger"><?= htmlspecialchars($req['blood_type']) ?></span></td>
                                                    <td><?= $req['desired_date'] ? date('M d, Y', strtotime($req['desired_date'])) : 'Flexible' ?></td>
                                                    <td><?= htmlspecialchars($req['city']) ?></td>
                                                    <td><span class="badge bg-warning"><?= ucfirst($req['status']) ?></span></td>
                                                    <td>
                                                        <a href="?tab=donor-matching&request_id=<?= $req['id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-handshake me-1"></i>Match Donors
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Pending Blood Requests</h5>
                                    <p class="text-muted">All blood requests have been processed or there are no new requests.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Request Details</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Requester:</strong> <?= htmlspecialchars($request['patient_name']) ?></p>
                            <p><strong>Blood Type Needed:</strong> <?= htmlspecialchars($request['blood_type']) ?></p>
                            <p><strong>Needed By:</strong> <?= $request['needed_by_date'] ? date('M d, Y', strtotime($request['needed_by_date'])) : 'Flexible' ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-<?= $request['status'] === 'pending' ? 'warning' : 'success' ?>"><?= ucfirst($request['status']) ?></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Matching Statistics</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Available Donors:</strong> <?= count($suggestions) ?></p>
                            <p><strong>Existing Matches:</strong> <?= count($existingMatches) ?></p>
                            <p><strong>Confirmed Matches:</strong> <?= count(array_filter($existingMatches, fn($m) => $m['status'] === 'confirmed')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Suggested Donors</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($suggestions)): ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($suggestions, 0, 5) as $suggestion): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($suggestion['full_name']) ?></h6>
                                                    <small>Blood Type: <?= htmlspecialchars($suggestion['blood_type']) ?> | Score: <?= number_format($suggestion['match_score'], 2) ?></small>
                                                </div>
                                                <button class="btn btn-sm btn-primary" onclick="createMatch(<?= $requestId ?>, <?= $suggestion['id'] ?>, <?= $suggestion['match_score'] ?>)">
                                                    Match
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No available donors found for this request.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Existing Matches</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($existingMatches)): ?>
                                <div class="list-group">
                                    <?php foreach ($existingMatches as $match): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($match['donor_name']) ?></h6>
                                                    <small>Status: <span class="badge bg-<?= $match['status'] === 'confirmed' ? 'success' : 'warning' ?>"><?= ucfirst($match['status']) ?></span></small>
                                                </div>
                                                <div>
                                                    <button class="btn btn-sm btn-success" onclick="sendNotification(<?= $match['id'] ?>)">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-info" onclick="updateMatchStatus(<?= $match['id'] ?>, 'confirmed')">
                                                        Confirm
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No matches created yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
        </div>

<!-- Reports Tab -->
<?php if ($activeTab === 'reports'): ?>
    <div class="tab-pane fade show active" id="reports" role="tabpanel">
        <h2 class="mb-4">Reports & Analytics</h2>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Donor Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $donorStats = $pdo->query("
                            SELECT 
                                COUNT(*) as total,
                                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                                COUNT(CASE WHEN status = 'served' THEN 1 END) as served,
                                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
                            FROM donors_new
                        ")->fetch();
                        ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <h4 class="text-primary"><?= $donorStats['total'] ?></h4>
                                <small>Total</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-warning"><?= $donorStats['pending'] ?></h4>
                                <small>Pending</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success"><?= $donorStats['served'] ?></h4>
                                <small>Served</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Blood Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $bloodTypeStats = $pdo->query("
                            SELECT blood_type, COUNT(*) as count 
                            FROM donors_new 
                            WHERE status IN ('approved', 'served') 
                            GROUP BY blood_type 
                            ORDER BY count DESC
                        ")->fetchAll();
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Blood Type</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bloodTypeStats as $stat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($stat['blood_type']) ?></td>
                                            <td><?= $stat['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentActions = getAdminActionLog($pdo, ['limit' => 10]);
                        ?>
                        
                        <?php if (!empty($recentActions)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Record</th>
                                            <th>Details</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActions as $action): ?>
                                            <tr>
                                                <td><span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $action['action_type'])) ?></span></td>
                                                <td><?= htmlspecialchars($action['record_name'] ?? 'Unknown') ?></td>
                                                <td><?= htmlspecialchars($action['action_details'] ?? '') ?></td>
                                                <td><?= date('M d, Y H:i', strtotime($action['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No recent activity found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Audit Log Tab -->
<?php if ($activeTab === 'audit-log'): ?>
    <!-- Audit Log Tab -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-clipboard-list me-2"></i>Audit Log</h2>
        <button type="button" class="btn btn-success" onclick="exportAuditLog()">
            <i class="fas fa-download me-1"></i>Export Log
        </button>
    </div>

    <!-- Filter Options -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Options</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="admin.php" id="auditLogFilterForm">
                <input type="hidden" name="tab" value="audit-log">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="action_type" class="form-label">Action Type</label>
                        <select name="action_type" id="action_type" class="form-select">
                            <option value="">All Actions</option>
                            <option value="donor_approved" <?= ($_GET['action_type'] ?? '') === 'donor_approved' ? 'selected' : '' ?>>Donor Approved</option>
                            <option value="donor_rejected" <?= ($_GET['action_type'] ?? '') === 'donor_rejected' ? 'selected' : '' ?>>Donor Rejected</option>
                            <option value="donor_marked_served" <?= ($_GET['action_type'] ?? '') === 'donor_marked_served' ? 'selected' : '' ?>>Donor Marked Served</option>
                            <option value="donor_marked_unserved" <?= ($_GET['action_type'] ?? '') === 'donor_marked_unserved' ? 'selected' : '' ?>>Donor Marked Unserved</option>
                            <option value="donor_deleted" <?= ($_GET['action_type'] ?? '') === 'donor_deleted' ? 'selected' : '' ?>>Donor Deleted</option>
                            <option value="request_approved" <?= ($_GET['action_type'] ?? '') === 'request_approved' ? 'selected' : '' ?>>Request Approved</option>
                            <option value="request_deferred" <?= ($_GET['action_type'] ?? '') === 'request_deferred' ? 'selected' : '' ?>>Request Deferred</option>
                            <option value="request_marked_served" <?= ($_GET['action_type'] ?? '') === 'request_marked_served' ? 'selected' : '' ?>>Request Marked Served</option>
                            <option value="request_marked_unserved" <?= ($_GET['action_type'] ?? '') === 'request_marked_unserved' ? 'selected' : '' ?>>Request Marked Unserved</option>
                            <option value="request_deleted" <?= ($_GET['action_type'] ?? '') === 'request_deleted' ? 'selected' : '' ?>>Request Deleted</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?= $_GET['date_from'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?= $_GET['date_to'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="auditLogFilterBtn">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <button type="button" class="btn btn-secondary" id="auditLogClearBtn" onclick="clearAuditLogFilters()">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Audit Log Entries</h5>
        </div>
        <div class="card-body">
            <?php
            try {
                // Build filters array
                $filters = [];
                if (!empty($_GET['action_type'])) {
                    $filters['action_type'] = $_GET['action_type'];
                }
                if (!empty($_GET['date_from'])) {
                    $filters['date_from'] = $_GET['date_from'];
                }
                if (!empty($_GET['date_to'])) {
                    $filters['date_to'] = $_GET['date_to'];
                }

                // Get audit log data
                $auditLog = getAdminActionLog($pdo, $filters);
                
                if (!empty($auditLog)):
            ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date & Time</th>
                                <th>Action</th>
                                <th>Record</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLog as $log): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M d, Y', strtotime($log['created_at'])) ?>
                                        </small>
                                        <br>
                                        <strong><?= date('H:i:s', strtotime($log['created_at'])) ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = 'bg-info';
                                        switch($log['action_type']) {
                                            case 'donor_approved':
                                            case 'request_approved':
                                                $badgeClass = 'bg-success';
                                                break;
                                            case 'donor_rejected':
                                            case 'request_deferred':
                                                $badgeClass = 'bg-warning';
                                                break;
                                            case 'donor_deleted':
                                            case 'request_deleted':
                                                $badgeClass = 'bg-danger';
                                                break;
                                            case 'donor_marked_served':
                                            case 'request_marked_served':
                                                $badgeClass = 'bg-primary';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucwords(str_replace('_', ' ', $log['action_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($log['record_name'] ?? 'Unknown') ?></strong>
                                        <br>
                                        <small class="text-muted">ID: <?= $log['record_id'] ?></small>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($log['action_details'] ?? '') ?>">
                                            <?= htmlspecialchars($log['action_details'] ?? 'No details') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">
                        Showing <?= count($auditLog) ?> entries
                        <?php if (!empty($filters)): ?>
                            (filtered results)
                        <?php endif; ?>
                    </small>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list text-muted" style="font-size: 3rem;"></i>
                    <h6 class="text-muted mt-3">No Audit Log Entries Found</h6>
                    <p class="text-muted">
                        <?php if (!empty($filters)): ?>
                            No entries match your current filter criteria. Try adjusting your filters.
                        <?php else: ?>
                            Audit log entries will appear here once admin actions are performed.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php } catch (Exception $e) { ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error loading audit log:</strong> <?= htmlspecialchars($e->getMessage()) ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
    // Audit Log specific JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Audit Log page loaded');
        
        // Initialize form elements
        const filterForm = document.getElementById('auditLogFilterForm');
        const clearBtn = document.getElementById('auditLogClearBtn');
        
        if (filterForm) {
            console.log('Filter form found');
        }
        
        if (clearBtn) {
            console.log('Clear button found');
        }
    });
    </script>
<?php endif; ?>

<!-- Update Contact Tab -->
<?php if ($activeTab === 'update-contact'): ?>
    <div class="tab-pane fade show active" id="update-contact" role="tabpanel">
        <h2 class="mb-4">Update Contact Information</h2>
        <form action="update-contact.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($site_settings['contact_email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($site_settings['contact_phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($site_settings['contact_address'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Contact Info</button>
        </form>
    </div>
<?php endif; ?>



<?php
require_once __DIR__ . '/admin_actions.php';

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_donors'])) {
    $action = $_POST['bulk_action'];
    $selectedIds = $_POST['selected_donors'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare('UPDATE donors_new SET status = "approved" WHERE id IN (' . str_repeat('?,', count($selectedIds) - 1) . '?)');
        $stmt->execute($selectedIds);
        header('Location: ?tab=pending-donors&success=Bulk approval completed.');
        exit();
    } elseif ($action === 'reject') {
        $reason = $_POST['rejection_reason'] ?? 'Bulk rejection';
        $stmt = $pdo->prepare('UPDATE donors_new SET status = "rejected", rejection_reason = ? WHERE id IN (' . str_repeat('?,', count($selectedIds) - 1) . '?)');
        $stmt->execute(array_merge([$reason], $selectedIds));
        header('Location: ?tab=pending-donors&success=Bulk rejection completed.');
        exit();
    }
}
?>

<!-- Pending Donors Tab -->
<?php if ($activeTab === 'pending-donors'): ?>
    <div class="tab-pane fade show active" id="pending-donors" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Pending Donor Applications</h2>
            <form class="d-flex" method="GET" action="">
                <input type="hidden" name="tab" value="pending-donors">
                <input class="form-control me-2" type="search" name="donor_search" placeholder="Search pending donors..." value="<?= htmlspecialchars($_GET['donor_search'] ?? '') ?>">
                <button class="btn btn-outline-success" type="submit">Search</button>
            </form>
        </div>
        
        <?php if (!empty($pendingDonors)): ?>
            <form method="POST" action="?tab=pending-donors">
                <div class="mb-3">
                    <button type="submit" name="bulk_action" value="approve" class="btn btn-success btn-sm me-2" onclick="return confirm('Approve selected donors?')">
                        <i class="fas fa-check"></i> Approve Selected
                    </button>
                    <button type="submit" name="bulk_action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Reject selected donors?')">
                        <i class="fas fa-times"></i> Reject Selected
                    </button>
                    <input type="text" name="rejection_reason" placeholder="Rejection reason (optional)" class="form-control d-inline-block w-auto ms-2">
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>Reference</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Blood Type</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingDonors as $donor): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_donors[]" value="<?= $donor['id'] ?>"></td>
                                    <td><?= htmlspecialchars($donor['reference_code'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></td>
                                    <td><?= htmlspecialchars($donor['email']) ?></td>
                                    <td><?= htmlspecialchars($donor['phone']) ?></td>
                                    <td><?= htmlspecialchars($donor['blood_type']) ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($donor['created_at'])) ?>
                                        <?php if ($donor['status'] === 'served' && !empty($donor['served_date'])): ?>
                                            <br><small class="text-info">Served: <?= date('M d, Y', strtotime($donor['served_date'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?tab=donor-details&id=<?= $donor['id'] ?>" class="btn btn-sm btn-info" title="View Complete Donor Information">
                                            <i class="fas fa-info-circle"></i> View More Information
                                        </a>
                                        <a href="?tab=pending-donors&approve_donor=<?= $donor['id'] ?>" class="btn btn-sm btn-success action-btn" title="Approve" onclick="showLoading(this, 'Approving...'); return confirm('Approve this donor?')">
                                            <i class="fas fa-check me-2"></i>Approve Donor
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" title="Reject" onclick="showRejectModal(<?= $donor['id'] ?>, '<?= addslashes($donor['first_name'] . ' ' . $donor['last_name']) ?>')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info">No pending donor applications found.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Donor Details Tab -->
<?php if ($activeTab === 'donor-details' && isset($_GET['id'])): ?>
    <?php
    $donorId = (int)$_GET['id'];
    $donor = $pdo->query("SELECT * FROM donors WHERE id = $donorId")->fetch();
    
    // Get medical screening data
    $medicalScreening = null;
    if ($donor) {
                    $medicalScreening = $pdo->query("SELECT * FROM donor_medical_screening_fixed WHERE donor_id = $donorId")->fetch();
    }
    
    // Load medical questions
    $medicalQuestions = include __DIR__ . '/medical_questions.php';
    $medicalQuestions = $medicalQuestions['sections'] ?? [];
    
    if ($donor):
    ?>
    <div class="tab-pane fade show active" id="donor-details" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Donor Details</h2>
            <a href="?tab=donor-list" class="btn btn-secondary">Back to List</a>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Reference:</strong> <?= htmlspecialchars($donor['reference_code'] ?? $donor['reference_number'] ?? '-') ?></p>
                        <p><strong>Name:</strong> <?= htmlspecialchars($donor['full_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($donor['email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($donor['phone']) ?></p>
                        <p><strong>Blood Type:</strong> <?= htmlspecialchars($donor['blood_type']) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= getDonorStatusColor($donor['status']) ?>">
                                <?= getDonorDisplayStatus($donor['status'] ?? 'pending') ?>
                            </span>
                        </p>
                        <p><strong>Submitted:</strong> <?= date('M d, Y H:i', strtotime($donor['created_at'])) ?></p>
                        <?php if ($donor['served_date']): ?>
                            <p><strong>Served Date:</strong> <?= date('M d, Y H:i', strtotime($donor['served_date'])) ?></p>
                        <?php endif; ?>
                        <?php if ($donor['rejection_reason']): ?>
                            <p><strong>Rejection Reason:</strong> <?= htmlspecialchars($donor['rejection_reason']) ?></p>
                        <?php endif; ?>
                        <?php if ($donor['unserved_reason']): ?>
                            <p><strong>Unserved Reason:</strong> <?= htmlspecialchars($donor['unserved_reason']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Actions</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($donor['status'] === 'pending'): ?>
                            <a href="?tab=donor-details&id=<?= $donor['id'] ?>&approve_donor=<?= $donor['id'] ?>" class="btn btn-success mb-2 w-100 action-btn" onclick="showLoading(this, 'Approving...'); return confirm('Approve this donor?')">
                                <i class="fas fa-check me-2"></i>Approve Donor
                            </a>
                            <button type="button" class="btn btn-danger mb-2 w-100" onclick="showRejectModal(<?= $donor['id'] ?>, '<?= addslashes($donor['full_name']) ?>')">
                                <i class="fas fa-times"></i> Reject Donor
                            </button>
                        <?php elseif ($donor['status'] === 'approved'): ?>
                            <a href="?tab=donor-details&id=<?= $donor['id'] ?>&mark_served=<?= $donor['id'] ?>" class="btn btn-info mb-2 w-100" onclick="return confirm('Mark as served?')">
                                <i class="fas fa-check-double"></i> Mark as Served
                            </a>
                            <button type="button" class="btn btn-warning mb-2 w-100" onclick="showUnservedModal(<?= $donor['id'] ?>, '<?= addslashes($donor['full_name']) ?>')">
                                <i class="fas fa-times-circle"></i> Mark as Unserved
                            </button>
                        <?php endif; ?>
                        
                        <a href="mailto:<?= htmlspecialchars($donor['email']) ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-envelope"></i> Send Email
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Medical Screening Questionnaire</h5>
                        <?php if ($medicalScreening): ?>
                            <small class="text-light">Completed on <?= date('M d, Y H:i', strtotime($medicalScreening['screening_date'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($medicalScreening): ?>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Medical Screening Status:</strong> 
                                <?php
                                $yesAnswers = 0;
                                $noAnswers = 0;
                                $notAnswered = 0;
                                
                                foreach ($medicalScreening as $key => $value) {
                                    if (strpos($key, 'q') === 0) {
                                        if ($value === 'yes') $yesAnswers++;
                                        elseif ($value === 'no') $noAnswers++;
                                        else $notAnswered++;
                                    }
                                }
                                ?>
                                <span class="badge bg-<?= $yesAnswers > 0 ? 'warning' : 'success' ?>">
                                    <?= $yesAnswers > 0 ? 'Review Required' : 'Passed' ?>
                                </span>
                            </div>
                            
                            <div class="accordion" id="medicalAccordion">
                                <?php foreach ($medicalQuestions as $sectionKey => $section): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?= ucfirst($sectionKey) ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= ucfirst($sectionKey) ?>" aria-expanded="false" aria-controls="collapse<?= ucfirst($sectionKey) ?>">
                                                <?= htmlspecialchars($section['title']) ?>
                                                <?php
                                                $sectionYesCount = 0;
                                                foreach ($section['questions'] as $questionId => $question) {
                                                    if (($medicalScreening[$questionId] ?? '') === 'yes') {
                                                        $sectionYesCount++;
                                                    }
                                                }
                                                if ($sectionYesCount > 0):
                                                ?>
                                                <span class="badge bg-danger ms-2"><?= $sectionYesCount ?> Positive</span>
                                                <?php endif; ?>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= ucfirst($sectionKey) ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= ucfirst($sectionKey) ?>" data-bs-parent="#medicalAccordion">
                                            <div class="accordion-body">
                                                <?php foreach ($section['questions'] as $questionId => $question): ?>
                                                    <div class="row mb-3 p-2 <?= ($medicalScreening[$questionId] ?? '') === 'yes' ? 'bg-warning bg-opacity-10' : '' ?>">
                                                        <div class="col-8">
                                                            <strong class="text-dark"><?= htmlspecialchars($question) ?></strong>
                                                        </div>
                                                        <div class="col-4 text-end">
                                                            <?php 
                                                            $answer = $medicalScreening[$questionId] ?? 'not_answered';
                                                            $answerClass = $answer === 'yes' ? 'danger' : ($answer === 'no' ? 'success' : 'secondary');
                                                            $answerText = $answer === 'yes' ? 'Yes' : ($answer === 'no' ? 'No' : 'Not Answered');
                                                            ?>
                                                            <span class="badge bg-<?= $answerClass ?> fs-6"><?= $answerText ?></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Medical Screening Summary -->
                            <div class="mt-4 p-4 bg-light rounded border">
                                <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2"></i>Medical Screening Summary</h6>
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="text-success">
                                            <h4 class="mb-0"><?= $noAnswers ?></h4>
                                            <small class="fw-bold">Safe Answers</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-danger">
                                            <h4 class="mb-0"><?= $yesAnswers ?></h4>
                                            <small class="fw-bold">Risk Answers</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-secondary">
                                            <h4 class="mb-0"><?= $notAnswered ?></h4>
                                            <small class="fw-bold">Not Answered</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($yesAnswers > 0): ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Medical Review Required:</strong> This donor has <?= $yesAnswers ?> positive responses that require medical review before approval.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success mt-3 mb-0">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Medical Screening Passed:</strong> All responses are negative or not applicable. Donor is medically eligible.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                No medical screening questionnaire completed for this donor.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Edit Donor Tab -->
<?php if ($activeTab === 'edit-donor'): 
    $donorId = $_GET['id'] ?? 0;
    if ($donorId) {
        $stmt = $pdo->prepare('SELECT * FROM donors_new WHERE id = ?');
        $stmt->execute([$donorId]);
        $editDonor = $stmt->fetch();
    }
?>
    <div class="tab-pane fade show active" id="edit-donor" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit Donor Information</h2>
            <a href="?tab=donor-list" class="btn btn-secondary">Back to List</a>
        </div>
        
        <?php if ($editDonor): ?>
        <form method="POST" action="?tab=edit-donor&id=<?= $donorId ?>">
            <input type="hidden" name="action" value="update_donor">
            <input type="hidden" name="donor_id" value="<?= $donorId ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="<?= htmlspecialchars($editDonor['first_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="<?= htmlspecialchars($editDonor['last_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($editDonor['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?= htmlspecialchars($editDonor['phone']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="blood_type" class="form-label">Blood Type</label>
                    <select class="form-select" id="blood_type" name="blood_type" required>
                        <option value="">Select Blood Type</option>
                        <option value="A+" <?= $editDonor['blood_type'] === 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= $editDonor['blood_type'] === 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= $editDonor['blood_type'] === 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= $editDonor['blood_type'] === 'B-' ? 'selected' : '' ?>>B-</option>
                        <option value="AB+" <?= $editDonor['blood_type'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= $editDonor['blood_type'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                        <option value="O+" <?= $editDonor['blood_type'] === 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= $editDonor['blood_type'] === 'O-' ? 'selected' : '' ?>>O-</option>
                        <option value="Unknown" <?= $editDonor['blood_type'] === 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="pending" <?= $editDonor['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $editDonor['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="served" <?= $editDonor['status'] === 'served' ? 'selected' : '' ?>>Served</option>
                        <option value="rejected" <?= $editDonor['status'] === 'rejected' ? 'selected' : '' ?>>Deferred</option>
                        <option value="unserved" <?= $editDonor['status'] === 'unserved' ? 'selected' : '' ?>>Unserved</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Update Donor Information</button>
                    <a href="?tab=donor-list" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
        <?php else: ?>
        <div class="alert alert-danger">Donor not found.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- All Donors Tab -->
<?php if ($activeTab === 'donor-list'): ?>
    <div class="tab-pane fade show active" id="donor-list" role="tabpanel">
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>All Donors</h2>
            <div class="d-flex gap-2">
            <!-- Enhanced Search and Filter Form -->
            <form class="d-flex flex-wrap gap-2" method="GET" action="">
                <input type="hidden" name="tab" value="donor-list">
                
                <!-- Search by name, blood type, or reference -->
                <div class="input-group" style="min-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, blood type, or reference..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <!-- Blood Type Filter -->
                <select name="blood_type_filter" class="form-select" style="min-width: 120px;" onchange="this.form.submit()">
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
                
                <!-- Status Filter -->
                <select name="status_filter" class="form-select" style="min-width: 120px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?= ($_GET['status_filter'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($_GET['status_filter'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="served" <?= ($_GET['status_filter'] ?? '') === 'served' ? 'selected' : '' ?>>Served</option>
                        <option value="rejected" <?= ($_GET['status_filter'] ?? '') === 'rejected' ? 'selected' : '' ?>>Deferred</option>
                        <option value="unserved" <?= ($_GET['status_filter'] ?? '') === 'unserved' ? 'selected' : '' ?>>Unserved</option>
                    </select>
                    <select name="blood_type_filter" class="form-select me-2" onchange="this.form.submit()">
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
                <input class="form-control me-2" type="search" name="donor_search" placeholder="Search donors..." value="<?= htmlspecialchars($_GET['donor_search'] ?? '') ?>">
                <button class="btn btn-outline-success" type="submit">Search</button>
            </form>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Blood Type</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donors as $donor): ?>
                        <tr>
                            <td><?= htmlspecialchars($donor['reference_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></td>
                            <td><?= htmlspecialchars($donor['email']) ?></td>
                            <td><?= htmlspecialchars($donor['phone']) ?></td>
                            <td><?= htmlspecialchars($donor['blood_type']) ?></td>
                            <td>
                                <span class="badge bg-<?= getDonorStatusColor($donor['status']) ?>">
                                    <?= getDonorDisplayStatus($donor['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td>
                                <?= date('M d, Y', strtotime($donor['created_at'])) ?>
                                <?php if ($donor['status'] === 'served' && !empty($donor['served_date'])): ?>
                                    <br><small class="text-info">Served: <?= date('M d, Y', strtotime($donor['served_date'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?tab=donor-details&id=<?= $donor['id'] ?>" class="btn btn-sm btn-info" title="View Complete Donor Information">
                                    <i class="fas fa-info-circle"></i> View
                                </a>
                                <a href="?tab=edit-donor&id=<?= $donor['id'] ?>" class="btn btn-sm btn-warning" title="Edit Donor Information">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php if ($donor['status'] === 'approved'): ?>
                                    <a href="?tab=donor-list&mark_served=<?= $donor['id'] ?>" class="btn btn-sm btn-success" title="Mark as Served" onclick="return confirm('Mark as served?')">
                                        <i class="fas fa-check-double"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning" title="Mark as Unserved" onclick="showUnservedModal(<?= $donor['id'] ?>, '<?= addslashes($donor['first_name'] . ' ' . $donor['last_name']) ?>')">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="?tab=donor-list&delete_donor=<?= $donor['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this donor?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Enhanced Blood Requests Tab -->
<?php if ($activeTab === 'blood-requests'): ?>
    <div class="tab-pane fade show active" id="blood-requests" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Blood Requests</h2>
            <div class="d-flex gap-2">
            <form class="d-flex" method="GET" action="">
                <input type="hidden" name="tab" value="blood-requests">
                    <select name="status_filter" class="form-select me-2" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" <?= ($_GET['status_filter'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($_GET['status_filter'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= ($_GET['status_filter'] ?? '') === 'rejected' ? 'selected' : '' ?>>Deferred</option>
                        <option value="in_progress" <?= ($_GET['status_filter'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="served" <?= ($_GET['status_filter'] ?? '') === 'served' ? 'selected' : '' ?>>Served</option>
                    </select>
                    <select name="blood_type_filter" class="form-select me-2" onchange="this.form.submit()">
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
                <input class="form-control me-2" type="search" name="request_search" placeholder="Search requests..." value="<?= htmlspecialchars($_GET['request_search'] ?? '') ?>">
                <button class="btn btn-outline-success" type="submit">Search</button>
            </form>
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
                        <th>Needed Blood</th>
                        <th>Desired Date</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= htmlspecialchars($request['id']) ?></td>
                            <td><?= htmlspecialchars($request['reference'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($request['patient_name']) ?></td>
                            <td><?= htmlspecialchars($request['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($request['phone'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($request['blood_type']) ?></td>
                            <td><?= !empty($request['desired_date']) ? date('M d, Y', strtotime($request['desired_date'])) : 'Not specified' ?></td>
                            <td>
                                <span class="badge bg-<?= $request['status'] === 'approved' ? 'success' : ($request['status'] === 'deferred' ? 'warning' : ($request['status'] === 'in_progress' ? 'info' : ($request['status'] === 'served' ? 'primary' : ($request['status'] === 'unserved' ? 'danger' : 'warning')))) ?>">
                                    <?= $request['status'] === 'deferred' ? 'Temporarily Deferred' : ($request['status'] === 'unserved' ? 'Unserved' : ucfirst(str_replace('_', ' ', $request['status'] ?? 'pending'))) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <!-- Pending: Can move to In Progress -->
                                        <a href="?tab=blood-requests&mark_in_progress=<?= $request['id'] ?>" class="btn btn-sm btn-info action-btn" title="Mark In Progress" onclick="showLoading(this, 'Processing...')">In Progress</a>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="showDeferModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['patient_name']) ?>')" title="Temporarily Defer">Defer</button>
                                    <?php elseif ($request['status'] === 'in_progress'): ?>
                                        <!-- In Progress: Can defer or mark pending -->
                                        <button type="button" class="btn btn-sm btn-warning" onclick="showDeferModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['patient_name']) ?>')" title="Temporarily Defer">Defer</button>
                                        <a href="?tab=blood-requests&mark_pending=<?= $request['id'] ?>" class="btn btn-sm btn-secondary action-btn" title="Mark Pending" onclick="showLoading(this, 'Processing...')">Pending</a>
                                    <?php elseif ($request['status'] === 'approved'): ?>
                                        <!-- Approved: Can mark as served or unserved (after visit) -->
                                        <button type="button" class="btn btn-sm btn-primary" onclick="showServedModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['patient_name']) ?>')" title="Mark as Served">Served</button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="showUnservedRequestModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['patient_name']) ?>')" title="Mark as Unserved">Unserved</button>
                                        <a href="?tab=blood-requests&mark_in_progress=<?= $request['id'] ?>" class="btn btn-sm btn-info action-btn" title="Mark In Progress" onclick="showLoading(this, 'Processing...')">In Progress</a>
                                    <?php elseif ($request['status'] === 'deferred'): ?>
                                        <!-- Deferred: Can move to pending -->
                                        <a href="?tab=blood-requests&mark_pending=<?= $request['id'] ?>" class="btn btn-sm btn-warning action-btn" title="Mark Pending" onclick="showLoading(this, 'Processing...')">Pending</a>
                                        <?php if ($request['remarks']): ?>
                                            <span class="badge bg-warning" title="Reason: <?= htmlspecialchars($request['remarks']) ?>">Deferred</span>
                                        <?php endif; ?>
                                    <?php elseif ($request['status'] === 'served'): ?>
                                        <!-- Served: Completed -->
                                        <span class="badge bg-success">Completed</span>
                                        <a href="?tab=blood-requests&mark_in_progress=<?= $request['id'] ?>" class="btn btn-sm btn-warning action-btn" title="Mark In Progress" onclick="showLoading(this, 'Processing...')">In Progress</a>
                                    <?php elseif ($request['status'] === 'unserved'): ?>
                                        <!-- Unserved: Can move to pending -->
                                        <a href="?tab=blood-requests&mark_pending=<?= $request['id'] ?>" class="btn btn-sm btn-warning action-btn" title="Mark Pending" onclick="showLoading(this, 'Processing...')">Pending</a>
                                        <?php if ($request['remarks']): ?>
                                            <span class="badge bg-danger" title="Reason: <?= htmlspecialchars($request['remarks']) ?>">Unserved</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#requestDetailsModal" onclick="showRequestDetails(<?= htmlspecialchars(json_encode($request)) ?>)">Details</button>
                                    <a href="?tab=blood-requests&delete_request=<?= $request['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this request?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modals for Reject and Unserved actions -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Donor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject <span id="rejectDonorName"></span>?</p>
                <div class="mb-3">
                    <label for="rejectionReason" class="form-label">Reason for Rejection</label>
                    <select class="form-select" id="rejectionReason">
                        <option value="Eligibility criteria not met">Eligibility criteria not met</option>
                        <option value="Medical conditions">Medical conditions</option>
                        <option value="Age requirements not met">Age requirements not met</option>
                        <option value="Incomplete information">Incomplete information</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3" id="customReasonDiv" style="display: none;">
                    <label for="customReason" class="form-label">Custom Reason</label>
                    <textarea class="form-control" id="customReason" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmReject()">Reject</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="unservedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark as Unserved</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark <span id="unservedDonorName"></span> as unserved?</p>
                <div class="mb-3">
                    <label for="unservedReason" class="form-label">Reason</label>
                    <select class="form-select" id="unservedReason">
                        <option value="No show">No show</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Medical deferral">Medical deferral</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3" id="customUnservedReasonDiv" style="display: none;">
                    <label for="customUnservedReason" class="form-label">Custom Reason</label>
                    <textarea class="form-control" id="customUnservedReason" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="confirmUnserved()">Mark as Unserved</button>
            </div>
        </div>
    </div>
</div>

<!-- Defer Request Modal -->
<div class="modal fade" id="deferModal" tabindex="-1" aria-labelledby="deferModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deferModalLabel">Temporarily Defer Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to temporarily defer the request for <span id="deferRequestName"></span>?</p>
                <div class="mb-3">
                    <label for="deferReason" class="form-label">Reason for Deferral</label>
                    <select class="form-select" id="deferReason">
                        <option value="Blood type not available">Blood type not available</option>
                        <option value="Medical assessment required">Medical assessment required</option>
                        <option value="Additional documentation needed">Additional documentation needed</option>
                        <option value="Temporary unavailability">Temporary unavailability</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3" id="customDeferReasonDiv" style="display: none;">
                    <label for="customDeferReason" class="form-label">Custom Reason</label>
                    <textarea class="form-control" id="customDeferReason" rows="3" placeholder="Please specify the reason for deferral..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="confirmDefer()">Defer Request</button>
            </div>
        </div>
    </div>
</div>

<!-- Served Request Modal -->
<div class="modal fade" id="servedModal" tabindex="-1" aria-labelledby="servedModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="servedModalLabel">Mark as Served</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark the request for <span id="servedRequestName"></span> as served?</p>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>This indicates the applicant has successfully received the blood donation service.</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmServed()">Mark as Served</button>
            </div>
        </div>
    </div>
</div>

<!-- Unserved Request Modal -->
<div class="modal fade" id="unservedRequestModal" tabindex="-1" aria-labelledby="unservedRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unservedRequestModalLabel">Mark as Unserved</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark the request for <span id="unservedRequestName"></span> as unserved?</p>
                <div class="mb-3">
                    <label for="unservedRequestReason" class="form-label">Reason for Unserved</label>
                    <select class="form-select" id="unservedRequestReason">
                        <option value="No show">No show</option>
                        <option value="Cancelled by applicant">Cancelled by applicant</option>
                        <option value="Medical deferral">Medical deferral</option>
                        <option value="Blood not available">Blood not available</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3" id="customUnservedRequestReasonDiv" style="display: none;">
                    <label for="customUnservedRequestReason" class="form-label">Custom Reason</label>
                    <textarea class="form-control" id="customUnservedRequestReason" rows="3" placeholder="Please specify the reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="confirmUnservedRequest()">Mark as Unserved</button>
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestDetailsModalLabel">Blood Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="forceCloseModal('requestDetailsModal')">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Select all functionality - only if element exists
const selectAllElement = document.getElementById('select-all');
if (selectAllElement) {
    selectAllElement.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected_donors[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });
}

// Reject modal functionality
let currentRejectId = null;
function showRejectModal(id, name) {
    currentRejectId = id;
    const rejectDonorNameElement = document.getElementById('rejectDonorName');
    if (rejectDonorNameElement) {
        rejectDonorNameElement.textContent = name;
    }
    const rejectModal = document.getElementById('rejectModal');
    if (rejectModal) {
        new bootstrap.Modal(rejectModal).show();
    }
}

function confirmReject() {
    const reasonElement = document.getElementById('rejectionReason');
    const customReasonElement = document.getElementById('customReason');
    if (reasonElement && customReasonElement) {
        const reason = reasonElement.value;
        const customReason = customReasonElement.value;
        const finalReason = reason === 'Other' ? customReason : reason;
        if (typeof showGlobalLoader === 'function') { showGlobalLoader('Rejecting donor...'); }
        window.location.href = `?tab=pending-donors&reject_donor=${currentRejectId}&reason=${encodeURIComponent(finalReason)}`;
    }
}

// Unserved modal functionality
let currentUnservedId = null;
function showUnservedModal(id, name) {
    currentUnservedId = id;
    const unservedDonorNameElement = document.getElementById('unservedDonorName');
    if (unservedDonorNameElement) {
        unservedDonorNameElement.textContent = name;
    }
    const unservedModal = document.getElementById('unservedModal');
    if (unservedModal) {
        new bootstrap.Modal(unservedModal).show();
    }
}

function confirmUnserved() {
    const reasonElement = document.getElementById('unservedReason');
    const customReasonElement = document.getElementById('customUnservedReason');
    if (reasonElement && customReasonElement) {
        const reason = reasonElement.value;
        const customReason = customReasonElement.value;
        const finalReason = reason === 'Other' ? customReason : reason;
        if (typeof showGlobalLoader === 'function') { showGlobalLoader('Marking as unserved...'); }
        window.location.href = `?tab=donor-list&mark_unserved=${currentUnservedId}&reason=${encodeURIComponent(finalReason)}`;
    }
}

// Show/hide custom reason fields - only if elements exist
const rejectionReasonElement = document.getElementById('rejectionReason');
if (rejectionReasonElement) {
    rejectionReasonElement.addEventListener('change', function() {
        const customReasonDiv = document.getElementById('customReasonDiv');
        if (customReasonDiv) {
            customReasonDiv.style.display = this.value === 'Other' ? 'block' : 'none';
        }
    });
}

// Defer reason dropdown functionality
const deferReasonElement = document.getElementById('deferReason');
if (deferReasonElement) {
    deferReasonElement.addEventListener('change', function() {
        const customDeferReasonDiv = document.getElementById('customDeferReasonDiv');
        if (customDeferReasonDiv) {
            customDeferReasonDiv.style.display = this.value === 'Other' ? 'block' : 'none';
        }
    });
}

// Unserved request reason dropdown functionality
const unservedRequestReasonElement = document.getElementById('unservedRequestReason');
if (unservedRequestReasonElement) {
    unservedRequestReasonElement.addEventListener('change', function() {
        const customUnservedRequestReasonDiv = document.getElementById('customUnservedRequestReasonDiv');
        if (customUnservedRequestReasonDiv) {
            customUnservedRequestReasonDiv.style.display = this.value === 'Other' ? 'block' : 'none';
        }
    });
}

// Donor matching functions
function createMatch(requestId, donorId, matchScore) {
    if (confirm('Create match between this donor and request?')) {
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
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while creating the match.');
        });
    }
}

function sendNotification(matchId) {
    if (confirm('Send notification to donor?')) {
        fetch('admin_actions_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=send_notification&match_id=${matchId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Notification sent successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while sending the notification.');
        });
    }
}

function updateMatchStatus(matchId, status) {
    if (confirm(`Update match status to ${status}?`)) {
        fetch('admin_actions_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_match_status&match_id=${matchId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the match status.');
        });
    }
}

// Defer request functionality
let currentDeferRequestId = null;
function showDeferModal(id, name) {
    currentDeferRequestId = id;
    const deferRequestNameElement = document.getElementById('deferRequestName');
    if (deferRequestNameElement) {
        deferRequestNameElement.textContent = name;
    }
    const deferModal = document.getElementById('deferModal');
    if (deferModal) {
        new bootstrap.Modal(deferModal).show();
    }
}

function confirmDefer() {
    const reasonElement = document.getElementById('deferReason');
    const customReasonElement = document.getElementById('customDeferReason');
    if (reasonElement && customReasonElement) {
        const reason = reasonElement.value;
        const customReason = customReasonElement.value;
        const finalReason = reason === 'Other' ? customReason : reason;
        window.location.href = `?tab=blood-requests&defer_request=${currentDeferRequestId}&reason=${encodeURIComponent(finalReason)}`;
    }
}

// Served request functionality
let currentServedRequestId = null;
function showServedModal(id, name) {
    currentServedRequestId = id;
    const servedRequestNameElement = document.getElementById('servedRequestName');
    if (servedRequestNameElement) {
        servedRequestNameElement.textContent = name;
    }
    const servedModal = document.getElementById('servedModal');
    if (servedModal) {
        new bootstrap.Modal(servedModal).show();
    }
}

function confirmServed() {
    window.location.href = `?tab=blood-requests&mark_served=${currentServedRequestId}`;
}

// Unserved request functionality
let currentUnservedRequestId = null;
function showUnservedRequestModal(id, name) {
    currentUnservedRequestId = id;
    const unservedRequestNameElement = document.getElementById('unservedRequestName');
    if (unservedRequestNameElement) {
        unservedRequestNameElement.textContent = name;
    }
    const unservedRequestModal = document.getElementById('unservedRequestModal');
    if (unservedRequestModal) {
        new bootstrap.Modal(unservedRequestModal).show();
    }
}

function confirmUnservedRequest() {
    const reasonElement = document.getElementById('unservedRequestReason');
    const customReasonElement = document.getElementById('customUnservedRequestReason');
    if (reasonElement && customReasonElement) {
        const reason = reasonElement.value;
        const customReason = customReasonElement.value;
        const finalReason = reason === 'Other' ? customReason : reason;
        window.location.href = `?tab=blood-requests&mark_unserved=${currentUnservedRequestId}&reason=${encodeURIComponent(finalReason)}`;
    }
}

// Function to populate request details modal
function showRequestDetails(requestData) {
    const modalContent = document.getElementById('requestDetailsContent');
    if (modalContent) {
        // Format status text
        let statusText = requestData.status || 'pending';
        let statusClass = 'warning';
        
        switch(statusText) {
            case 'approved':
                statusClass = 'success';
                statusText = 'Approved';
                break;
            case 'deferred':
                statusClass = 'warning';
                statusText = 'Temporarily Deferred';
                break;
            case 'in_progress':
                statusClass = 'info';
                statusText = 'In Progress';
                break;
            case 'served':
                statusClass = 'primary';
                statusText = 'Served';
                break;
            case 'unserved':
                statusClass = 'danger';
                statusText = 'Unserved';
                break;
            default:
                statusClass = 'warning';
                statusText = 'Pending';
        }
        
        modalContent.innerHTML = `
        <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold">Basic Information</h6>
                    <p><strong>ID:</strong> ${requestData.id}</p>
                    <p><strong>Reference:</strong> ${requestData.reference || 'N/A'}</p>
                                            <p><strong>Requester:</strong> ${requestData.patient_name}</p>
                    <p><strong>Email:</strong> ${requestData.email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${requestData.phone || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold">Request Details</h6>
                                            <p><strong>Blood Type Needed:</strong> ${requestData.blood_type}</p>
                    <p><strong>Desired Date:</strong> ${requestData.desired_date ? new Date(requestData.desired_date).toLocaleDateString() : 'Not specified'}</p>
                    <p><strong>Status:</strong> <span class="badge bg-${statusClass}">${statusText}</span></p>
                    <p><strong>Submitted:</strong> ${new Date(requestData.created_at).toLocaleDateString()}</p>
                </div>
            </div>
            ${requestData.remarks ? `
            <div class="mt-3">
                <h6 class="fw-bold">Remarks</h6>
                <div class="alert alert-info">
                    ${requestData.remarks}
                </div>
            </div>
            ` : ''}
        `;
        
        // Get the modal element
        const modalElement = document.getElementById('requestDetailsModal');
        
        // Destroy any existing modal instance
        const existingModal = bootstrap.Modal.getInstance(modalElement);
        if (existingModal) {
            existingModal.dispose();
        }
        
        // Create a new modal instance
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
        
        // Show the modal
        modal.show();
        
        // Add event listener to ensure proper cleanup when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function () {
            // Clear the content when modal is closed
            modalContent.innerHTML = '';
            // Dispose the modal instance
            modal.dispose();
        });
    }
}

function closeRequestDetailsModal() {
    const modalElement = document.getElementById('requestDetailsModal');
    if (modalElement) {
        // Force hide the modal
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        } else {
            // If no instance exists, create one and hide it
            const newModal = new bootstrap.Modal(modalElement);
            newModal.hide();
        }
        
        // Force remove backdrop and modal classes
        setTimeout(() => {
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            
            // Remove backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
            
            // Re-enable body scrolling
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }, 100);
    }
}

// Global function to force close any modal
function forceCloseModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        // Hide the modal
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
        
        // Force cleanup
        setTimeout(() => {
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            
            // Remove backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
            
            // Re-enable body scrolling
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }, 100);
    }
}

// Add keyboard event listener for Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        // Find any open modal and close it
        const openModal = document.querySelector('.modal.show');
        if (openModal) {
            const modalId = openModal.id;
            forceCloseModal(modalId);
        }
    }
});

// Function to show loading state on action buttons
function showLoading(button, loadingText) {
    // Store original text and disable button
    const originalText = button.innerHTML;
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${loadingText}`;
    button.disabled = true;
    button.classList.add('disabled');
    
    // Add loading class for styling
    button.classList.add('loading');
    
    // Prevent multiple clicks
    button.style.pointerEvents = 'none';
    
    // Set a timeout to re-enable the button if the page doesn't redirect
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        button.classList.remove('disabled', 'loading');
        button.style.pointerEvents = '';
    }, 10000); // 10 seconds timeout
}

// Global full-page loader shown during long navigations/actions
function showGlobalLoader(message) {
    let overlay = document.getElementById('globalLoader');
    if (!overlay) return;
    const msg = overlay.querySelector('.global-loader-message');
    if (msg) { msg.textContent = message || 'Processing...'; }
    overlay.style.display = 'flex';
}

// Attach overlay to action buttons and on navigation
document.addEventListener('DOMContentLoaded', function() {
    // Any action buttons trigger overlay
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            showGlobalLoader('Processing...');
        });
    });

    // Show overlay on page unload if an action is in progress
    window.addEventListener('beforeunload', function() {
        const anyLoading = document.querySelector('.action-btn.loading');
        if (anyLoading) {
            showGlobalLoader('Processing...');
        }
    });
});

// Help system functions
function printHelp() {
    window.print();
}

function contactSupport() {
    // Open email client with support email
    window.open('mailto:support@redcross.org?subject=Blood%20Donation%20System%20Support', '_blank');
}

function showCurrentURLParams() {
    // Get the current URL
    const url = window.location.href;
    
    // Extract query parameters
    const params = new URLSearchParams(url.split('?')[1]);
    
    // Create a string to hold the parameters
    let paramString = '';
    
    // Loop through each parameter and add to the string
    for (const [key, value] of params) {
        paramString += `${key}=${value}&`;
    }
    
    // Display the parameters
    alert('Current URL Parameters:\n' + paramString);
}

function forceReload() {
    location.reload();
}
</script>

<style>
/* Loading state styling */
.action-btn.loading {
    opacity: 0.7;
    cursor: not-allowed;
}

.action-btn.loading .spinner-border {
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

/* Disable pointer events for loading buttons */
.action-btn.loading {
    pointer-events: none !important;
}

/* Success state styling */
.action-btn.success {
    background-color: #198754 !important;
    border-color: #198754 !important;
    color: white !important;
}
</style>

<!-- Help & Guide Tab -->
<?php if ($activeTab === 'help'): ?>
    <!-- Help & Guide Tab -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-question-circle me-2"></i>Help & User Guide</h2>
        <button class="btn btn-outline-primary btn-sm" onclick="printHelp()">
            <i class="fas fa-print me-1"></i>Print Guide
        </button>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Quick Start Guide -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Quick Start Guide</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Welcome to the Blood Donation Management System!</strong><br>
                        This guide will help you understand how to use all the features effectively.
                    </div>
                    
                    <h6 class="fw-bold">Getting Started:</h6>
                    <ol>
                        <li><strong>Dashboard:</strong> View overview statistics and recent activity</li>
                        <li><strong>Pending Donors:</strong> Review and approve new donor applications</li>
                        <li><strong>Blood Requests:</strong> Manage blood donation requests</li>
                        <li><strong>Reports:</strong> Generate detailed analytics and reports</li>
                    </ol>
                </div>
            </div>

            <!-- Donor Management Guide -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Donor Management Guide</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Pending Donors Page:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Button/Action</th>
                                    <th>What it does</th>
                                    <th>When to use</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-success">Approve</span></td>
                                    <td>Approves the donor application and sends approval email</td>
                                    <td>When donor meets all eligibility criteria</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">Reject</span></td>
                                    <td>Rejects the application with a reason</td>
                                    <td>When donor doesn't meet criteria</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-info">View Details</span></td>
                                    <td>Shows complete donor information and medical screening</td>
                                    <td>To review full application before decision</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning">Mark Served</span></td>
                                    <td>Marks donor as having completed donation</td>
                                    <td>After donor visits and donates blood</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">Mark Unserved</span></td>
                                    <td>Marks donor as not served with reason</td>
                                    <td>When donor doesn't show up or can't donate</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold mt-3">Donor Status Flow:</h6>
                    <div class="alert alert-light">
                        <strong>Pending</strong> → <strong>Approved</strong> → <strong>Served/Unserved</strong>
                    </div>
                </div>
            </div>

            <!-- Blood Requests Guide -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-tint me-2"></i>Blood Requests Guide</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Blood Requests Workflow:</h6>
                    <div class="alert alert-info">
                        <strong>Pending</strong> → <strong>In Progress</strong> → <strong>Approved/Deferred</strong> → <strong>Served/Unserved</strong>
                    </div>

                    <h6 class="fw-bold">Action Buttons Explained:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Available Actions</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                    <td>
                                        <span class="badge bg-info">In Progress</span>
                                        <span class="badge bg-warning">Defer</span>
                                    </td>
                                    <td>Initial review stage</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-info">In Progress</span></td>
                                    <td>
                                        <span class="badge bg-success">Approve</span>
                                        <span class="badge bg-warning">Defer</span>
                                        <span class="badge bg-secondary">Pending</span>
                                    </td>
                                    <td>Processing the request</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">Approved</span></td>
                                    <td>
                                        <span class="badge bg-primary">Served</span>
                                        <span class="badge bg-warning">Unserved</span>
                                    </td>
                                    <td>Request approved, waiting for service</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning">Deferred</span></td>
                                    <td>
                                        <span class="badge bg-success">Approve</span>
                                        <span class="badge bg-secondary">Pending</span>
                                    </td>
                                    <td>Temporarily deferred with reason</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold mt-3">Email Notifications:</h6>
                    <ul>
                        <li><strong>Approved:</strong> "You may visit the Red Cross from 8:00 AM to 5:00 PM to complete your blood donation process"</li>
                        <li><strong>Deferred:</strong> "Your blood request has been temporarily deferred. Reason: [specific reason]"</li>
                        <li><strong>Served:</strong> Confirmation of successful service</li>
                        <li><strong>Unserved:</strong> Notification with specific reason</li>
                    </ul>
                </div>
            </div>

            <!-- Dashboard Analytics Guide -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Dashboard Analytics Guide</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Key Metrics Explained:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Donor Statistics:</h6>
                            <ul>
                                <li><strong>Total Donors:</strong> All registered donors</li>
                                <li><strong>Pending Donors:</strong> Awaiting approval</li>
                                <li><strong>Approved Donors:</strong> Cleared for donation</li>
                                <li><strong>Served Donors:</strong> Completed donations</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Request Statistics:</h6>
                            <ul>
                                <li><strong>Total Requests:</strong> All blood requests</li>
                                <li><strong>Pending Requests:</strong> Awaiting processing</li>
                                <li><strong>In Progress:</strong> Currently being processed</li>
                                <li><strong>Deferred:</strong> Temporarily postponed</li>
                                <li><strong>Served:</strong> Successfully fulfilled</li>
                            </ul>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-3">Blood Inventory Management:</h6>
                    <ul>
                        <li><strong>Blood Type Distribution:</strong> Shows available blood types from approved donors</li>
                        <li><strong>Request Analysis:</strong> Shows blood type demand from requests</li>
                        <li><strong>Monthly Trends:</strong> Tracks donor and request patterns over time</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Tips -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Tips</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <strong>💡 Pro Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Always review donor details before approval</li>
                            <li>Use the search and filter functions for large lists</li>
                            <li>Check blood inventory before approving requests</li>
                            <li>Keep remarks detailed for deferred requests</li>
                            <li>Monitor monthly trends for capacity planning</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Keyboard Shortcuts -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tr>
                                <td><kbd>Ctrl</kbd> + <kbd>F</kbd></td>
                                <td>Search in current page</td>
                            </tr>
                            <tr>
                                <td><kbd>Esc</kbd></td>
                                <td>Close any open modal</td>
                            </tr>
                            <tr>
                                <td><kbd>Enter</kbd></td>
                                <td>Submit forms</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Contact Support -->
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-headset me-2"></i>Need Help?</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">If you need additional support:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i>Email: support@redcross.org</li>
                        <li><i class="fas fa-phone me-2"></i>Phone: (123) 456-7890</li>
                        <li><i class="fas fa-clock me-2"></i>Hours: Mon-Fri 8AM-5PM</li>
                    </ul>
                    <button class="btn btn-outline-primary btn-sm w-100" onclick="contactSupport()">
                        <i class="fas fa-comment me-1"></i>Contact Support
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Help & Guide specific JavaScript
    function printHelp() {
        window.print();
    }
    
    function contactSupport() {
        alert('Contact support at support@redcross.org or call (123) 456-7890');
    }
    </script>
<?php endif; ?>

<?php if ($activeTab === 'blood-inventory'): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tint me-2"></i>Blood Inventory Management</h2>
        <div class="d-flex gap-2">
            <!-- Blood Type Filter -->
            <form class="d-flex" method="GET" action="">
                <input type="hidden" name="tab" value="blood-inventory">
                <select name="inventory_filter" class="form-select" onchange="this.form.submit()">
                    <option value="">All Blood Types</option>
                    <option value="A+" <?= ($_GET['inventory_filter'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                    <option value="A-" <?= ($_GET['inventory_filter'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                    <option value="B+" <?= ($_GET['inventory_filter'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                    <option value="B-" <?= ($_GET['inventory_filter'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                    <option value="AB+" <?= ($_GET['inventory_filter'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                    <option value="AB-" <?= ($_GET['inventory_filter'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                    <option value="O+" <?= ($_GET['inventory_filter'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                    <option value="O-" <?= ($_GET['inventory_filter'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                </select>
            </form>
            <button class="btn btn-outline-primary btn-sm" onclick="refreshInventory()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/blood_inventory.php';
    
    // Initialize inventory tables if they don't exist
    createBloodInventoryTable($pdo);
    
    // Check for expired units
    checkExpiredUnits($pdo);
    
    // Get inventory data with optional filter
    $inventoryFilter = $_GET['inventory_filter'] ?? null;
    $inventoryData = getBloodInventory($pdo, $inventoryFilter);
    ?>

    <!-- Inventory Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Current Inventory Status</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if ($inventoryData && isset($inventoryData['inventory'])): ?>
                            <?php foreach ($inventoryData['inventory'] as $item): ?>
                                <?php 
                                $bgClass = $item['units_available'] > 10 ? 'bg-success' : 
                                          ($item['units_available'] > 5 ? 'bg-warning' : 'bg-danger');
                                $textClass = $item['units_available'] > 10 ? 'text-success' : 
                                           ($item['units_available'] > 5 ? 'text-warning' : 'text-danger');
                                ?>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card h-100 border-0">
                                        <div class="card-body text-center">
                                            <div class="mb-2">
                                                <span class="badge <?= $bgClass ?>-subtle <?= $textClass ?> p-2 fs-5 w-100">
                                                    <?= $item['blood_type'] ?>
                                                </span>
                                            </div>
                                            <h3 class="mb-1 <?= $textClass ?>"><?= $item['units_available'] ?></h3>
                                            <small class="text-muted">Available Units</small>
                                            <?php if ($item['units_expired'] > 0): ?>
                                                <div class="mt-2">
                                                    <small class="text-danger"><?= $item['units_expired'] ?> expired</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center">
                                <p class="text-muted">No inventory data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alerts</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($inventoryData['low_stock']) && !empty($inventoryData['low_stock'])): ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($inventoryData['low_stock'] as $bloodType): ?>
                                <li class="mb-2"><i class="fas fa-arrow-right text-warning me-2"></i><?= $bloodType ?> - Low stock</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">No low stock alerts</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Expiring Soon</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($inventoryData['expiring']) && !empty($inventoryData['expiring'])): ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($inventoryData['expiring'] as $expiring): ?>
                                <li class="mb-2"><i class="fas fa-arrow-right text-info me-2"></i><?= $expiring['blood_type'] ?> - <?= $expiring['expiring_count'] ?> units expiring in 7 days</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">No units expiring soon</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Blood Unit Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Blood Unit</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="admin.php?tab=blood-inventory" id="addBloodUnitForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="blood_type" class="form-label">Blood Type</label>
                                <select name="blood_type" id="blood_type" class="form-select" required>
                                    <option value="">Select Blood Type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="donor_id" class="form-label">Donor ID (Optional)</label>
                                <input type="number" class="form-control" id="donor_id" name="donor_id" placeholder="Donor ID">
                            </div>
                            <div class="col-md-3">
                                <label for="collection_date" class="form-label">Collection Date</label>
                                <input type="date" class="form-control" id="collection_date" name="collection_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-1"></i>Add Unit
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function refreshInventory() {
        location.reload();
    }
    </script>
<?php endif; ?>




