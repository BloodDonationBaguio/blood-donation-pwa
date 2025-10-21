<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/header.php';

// Check admin login
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$action = $_GET['action'] ?? 'list';
$donorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';

// Clear session messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Handle actions
if ($action === 'view' && $donorId) {
    try {
        $stmt = $pdo->prepare("SELECT d.*, u.name, u.email, u.phone, u.blood_type 
                             FROM donors d 
                             JOIN users u ON d.user_id = u.id 
                             WHERE d.id = ?");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$donor) throw new Exception('Donor not found');
        
    } catch (Exception $e) {
        $error = 'Error loading donor details: ' . $e->getMessage();
        error_log($error);
        $action = 'list';
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    
    if (isset($_POST['update_status'])) {
        $status = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            // Update donor status
            $stmt = $pdo->prepare("UPDATE donors_new SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $donorId]);
            
            // Log status change
            $stmt = $pdo->prepare("INSERT INTO donor_status_history 
                                 (donor_id, status, notes, admin_id) 
                                 VALUES (?, ?, ?, ?)");
            $stmt->execute([$donorId, $status, $notes, $_SESSION['admin_id']]);
            
            // Send email notification if requested
            if (!empty($_POST['send_email'])) {
                $stmt = $pdo->prepare("SELECT email, name FROM users u 
                                      JOIN donors d ON d.user_id = u.id 
                                      WHERE d.id = ?");
                $stmt->execute([$donorId]);
                if ($donor = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $to = $donor['email'];
                    $subject = "Your Donor Status Has Been Updated";
                    $message = "<p>Hello " . htmlspecialchars($donor['name']) . ",</p>";
                    $message .= "<p>Your donor status has been updated to: <strong>" . ucfirst($status) . "</strong></p>";
                    if (!empty($notes)) {
                        $message .= "<p><strong>Note from staff:</strong> " . nl2br(htmlspecialchars($notes)) . "</p>";
                    }
                    $message .= "<p>Thank you for your support!</p>";
                    
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                    $headers .= 'From: Blood Donation Team <noreply@blooddonation.org>' . "\r\n";
                    
                    @mail($to, $subject, $message, $headers);
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Donor status updated successfully';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Error updating donor status: ' . $e->getMessage();
            error_log('Error updating donor status: ' . $e->getMessage());
        }
        
        header("Location: donors.php?action=view&id=$donorId");
        exit();
    }
}

// Get filter parameters for list view
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query for donors list
$query = "SELECT SQL_CALC_FOUND_ROWS d.*, u.name, u.email, u.phone, u.blood_type 
          FROM donors d 
          JOIN users u ON d.user_id = u.id ";
          
$where = [];
$params = [];

// Apply filters
if ($status === 'inactive') {
    $where[] = "d.status IN ('rejected', 'suspended')";
} elseif (!empty($status)) {
    $where[] = "d.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

// Add WHERE clause if filters are applied
if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

// Add sorting and pagination
$query .= " ORDER BY d.updated_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $totalRows = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $totalPages = ceil($totalRows / $perPage);
    
} catch (Exception $e) {
    $error = 'Error loading donors: ' . $e->getMessage();
    error_log($error);
    $donors = [];
    $totalPages = 0;
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">
            <?= $action === 'view' ? 'View Donor' : 'Manage Donors' ?>
        </h1>
        
        <?php if ($action === 'list'): ?>
            <a href="donors.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add New Donor
            </a>
        <?php else: ?>
            <a href="donors.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'view' && isset($donor)): ?>
        <!-- Donor Details View -->
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <h4><?= htmlspecialchars($donor['name']) ?></h4>
                        <p class="text-muted">Donor ID: #<?= $donor['id'] ?></p>
                        
                        <?php
                        $statusClass = [
                            'pending' => 'bg-warning',
                            'approved' => 'bg-success',
                            'rejected' => 'bg-danger',
                            'suspended' => 'bg-secondary'
                        ][$donor['status']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $statusClass ?> mb-3">
                            <?= ucfirst($donor['status']) ?>
                        </span>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                <i class="fas fa-exchange-alt me-1"></i> Update Status
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Contact Information</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-envelope me-2 text-muted"></i>
                                <?= htmlspecialchars($donor['email']) ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-phone me-2 text-muted"></i>
                                <?= htmlspecialchars($donor['phone']) ?: 'N/A' ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-tint me-2 text-muted"></i>
                                Blood Type: 
                                <span class="badge bg-danger">
                                    <?= htmlspecialchars($donor['blood_type'] ?? 'N/A') ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Status History</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $stmt = $pdo->prepare("SELECT h.*, a.username as admin_name 
                                                  FROM donor_status_history h 
                                                  LEFT JOIN admin_users a ON h.admin_id = a.id 
                                                  WHERE h.donor_id = ? 
                                                  ORDER BY h.created_at DESC");
                            $stmt->execute([$donorId]);
                            $statusHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($statusHistory)) {
                                echo '<p class="text-muted">No status history available.</p>';
                            } else {
                                foreach ($statusHistory as $history) {
                                    $statusClass = [
                                        'pending' => 'bg-warning',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        'suspended' => 'bg-secondary'
                                    ][$history['status']] ?? 'bg-secondary';
                                    
                                    echo '<div class="mb-3 pb-3 border-bottom">';
                                    echo '<div class="d-flex justify-content-between align-items-center">';
                                    echo '<div class="d-flex align-items-center">';
                                    echo '<span class="badge ' . $statusClass . ' me-2">' . ucfirst($history['status']) . '</span>';
                                    echo '<small class="text-muted">' . date('M j, Y g:i A', strtotime($history['created_at'])) . '</small>';
                                    echo '</div>';
                                    if (!empty($history['admin_name'])) {
                                        echo '<small class="text-muted">By: ' . htmlspecialchars($history['admin_name']) . '</small>';
                                    }
                                    echo '</div>';
                                    if (!empty($history['notes'])) {
                                        echo '<div class="mt-2 p-2 bg-light rounded">' . nl2br(htmlspecialchars($history['notes'])) . '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                        } catch (Exception $e) {
                            echo '<div class="alert alert-warning">Error loading status history.</div>';
                            error_log('Error loading status history: ' . $e->getMessage());
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Update Status Modal -->
        <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="donors.php?action=view&id=<?= $donor['id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="updateStatusModalLabel">Update Donor Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending" <?= $donor['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $donor['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $donor['status'] === 'rejected' ? 'selected' : '' ?>>Deferred</option>
                                    <option value="suspended" <?= $donor['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any additional notes...                             </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="sendEmail" name="send_email" checked>
                                <label class="form-check-label" for="sendEmail">
                                    Send email notification to donor
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Donors List View -->
        <div class="card">
            <div class="card-header">
                <form action="donors.php" method="get" class="row g-2">
                    <input type="hidden" name="action" value="list">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Deferred</option>
                            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="donors.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <?php if (empty($donors)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                        <h5>No donors found</h5>
                        <p class="text-muted">No donors match your current filters.</p>
                        <a href="donors.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Blood Type</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donors as $d): 
                                    $statusClass = [
                                        'pending' => 'bg-warning',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        'suspended' => 'bg-secondary'
                                    ][$d['status']] ?? 'bg-secondary';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2 text-primary"></i>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($d['name']) ?></div>
                                                <small class="text-muted">ID: #<?= $d['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;">
                                            <?= htmlspecialchars($d['email']) ?>
                                        </div>
                                        <?php if (!empty($d['phone'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($d['phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?= htmlspecialchars($d['blood_type'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= ucfirst($d['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted" title="<?= date('M j, Y g:i A', strtotime($d['updated_at'])) ?>">
                                            <?= timeAgo($d['updated_at']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="donors.php?action=view&id=<?= $d['id'] ?>">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="donors.php?action=edit&id=<?= $d['id'] ?>">
                                                        <i class="fas fa-edit me-1"></i> Edit
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $d['id'] ?>">
                                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?= $d['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Deletion</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this donor? This action cannot be undone.</p>
                                                        <p class="mb-0"><strong>Donor:</strong> <?= htmlspecialchars($d['name']) ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form action="donors.php" method="post" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                            <input type="hidden" name="donor_id" value="<?= $d['id'] ?>">
                                                            <button type="submit" name="delete_donor" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>