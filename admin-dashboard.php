<?php
// Admin Dashboard Wrapper
// Enforces auth, prepares data, renders tabbed UI, and delegates tab contents

require_once __DIR__ . '/includes/admin_auth.php';
requireAdminLogin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/enhanced_donor_management.php'; // status helpers
require_once __DIR__ . '/includes/blood_inventory.php'; // inventory helpers used by tabs
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/admin_actions.php'; // audit log helpers

// Active tab from query, default to dashboard
$activeTab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9\-]/i', '', $_GET['tab']) : 'dashboard';
$GLOBALS['activeTab'] = $activeTab;

// Utility: check if a column exists in a table
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// Normalize donor rows to a unified shape expected by admin-tabs
function normalizeDonorRow(array $row): array {
    $first = $row['first_name'] ?? null;
    $last  = $row['last_name'] ?? null;
    if (!$first && isset($row['name'])) {
        $parts = preg_split('/\s+/', trim((string)$row['name']), 2);
        $first = $parts[0] ?? '';
        $last  = $parts[1] ?? '';
    }
    return [
        'id'             => $row['id'] ?? null,
        'reference_code' => $row['reference_code'] ?? ($row['ref_code'] ?? null),
        'first_name'     => $first ?? '',
        'last_name'      => $last ?? '',
        'email'          => $row['email'] ?? '',
        'phone'          => $row['phone'] ?? '',
        'blood_type'     => $row['blood_type'] ?? '',
        'status'         => $row['status'] ?? 'pending',
        'created_at'     => $row['created_at'] ?? ($row['registration_date'] ?? date('Y-m-d H:i:s')),
        'served_date'    => $row['served_date'] ?? null,
    ];
}

// Prepare Pending Donors list (robust across donors_new and donors)
$pendingDonors = [];
try {
    $hasStatusNew     = columnExists($pdo, 'donors_new', 'status');
    $hasStatusLegacy  = columnExists($pdo, 'donors', 'status');
    $hasCreatedNew    = columnExists($pdo, 'donors_new', 'created_at');
    $hasCreatedLegacy = columnExists($pdo, 'donors', 'created_at');

    $unknownExpr = "(LOWER(TRIM(COALESCE(blood_type,''))) IN ('unknown','unk',''))";
    $pendingStatusExpr = "(status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))";

    $whereNew    = $hasStatusNew    ? " WHERE (" . $pendingStatusExpr . " OR " . $unknownExpr . ")" : " WHERE " . $unknownExpr;
    $whereLegacy = $hasStatusLegacy ? " WHERE (" . $pendingStatusExpr . " OR " . $unknownExpr . ")" : " WHERE " . $unknownExpr;

    $orderNew    = $hasCreatedNew    ? " ORDER BY created_at DESC" : " ORDER BY id DESC";
    $orderLegacy = $hasCreatedLegacy ? " ORDER BY created_at DESC" : " ORDER BY id DESC";

    // donors_new
    try {
        $stmt = $pdo->query("SELECT * FROM donors_new" . $whereNew . $orderNew);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) { $pendingDonors[] = normalizeDonorRow($r); }
    } catch (Throwable $e) {}

    // donors (legacy)
    try {
        $stmt = $pdo->query("SELECT * FROM donors" . $whereLegacy . $orderLegacy);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) { $pendingDonors[] = normalizeDonorRow($r); }
    } catch (Throwable $e) {}

    // newest-first
    usort($pendingDonors, function($a, $b) {
        $ta = isset($a['created_at']) ? strtotime($a['created_at']) : (isset($a['id']) ? (int)$a['id'] : 0);
        $tb = isset($b['created_at']) ? strtotime($b['created_at']) : (isset($b['id']) ? (int)$b['id'] : 0);
        return $tb <=> $ta;
    });
} catch (Throwable $e) {}
$GLOBALS['pendingDonors'] = $pendingDonors;
$GLOBALS['pendingDonorsFallback'] = null; // optional message hook used by tabs

// Prepare Donor List (recent donors across tables)
$donors = [];
try {
    // donors_new recent
    try {
        $stmt = $pdo->query("SELECT * FROM donors_new ORDER BY created_at DESC LIMIT 50");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $donors[] = normalizeDonorRow($r); }
    } catch (Throwable $e) {}
    // donors legacy recent
    try {
        $stmt = $pdo->query("SELECT * FROM donors ORDER BY id DESC LIMIT 50");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $donors[] = normalizeDonorRow($r); }
    } catch (Throwable $e) {}
} catch (Throwable $e) {}
$GLOBALS['donors'] = $donors;

// Prepare Blood Requests
$requests = [];
try {
    $statusFilter = $_GET['status_filter'] ?? '';
    $bloodTypeFilter = $_GET['blood_type_filter'] ?? '';
    $params = [];
    $where = [];
    if ($statusFilter !== '') { $where[] = 'status = ?'; $params[] = $statusFilter; }
    if ($bloodTypeFilter !== '') { $where[] = 'blood_type = ?'; $params[] = $bloodTypeFilter; }
    $sql = 'SELECT * FROM blood_requests' . (count($where) ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$GLOBALS['requests'] = $requests;

// Site settings for update-contact tab
$site_settings = [];
try { $site_settings = get_site_settings(); } catch (Throwable $e) {}
$GLOBALS['site_settings'] = $site_settings;

// Simple dashboard metrics
$totalDonors = 0; $pendingDonorCount = count($pendingDonors); $pendingRequestCount = 0;
try { $totalDonors += (int)$pdo->query("SELECT COUNT(*) FROM donors_new")->fetchColumn(); } catch (Throwable $e) {}
try { $totalDonors += (int)$pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn(); } catch (Throwable $e) {}
try { $pendingRequestCount = (int)$pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'pending'")->fetchColumn(); } catch (Throwable $e) {}

// Render
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Admin Dashboard</h1>
        <a href="/admin_logout.php" class="btn btn-outline-secondary">Logout</a>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" role="tablist">
        <?php
        $tabs = [
            'dashboard'       => 'Dashboard',
            'pending-donors'  => 'Pending Donors',
            'donor-list'      => 'Donor List',
            'blood-requests'  => 'Blood Requests',
            'donor-matching'  => 'Donor Matching',
            'blood-inventory' => 'Blood Inventory',
            'reports'         => 'Reports',
            'audit-log'       => 'Audit Log',
            'manage-pages'    => 'Manage Pages',
            'update-contact'  => 'Update Contact',
            'help'            => 'Help',
        ];
        foreach ($tabs as $key => $label):
            $active = ($activeTab === $key) ? 'active' : '';
            echo '<li class="nav-item" role="presentation">'
               . '<a class="nav-link ' . $active . '" href="admin-dashboard.php?tab=' . urlencode($key) . '" role="tab">' . htmlspecialchars($label) . '</a>'
               . '</li>';
        endforeach;
        ?>
    </ul>

    <div class="tab-content p-3 border border-top-0 bg-white">
        <?php if ($activeTab === 'dashboard'): ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Total Donors</h5>
                            <p class="display-6 mb-0"><?= (int)$totalDonors ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Pending Donors</h5>
                            <p class="display-6 mb-0"><?= (int)$pendingDonorCount ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Pending Requests</h5>
                            <p class="display-6 mb-0"><?= (int)$pendingRequestCount ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php require __DIR__ . '/includes/admin-tabs.php'; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>