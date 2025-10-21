<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and include database connection
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/header.php';

// Check admin login
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get dashboard statistics
try {
    // Get total donors count
    $stmt = $pdo->query("SELECT COUNT(*) as total_donors FROM donors_new");
    $totalDonors = $stmt->fetch(PDO::FETCH_ASSOC)['total_donors'];
    
    // Get donors by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM donors_new GROUP BY status");
    $statusCounts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'suspended' => 0
    ];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
    // Get recent donors - MySQL compatible
    $stmt = $pdo->query("SELECT * FROM donors_new ORDER BY created_at DESC LIMIT 5");
    $recentDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get blood type distribution
    $stmt = $pdo->query("SELECT blood_type, COUNT(*) as count 
                         FROM donors_new 
                         WHERE status = 'approved' 
                         GROUP BY blood_type");
    $bloodTypeData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly registration data for the chart - MySQL compatible
    $stmt = $pdo->query("SELECT 
                            DATE_FORMAT(created_at, '%Y-%m') as month,
                            COUNT(*) as count
                         FROM donors_new 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                         ORDER BY month");
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch recent requests and monthly request stats
    // Get recent requests
    $stmt = $pdo->query("SELECT * FROM blood_requests ORDER BY request_date DESC LIMIT 5");
    $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Get monthly requests data for the chart - MySQL compatible
    $stmt = $pdo->query("SELECT DATE_FORMAT(request_date, '%Y-%m') as month, COUNT(*) as count FROM blood_requests WHERE request_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(request_date, '%Y-%m') ORDER BY month");
    $monthlyRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    $error = 'Error loading dashboard data. Please try again later.';
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Dashboard</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Overview</li>
    </ol>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($totalDonors); ?></div>
                            <div>Total Donors</div>
                        </div>
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="donors.php">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($statusCounts['approved']); ?></div>
                            <div>Active Donors</div>
                        </div>
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="donors.php?status=approved">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-dark mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($statusCounts['pending']); ?></div>
                            <div>Pending Review</div>
                        </div>
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-dark stretched-link" href="donors.php?status=pending">Review Now</a>
                    <div class="small text-dark"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo number_format($statusCounts['rejected'] + $statusCounts['suspended']); ?></div>
                            <div>Inactive Donors</div>
                        </div>
                        <i class="fas fa-user-slash fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="donors.php?status=inactive">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <!-- Monthly Registrations Chart -->
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-area me-1"></i>
                    Monthly Donor Registrations
                </div>
                <div class="card-body">
                    <canvas id="monthlyRegistrationsChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Blood Type Distribution -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Blood Type Distribution
                </div>
                <div class="card-body">
                    <canvas id="bloodTypeChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Requests -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Recent Blood Requests
            <a href="requests.php" class="btn btn-sm btn-primary float-end">View All Requests</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="recentRequestsTable">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Patient</th>
                            <th>Blood Type</th>
                            <th>Units</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequests as $req): ?>
                        <tr>
                            <td><span class="text-muted"><?php echo htmlspecialchars($req['reference_number']); ?></span></td>
                            <td><?php echo htmlspecialchars($req['patient_name']); ?></td>
                            <td><span class="badge bg-danger"><?php echo htmlspecialchars($req['blood_type_needed']); ?></span></td>
                            <td><?php echo (int)$req['units_required']; ?></td>
                            <td><span class="badge bg-info"><?php echo ucfirst($req['status']); ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($req['request_date'])); ?></td>
                            <td>
                                <a href="requests.php?action=view&id=<?php echo $req['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <a href="requests.php?action=edit&id=<?php echo $req['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Requests Analysis Chart -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    Monthly Blood Requests
                </div>
                <div class="card-body">
                    <canvas id="monthlyRequestsChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Monthly Registrations Chart
var ctx = document.getElementById('monthlyRegistrationsChart').getContext('2d');
var monthlyRegistrationsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            <?php 
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = new DateTime("first day of -$i months");
                $months[] = $date->format('M Y');
                echo "'" . $date->format('M Y') . "', ";
            }
            ?>
        ],
        datasets: [{
            label: 'New Donors',
            data: [
                <?php 
                $monthlyCounts = [];
                foreach ($months as $month) {
                    $count = 0;
                    foreach ($monthlyData as $data) {
                        $dataMonth = date('M Y', strtotime($data['month'] . '-01'));
                        if ($dataMonth === $month) {
                            $count = $data['count'];
                            break;
                        }
                    }
                    echo $count . ', ';
                }
                ?>
            ],
            backgroundColor: 'rgba(217, 35, 15, 0.1)',
            borderColor: 'rgba(217, 35, 15, 0.8)',
            borderWidth: 2,
            tension: 0.3,
            fill: true
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

// Blood Type Distribution Chart
var ctx2 = document.getElementById('bloodTypeChart').getContext('2d');
var bloodTypeChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: [
            <?php 
            foreach ($bloodTypeData as $data) {
                echo "'" . htmlspecialchars($data['blood_type']) . "', ";
            }
            ?>
        ],
        datasets: [{
            data: [
                <?php 
                foreach ($bloodTypeData as $data) {
                    echo $data['count'] . ', ';
                }
                ?>
            ],
            backgroundColor: [
                '#d9230f', '#ff6b6b', '#ff9e7d', '#ffd166',
                '#06d6a0', '#118ab2', '#073b4c', '#7209b7'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});

// Monthly Requests Chart
var ctxReq = document.getElementById('monthlyRequestsChart').getContext('2d');
var monthlyRequestsChart = new Chart(ctxReq, {
  type: 'bar',
  data: {
    labels: [
      <?php 
      $reqMonths = [];
      for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i months");
        $reqMonths[] = $date->format('M Y');
        echo "'" . $date->format('M Y') . "', ";
      }
      ?>
    ],
    datasets: [{
      label: 'Requests',
      data: [
        <?php 
        foreach ($reqMonths as $month) {
          $count = 0;
          foreach ($monthlyRequests as $data) {
            $dataMonth = date('M Y', strtotime($data['month'] . '-01'));
            if ($dataMonth === $month) {
              $count = $data['count'];
              break;
            }
          }
          echo $count . ', ';
        }
        ?>
      ],
      backgroundColor: 'rgba(33, 150, 243, 0.5)',
      borderColor: 'rgba(33, 150, 243, 1)',
      borderWidth: 1
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 } }
    }
  }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>
