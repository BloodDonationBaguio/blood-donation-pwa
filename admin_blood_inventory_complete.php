<?php
/**
 * Complete Blood Inventory Management System
 * Full admin capabilities with security and compliance
 */

session_start();
require_once 'db.php';
require_once 'includes/BloodInventoryManagerComplete.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Initialize manager
$inventoryManager = new BloodInventoryManagerComplete($pdo);

// Get user permissions - ALL FEATURES ENABLED
$userRole = $_SESSION['admin_role'] ?? 'super_admin';
$canEdit = true; // Always allow editing
$canViewPII = true; // Always allow viewing PII

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_unit':
            if (!$canEdit) {
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            }
            $result = $inventoryManager->addBloodUnit($_POST);
            echo json_encode($result);
            exit;
            
        case 'update_status':
            if (!$canEdit) {
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            }
            $result = $inventoryManager->updateUnitStatus($_POST['unit_id'], $_POST['status'], $_POST['reason'] ?? '');
            echo json_encode($result);
            exit;
            
        case 'update_blood_type':
            if (!$canEdit) {
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            }
            $result = $inventoryManager->updateBloodType($_POST['unit_id'], $_POST['blood_type']);
            echo json_encode($result);
            exit;
            
        case 'delete_unit':
            if (!$canEdit) {
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            }
            $result = $inventoryManager->deleteUnit($_POST['unit_id'], $_POST['reason'] ?? '');
            echo json_encode($result);
            exit;
            
        case 'get_unit_details':
            $result = $inventoryManager->getUnitDetails($_POST['unit_id'], $canViewPII);
            echo json_encode($result);
            exit;
    }
}

// Get data
$filters = [
    'blood_type' => $_GET['blood_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'page' => (int)($_GET['page'] ?? 1)
];

$inventory = $inventoryManager->getInventory($filters, $filters['page'], 20);
$summary = $inventoryManager->getDashboardSummary();
$alerts = $inventoryManager->getAlerts();
$donors = $inventoryManager->getEligibleDonors();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Inventory Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .alert-card { border-left: 4px solid #dc3545; }
        .status-available { background: #28a745; }
        .status-used { background: #6c757d; }
        .status-expired { background: #dc3545; }
        .status-quarantined { background: #ffc107; color: #000; }
        .pii-masked { filter: blur(2px); }
        .expiring-soon { background: #fff3cd; }
        .low-stock { background: #f8d7da; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-primary text-white py-3 mb-4">
            <div class="col">
                <h2><i class="fas fa-tint me-2"></i>Blood Inventory Management</h2>
                <p class="mb-0">Complete blood unit tracking and management system</p>
            </div>
            <div class="col-auto">
                <div class="d-flex align-items-center">
                    <!-- Admin Role Badge -->
                    <div class="me-3">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-user-shield me-2"></i>
                            <?php
                            // Get role from session or default to super_admin
                            $adminRole = $_SESSION['admin_role'] ?? 'super_admin';
                            if ($adminRole === 'admin') {
                                $adminRole = 'super_admin'; // Convert old 'admin' to 'super_admin'
                            }
                            echo ucfirst(str_replace('_', ' ', $adminRole)) . ' Admin';
                            ?>
                        </span>
                    </div>
                    <a href="admin.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin
                    </a>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($alerts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning alert-card">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>System Alerts</h5>
                    <ul class="mb-0">
                        <?php foreach ($alerts as $alert): ?>
                        <li><?= htmlspecialchars($alert) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dashboard Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?= $summary['total_units'] ?></h3>
                        <p class="mb-0">Total Units</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= $summary['available_units'] ?></h3>
                        <p class="mb-0">Available</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?= $summary['expiring_units'] ?></h3>
                        <p class="mb-0">Expiring Soon</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger"><?= $summary['expired_units'] ?></h3>
                        <p class="mb-0">Expired</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="blood_type" class="form-select">
                                    <option value="">All Blood Types</option>
                                    <option value="A+" <?= $filters['blood_type'] === 'A+' ? 'selected' : '' ?>>A+</option>
                                    <option value="A-" <?= $filters['blood_type'] === 'A-' ? 'selected' : '' ?>>A-</option>
                                    <option value="B+" <?= $filters['blood_type'] === 'B+' ? 'selected' : '' ?>>B+</option>
                                    <option value="B-" <?= $filters['blood_type'] === 'B-' ? 'selected' : '' ?>>B-</option>
                                    <option value="AB+" <?= $filters['blood_type'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                    <option value="AB-" <?= $filters['blood_type'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                    <option value="O+" <?= $filters['blood_type'] === 'O+' ? 'selected' : '' ?>>O+</option>
                                    <option value="O-" <?= $filters['blood_type'] === 'O-' ? 'selected' : '' ?>>O-</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="available" <?= $filters['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="used" <?= $filters['status'] === 'used' ? 'selected' : '' ?>>Used</option>
                                    <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="quarantined" <?= $filters['status'] === 'quarantined' ? 'selected' : '' ?>>Quarantined</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search by Unit ID or Donor..." value="<?= htmlspecialchars($filters['search']) ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <?php if ($canEdit): ?>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                            <i class="fas fa-plus me-2"></i>Add Unit
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-info" onclick="exportToCSV()">
                            <i class="fas fa-download me-2"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Blood Inventory (<?= count($inventory['data']) ?> of <?= $inventory['total'] ?> units)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Unit ID</th>
                                        <th>Blood Type</th>
                                        <th>Donor</th>
                                        <th>Collection Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory['data'] as $unit): ?>
                                    <tr class="<?= $unit['expiring_soon'] ? 'expiring-soon' : '' ?>">
                                        <td><code><?= htmlspecialchars($unit['unit_id']) ?></code></td>
                                        <td><span class="badge bg-danger"><?= htmlspecialchars($unit['blood_type']) ?></span></td>
                                        <td>
                                            <?php if ($canViewPII): ?>
                                                <?= htmlspecialchars($unit['donor_name']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($unit['reference_code']) ?></small>
                                            <?php else: ?>
                                                <span class="pii-masked">***-****-****</span><br>
                                                <small class="text-muted">***-****</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($unit['collection_date'])) ?></td>
                                        <td>
                                            <?= date('M d, Y', strtotime($unit['expiry_date'])) ?>
                                            <?php if ($unit['expiring_soon']): ?>
                                                <br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Expiring Soon</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge status-<?= $unit['status'] ?>">
                                                <?= ucfirst($unit['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewUnitDetails('<?= $unit['unit_id'] ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($canEdit): ?>
                                                <button class="btn btn-sm btn-outline-warning" onclick="updateUnitStatus('<?= $unit['unit_id'] ?>', '<?= $unit['status'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUnit('<?= $unit['unit_id'] ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
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

    <!-- Add Unit Modal -->
    <?php if ($canEdit): ?>
    <div class="modal fade" id="addUnitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Blood Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUnitForm">
                        <div class="mb-3">
                            <label class="form-label">Donor <span class="text-danger">*</span></label>
                            <select name="donor_id" class="form-select" required>
                                <option value="">Select Donor</option>
                                <?php foreach ($donors as $donor): ?>
                                <option value="<?= $donor['id'] ?>">
                                    <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?> 
                                    (<?= htmlspecialchars($donor['reference_code']) ?>) - <?= htmlspecialchars($donor['blood_type']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Collection Date <span class="text-danger">*</span></label>
                            <input type="date" name="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Collection Site</label>
                            <input type="text" name="collection_site" class="form-control" value="Main Center">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Storage Location</label>
                            <input type="text" name="storage_location" class="form-control" value="Storage A">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="addBloodUnit()">Add Unit</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Unit Details Modal -->
    <div class="modal fade" id="unitDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="unitDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <?php if ($canEdit): ?>
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Unit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="updateStatusForm">
                        <input type="hidden" id="updateUnitId" name="unit_id">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" required>
                                <option value="available">Available</option>
                                <option value="used">Used</option>
                                <option value="expired">Expired</option>
                                <option value="quarantined">Quarantined</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason/Notes</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for status change..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmStatusUpdate()">Update Status</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add Blood Unit
        function addBloodUnit() {
            const form = document.getElementById('addUnitForm');
            const formData = new FormData(form);
            formData.append('action', 'add_unit');
            
            fetch('admin_blood_inventory_complete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Blood unit added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the unit.');
            });
        }

        // View Unit Details
        function viewUnitDetails(unitId) {
            const modal = new bootstrap.Modal(document.getElementById('unitDetailsModal'));
            modal.show();
            
            fetch('admin_blood_inventory_complete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_unit_details&unit_id=' + unitId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUnitDetails(data.unit);
                } else {
                    document.getElementById('unitDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Error: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('unitDetailsContent').innerHTML = 
                    '<div class="alert alert-danger">Error loading unit details</div>';
            });
        }

        // Display Unit Details
        function displayUnitDetails(unit) {
            const content = document.getElementById('unitDetailsContent');
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Unit Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Unit ID:</strong></td><td><code>${unit.unit_id}</code></td></tr>
                            <tr><td><strong>Blood Type:</strong></td><td><span class="badge bg-danger">${unit.blood_type}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge status-${unit.status}">${unit.status}</span></td></tr>
                            <tr><td><strong>Collection Date:</strong></td><td>${unit.collection_date}</td></tr>
                            <tr><td><strong>Expiry Date:</strong></td><td>${unit.expiry_date}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Donor Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${unit.donor_name}</td></tr>
                            <tr><td><strong>Reference:</strong></td><td><code>${unit.reference_code}</code></td></tr>
                            <tr><td><strong>Blood Type:</strong></td><td>${unit.donor_blood_type}</td></tr>
                        </table>
                    </div>
                </div>
                ${unit.audit_log ? `
                <div class="mt-3">
                    <h6>Audit Log</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Date</th><th>Action</th><th>User</th><th>Details</th></tr>
                            </thead>
                            <tbody>
                                ${unit.audit_log.map(log => `
                                    <tr>
                                        <td>${log.timestamp}</td>
                                        <td>${log.action}</td>
                                        <td>${log.admin_name}</td>
                                        <td>${log.description}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
            `;
        }

        // Update Unit Status
        function updateUnitStatus(unitId, currentStatus) {
            document.getElementById('updateUnitId').value = unitId;
            document.querySelector('#updateStatusForm select[name="status"]').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        // Confirm Status Update
        function confirmStatusUpdate() {
            const form = document.getElementById('updateStatusForm');
            const formData = new FormData(form);
            formData.append('action', 'update_status');
            
            fetch('admin_blood_inventory_complete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Unit status updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the status.');
            });
        }

        // Delete Unit
        function deleteUnit(unitId) {
            if (confirm('Are you sure you want to delete this blood unit? This action cannot be undone.')) {
                const reason = prompt('Please enter reason for deletion:');
                if (reason) {
                    const formData = new FormData();
                    formData.append('action', 'delete_unit');
                    formData.append('unit_id', unitId);
                    formData.append('reason', reason);
                    
                    fetch('admin_blood_inventory_complete.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Blood unit deleted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the unit.');
                    });
                }
            }
        }

        // Export to CSV
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'admin_blood_inventory_complete.php?' + params.toString();
        }
    </script>
</body>
</html>
