<?php
/**
 * Demo: How Admin Updates Blood Units
 * Visual demonstration of the update process
 */

session_start();
require_once 'db.php';
require_once 'includes/BloodInventoryManagerComplete.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$inventoryManager = new BloodInventoryManagerComplete($pdo);
$inventory = $inventoryManager->getInventory([], 1, 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Update Demo - Blood Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .demo-step { border-left: 4px solid #007bff; margin-bottom: 2rem; padding-left: 1rem; }
        .demo-code { background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; font-family: monospace; }
        .status-available { background: #28a745; }
        .status-used { background: #6c757d; }
        .status-expired { background: #dc3545; }
        .status-quarantined { background: #ffc107; color: #000; }
        .demo-highlight { background: #fff3cd; padding: 0.5rem; border-radius: 0.25rem; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row bg-primary text-white py-3 mb-4 rounded">
            <div class="col">
                <h2><i class="fas fa-graduation-cap me-2"></i>Admin Update Demo</h2>
                <p class="mb-0">Step-by-step guide on how to update blood units</p>
            </div>
            <div class="col-auto">
                <a href="admin_blood_inventory_complete.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Go to Real System
                </a>
            </div>
        </div>

        <!-- Demo Steps -->
        <div class="row">
            <div class="col-md-8">
                <!-- Step 1: Access System -->
                <div class="demo-step">
                    <h4><i class="fas fa-sign-in-alt me-2"></i>Step 1: Access the System</h4>
                    <p>Navigate to the Blood Inventory Management system:</p>
                    <div class="demo-code">
                        Admin Panel → Blood Inventory<br>
                        URL: admin_blood_inventory_complete.php
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> You must be logged in as an admin with appropriate permissions.
                    </div>
                </div>

                <!-- Step 2: Find Unit -->
                <div class="demo-step">
                    <h4><i class="fas fa-search me-2"></i>Step 2: Find the Unit to Update</h4>
                    <p>Use the filtering and search options to locate the blood unit:</p>
                    <div class="demo-code">
                        • Filter by Blood Type: A+, A-, B+, B-, AB+, AB-, O+, O-<br>
                        • Filter by Status: Available, Used, Expired, Quarantined<br>
                        • Search: Unit ID, Donor Name, Reference Code
                    </div>
                </div>

                <!-- Step 3: Update Status -->
                <div class="demo-step">
                    <h4><i class="fas fa-edit me-2"></i>Step 3: Update Unit Status</h4>
                    <p>Click the <strong>Edit</strong> button (pencil icon) in the Actions column:</p>
                    <div class="demo-code">
                        1. Click ✏️ Edit button<br>
                        2. Select new status from dropdown<br>
                        3. Enter reason/notes<br>
                        4. Click "Update Status"
                    </div>
                    <div class="demo-highlight">
                        <strong>Status Options:</strong><br>
                        <span class="badge status-available">Available</span> → 
                        <span class="badge status-used">Used</span> (issued to patient)<br>
                        <span class="badge status-available">Available</span> → 
                        <span class="badge status-expired">Expired</span> (past expiry)<br>
                        <span class="badge status-available">Available</span> → 
                        <span class="badge status-quarantined">Quarantined</span> (failed screening)
                    </div>
                </div>

                <!-- Step 4: Update Blood Type -->
                <div class="demo-step">
                    <h4><i class="fas fa-tint me-2"></i>Step 4: Update Blood Type (if needed)</h4>
                    <p>For units with "Unknown" blood type after lab screening:</p>
                    <div class="demo-code">
                        1. Find unit with "Unknown" blood type<br>
                        2. Click ✏️ Edit button<br>
                        3. Select confirmed blood type<br>
                        4. Enter lab results/notes<br>
                        5. Click "Update Blood Type"
                    </div>
                </div>

                <!-- Step 5: Add New Unit -->
                <div class="demo-step">
                    <h4><i class="fas fa-plus me-2"></i>Step 5: Add New Blood Unit</h4>
                    <p>To add a new blood unit to the inventory:</p>
                    <div class="demo-code">
                        1. Click "Add Unit" button (green)<br>
                        2. Select donor from dropdown<br>
                        3. Set collection date<br>
                        4. Set collection site<br>
                        5. Set storage location<br>
                        6. Click "Add Unit"
                    </div>
                    <div class="alert alert-success">
                        <i class="fas fa-magic me-2"></i>
                        <strong>Auto-Detection:</strong> Blood type and expiry date are automatically calculated!
                    </div>
                </div>

                <!-- Step 6: Delete Unit -->
                <div class="demo-step">
                    <h4><i class="fas fa-trash me-2"></i>Step 6: Delete Unit (if needed)</h4>
                    <p>To remove a unit from the inventory:</p>
                    <div class="demo-code">
                        1. Find unit to delete<br>
                        2. Click 🗑️ Delete button<br>
                        3. Confirm deletion in popup<br>
                        4. Enter reason for deletion<br>
                        5. Click "Confirm"
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Deletion is permanent and will be logged in audit trail!
                    </div>
                </div>
            </div>

            <!-- Live Demo Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Live Demo</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Current blood units in system:</p>
                        
                        <?php if (!empty($inventory['data'])): ?>
                            <?php foreach ($inventory['data'] as $unit): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($unit['unit_id']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($unit['donor_name']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge status-<?= $unit['status'] ?>">
                                                <?= ucfirst($unit['status']) ?>
                                            </span><br>
                                            <small class="text-muted"><?= htmlspecialchars($unit['blood_type']) ?></small>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Collection: <?= date('M d, Y', strtotime($unit['collection_date'])) ?><br>
                                            Expiry: <?= date('M d, Y', strtotime($unit['expiry_date'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                No blood units found
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="admin_blood_inventory_complete.php" class="btn btn-primary w-100">
                                <i class="fas fa-external-link-alt me-2"></i>Try It Now
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="showUpdateModal()">
                                <i class="fas fa-edit me-2"></i>Update Status
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="showAddModal()">
                                <i class="fas fa-plus me-2"></i>Add Unit
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="showExportModal()">
                                <i class="fas fa-download me-2"></i>Export CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Permissions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Role-Based Permissions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-crown fa-2x text-warning mb-2"></i>
                                    <h6>Super Admin</h6>
                                    <small class="text-muted">Full access to all features</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-boxes fa-2x text-primary mb-2"></i>
                                    <h6>Inventory Manager</h6>
                                    <small class="text-muted">Add, edit, delete units</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-user-md fa-2x text-success mb-2"></i>
                                    <h6>Medical Staff</h6>
                                    <small class="text-muted">View PII, update medical data</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-eye fa-2x text-info mb-2"></i>
                                    <h6>Viewer</h6>
                                    <small class="text-muted">Read-only access</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showUpdateModal() {
            alert('This would open the Update Status modal in the real system!');
        }
        
        function showAddModal() {
            alert('This would open the Add Unit modal in the real system!');
        }
        
        function showExportModal() {
            alert('This would trigger CSV export in the real system!');
        }
    </script>
</body>
</html>
