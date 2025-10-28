<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/donor_history.php';
require_once __DIR__ . '/pg_compat.php';

// Initialize donor history tables
createDonorHistoryTable($pdo);

$donorId = $_GET['id'] ?? null;
$donor = null;
$donationHistory = [];
$donorStats = null;

if ($donorId) {
    // Check which table to use
    $donorsTable = 'donors';
    if (tableExists($pdo, 'donors_new')) {
        $donorsTable = 'donors_new';
    }
    
    // Get donor information
    $stmt = $pdo->prepare("SELECT * FROM " . $donorsTable . " WHERE id = ?");
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch();
    
    if ($donor) {
        // Get donation history
        $donationHistory = getDonorHistory($pdo, $donorId);
        $donorStats = getDonorStatistics($pdo, $donorId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Profile - Blood Donation System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        .donation-card { transition: transform 0.2s; }
        .donation-card:hover { transform: translateY(-2px); }
        .stats-card { border-left: 4px solid #dc3545; }
        .eligibility-badge { font-size: 0.8rem; }
    </style>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container py-5">
    <?php if ($donor): ?>
        <!-- Donor Profile Header -->
        <div class="card profile-header text-white mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2"><i class="fas fa-user-circle me-2"></i><?= htmlspecialchars($donor['full_name']) ?></h2>
                        <p class="mb-1"><strong>Reference Code:</strong> <?= htmlspecialchars($donor['reference_code']) ?></p>
                        <p class="mb-1"><strong>Blood Type:</strong> <span class="badge bg-light text-dark"><?= htmlspecialchars($donor['blood_type']) ?></span></p>
                        <p class="mb-0"><strong>Status:</strong> 
                            <span class="badge <?= $donor['status'] === 'approved' ? 'bg-success' : ($donor['status'] === 'pending' ? 'bg-warning' : 'bg-danger') ?>">
                                <?= ucfirst(htmlspecialchars($donor['status'])) ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($donorStats && $donorStats['eligible_for_next']): ?>
                            <span class="badge bg-success fs-6 eligibility-badge">
                                <i class="fas fa-check-circle me-1"></i>Eligible to Donate
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning fs-6 eligibility-badge">
                                <i class="fas fa-clock me-1"></i>Next eligible in <?= $donorStats['days_until_eligible'] ?? 0 ?> days
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donation Statistics -->
        <?php if ($donorStats): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-heartbeat text-danger mb-3" style="font-size: 2rem;"></i>
                        <h3 class="mb-1"><?= $donorStats['stats']['total_donations'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Total Donations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-tint text-danger mb-3" style="font-size: 2rem;"></i>
                        <h3 class="mb-1"><?= number_format($donorStats['stats']['total_units'] ?? 0, 1) ?></h3>
                        <p class="text-muted mb-0">Total Units</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-check text-danger mb-3" style="font-size: 2rem;"></i>
                        <h3 class="mb-1"><?= $donorStats['stats']['first_donation'] ? date('M Y', strtotime($donorStats['stats']['first_donation'])) : 'N/A' ?></h3>
                        <p class="text-muted mb-0">First Donation</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt text-danger mb-3" style="font-size: 2rem;"></i>
                        <h3 class="mb-1"><?= $donorStats['stats']['last_donation'] ? date('M Y', strtotime($donorStats['stats']['last_donation'])) : 'N/A' ?></h3>
                        <p class="text-muted mb-0">Last Donation</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Donation History -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Donation History</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($donationHistory)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Blood Type</th>
                                    <th>Units</th>
                                    <th>Center</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donationHistory as $donation): ?>
                                <tr class="donation-card">
                                    <td><?= date('M d, Y', strtotime($donation['donation_date'])) ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($donation['blood_type']) ?></span></td>
                                    <td><?= number_format($donation['units_donated'], 1) ?></td>
                                    <td><?= htmlspecialchars($donation['donation_center']) ?></td>
                                    <td>
                                        <span class="badge <?= $donation['status'] === 'completed' ? 'bg-success' : ($donation['status'] === 'deferred' ? 'bg-warning' : 'bg-danger') ?>">
                                            <?= ucfirst(htmlspecialchars($donation['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDonationDetails(<?= $donation['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                        <h5 class="text-muted">No Donation History</h5>
                        <p class="text-muted">This donor hasn't made any donations yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Blood Type Distribution Chart -->
        <?php if ($donorStats && !empty($donorStats['blood_types'])): ?>
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Blood Type Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="bloodTypeChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Donation Trend</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyTrendChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-user-slash text-muted mb-3" style="font-size: 4rem;"></i>
            <h3 class="text-muted">Donor Not Found</h3>
            <p class="text-muted">The requested donor profile could not be found.</p>
            <a href="admin.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($donorStats && !empty($donorStats['blood_types'])): ?>
<script>
// Blood Type Distribution Chart
const bloodTypeCtx = document.getElementById('bloodTypeChart').getContext('2d');
new Chart(bloodTypeCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($donorStats['blood_types'], 'blood_type')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($donorStats['blood_types'], 'count')) ?>,
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Monthly Trend Chart
const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($donorStats['monthly_trend'], 'month')) ?>,
        datasets: [{
            label: 'Donations',
            data: <?= json_encode(array_column($donorStats['monthly_trend'], 'donations')) ?>,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<script>
function viewDonationDetails(donationId) {
    // Implement donation details modal or redirect
    alert('Donation details feature coming soon!');
}
</script>

</body>
</html>