<?php
// Get dashboard statistics
require_once __DIR__ . '/../includes/db.php';

$stats = [
    'total_donors' => $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn(),
    'active_donors' => $pdo->query("SELECT COUNT(*) FROM donors WHERE status = 'active'")->fetchColumn(),
    'total_requests' => $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn(),
    'pending_requests' => $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn(),
    'completed_requests' => $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'completed'")->fetchColumn()
];

// Get recent donors
$recentDonors = $pdo->query("SELECT * FROM donors ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get recent requests
$recentRequests = $pdo->query("
    SELECT r.*, d.full_name, d.blood_type 
    FROM requests r 
    LEFT JOIN donors d ON r.donor_id = d.id 
    ORDER BY r.created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100 bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-0">Total Donors</h6>
                        <h2 class="mb-0"><?= number_format($stats['total_donors']) ?></h2>
                    </div>
                    <i class="fas fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-0">Active Donors</h6>
                        <h2 class="mb-0"><?= number_format($stats['active_donors']) ?></h2>
                    </div>
                    <i class="fas fa-user-check fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-0">Pending Requests</h6>
                        <h2 class="mb-0"><?= number_format($stats['pending_requests']) ?></h2>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-0">Total Requests</h6>
                        <h2 class="mb-0"><?= number_format($stats['total_requests']) ?></h2>
                    </div>
                    <i class="fas fa-tint fa-2x opacity-50"></i>
                </div>
            </div>
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
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Blood Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDonors as $donor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($donor['full_name']) ?></td>
                                    <td><span class="badge bg-danger"><?= htmlspecialchars($donor['blood_type'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= $donor['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($donor['status'] ?? 'inactive') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentDonors)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No donors found</td>
                                </tr>
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
                <a href="../?page=requests" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Blood Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRequests as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars($request['patient_name'] ?? 'N/A') ?></td>
                                    <td><span class="badge bg-danger"><?= htmlspecialchars($request['blood_type_needed'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <?php 
                                        $statusClass = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'completed' => 'info'
                                        ][$request['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= ucfirst($request['status'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentRequests)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No requests found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <a href="../?page=donors&action=add" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-user-plus me-2"></i> Add Donor
                        </a>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <a href="../?page=requests&action=add" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-plus-circle me-2"></i> New Request
                        </a>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <a href="../?page=donors" class="btn btn-info btn-lg w-100">
                            <i class="fas fa-search me-2"></i> Find Donor
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../?page=reports" class="btn btn-secondary btn-lg w-100">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
