<?php
/**
 * Blood Inventory - Shows ALL Blood Units
 * Includes both real and test blood units to match donor list
 */

require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/header.php';

// Check admin login
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$status = $_GET['status'] ?? '';
$bloodType = $_GET['blood_type'] ?? '';
$search = $_GET['search'] ?? '';
$showTest = $_GET['show_test'] ?? '1'; // Show test data by default

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

// Only filter by seed_flag if show_test is 0
if ($showTest == '0') {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM blood_inventory LIKE 'seed_flag'");
        if ($stmt->fetch()) {
            $whereConditions[] = 'bi.seed_flag = 0'; // Exclude test data
        }
    } catch (Exception $e) {
        // Column doesn't exist, continue without filter
    }
}

if (!empty($status)) {
    $whereConditions[] = 'bi.status = ?';
    $params[] = $status;
}

if (!empty($bloodType)) {
    $whereConditions[] = 'bi.blood_type = ?';
    $params[] = $bloodType;
}

if (!empty($search)) {
    $whereConditions[] = '(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)';
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM blood_inventory bi
    LEFT JOIN donors_new d ON bi.donor_id = d.id
    $whereClause
";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get paginated blood units with donor info
$query = "
    SELECT 
        bi.id,
        bi.unit_id,
        bi.blood_type,
        bi.collection_date,
        bi.expiry_date,
        bi.status,
        bi.collection_site,
        bi.storage_location,
        bi.volume_ml,
        bi.screening_status,
        bi.notes,
        bi.created_at,
        bi.updated_at,
        bi.seed_flag,
        d.id as donor_id,
        d.first_name as donor_first_name,
        d.last_name as donor_last_name,
        d.reference_code as donor_reference,
        d.email as donor_email,
        d.phone as donor_phone,
        d.blood_type as donor_blood_type,
        CONCAT(d.first_name, ' ', d.last_name) as donor_full_name,
        DATEDIFF(bi.expiry_date, CURDATE()) as days_to_expiry,
        CASE 
            WHEN bi.expiry_date < CURDATE() THEN 'expired'
            WHEN DATEDIFF(bi.expiry_date, CURDATE()) <= 5 THEN 'expiring_soon'
            ELSE 'good'
        END as urgency_status
    FROM blood_inventory bi
    LEFT JOIN donors_new d ON bi.donor_id = d.id
    $whereClause
    ORDER BY bi.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bloodUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate pagination info
$startRecord = $offset + 1;
$endRecord = min($offset + $perPage, $totalRecords);

// Get status counts for filters
$statusCounts = [];
try {
    $statusQuery = "SELECT status, COUNT(*) as count FROM blood_inventory GROUP BY status";
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
    $bloodTypeQuery = "SELECT blood_type, COUNT(*) as count FROM blood_inventory GROUP BY blood_type";
    $bloodTypeStmt = $pdo->query($bloodTypeQuery);
    while ($row = $bloodTypeStmt->fetch(PDO::FETCH_ASSOC)) {
        $bloodTypeCounts[$row['blood_type']] = $row['count'];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get approved donors for adding new blood units
$approvedDonors = [];
try {
    $approvedDonorsQuery = "
        SELECT id, first_name, last_name, reference_code, blood_type, status
        FROM donors_new 
        WHERE status = 'approved'
        ORDER BY first_name, last_name
        LIMIT 100
    ";
    $approvedDonorsStmt = $pdo->query($approvedDonorsQuery);
    $approvedDonors = $approvedDonorsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">
            <i class="fas fa-tint me-2"></i>Blood Inventory Management
            <small class="text-muted">(All Units - <?= $totalRecords ?> total)</small>
        </h1>
        
        <div class="d-flex gap-2">
            <a href="admin_blood_inventory.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i> Standard View
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                <i class="fas fa-plus me-1"></i> Add Blood Unit
            </button>
        </div>
    </div>

    <!-- Test Data Toggle -->
    <div class="alert alert-info">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-info-circle me-2"></i>
                <strong>Showing:</strong> 
                <?php if ($showTest == '1'): ?>
                    All blood units (including test data)
                <?php else: ?>
                    Real blood units only
                <?php endif; ?>
            </div>
            <div>
                <a href="?show_test=1" class="btn btn-sm <?= $showTest == '1' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Show All
                </a>
                <a href="?show_test=0" class="btn btn-sm <?= $showTest == '0' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Real Only
                </a>
            </div>
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
                <input type="hidden" name="show_test" value="<?= $showTest ?>">
                
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Unit ID, donor name, or reference..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach (['available', 'used', 'expired', 'quarantined'] as $statusOption): ?>
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
                
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                        <a href="blood_inventory_all.php" class="btn btn-outline-secondary">
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
                Showing <strong><?= $startRecord ?>-<?= $endRecord ?></strong> of <strong><?= number_format($totalRecords) ?></strong> blood units
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

    <!-- Blood Units Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($bloodUnits)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>No blood units found</h5>
                    <p class="text-muted">No blood units match your current filters.</p>
                    <a href="blood_inventory_all.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Unit ID</th>
                                <th>Blood Type</th>
                                <th>Donor Info</th>
                                <th>Collection Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bloodUnits as $unit): 
                                $statusClass = [
                                    'available' => 'bg-success',
                                    'used' => 'bg-info',
                                    'expired' => 'bg-danger',
                                    'quarantined' => 'bg-warning'
                                ][$unit['status']] ?? 'bg-secondary';
                                
                                $urgencyClass = [
                                    'expired' => 'text-danger fw-bold',
                                    'expiring_soon' => 'text-warning fw-bold',
                                    'good' => 'text-muted'
                                ][$unit['urgency_status']] ?? 'text-muted';
                                
                                $isTestData = $unit['seed_flag'] == 1;
                                $testBadge = $isTestData ? '<span class="badge bg-warning text-dark">TEST</span>' : '<span class="badge bg-success">REAL</span>';
                            ?>
                            <tr class="<?= $unit['urgency_status'] === 'expired' ? 'table-danger' : ($unit['urgency_status'] === 'expiring_soon' ? 'table-warning' : '') ?>">
                                <td>
                                    <code><?= htmlspecialchars($unit['unit_id']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?= htmlspecialchars($unit['blood_type']) ?>
                                    </span>
                                    <?php if (isset($unit['donor_blood_type']) && $unit['blood_type'] !== $unit['donor_blood_type']): ?>
                                        <br><small class="text-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Donor: <?= $unit['donor_blood_type'] ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="donor-info">
                                        <strong><?= htmlspecialchars($unit['donor_full_name'] ?? 'Unknown Donor') ?></strong><br>
                                        <small class="text-muted">
                                            Ref: <?= htmlspecialchars($unit['donor_reference'] ?? 'N/A') ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($unit['collection_date'])) ?></strong><br>
                                    <small class="text-muted"><?= date('D', strtotime($unit['collection_date'])) ?></small>
                                </td>
                                <td>
                                    <strong class="<?= $urgencyClass ?>">
                                        <?= date('M d, Y', strtotime($unit['expiry_date'])) ?>
                                    </strong>
                                    <?php if ($unit['urgency_status'] === 'expiring_soon'): ?>
                                        <br><small class="text-warning fw-bold">
                                            <i class="fas fa-clock"></i> <?= $unit['days_to_expiry'] ?> days left
                                        </small>
                                    <?php elseif ($unit['urgency_status'] === 'expired'): ?>
                                        <br><small class="text-danger fw-bold">
                                            <i class="fas fa-times-circle"></i> Expired
                                        </small>
                                    <?php else: ?>
                                        <br><small class="text-muted"><?= $unit['days_to_expiry'] ?> days left</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= ucfirst($unit['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($unit['storage_location'] ?? 'Not Set') ?>
                                    </small>
                                </td>
                                <td>
                                    <?= $testBadge ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewUnit('<?= $unit['unit_id'] ?>')" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="editUnit('<?= $unit['unit_id'] ?>')" title="Edit Unit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($unit['status'] === 'available'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="issueUnit('<?= $unit['unit_id'] ?>')" title="Issue Unit">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                        <?php endif; ?>
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

<!-- Add Blood Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Blood Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUnitForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Donor <span class="text-danger">*</span></label>
                        <?php if (empty($approvedDonors)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>No approved donors available</strong><br>
                                Blood units can only be created for real donors who have registered through this website and been approved.
                            </div>
                            <input type="hidden" name="donor_id" value="">
                        <?php else: ?>
                            <select name="donor_id" class="form-select" required>
                                <option value="">Choose an approved donor...</option>
                                <?php foreach ($approvedDonors as $donor): ?>
                                    <option value="<?= $donor['id'] ?>" data-blood-type="<?= $donor['blood_type'] ?>">
                                        <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?> 
                                        (<?= htmlspecialchars($donor['reference_code']) ?>) - 
                                        <?= $donor['blood_type'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Only showing approved donors who registered through this website
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Blood Type <span class="text-danger">*</span></label>
                        <select name="blood_type" class="form-select" required>
                            <option value="">Select Blood Type</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $type): ?>
                            <option value="<?= $type ?>"><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Collection Date <span class="text-danger">*</span></label>
                        <input type="date" name="collection_date" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Volume (mL)</label>
                        <input type="number" name="volume_ml" class="form-control" value="450" min="1">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Collection Site</label>
                        <input type="text" name="collection_site" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Storage Location</label>
                        <input type="text" name="storage_location" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-fill blood type when donor is selected
document.querySelector('select[name="donor_id"]')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const bloodType = selectedOption.dataset.bloodType;
    if (bloodType) {
        document.querySelector('select[name="blood_type"]').value = bloodType;
    }
});

// Add Unit Form
document.getElementById('addUnitForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    submitBtn.disabled = true;
    
    const formData = new FormData(this);
    formData.append('action', 'create_unit');
    
    fetch('admin_blood_inventory.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Blood unit added successfully! Unit ID: ' + data.unit_id);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the blood unit.');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

function viewUnit(unitId) {
    alert('View unit details for: ' + unitId);
}

function editUnit(unitId) {
    alert('Edit unit: ' + unitId);
}

function issueUnit(unitId) {
    if (confirm('Are you sure you want to issue this blood unit?')) {
        alert('Issue unit: ' + unitId);
    }
}
</script>

<?php
/**
 * Build pagination URL with current parameters
 */
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

require_once __DIR__ . '/../includes/footer.php';
?>
