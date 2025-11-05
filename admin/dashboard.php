<?php
// Standalone Admin Dashboard (no header/footer/page includes)
// Auth + DB only to ensure functionality
require_once __DIR__ . '/includes/admin_auth.php';
requireAdminLogin();
require_once __DIR__ . '/includes/db.php';

// Dashboard queries (inline so this file is self-contained)
// Basic stats
$stats = [
    'total_donors' => 0,
    'active_donors' => 0,
    'total_requests' => 0,
    'pending_requests' => 0,
    'completed_requests' => 0,
    'blood_inventory' => []
];

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

try {
    // Counts with safe fallbacks
    $stats['total_donors'] = (int)$pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
    $stats['active_donors'] = (int)$pdo->query("SELECT COUNT(*) FROM donors WHERE status = 'approved'")->fetchColumn();
    $stats['total_requests'] = (int)$pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
    $stats['pending_requests'] = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn();
    $stats['completed_requests'] = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'completed'")->fetchColumn();

    foreach ($bloodTypes as $type) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE blood_type = ? AND status = 'approved' AND last_donation_date >= datetime('now', '-3 months')");
        $stmt->execute([$type]);
        $stats['blood_inventory'][$type] = (int)$stmt->fetchColumn();
    }
} catch (Throwable $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
}

// Recent donors
$recentDonors = [];
try {
    $recentDonors = $pdo->query(
        "SELECT d.*, u.name, u.email, u.phone, u.blood_type, u.city,
               (SELECT COUNT(*) FROM donations WHERE donor_id = d.id) as donation_count
         FROM donors d 
         JOIN users u ON d.user_id = u.id 
         ORDER BY d.updated_at DESC 
         LIMIT 5"
    )->fetchAll();
} catch (Throwable $e) { error_log('Recent donors error: ' . $e->getMessage()); }

// Recent requests
$recentRequests = [];
try {
    $recentRequests = $pdo->query(
        "SELECT r.*, u.name as requester_name, u.phone, u.blood_type, u.city,
               d.name as donor_name, d.phone as donor_phone
         FROM requests r 
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN donors d ON r.donor_id = d.id
         ORDER BY r.created_at DESC 
         LIMIT 5"
    )->fetchAll();
} catch (Throwable $e) { error_log('Recent requests error: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - Blood Donation System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <!-- Site CSS -->
    <link href="../css/style.css" rel="stylesheet" />
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../">
                <i class="fas fa-heartbeat me-2"></i>Blood Donation Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../?page=donors">
                            <i class="fas fa-users me-1"></i> Donors
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="../?page=settings">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../?page=donors">
                                <i class="fas fa-users me-2"></i> Donors
                                <span class="badge bg-primary rounded-pill ms-2"><?= function_exists('getDonorCount') ? getDonorCount() : 0; ?></span>
                            </a>
                        </li>
                    </ul>
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Account</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link" href="../?page=settings">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="container-fluid px-4">
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Dashboard Overview</h1>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary">
                                <i class="fas fa-download me-1"></i> Export Report
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickActionModal">
                                <i class="fas fa-bolt me-1"></i> Quick Actions
                            </button>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row g-4 mb-4">
                        <!-- Total Donors -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card bg-primary bg-opacity-10 border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase text-muted mb-1">Total Donors</h6>
                                            <h2 class="mb-0"><?= number_format($stats['total_donors']) ?></h2>
                                        </div>
                                        <div class="bg-primary bg-opacity-25 p-3 rounded-3">
                                            <i class="fas fa-users fs-2 text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="../?page=donors" class="text-primary text-decoration-none small">
                                            View Details <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Donors -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card bg-success bg-opacity-10 border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase text-muted mb-1">Active Donors</h6>
                                            <h2 class="mb-0"><?= number_format($stats['active_donors']) ?></h2>
                                        </div>
                                        <div class="bg-success bg-opacity-25 p-3 rounded-3">
                                            <i class="fas fa-user-check fs-2 text-success"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="../?page=donors&status=approved" class="text-success text-decoration-none small">
                                            View Details <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Requests -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card bg-warning bg-opacity-10 border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase text-muted mb-1">Pending Requests</h6>
                                            <h2 class="mb-0"><?= number_format($stats['pending_requests']) ?></h2>
                                        </div>
                                        <div class="bg-warning bg-opacity-25 p-3 rounded-3">
                                            <i class="fas fa-clock fs-2 text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="?page=requests&status=pending" class="text-warning text-decoration-none small">
                                            Review Now <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Completed Requests -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card bg-info bg-opacity-10 border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase text-muted mb-1">Completed</h6>
                                            <h2 class="mb-0"><?= number_format($stats['completed_requests']) ?></h2>
                                        </div>
                                        <div class="bg-info bg-opacity-25 p-3 rounded-3">
                                            <i class="fas fa-check-circle fs-2 text-info"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="?page=requests&status=completed" class="text-info text-decoration-none small">
                                            View history <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Blood Inventory -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0">Blood Inventory</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($bloodTypes as $type): 
                                    $count = $stats['blood_inventory'][$type] ?? 0;
                                    $bgClass = $count > 5 ? 'bg-success' : ($count > 2 ? 'bg-warning' : 'bg-danger');
                                ?>
                                <div class="col-6 col-md-3">
                                    <div class="card <?= $bgClass ?> bg-opacity-10 border-0">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="text-uppercase text-muted mb-1"><?= htmlspecialchars($type) ?></h6>
                                                    <h3 class="mb-0"><?= number_format($count) ?></h3>
                                                </div>
                                                <div class="p-2 rounded-3 <?= $bgClass ?> bg-opacity-25">
                                                    <i class="fas fa-tint fs-3"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Recent Donors -->
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Donors</h5>
                                    <a href="../?page=donors" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Blood Type</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentDonors as $donor): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <div class="avatar-sm">
                                                                    <span class="avatar-title bg-primary bg-opacity-10 text-primary rounded-circle">
                                                                        <i class="fas fa-user"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <div class="fw-semibold"><?= htmlspecialchars($donor['name'] ?? 'Unknown') ?></div>
                                                                <div class="text-muted small"><?= htmlspecialchars($donor['email'] ?? '') ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($donor['blood_type'] ?? '-') ?></td>
                                                    <td><span class="badge bg-success">Approved</span></td>
                                                    <td><?= !empty($donor['updated_at']) ? date('M d, Y', strtotime($donor['updated_at'])) : '-' ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($recentDonors)): ?>
                                                    <tr><td colspan="4" class="text-center text-muted py-4">No recent donors found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Requests -->
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Blood Requests</h5>
                                    <a href="?page=requests" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Requester</th>
                                                    <th>Blood Type</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentRequests as $request): 
                                                    $statusClass = [
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger',
                                                        'completed' => 'bg-info'
                                                    ][$request['status']] ?? 'bg-secondary';
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-2">
                                                                    <div class="avatar-sm">
                                                                        <span class="avatar-title bg-primary bg-opacity-10 text-primary rounded-circle">
                                                                            <i class="fas fa-user"></i>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <div class="fw-semibold"><?= htmlspecialchars($request['requester_name'] ?? 'Unknown') ?></div>
                                                                    <div class="text-muted small"><?= htmlspecialchars($request['city'] ?? '') ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($request['blood_type'] ?? '-') ?></td>
                                                        <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($request['status'] ?? '-') ?></span></td>
                                                        <td><?= !empty($request['created_at']) ? date('M d, Y', strtotime($request['created_at'])) : '-' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($recentRequests)): ?>
                                                    <tr><td colspan="4" class="text-center text-muted py-4">No recent requests found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card border-0 shadow-sm mb-5">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <a href="../?page=donors" class="btn btn-outline-primary w-100 h-100 py-3">
                                        <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                        View Donors
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="?page=requests&status=pending" class="btn btn-outline-warning w-100 h-100 py-3">
                                        <i class="fas fa-user-clock fa-2x mb-2 d-block"></i>
                                        Review Pending
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="?page=inventory" class="btn btn-outline-danger w-100 h-100 py-3">
                                        <i class="fas fa-tint fa-2x mb-2 d-block"></i>
                                        Manage Inventory
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="?page=reports" class="btn btn-outline-info w-100 h-100 py-3">
                                        <i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>
                                        View Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                .avatar-sm {
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .avatar-title {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                </style>

                <!-- Footer -->
                <footer class="footer mt-auto py-3 bg-light">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12 text-center">
                                <span class="text-muted">
                                    &copy; <?= date('Y') ?> Blood Donation System. All rights reserved.
                                </span>
                            </div>
                        </div>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
