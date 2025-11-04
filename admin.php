<?php
// Legacy entry point: redirect to the modern admin dashboard
session_start();
header('Location: /admin/dashboard.php');
exit();

// Order by clause per table
$orderNew    = $hasCreatedNew    ? " ORDER BY created_at DESC" : " ORDER BY id DESC";
$orderLegacy = $hasCreatedLegacy ? " ORDER BY created_at DESC" : " ORDER BY id DESC";

// Query donors_new if present
try {
    $stmt = $pdo->prepare("SELECT * FROM donors_new" . $whereNew . $orderNew);
    $stmt->execute($paramsNew);
    $pendingDonors = array_merge($pendingDonors, $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) { /* ignore if table doesn't exist or query fails */ }

// Query legacy donors table
try {
    $stmt = $pdo->prepare("SELECT * FROM donors" . $whereLegacy . $orderLegacy);
    $stmt->execute($paramsLegacy);
    $pendingDonors = array_merge($pendingDonors, $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) { /* ignore if table doesn't exist or query fails */ }

// Sort newest-first by available timestamp or id
usort($pendingDonors, function($a, $b) {
    $ta = isset($a['created_at']) ? strtotime($a['created_at']) : (isset($a['id']) ? (int)$a['id'] : 0);
    $tb = isset($b['created_at']) ? strtotime($b['created_at']) : (isset($b['id']) ? (int)$b['id'] : 0);
    return $tb <=> $ta;
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Blood Donation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h2>Blood Donation</h2>
            <nav>
                <ul>
                    <li><a href="admin.php?page=dashboard">Dashboard</a></li>
                    <li class="active"><a href="admin.php?page=pending-donors">Pending Donors</a></li>
                    <li><a href="admin.php?page=all-donors">All Donors</a></li>
                    <li><a href="admin.php?page=blood-inventory">Blood Inventory</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <header>
                <h1>Pending Donors</h1>
            </header>
            <main>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Blood Type</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingDonors)): ?>
                                <tr>
                                    <td colspan="7">No pending donors.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingDonors as $donor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donor['id']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['name'] ?? ($donor['first_name'] . ' ' . $donor['last_name'])); ?></td>
                                        <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['blood_type']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['created_at'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="approve_donor.php?id=<?php echo $donor['id']; ?>" class="btn btn-success">Approve</a>
                                            <a href="reject_donor.php?id=<?php echo $donor['id']; ?>" class="btn btn-danger">Reject</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

