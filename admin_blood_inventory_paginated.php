<?php
/**
 * Enhanced Blood Inventory Management with Server-Side Pagination
 * Blood Donation PWA System
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

// Pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$bloodType = $_GET['blood_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

// Validate per_page values
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 20;
}

// Validate sort fields
$allowedSortFields = ['created_at', 'unit_id', 'blood_type', 'collection_date', 'expiry_date', 'status'];
if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'created_at';
}

// Validate sort order
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Get filter parameters
$filters = [
    'blood_type' => $bloodType,
    'status' => $status,
    'collection_date_from' => $dateFrom,
    'collection_date_to' => $dateTo,
    'search' => $search
];

try {
    // Get inventory data with pagination
    $inventory = $inventoryManager->getInventoryPaginated($filters, $page, $perPage, $sortBy, $sortOrder);
    $totalRecords = $inventoryManager->getInventoryCount($filters);
    $totalPages = ceil($totalRecords / $perPage);
    
    // Calculate pagination info
    $offset = ($page - 1) * $perPage;
    $startRecord = $offset + 1;
    $endRecord = min($offset + $perPage, $totalRecords);
    
    // Get dashboard data
    $dashboardData = $inventoryManager->getDashboardSummary();
    $realDonors = $inventoryManager->getRealDonors(50);
    $approvedDonors = $inventoryManager->getApprovedDonors(50);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $inventory = [];
    $totalRecords = 0;
    $totalPages = 0;
    $startRecord = 0;
    $endRecord = 0;
    $dashboardData = ['summary' => [], 'expiring_units' => [], 'low_stock_alerts' => []];
    $realDonors = [];
    $approvedDonors = [];
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generate pagination links
function generatePaginationLinks($currentPage, $totalPages, $queryParams) {
    $links = [];
    $maxVisible = 5; // Maximum number of page links to show
    
    // Previous page
    if ($currentPage > 1) {
        $prevParams = $queryParams;
        $prevParams['page'] = $currentPage - 1;
        $links[] = [
            'type' => 'prev',
            'page' => $currentPage - 1,
            'url' => '?' . http_build_query($prevParams),
            'disabled' => false
        ];
    } else {
        $links[] = ['type' => 'prev', 'page' => 1, 'url' => '#', 'disabled' => true];
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - floor($maxVisible / 2));
    $endPage = min($totalPages, $startPage + $maxVisible - 1);
    
    if ($endPage - $startPage + 1 < $maxVisible) {
        $startPage = max(1, $endPage - $maxVisible + 1);
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $pageParams = $queryParams;
        $pageParams['page'] = $i;
        $links[] = [
            'type' => 'page',
            'page' => $i,
            'url' => '?' . http_build_query($pageParams),
            'active' => $i === $currentPage
        ];
    }
    
    // Next page
    if ($currentPage < $totalPages) {
        $nextParams = $queryParams;
        $nextParams['page'] = $currentPage + 1;
        $links[] = [
            'type' => 'next',
            'page' => $currentPage + 1,
            'url' => '?' . http_build_query($nextParams),
            'disabled' => false
        ];
    } else {
        $links[] = ['type' => 'next', 'page' => $totalPages, 'url' => '#', 'disabled' => true];
    }
    
    return $links;
}

$queryParams = array_filter([
    'search' => $search,
    'status' => $status,
    'blood_type' => $bloodType,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort_by' => $sortBy,
    'sort_order' => $sortOrder,
    'per_page' => $perPage
]);

$paginationLinks = generatePaginationLinks($page, $totalPages, $queryParams);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Inventory Management - Paginated</title>
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
                    <small class="d-block" style="font-size: 0.7rem; opacity: 0.8;">Server-Side Pagination</small>
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
                            <i class="fas fa-chart-line me-2 text-danger"></i>Blood Inventory Dashboard
                        </h2>
                        <p class="text-muted mb-0">Server-side paginated blood inventory management</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Last updated:</small><br>
                        <strong><?= date('M d, Y - H:i:s') ?></strong>
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
                                <small class="text-muted">Refine your blood inventory search with server-side pagination</small>
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
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Blood Type</label>
                                <select name="blood_type" class="form-select">
                                    <option value="">All Types</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $type): ?>
                                    <option value="<?= $type ?>" <?= $bloodType === $type ? 'selected' : '' ?>><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="used" <?= $status === 'used' ? 'selected' : '' ?>>Used</option>
                                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="quarantined" <?= $status === 'quarantined' ? 'selected' : '' ?>>Quarantined</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Unit ID, Donor Name..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-lg-1 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Per Page</label>
                                <select name="per_page" class="form-select">
                                    <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10</option>
                                    <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20</option>
                                    <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>
                            <div class="col-lg-1 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Sort By</label>
                                <select name="sort_by" class="form-select">
                                    <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Date</option>
                                    <option value="unit_id" <?= $sortBy === 'unit_id' ? 'selected' : '' ?>>Unit ID</option>
                                    <option value="blood_type" <?= $sortBy === 'blood_type' ? 'selected' : '' ?>>Blood Type</option>
                                    <option value="collection_date" <?= $sortBy === 'collection_date' ? 'selected' : '' ?>>Collection</option>
                                    <option value="expiry_date" <?= $sortBy === 'expiry_date' ? 'selected' : '' ?>>Expiry</option>
                                    <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
                                </select>
                            </div>
                            <div class="col-lg-1 col-md-3 col-sm-6">
                                <label class="form-label fw-medium">Order</label>
                                <select name="sort_order" class="form-select">
                                    <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Desc</option>
                                    <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Asc</option>
                                </select>
                            </div>
                            <div class="col-lg-1 col-md-3 col-sm-6">
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

        <!-- Results Summary -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <span class="text-muted">
                        Showing <strong><?= $startRecord ?></strong> to <strong><?= $endRecord ?></strong> 
                        of <strong><?= number_format($totalRecords) ?></strong> blood units
                    </span>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportData()">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshData()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
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
                                <small class="text-muted">Server-side paginated results</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-modern m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($inventory)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i>
                            <h5>No blood units found</h5>
                            <p>Try adjusting your filters or add new blood units to get started.</p>
                        </div>
                        <?php else: ?>
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
                                    <?php foreach ($inventory as $unit): ?>
                                    <tr class="status-<?= $unit['status'] ?> urgency-<?= $unit['urgency_status'] ?? 'good' ?>">
                                        <td class="px-4">
                                            <span class="unit-id-badge"><?= htmlspecialchars($unit['unit_id']) ?></span>
                                        </td>
                                        <td>
                                            <span class="blood-type-badge badge bg-danger"><?= htmlspecialchars($unit['blood_type']) ?></span>
                                        </td>
                                        <td>
                                            <div class="donor-info">
                                                <?php if ($adminRole === 'super_admin' || $adminRole === 'inventory_manager' || $adminRole === 'medical_staff'): ?>
                                                    <strong><?= htmlspecialchars($unit['donor_first_name'] ?? '***') ?> <?= htmlspecialchars($unit['donor_last_name'] ?? '***') ?></strong><br>
                                                <?php else: ?>
                                                    <strong>*** ***</strong><br>
                                                <?php endif; ?>
                                                <small class="text-muted">Ref: <?= htmlspecialchars($unit['donor_reference'] ?? 'N/A') ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= date('M d, Y', strtotime($unit['collection_date'])) ?></strong><br>
                                            <small class="text-muted"><?= date('D', strtotime($unit['collection_date'])) ?></small>
                                        </td>
                                        <td>
                                            <strong class="<?= ($unit['urgency_status'] ?? 'good') === 'expired' ? 'text-danger' : (($unit['urgency_status'] ?? 'good') === 'expiring_soon' ? 'text-warning' : '') ?>">
                                                <?= date('M d, Y', strtotime($unit['expiry_date'])) ?>
                                            </strong>
                                            <?php if (($unit['urgency_status'] ?? 'good') === 'expiring_soon'): ?>
                                                <br><small class="text-warning fw-bold">
                                                    <i class="fas fa-clock"></i> <?= $unit['days_to_expiry'] ?? 0 ?> days left
                                                </small>
                                            <?php elseif (($unit['urgency_status'] ?? 'good') === 'expired'): ?>
                                                <br><small class="text-danger fw-bold">
                                                    <i class="fas fa-times-circle"></i> Expired
                                                </small>
                                            <?php else: ?>
                                                <br><small class="text-muted"><?= $unit['days_to_expiry'] ?? 0 ?> days left</small>
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
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        Page <?= $page ?> of <?= $totalPages ?> 
                                        (<?= number_format($totalRecords) ?> total units)
                                    </small>
                                </div>
                                
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php foreach ($paginationLinks as $link): ?>
                                            <?php if ($link['type'] === 'prev'): ?>
                                                <li class="page-item <?= $link['disabled'] ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="<?= $link['url'] ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php elseif ($link['type'] === 'next'): ?>
                                                <li class="page-item <?= $link['disabled'] ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="<?= $link['url'] ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= $link['url'] ?>"><?= $link['page'] ?></a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when per_page changes
        document.getElementById('per_page').addEventListener('change', function() {
            this.form.submit();
        });

        // Auto-submit form when sort fields change
        document.getElementById('sort_by').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('sort_order').addEventListener('change', function() {
            this.form.submit();
        });

        function clearFilters() {
            const form = document.getElementById('filterForm');
            form.reset();
            window.location.href = 'admin_blood_inventory_paginated.php';
        }

        function exportData() {
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('action', 'export');
            
            // Create download link
            const exportUrl = 'admin_blood_inventory_paginated.php?' + urlParams.toString();
            window.open(exportUrl, '_blank');
        }

        function refreshData() {
            window.location.reload();
        }

        function viewUnit(unitId) {
            console.log('View unit:', unitId);
            // Implement view functionality
        }

        function editUnit(unitId) {
            console.log('Edit unit:', unitId);
            // Implement edit functionality
        }

        function issueUnit(unitId) {
            console.log('Issue unit:', unitId);
            // Implement issue functionality
        }
    </script>
</body>
</html>
