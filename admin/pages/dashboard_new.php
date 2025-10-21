<?php
// Get dashboard statistics
require_once __DIR__ . '/../includes/db.php';

// Get dashboard statistics
$stats = [
    'total_donors' => $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn(),
    'active_donors' => $pdo->query("SELECT COUNT(*) FROM donors WHERE status = 'approved'")->fetchColumn(),
    'total_requests' => $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn(),
    'pending_requests' => $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn(),
    'completed_requests' => $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'completed'")->fetchColumn(),
    'blood_inventory' => []
];

// Get blood inventory
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
foreach ($bloodTypes as $type) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE blood_type = ? AND status = 'approved' AND last_donation_date >= datetime('now', '-3 months')");
    $stmt->execute([$type]);
    $stats['blood_inventory'][$type] = $stmt->fetchColumn();
}

// Get recent donors with more details
$recentDonors = $pdo->query("
    SELECT d.*, u.name, u.email, u.phone, u.blood_type, u.city,
           (SELECT COUNT(*) FROM donations WHERE donor_id = d.id) as donation_count
    FROM donors d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.updated_at DESC 
    LIMIT 5
")->fetchAll();

// Get recent requests with donor info
$recentRequests = $pdo->query("
    SELECT r.*, u.name as requester_name, u.phone, u.blood_type, u.city,
           d.name as donor_name, d.phone as donor_phone
    FROM requests r 
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN donors d ON r.donor_id = d.id
    ORDER BY r.created_at DESC 
    LIMIT 5
")->fetchAll();
?>

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
                        <a href="?page=donors" class="text-primary text-decoration-none small">
                            View all donors <i class="fas fa-arrow-right ms-1"></i>
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
                        <a href="?page=donors&status=approved" class="text-success text-decoration-none small">
                            View active <i class="fas fa-arrow-right ms-1"></i>
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
                            Review requests <i class="fas fa-arrow-right ms-1"></i>
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
                    $textClass = $count > 5 ? 'text-success' : ($count > 2 ? 'text-warning' : 'text-danger');
                ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="card h-100 border-0">
                            <div class="card-body text-center">
                                <div class="mb-2">
                                    <span class="badge <?= $bgClass ?>-subtle <?= $textClass ?> p-2 fs-5 w-100">
                                        <?= $type ?>
                                    </span>
                                </div>
                                <h3 class="mb-0 <?= $textClass ?>"><?= $count ?></h3>
                                <small class="text-muted">Available</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Donors -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Donors</h5>
                    <a href="?page=donors" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Blood Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDonors as $donor): 
                                    $statusClass = [
                                        'pending' => 'bg-warning',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        'suspended' => 'bg-secondary'
                                    ][$donor['status']] ?? 'bg-secondary';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-2">
                                                    <div class="avatar-sm">
                                                        <div class="avatar-title bg-primary bg-opacity-10 text-primary rounded-circle fw-medium">
                                                            <?= strtoupper(substr($donor['name'], 0, 1)) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?= htmlspecialchars($donor['name']) ?></h6>
                                                    <small class="text-muted"><?= $donor['city'] ?? 'N/A' ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger bg-opacity-10 text-danger">
                                                <?= $donor['blood_type'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $statusClass ?>-subtle <?= $statusClass === 'bg-warning' ? 'text-warning' : 'text-' . str_replace('bg-', '', $statusClass) ?>">
                                                <?= ucfirst($donor['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?page=donors&action=view&id=<?= $donor['id'] ?>" class="btn btn-sm btn-light">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?page=donors&action=edit&id=<?= $donor['id'] ?>" class="btn btn-sm btn-light">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
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
                                                        <div class="avatar-title bg-info bg-opacity-10 text-info rounded-circle fw-medium">
                                                            <?= strtoupper(substr($request['requester_name'], 0, 1)) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?= htmlspecialchars($request['requester_name']) ?></h6>
                                                    <small class="text-muted"><?= $request['city'] ?? 'N/A' ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger bg-opacity-10 text-danger">
                                                <?= $request['blood_type'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $statusClass ?>-subtle <?= $statusClass === 'bg-warning' ? 'text-warning' : 'text-' . str_replace('bg-', '', $statusClass) ?>">
                                                <?= ucfirst($request['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($request['created_at'])) ?>
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

<!-- Quick Action Modal -->
<div class="modal fade" id="quickActionModal" tabindex="-1" aria-labelledby="quickActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickActionModalLabel">Quick Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="?page=donors&action=add" class="btn btn-outline-primary w-100 h-100 py-3">
                            <i class="fas fa-plus-circle fa-2x mb-2 d-block"></i>
                            Add New Donor
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="?page=donors&status=pending" class="btn btn-outline-warning w-100 h-100 py-3">
                            <i class="fas fa-user-clock fa-2x mb-2 d-block"></i>
                            Review Pending
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="?page=inventory" class="btn btn-outline-danger w-100 h-100 py-3">
                            <i class="fas fa-tint fa-2x mb-2 d-block"></i>
                            Manage Inventory
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="?page=reports" class="btn btn-outline-info w-100 h-100 py-3">
                            <i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>
                            View Reports
                        </a>
                    </div>
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
