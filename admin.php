<?php
session_start();
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'admin_auth.php';

// Enforce admin authentication using centralized guard
requireAdminLogin();

// Legacy admin is the default entry; no redirect to modern UI

$page = $_GET['page'] ?? 'dashboard';

// Logic from debug_pending_donors.php
$pendingDonors = [];

// Build robust WHERE clauses per table, tolerating schema differences
$whereNew = " WHERE 1=1";
$whereLegacy = " WHERE 1=1";
$paramsNew = [];
$paramsLegacy = [];

// Detect presence of status and created_at columns per table
$hasStatusNew = false; $hasStatusLegacy = false;
$hasCreatedNew = false; $hasCreatedLegacy = false;
// Portable column existence check
function columnExists($pdo, $table, $column) {
    try {
        // PRAGMA for SQLite
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            return in_array($column, $columns);
        }
        // information_schema for MySQL/PostgreSQL
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

$hasStatusNew     = columnExists($pdo, 'donors_new', 'status');
$hasStatusLegacy  = columnExists($pdo, 'donors', 'status');
$hasCreatedNew    = columnExists($pdo, 'donors_new', 'created_at');
$hasCreatedLegacy = columnExists($pdo, 'donors', 'created_at');

// Pending condition:
// - If status exists: a donor is pending if their status is a pending-like value.
// - If status does not exist: a donor is pending if their blood type is unknown (legacy fallback).
$unknownExpr = "(LOWER(TRIM(COALESCE(blood_type,''))) IN ('unknown','unk',''))";
$pendingStatusExpr = "(status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))";

if ($hasStatusNew) {
    $whereNew .= " AND (" . $pendingStatusExpr . " OR " . $unknownExpr . ")";
} else {
    $whereNew .= " AND " . $unknownExpr;
}
if ($hasStatusLegacy) {
    $whereLegacy .= " AND (" . $pendingStatusExpr . " OR " . $unknownExpr . ")";
} else {
    $whereLegacy .= " AND " . $unknownExpr;
}

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
    <title>Admin (Legacy) - Blood Donation</title>
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

