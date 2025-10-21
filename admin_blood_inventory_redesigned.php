<?php
/**
 * Blood Inventory Management - Redesigned Admin Panel
 * Clean, user-friendly interface with comprehensive features
 */

// Start session and include dependencies
session_start();
require_once 'db.php';
require_once 'includes/enhanced_donor_management.php';
require_once 'includes/BloodInventoryManagerSimple.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Initialize Blood Inventory Manager
$inventoryManager = new BloodInventoryManagerSimple($pdo);

// Get current user role for RBAC
$userRole = $_SESSION['admin_role'] ?? 'viewer';
$canEdit = in_array($userRole, ['super_admin', 'inventory_manager', 'medical_staff']);
$canViewDonorInfo = in_array($userRole, ['super_admin', 'medical_staff']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_unit':
            if (!$canEdit) {
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            }
            
            $data = [
                'donor_id' => $_POST['donor_id'],
                'collection_date' => $_POST['collection_date'],
                'collection_center' => $_POST['collection_center'] ?? 'Main Center',
                'storage_location' => $_POST['storage_location'] ?? 'Storage A',
                'collection_staff' => $_SESSION['admin_username'] ?? 'Unknown'
            ];
            
            $result = $inventoryManager->createBloodUnit($data);
            echo json_encode($result);
            exit;
            
        case 'update_status':
            if (!$canEdit) {
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            }
            
            $unitId = $_POST['unit_id'];
            $newStatus = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            $result = $inventoryManager->updateBloodUnitStatus($unitId, $newStatus, $notes);
            echo json_encode($result);
            exit;
            
        case 'update_blood_type':
            if (!$canEdit) {
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            }
            
            $unitId = $_POST['unit_id'];
            $bloodType = $_POST['blood_type'];
            
            $result = $inventoryManager->updateBloodType($unitId, $bloodType);
            echo json_encode($result);
            exit;
            
        case 'get_unit_details':
            $unitId = $_POST['unit_id'];
            $result = $inventoryManager->getBloodUnit($unitId);
            echo json_encode($result);
            exit;
    }
}

// Get filter parameters
$filters = [
    'blood_type' => $_GET['blood_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? '',
    'page' => (int)($_GET['page'] ?? 1)
];

// Get inventory data
$inventory = $inventoryManager->getInventory($filters, $filters['page'], 20);
$dashboardSummary = $inventoryManager->getDashboardSummary();
$expiringUnits = $inventoryManager->getExpiringUnits(5); // Units expiring in 5 days

// Get available donors for adding units
$availableDonors = $inventoryManager->getApprovedDonors(100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Inventory Management - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #e74c3c);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 10px 0 0 0;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .blood-type-card {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .blood-type-card:hover {
            transform: scale(1.05);
        }

        .blood-type-card.healthy {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .blood-type-card.low {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
        }

        .blood-type-card.critical {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            color: white;
        }

        .blood-type-card.unknown {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }

        .alert-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--warning-color);
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .inventory-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 20px 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .table tbody tr:hover {
            background-color: rgba(220, 53, 69, 0.05);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-available {
            background: var(--success-color);
            color: white;
        }

        .status-used {
            background: var(--secondary-color);
            color: white;
        }

        .status-expired {
            background: var(--danger-color);
            color: white;
        }

        .status-quarantined {
            background: var(--warning-color);
            color: #212529;
        }

        .btn-custom {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 25px;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }

        .pagination {
            justify-content: center;
            margin-top: 30px;
        }

        .page-link {
            border-radius: 8px;
            margin: 0 3px;
            border: 1px solid var(--border-color);
            color: var(--primary-color);
        }

        .page-link:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .search-box {
            position: relative;
        }

        .search-box .form-control {
            padding-left: 45px;
            border-radius: 25px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .search-box .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .search-box .fa-search {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 5px 0 0 0;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 50px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--secondary-color);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 10px;
            }
            
            .page-header {
                padding: 20px;
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-tint me-3"></i>Blood Inventory Management</h1>
                    <p>Comprehensive blood unit tracking and management system</p>
                </div>
                <div>
                    <a href="admin.php" class="btn btn-light btn-custom">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin
                    </a>
                </div>
            </div>
        </div>

        <!-- Dashboard Summary -->
        <div class="row g-4 mb-4">
            <!-- Blood Type Summary -->
            <div class="col-12">
                <div class="dashboard-card">
                    <h4 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Blood Type Inventory Summary</h4>
                    <div class="row g-3">
                        <?php
                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
                        foreach ($bloodTypes as $type):
                            $count = $dashboardSummary['by_blood_type'][$type] ?? 0;
                            $class = 'unknown';
                            if ($count >= 10) $class = 'healthy';
                            elseif ($count >= 5) $class = 'low';
                            else $class = 'critical';
                        ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="blood-type-card <?= $class ?>">
                                <div class="stats-number"><?= $count ?></div>
                                <div class="stats-label"><?= $type ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <i class="fas fa-boxes text-success" style="font-size: 2.5rem; margin-bottom: 15px;"></i>
                    <div class="stats-number text-success"><?= $dashboardSummary['total_units'] ?></div>
                    <div class="stats-label">Total Units</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <i class="fas fa-check-circle text-info" style="font-size: 2.5rem; margin-bottom: 15px;"></i>
                    <div class="stats-number text-info"><?= $dashboardSummary['available_units'] ?></div>
                    <div class="stats-label">Available</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 2.5rem; margin-bottom: 15px;"></i>
                    <div class="stats-number text-warning"><?= count($expiringUnits) ?></div>
                    <div class="stats-label">Expiring Soon</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <i class="fas fa-times-circle text-danger" style="font-size: 2.5rem; margin-bottom: 15px;"></i>
                    <div class="stats-number text-danger"><?= $dashboardSummary['expired_units'] ?></div>
                    <div class="stats-label">Expired</div>
                </div>
            </div>
        </div>

        <!-- Expiring Units Alert -->
        <?php if (!empty($expiringUnits)): ?>
        <div class="alert-card mb-4">
            <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Units Expiring Soon (Within 5 Days)</h5>
            <div class="row g-2">
                <?php foreach (array_slice($expiringUnits, 0, 6) as $unit): ?>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center p-2 bg-warning bg-opacity-10 rounded">
                        <span><strong><?= htmlspecialchars($unit['unit_id']) ?></strong> - <?= htmlspecialchars($unit['blood_type']) ?></span>
                        <small class="text-muted"><?= date('M d', strtotime($unit['expiry_date'])) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($expiringUnits) > 6): ?>
            <div class="mt-2">
                <small class="text-muted">And <?= count($expiringUnits) - 6 ?> more units...</small>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Filter & Search Section -->
        <div class="filter-section">
            <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Filter & Search</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" class="form-select">
                        <option value="">All Blood Types</option>
                        <?php foreach ($bloodTypes as $type): ?>
                        <option value="<?= $type ?>" <?= $filters['blood_type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="available" <?= $filters['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="used" <?= $filters['status'] === 'used' ? 'selected' : '' ?>>Used</option>
                        <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="quarantined" <?= $filters['status'] === 'quarantined' ? 'selected' : '' ?>>Quarantined</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-custom">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control" placeholder="Search by Unit ID, Donor Name, or Reference Number..." value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Inventory List</h5>
                <small class="text-muted">Showing <?= count($inventory['data']) ?> of <?= $inventory['total'] ?> units</small>
            </div>
            <div>
                <?php if ($canEdit): ?>
                <button class="btn btn-success btn-custom me-2" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                    <i class="fas fa-plus me-2"></i>Add Unit
                </button>
                <?php endif; ?>
                <button class="btn btn-info btn-custom" onclick="exportToCSV()">
                    <i class="fas fa-download me-2"></i>Export CSV
                </button>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="inventory-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-barcode me-2"></i>Unit ID</th>
                            <th><i class="fas fa-tint me-2"></i>Blood Type</th>
                            <th><i class="fas fa-user me-2"></i>Donor</th>
                            <th><i class="fas fa-calendar me-2"></i>Collection Date</th>
                            <th><i class="fas fa-clock me-2"></i>Expiry Date</th>
                            <th><i class="fas fa-info-circle me-2"></i>Status</th>
                            <th><i class="fas fa-map-marker-alt me-2"></i>Storage</th>
                            <th><i class="fas fa-cogs me-2"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory['data'])): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h5>No Blood Units Found</h5>
                                    <p>No units match your current filters. Try adjusting your search criteria.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($inventory['data'] as $unit): ?>
                        <tr>
                            <td>
                                <code class="text-primary"><?= htmlspecialchars($unit['unit_id']) ?></code>
                            </td>
                            <td>
                                <span class="badge bg-danger"><?= htmlspecialchars($unit['blood_type']) ?></span>
                            </td>
                            <td>
                                <?php if ($canViewDonorInfo): ?>
                                    <div>
                                        <strong><?= htmlspecialchars($unit['donor_name']) ?></strong><br>
                                        <small class="text-muted">ID: <?= $unit['donor_id'] ?></small>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <strong>***-****-****</strong><br>
                                        <small class="text-muted">ID: <?= substr($unit['donor_id'], 0, 2) ?>***</small>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($unit['collection_date'])) ?></td>
                            <td>
                                <?php
                                $expiryDate = strtotime($unit['expiry_date']);
                                $daysLeft = ceil(($expiryDate - time()) / (60 * 60 * 24));
                                $isExpired = $daysLeft < 0;
                                $isExpiringSoon = $daysLeft <= 5 && $daysLeft >= 0;
                                ?>
                                <div>
                                    <?= date('M d, Y', $expiryDate) ?>
                                    <?php if ($isExpired): ?>
                                        <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Expired</small>
                                    <?php elseif ($isExpiringSoon): ?>
                                        <br><small class="text-warning"><i class="fas fa-clock"></i> <?= $daysLeft ?> days left</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $unit['status'] ?>">
                                    <?= ucfirst($unit['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($unit['storage_location'] ?? 'N/A') ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewUnitDetails(<?= $unit['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($canEdit): ?>
                                    <button class="btn btn-sm btn-outline-warning" onclick="updateUnitStatus(<?= $unit['id'] ?>, '<?= $unit['status'] ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($inventory['total_pages'] > 1): ?>
            <nav aria-label="Inventory pagination">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $inventory['total_pages']; $i++): ?>
                    <li class="page-item <?= $i === $filters['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Unit Modal -->
    <?php if ($canEdit): ?>
    <div class="modal fade" id="addUnitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Blood Unit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUnitForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Donor <span class="text-danger">*</span></label>
                                <select name="donor_id" class="form-select" required>
                                    <option value="">Select Donor</option>
                                    <?php foreach ($availableDonors as $donor): ?>
                                    <option value="<?= $donor['id'] ?>">
                                        <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?> 
                                        (<?= htmlspecialchars($donor['reference_code']) ?>) - <?= htmlspecialchars($donor['blood_type']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Blood Type</label>
                                <input type="text" class="form-control" value="Auto-detected from donor" readonly>
                                <small class="text-muted">Blood type will be automatically detected from the selected donor</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Collection Date <span class="text-danger">*</span></label>
                                <input type="date" name="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Collection Site</label>
                                <input type="text" name="collection_center" class="form-control" value="Main Center">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Storage Location</label>
                                <input type="text" name="storage_location" class="form-control" value="Storage A">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="addBloodUnit()">
                        <i class="fas fa-plus me-2"></i>Add Unit
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Unit Details Modal -->
    <div class="modal fade" id="unitDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Unit Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="unitDetailsContent">
                    <div class="loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading unit details...</p>
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
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Unit Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Add notes about this status change..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmStatusUpdate()">
                        <i class="fas fa-save me-2"></i>Update Status
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Add Blood Unit
        function addBloodUnit() {
            const form = document.getElementById('addUnitForm');
            const formData = new FormData(form);
            formData.append('action', 'add_unit');
            
            fetch('admin_blood_inventory_redesigned.php', {
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
            const content = document.getElementById('unitDetailsContent');
            
            // Show loading
            content.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading unit details...</p>
                </div>
            `;
            
            modal.show();
            
            // Fetch unit details
            const formData = new FormData();
            formData.append('action', 'get_unit_details');
            formData.append('unit_id', unitId);
            
            fetch('admin_blood_inventory_redesigned.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUnitDetails(data.data);
                } else {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading unit details: ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        An error occurred while loading unit details.
                    </div>
                `;
            });
        }

        // Display Unit Details
        function displayUnitDetails(unit) {
            const content = document.getElementById('unitDetailsContent');
            const canViewDonor = <?= $canViewDonorInfo ? 'true' : 'false' ?>;
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Unit Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Unit ID:</strong></td><td><code>${unit.unit_id}</code></td></tr>
                            <tr><td><strong>Blood Type:</strong></td><td><span class="badge bg-danger">${unit.blood_type}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="status-badge status-${unit.status}">${unit.status.charAt(0).toUpperCase() + unit.status.slice(1)}</span></td></tr>
                            <tr><td><strong>Collection Date:</strong></td><td>${new Date(unit.collection_date).toLocaleDateString()}</td></tr>
                            <tr><td><strong>Expiry Date:</strong></td><td>${new Date(unit.expiry_date).toLocaleDateString()}</td></tr>
                            <tr><td><strong>Collection Center:</strong></td><td>${unit.collection_center}</td></tr>
                            <tr><td><strong>Collection Staff:</strong></td><td>${unit.collection_staff}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Donor Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Donor ID:</strong></td><td>${canViewDonor ? unit.donor_id : '***'}</td></tr>
                            <tr><td><strong>Name:</strong></td><td>${canViewDonor ? unit.donor_name : '***-****-****'}</td></tr>
                            <tr><td><strong>Blood Type:</strong></td><td>${canViewDonor ? unit.donor_blood_type : '***'}</td></tr>
                            <tr><td><strong>Status:</strong></td><td>${canViewDonor ? unit.donor_status : '***'}</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-history me-2"></i>Audit Log</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>User</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${unit.audit_log ? unit.audit_log.map(log => `
                                        <tr>
                                            <td>${new Date(log.created_at).toLocaleString()}</td>
                                            <td>${log.action_type}</td>
                                            <td>${log.admin_username}</td>
                                            <td>${log.description}</td>
                                        </tr>
                                    `).join('') : '<tr><td colspan="4" class="text-center text-muted">No audit log entries</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }

        // Update Unit Status
        function updateUnitStatus(unitId, currentStatus) {
            const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            document.getElementById('updateUnitId').value = unitId;
            document.querySelector('#updateStatusForm select[name="status"]').value = currentStatus;
            modal.show();
        }

        // Confirm Status Update
        function confirmStatusUpdate() {
            const form = document.getElementById('updateStatusForm');
            const formData = new FormData(form);
            formData.append('action', 'update_status');
            
            fetch('admin_blood_inventory_redesigned.php', {
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

        // Export to CSV
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'admin_blood_inventory_redesigned.php?' + params.toString();
        }

        // Auto-refresh dashboard every 5 minutes
        setInterval(() => {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>
