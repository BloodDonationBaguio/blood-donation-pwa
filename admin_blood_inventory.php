<?php
/**
 * Blood Inventory Management Interface
 * Philippine Red Cross Compliant
 * Data Privacy Act 2012 Compliant
 */

require_once 'db.php';
require_once __DIR__ . '/admin/includes/admin_auth.php';
require_once 'includes/BloodInventoryManager.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Initialize Blood Inventory Manager
$adminId = $_SESSION['admin_id'] ?? 1;
$adminName = $_SESSION['admin_username'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'viewer';

$inventoryManager = new BloodInventoryManager($pdo, $adminId, $adminName, $adminRole);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_unit':
                $unitId = $inventoryManager->createBloodUnit($_POST);
                echo json_encode(['success' => true, 'unit_id' => $unitId]);
                break;
                
            case 'update_unit':
                $inventoryManager->updateBloodUnit($_POST['unit_id'], $_POST);
                echo json_encode(['success' => true]);
                break;
                
            case 'update_test_results':
                $testResults = json_decode($_POST['test_results'], true);
                $inventoryManager->updateTestResults($_POST['unit_id'], $testResults, $_POST['screening_status']);
                echo json_encode(['success' => true]);
                break;
                
            case 'issue_blood':
                $unitId = $inventoryManager->issueBloodUnit($_POST['blood_type'], $_POST);
                echo json_encode(['success' => true, 'unit_id' => $unitId]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get filter parameters
$filters = [
    'blood_type' => $_GET['blood_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'collection_date_from' => $_GET['date_from'] ?? '',
    'collection_date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? '',
    'show_test' => $_GET['show_test'] ?? '1' // Show test data by default
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);

// Validate per_page options
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 20;
}

// Get inventory data and real donors
try {
    $inventory = $inventoryManager->getInventory($filters, $page, $perPage);
    $dashboardData = $inventoryManager->getDashboardSummary();
    $realDonors = $inventoryManager->getRealDonors(50);
    $approvedDonors = $inventoryManager->getApprovedDonors(50);
    
    // Get total count for pagination
    $totalRecords = $inventoryManager->getInventoryCount($filters);
    $totalPages = ceil($totalRecords / $perPage);
    
    // Calculate pagination info
    $offset = ($page - 1) * $perPage;
    $startRecord = $offset + 1;
    $endRecord = min($offset + $perPage, $totalRecords);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $inventory = [];
    $dashboardData = ['summary' => [], 'expiring_units' => [], 'low_stock_alerts' => []];
    $realDonors = [];
    $approvedDonors = [];
    $totalRecords = 0;
    $totalPages = 0;
    $startRecord = 0;
    $endRecord = 0;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Inventory Management - Philippine Red Cross</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prc-red: #dc2626;
            --prc-dark-red: #b91c1c;
            --success-green: #059669;
            --warning-orange: #d97706;
            --info-blue: #0284c7;
        }
        
        body { background-color: #f8fafc; }
        
        .navbar-brand { font-weight: 600; }
        
        .status-available { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%) !important;
            border-left: 4px solid var(--success-green) !important;
        }
        .status-used { 
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
            border-left: 4px solid var(--info-blue) !important;
        }
        .status-expired { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%) !important;
            border-left: 4px solid var(--prc-red) !important;
        }
        .status-quarantined { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%) !important;
            border-left: 4px solid var(--warning-orange) !important;
        }
        
        .urgency-expiring_soon { 
            box-shadow: 0 0 0 2px #fbbf24 !important;
            animation: pulse-warning 2s infinite;
        }
        .urgency-expired { 
            box-shadow: 0 0 0 2px var(--prc-red) !important;
            animation: pulse-danger 2s infinite;
        }
        
        @keyframes pulse-warning {
            0%, 100% { box-shadow: 0 0 0 2px #fbbf24; }
            50% { box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.3); }
        }
        @keyframes pulse-danger {
            0%, 100% { box-shadow: 0 0 0 2px var(--prc-red); }
            50% { box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.3); }
        }
        
        .low-stock { 
            color: var(--prc-red); 
            font-weight: bold;
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.5; }
        }
        
        .dashboard-card { 
            border: none; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .blood-type-badge {
            font-size: 1.1rem;
            font-weight: 700;
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .unit-id-badge {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .donor-info {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.85rem;
        }
        
        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .table-modern thead {
            background: linear-gradient(135deg, var(--prc-red) 0%, var(--prc-dark-red) 100%);
        }
        
        .table-modern tbody tr {
            transition: all 0.2s ease;
        }
        .table-modern tbody tr:hover {
            background-color: #f8fafc !important;
            transform: scale(1.01);
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .alert-modern {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn-modern {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-modern:hover {
            transform: translateY(-1px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container-fluid fade-in">
        <!-- Modern Header -->
        <nav class="navbar navbar-expand-lg navbar-dark mb-4" style="background: linear-gradient(135deg, var(--prc-red) 0%, var(--prc-dark-red) 100%); border-radius: 0 0 16px 16px;">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <i class="fas fa-tint me-2"></i>Blood Inventory Management
                    <small class="d-block" style="font-size: 0.7rem; opacity: 0.8;">Philippine Red Cross System</small>
                </a>
                <div class="navbar-nav ms-auto d-flex align-items-center">
                    <div class="me-4">
                        <small class="text-white-50">Logged in as</small><br>
                        <span class="fw-bold">
                            <i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($adminName) ?>
                        </span>
                        <small class="badge bg-light text-dark ms-2"><?= htmlspecialchars($adminRole) ?></small>
                    </div>
                    <a class="btn btn-outline-light btn-modern" href="admin.php">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin
                    </a>
                </div>
            </div>
        </nav>

        <!-- Dashboard Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-chart-line me-2 text-danger"></i>Dashboard Overview
                        </h2>
                        <p class="text-muted mb-0">Real-time blood inventory monitoring and management</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Last updated:</small><br>
                        <strong><?= date('M d, Y - H:i:s') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Data Toggle -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info alert-modern">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Showing:</strong> 
                            <?php if ($filters['show_test'] == '1'): ?>
                                All blood units (including test data) - <?= $totalRecords ?> total
                            <?php else: ?>
                                Real blood units only - <?= $totalRecords ?> total
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="?show_test=1<?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['show_test' => ''])) : '' ?>" 
                               class="btn btn-sm <?= $filters['show_test'] == '1' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                Show All
                            </a>
                            <a href="?show_test=0<?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['show_test' => ''])) : '' ?>" 
                               class="btn btn-sm <?= $filters['show_test'] == '0' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                Real Only
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modern Blood Type Summary Cards -->
        <div class="row mb-4">
            <?php if (empty($dashboardData['summary'])): ?>
                <div class="col-12">
                    <div class="alert alert-info alert-modern text-center">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <h5>No Blood Units Found</h5>
                        <p class="mb-0">Start by adding blood units to see the inventory summary.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($dashboardData['summary'] as $summary): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <span class="blood-type-badge badge bg-danger"><?= htmlspecialchars($summary['blood_type']) ?></span>
                            </div>
                            
                            <div class="row g-0 mb-3">
                                <div class="col-6">
                                    <div class="stats-number text-success"><?= $summary['available_units'] ?></div>
                                    <small class="text-muted fw-medium">Available</small>
                                </div>
                                <div class="col-6">
                                    <div class="stats-number text-primary"><?= $summary['total_units'] ?></div>
                                    <small class="text-muted fw-medium">Total</small>
                                </div>
                            </div>
                            
                            <div class="row g-0 text-center">
                                <div class="col-4">
                                    <small class="text-info fw-bold"><?= $summary['used_units'] ?></small>
                                    <br><small class="text-muted" style="font-size: 0.7rem;">Used</small>
                                </div>
                                <div class="col-4">
                                    <small class="text-danger fw-bold"><?= $summary['expired_units'] ?></small>
                                    <br><small class="text-muted" style="font-size: 0.7rem;">Expired</small>
                                </div>
                                <div class="col-4">
                                    <small class="text-warning fw-bold"><?= $summary['quarantined_units'] ?></small>
                                    <br><small class="text-muted" style="font-size: 0.7rem;">Quarantined</small>
                                </div>
                            </div>
                            
                            <?php if ($summary['available_units'] < 5): ?>
                            <div class="mt-3">
                                <span class="badge bg-danger low-stock">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Low Stock Alert
                                </span>
                            </div>
                            <?php elseif ($summary['available_units'] < 10): ?>
                            <div class="mt-3">
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-exclamation-circle me-1"></i>Running Low
                                </span>
                            </div>
                            <?php else: ?>
                            <div class="mt-3">
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>Good Stock
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Modern Alerts Section -->
        <?php if (!empty($dashboardData['low_stock_alerts']) || !empty($dashboardData['expiring_units'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning alert-modern border-0">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">System Alerts</h5>
                            <small class="text-muted">Immediate attention required</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php if (!empty($dashboardData['low_stock_alerts'])): ?>
                        <div class="col-md-6">
                            <div class="bg-light rounded p-3">
                                <h6 class="text-danger mb-2">
                                    <i class="fas fa-arrow-down me-2"></i>Low Stock Alert
                                </h6>
                                <?php foreach ($dashboardData['low_stock_alerts'] as $alert): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge bg-danger"><?= $alert['blood_type'] ?></span>
                                        <small class="text-danger fw-bold"><?= $alert['available_units'] ?> units remaining</small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($dashboardData['expiring_units'])): ?>
                        <div class="col-md-6">
                            <div class="bg-light rounded p-3">
                                <h6 class="text-warning mb-2">
                                    <i class="fas fa-clock me-2"></i>Expiring Soon
                                </h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Units expiring in 5 days</span>
                                    <span class="badge bg-warning text-dark fs-6"><?= count($dashboardData['expiring_units']) ?> units</span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modern Filters and Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="mb-1">
                                    <i class="fas fa-filter me-2 text-primary"></i>Advanced Filters
                                </h5>
                                <small class="text-muted">Refine your blood inventory search</small>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($adminRole === 'super_admin' || $adminRole === 'inventory_manager'): ?>
                                <button class="btn btn-success btn-modern" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                                    <i class="fas fa-plus me-2"></i>Add Blood Unit
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-modern" onclick="clearFilters()">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </button>
                            </div>
                        </div>
                        
                        <form method="GET" class="row g-3" id="filterForm">
                            <input type="hidden" name="show_test" value="<?= $filters['show_test'] ?>">
                            <input type="hidden" name="page" value="1">
                            
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Blood Type</label>
                                <select name="blood_type" class="form-select">
                                    <option value="">All Types</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $type): ?>
                                    <option value="<?= $type ?>" <?= $filters['blood_type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="available" <?= $filters['status'] === 'available' ? 'selected' : '' ?>>
                                        <i class="fas fa-check-circle"></i> Available
                                    </option>
                                    <option value="used" <?= $filters['status'] === 'used' ? 'selected' : '' ?>>
                                        <i class="fas fa-arrow-right"></i> Used
                                    </option>
                                    <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>
                                        <i class="fas fa-times-circle"></i> Expired
                                    </option>
                                    <option value="quarantined" <?= $filters['status'] === 'quarantined' ? 'selected' : '' ?>>
                                        <i class="fas fa-exclamation-triangle"></i> Quarantined
                                    </option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['collection_date_from']) ?>">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['collection_date_to']) ?>">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Per Page</label>
                                <select name="per_page" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($allowedPerPage as $option): ?>
                                    <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label fw-medium">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Unit ID, Donor Reference, etc." value="<?= htmlspecialchars($filters['search']) ?>">
                            </div>
                            <div class="col-lg-1 col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-modern w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modern Inventory Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    <i class="fas fa-table me-2 text-primary"></i>Blood Inventory
                                </h5>
                                <small class="text-muted">
                                    Showing <strong><?= $startRecord ?>-<?= $endRecord ?></strong> of <strong><?= number_format($totalRecords) ?></strong> blood units
                                    <?php if ($totalPages > 1): ?>
                                        (Page <?= $page ?> of <?= $totalPages ?>)
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm" onclick="exportData()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="refreshData()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-modern m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-modern table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="px-4">Unit ID</th>
                                        <th>Blood Type</th>
                                        <th>Donor Info</th>
                                        <th>Collection Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inventory)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                                                <h5>No blood units found</h5>
                                                <p>Try adjusting your filters or add new blood units to get started.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($inventory as $unit): ?>
                                    <tr class="status-<?= $unit['status'] ?> urgency-<?= $unit['urgency_status'] ?>">
                                        <td class="px-4">
                                            <span class="unit-id-badge"><?= htmlspecialchars($unit['unit_id']) ?></span>
                                        </td>
                                        <td>
                                            <span class="blood-type-badge badge bg-danger"><?= htmlspecialchars($unit['blood_type']) ?></span>
                                            <?php if (isset($unit['donor_blood_type']) && $unit['blood_type'] !== $unit['donor_blood_type']): ?>
                                                <br><small class="text-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Donor: <?= $unit['donor_blood_type'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="donor-info">
                                                <?php if ($adminRole === 'super_admin' || $adminRole === 'inventory_manager' || $adminRole === 'medical_staff'): ?>
                                                    <strong><?= htmlspecialchars($unit['first_name'] ?? '***') ?> <?= htmlspecialchars($unit['last_name'] ?? '***') ?></strong><br>
                                                <?php else: ?>
                                                    <strong>*** ***</strong><br>
                                                <?php endif; ?>
                                                <small class="text-muted">Ref: <?= htmlspecialchars($unit['reference_code'] ?? 'N/A') ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= date('M d, Y', strtotime($unit['collection_date'])) ?></strong><br>
                                            <small class="text-muted"><?= date('D', strtotime($unit['collection_date'])) ?></small>
                                        </td>
                                        <td>
                                            <strong class="<?= $unit['urgency_status'] === 'expired' ? 'text-danger' : ($unit['urgency_status'] === 'expiring_soon' ? 'text-warning' : '') ?>">
                                                <?= date('M d, Y', strtotime($unit['expiry_date'])) ?>
                                            </strong>
                                            <?php if ($unit['urgency_status'] === 'expiring_soon'): ?>
                                                <br><small class="text-warning fw-bold">
                                                    <i class="fas fa-clock"></i> <?= $unit['days_to_expire'] ?> days left
                                                </small>
                                            <?php elseif ($unit['urgency_status'] === 'expired'): ?>
                                                <br><small class="text-danger fw-bold">
                                                    <i class="fas fa-times-circle"></i> Expired
                                                </small>
                                            <?php else: ?>
                                                <br><small class="text-muted"><?= $unit['days_to_expire'] ?> days left</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusConfig = [
                                                'available' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Available'],
                                                'used' => ['class' => 'info', 'icon' => 'arrow-right', 'text' => 'Used'],
                                                'expired' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Expired'],
                                                'quarantined' => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'text' => 'Quarantined']
                                            ];
                                            $config = $statusConfig[$unit['status']] ?? ['class' => 'secondary', 'icon' => 'question', 'text' => 'Unknown'];
                                            ?>
                                            <span class="badge bg-<?= $config['class'] ?> d-flex align-items-center gap-1" style="width: fit-content;">
                                                <i class="fas fa-<?= $config['icon'] ?>"></i>
                                                <?= $config['text'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($unit['storage_location'] ?? 'Not Set') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary btn-modern" onclick="viewUnit('<?= $unit['unit_id'] ?>')" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($adminRole === 'super_admin' || $adminRole === 'inventory_manager' || $adminRole === 'medical_staff'): ?>
                                                <button class="btn btn-sm btn-outline-warning btn-modern" onclick="editUnit('<?= $unit['unit_id'] ?>')" title="Edit Unit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($unit['status'] === 'available' && ($adminRole === 'super_admin' || $adminRole === 'inventory_manager')): ?>
                                                <button class="btn btn-sm btn-outline-success btn-modern" onclick="issueUnit('<?= $unit['unit_id'] ?>')" title="Issue Unit">
                                                    <i class="fas fa-paper-plane"></i>
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="row mt-4">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Page -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildPaginationUrl(1) ?>" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildPaginationUrl($page - 1) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildPaginationUrl($page + 1) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildPaginationUrl($totalPages) ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Unit Modal -->
    <?php if ($adminRole === 'super_admin' || $adminRole === 'inventory_manager'): ?>
    <div class="modal fade" id="addUnitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Blood Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addUnitForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Select Donor</label>
                            <?php if (empty($approvedDonors)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>No approved donors available</strong><br>
                                    Blood units can only be created for real donors who have registered through this website and been approved.
                                    <hr>
                                    <small>To create blood units:</small>
                                    <ol class="mb-0" style="font-size: 0.9rem;">
                                        <li>Donors must register via the website</li>
                                        <li>Admin must approve the donor</li>
                                        <li>Then blood units can be created</li>
                                    </ol>
                                </div>
                                <input type="hidden" name="donor_id" value="">
                            <?php else: ?>
                                <select name="donor_id" class="form-select" required>
                                    <option value="">Choose an approved donor...</option>
                                    <?php foreach ($approvedDonors as $donor): ?>
                                        <option value="<?= $donor['id'] ?>" data-blood-type="<?= $donor['blood_type'] ?>">
                                            <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?> 
                                            (<?= htmlspecialchars($donor['reference_code']) ?>) - 
                                            <?= $donor['blood_type'] ?> - 
                                            Status: <?= ucfirst($donor['status']) ?>
                                            <?php if ($donor['last_donation_date']): ?>
                                                - Last donated: <?= date('M d, Y', strtotime($donor['last_donation_date'])) ?>
                                            <?php else: ?>
                                                - First time donor
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    Only showing approved donors who registered through this website
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Blood Type</label>
                            <select name="blood_type" class="form-select" required>
                                <option value="">Select Blood Type</option>
                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Collection Date</label>
                            <input type="date" name="collection_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Volume (mL)</label>
                            <input type="number" name="volume_ml" class="form-control" value="450" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Collection Site</label>
                            <input type="text" name="collection_site" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Storage Location</label>
                            <input type="text" name="storage_location" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Unit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modern JavaScript functionality
        
        // Add Unit Form
        document.getElementById('addUnitForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            formData.append('action', 'create_unit');
            
            fetch('admin_blood_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Blood unit added successfully! Unit ID: ' + data.unit_id);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('error', 'Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An error occurred while adding the blood unit.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Notification system
        function showNotification(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // View Unit Details
        function viewUnit(unitId) {
            // Create and show modal with unit details
            fetch(`get_blood_unit.php?unit_id=${unitId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showUnitDetailsModal(data.unit);
                    } else {
                        showNotification('error', 'Failed to load unit details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('error', 'Error loading unit details');
                });
        }
        
        // Show Unit Details Modal
        function showUnitDetailsModal(unit) {
            // Create modal HTML
            const modalHtml = `
                <div class="modal fade" id="unitDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-tint me-2"></i>Blood Unit Details
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Unit Information</h6>
                                        <table class="table table-sm">
                                            <tr><td><strong>Unit ID:</strong></td><td><code>${unit.unit_id}</code></td></tr>
                                            <tr><td><strong>Blood Type:</strong></td><td><span class="badge bg-danger">${unit.blood_type}</span></td></tr>
                                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-${getStatusClass(unit.status)}">${unit.status}</span></td></tr>
                                            <tr><td><strong>Collection Date:</strong></td><td>${unit.collection_date}</td></tr>
                                            <tr><td><strong>Expiry Date:</strong></td><td>${unit.expiry_date}</td></tr>
                                            <tr><td><strong>Collection Site:</strong></td><td>${unit.collection_site}</td></tr>
                                            <tr><td><strong>Storage Location:</strong></td><td>${unit.storage_location}</td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Donor Information</h6>
                                        <table class="table table-sm">
                                            <tr><td><strong>Name:</strong></td><td>${unit.donor_info.name}</td></tr>
                                            <tr><td><strong>Reference Code:</strong></td><td><code>${unit.donor_info.reference_code}</code></td></tr>
                                            <tr><td><strong>Email:</strong></td><td>${unit.donor_info.email}</td></tr>
                                            <tr><td><strong>Phone:</strong></td><td>${unit.donor_info.phone}</td></tr>
                                        </table>
                                    </div>
                                </div>
                                ${unit.notes ? `<div class="mt-3"><h6 class="text-primary">Notes:</h6><p class="text-muted">${unit.notes}</p></div>` : ''}
                                <div class="mt-3">
                                    <small class="text-muted">
                                        Created: ${unit.created_at} | Updated: ${unit.updated_at}
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('unitDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('unitDetailsModal'));
            modal.show();
            
            // Remove modal from DOM when hidden
            document.getElementById('unitDetailsModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
        
        // Get status class for badge
        function getStatusClass(status) {
            const statusClasses = {
                'Available': 'success',
                'Used': 'info',
                'Expired': 'danger',
                'Quarantined': 'warning'
            };
            return statusClasses[status] || 'secondary';
        }
        
        // Edit Unit
        function editUnit(unitId) {
            // Implementation for editing unit
            showNotification('info', 'Edit functionality coming soon for unit: ' + unitId);
        }
        
        // Issue Unit
        function issueUnit(unitId) {
            if (confirm('Are you sure you want to issue this blood unit?')) {
                showNotification('info', 'Issue functionality coming soon for unit: ' + unitId);
            }
        }
        
        // Clear Filters
        function clearFilters() {
            const form = document.getElementById('filterForm');
            form.reset();
            window.location.href = 'admin_blood_inventory.php';
        }
        
        // Export Data
        function exportData() {
            showNotification('info', 'Export functionality coming soon');
        }
        
        // Refresh Data
        function refreshData() {
            location.reload();
        }
        
        // Auto-refresh every 5 minutes
        setInterval(function() {
            const lastUpdate = document.querySelector('[data-last-update]');
            if (lastUpdate) {
                lastUpdate.textContent = new Date().toLocaleString();
            }
        }, 300000);
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>

<?php
/**
 * Build pagination URL with current parameters
 */
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
