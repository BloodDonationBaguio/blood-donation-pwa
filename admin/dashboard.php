<?php
// Enable error reporting (non-fatal for production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session and include database connection
require_once __DIR__ . '/includes/admin_auth.php';
$page = 'dashboard';
require_once __DIR__ . '/includes/header.php';

// Check admin login
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Helper: detect driver and month format expressions
$driver = 'pgsql';
try { $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); } catch (Throwable $e) {}
$monthExpr = function($column) use ($driver) {
    if ($driver === 'mysql') return "DATE_FORMAT($column, '%Y-%m')";
    if ($driver === 'sqlite') return "strftime('%Y-%m', $column)";
    return "TO_CHAR($column, 'YYYY-MM')"; // pgsql default
};

// Get dashboard statistics
try {
    // Resolve donors table dynamically
    $donorsTable = null;
    if (function_exists('tableExists')) {
        if (tableExists($pdo, 'donors_new')) $donorsTable = 'donors_new';
        elseif (tableExists($pdo, 'donors')) $donorsTable = 'donors';
    } else {
        $donorsTable = 'donors';
    }

    // Resolve requests table dynamically
    $requestsTable = null;
    if (function_exists('tableExists')) {
        if (tableExists($pdo, 'blood_requests')) $requestsTable = 'blood_requests';
        elseif (tableExists($pdo, 'requests')) $requestsTable = 'requests';
    } else {
        $requestsTable = 'blood_requests';
    }

    // Defaults
    $totalDonors = 0;
    $statusCounts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'suspended' => 0,
        'active' => 0
    ];
    $recentDonors = [];
    $bloodTypeData = [];
    $monthlyData = [];
    $recentRequests = [];
    $monthlyRequests = [];

    // Donor metrics if table available
    if ($donorsTable) {
        // Total donors
        $totalDonors = (int)$pdo->query("SELECT COUNT(*) FROM {$donorsTable}")->fetchColumn();

        // Status breakdown (normalize label variants)
        $stmt = $pdo->query("SELECT COALESCE(status,'') AS status, COUNT(*) AS count FROM {$donorsTable} GROUP BY COALESCE(status,'')");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($raw as $row) {
            $s = strtolower(trim($row['status']));
            $c = (int)$row['count'];
            if (in_array($s, ['approved','active'], true)) $statusCounts['approved'] += $c; // show as approved/active
            if (in_array($s, ['active'], true)) $statusCounts['active'] += $c;
            if (in_array($s, ['pending','new','submitted','awaiting_review','in_review',''], true)) $statusCounts['pending'] += $c;
            if (in_array($s, ['rejected','denied'], true)) $statusCounts['rejected'] += $c;
            if (in_array($s, ['suspended','inactive'], true)) $statusCounts['suspended'] += $c;
        }

        // Determine created column
        $createdCol = 'created_at';
        try {
            if (function_exists('getTableStructure')) {
                $cols = getTableStructure($pdo, $donorsTable);
                $names = array_map(function($c){ return strtolower($c['column_name'] ?? ($c['name'] ?? '')); }, $cols);
                if (!in_array('created_at', $names, true)) {
                    if (in_array('created', $names, true)) $createdCol = 'created';
                }
            }
        } catch (Throwable $e) {}

        // Recent donors
        $stmt = $pdo->query("SELECT * FROM {$donorsTable} ORDER BY {$createdCol} DESC LIMIT 5");
        $recentDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Blood type distribution (approved/active)
        $stmt = $pdo->query("SELECT blood_type, COUNT(*) AS count FROM {$donorsTable} WHERE COALESCE(status,'pending') IN ('approved','active') GROUP BY blood_type");
        $bloodTypeData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Monthly donor registrations (last 6 months)
        $monthExprDonor = $monthExpr($createdCol);
        $interval = ($driver === 'mysql') ? "INTERVAL 6 MONTH" : "INTERVAL '6 months'";
        $whereRecent = ($driver === 'sqlite') ? "$createdCol >= datetime('now','-6 months')" : "$createdCol >= CURRENT_TIMESTAMP - $interval";
        $sql = "SELECT $monthExprDonor AS month, COUNT(*) AS count FROM {$donorsTable} WHERE $whereRecent GROUP BY $monthExprDonor ORDER BY month";
        $monthlyData = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Requests metrics if table available
    if ($requestsTable) {
        // Determine request date column
        $requestDateCol = 'request_date';
        try {
            if (function_exists('getTableStructure')) {
                $cols = getTableStructure($pdo, $requestsTable);
                $names = array_map(function($c){ return strtolower($c['column_name'] ?? ($c['name'] ?? '')); }, $cols);
                if (!in_array('request_date', $names, true)) {
                    if (in_array('created_at', $names, true)) $requestDateCol = 'created_at';
                    elseif (in_array('created', $names, true)) $requestDateCol = 'created';
                }
            }
        } catch (Throwable $e) {}

        // Recent requests
        $recentRequests = $pdo->query("SELECT * FROM {$requestsTable} ORDER BY {$requestDateCol} DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        // Monthly requests (last 6 months)
        $monthExprReq = $monthExpr($requestDateCol);
        $interval = ($driver === 'mysql') ? "INTERVAL 6 MONTH" : "INTERVAL '6 months'";
        $whereRecent = ($driver === 'sqlite') ? "$requestDateCol >= datetime('now','-6 months')" : "$requestDateCol >= CURRENT_TIMESTAMP - $interval";
        $sql = "SELECT $monthExprReq AS month, COUNT(*) AS count FROM {$requestsTable} WHERE $whereRecent GROUP BY $monthExprReq ORDER BY month";
        $monthlyRequests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
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
