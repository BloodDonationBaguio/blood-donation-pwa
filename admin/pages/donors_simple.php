<?php
/**
 * Simple Paginated Donor Management
 * Works without complex stored procedures
 */

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check admin login
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

/**
 * Build pagination URL with current parameters
 */
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$bloodType = $_GET['blood_type'] ?? '';

// Validate per_page options
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 20;
}

// Calculate offset
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ['1=1']; // Start with a true condition
$params = [];

// Show all donors - no filtering by test status

if (!empty($status)) {
    $whereConditions[] = 'status = ?';
    $params[] = $status;
}

if (!empty($bloodType)) {
    $whereConditions[] = 'blood_type = ?';
    $params[] = $bloodType;
}

if (!empty($search)) {
    $whereConditions[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR reference_code LIKE ?)';
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM donors_new $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get paginated donors
$query = "
    SELECT 
        id,
        first_name,
        last_name,
        email,
        phone,
        blood_type,
        status,
        reference_code,
        created_at,
        updated_at,
        CONCAT(first_name, ' ', last_name) as full_name
    FROM donors_new
    $whereClause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$donors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate pagination info
$startRecord = $offset + 1;
$endRecord = min($offset + $perPage, $totalRecords);

// Get status counts for filters
$statusCounts = [];
try {
    $statusQuery = "SELECT status, COUNT(*) as count FROM donors_new GROUP BY status";
    $statusStmt = $pdo->query($statusQuery);
    while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = $row['count'];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get blood type counts for filters
$bloodTypeCounts = [];
try {
    $bloodTypeQuery = "SELECT blood_type, COUNT(*) as count FROM donors_new GROUP BY blood_type";
    $bloodTypeStmt = $pdo->query($bloodTypeQuery);
    while ($row = $bloodTypeStmt->fetch(PDO::FETCH_ASSOC)) {
        $bloodTypeCounts[$row['blood_type']] = $row['count'];
    }
} catch (Exception $e) {
    // Handle error silently
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">
            <i class="fas fa-users me-2"></i>Donor Management
            <small class="text-muted">(Simple Paginated)</small>
        </h1>
        
        <div class="d-flex gap-2">
            <a href="donors.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i> Standard View
            </a>
            <a href="donors.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add New Donor
            </a>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filters & Search
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3" id="filterForm">
                <input type="hidden" name="page" value="1">
                
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, or reference..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach (['pending', 'approved', 'served', 'unserved', 'rejected'] as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $status === $statusOption ? 'selected' : '' ?>>
                            <?= ucfirst($statusOption) ?> (<?= $statusCounts[$statusOption] ?? 0 ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $type): ?>
                        <option value="<?= $type ?>" <?= $bloodType === $type ? 'selected' : '' ?>>
                            <?= $type ?> (<?= $bloodTypeCounts[$type] ?? 0 ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select name="per_page" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($allowedPerPage as $option): ?>
                        <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                        <a href="donors_simple.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Showing <strong><?= $startRecord ?>-<?= $endRecord ?></strong> of <strong><?= number_format($totalRecords) ?></strong> donors
                <?php if ($totalPages > 1): ?>
                    (Page <?= $page ?> of <?= $totalPages ?>)
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6 text-end">
            <small class="text-muted">
                <i class="fas fa-clock me-1"></i>
                Last updated: <?= date('M d, Y H:i:s') ?>
            </small>
        </div>
    </div>

    <!-- Donors Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($donors)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <h5>No donors found</h5>
                    <p class="text-muted">No donors match your current filters.</p>
                    <a href="donors_simple.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Blood Type</th>
                                <th>Status</th>
                                <th>Reference</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donors as $donor): 
                                $statusClass = [
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'served' => 'bg-info',
                                    'unserved' => 'bg-danger',
                                    'rejected' => 'bg-secondary'
                                ][$donor['status']] ?? 'bg-secondary';
                            ?>
                            <tr>
                                <td><?= $donor['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle me-2 text-primary"></i>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($donor['full_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;">
                                        <?= htmlspecialchars($donor['email']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($donor['phone'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?= htmlspecialchars($donor['blood_type'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= ucfirst($donor['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($donor['reference_code'] ?? 'N/A') ?></code>
                                </td>
                                <td>
                                    <span class="text-muted">
                                        <?= date('M d, Y', strtotime($donor['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="donors.php?action=view&id=<?= $donor['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="donors.php?action=edit&id=<?= $donor['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $donor['id'] ?>, '<?= htmlspecialchars($donor['full_name']) ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <!-- Previous Page -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl(1) ?>" aria-label="First">
                    <span aria-hidden="true">&laquo;&laquo;</span>
                </a>
            </li>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl($page - 1) ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            
            <!-- Page Numbers -->
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            
            <!-- Next Page -->
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl($page + 1) ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPaginationUrl($totalPages) ?>" aria-label="Last">
                    <span aria-hidden="true">&raquo;&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<script>
function confirmDelete(donorId, donorName) {
    if (confirm(`Are you sure you want to delete donor "${donorName}"? This action cannot be undone.`)) {
        // Implement delete functionality
        alert('Delete functionality would be implemented here');
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
