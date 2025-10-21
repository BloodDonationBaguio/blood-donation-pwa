<?php
// Check if we're viewing/editing a request
$action = $_GET['action'] ?? 'list';
$requestId = $_GET['id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    // Basic validation
    $required = ['patient_name', 'blood_type_needed', 'units_required', 'hospital_name', 'status'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (empty($errors)) {
        try {
            $data = [
                'patient_name' => $_POST['patient_name'],
                'blood_type_needed' => $_POST['blood_type_needed'],
                'units_required' => (int)$_POST['units_required'],
                'hospital_name' => $_POST['hospital_name'],
                'hospital_address' => $_POST['hospital_address'] ?? '',
                'contact_person' => $_POST['contact_person'] ?? '',
                'contact_phone' => $_POST['contact_phone'] ?? '',
                'status' => $_POST['status'],
                'notes' => $_POST['notes'] ?? ''
            ];
            
            if ($action === 'edit' && $requestId) {
                // Update existing request
                $sql = "UPDATE requests SET ";
                $updates = [];
                $params = [];
                
                foreach ($data as $key => $value) {
                    $updates[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
                
                $sql .= implode(', ', $updates) . " WHERE id = :id";
                $params[':id'] = $requestId;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['success_message'] = 'Request updated successfully';
                header('Location: /admin/?page=requests');
                exit();
                
            } else {
                // Add new request
                $data['reference_number'] = 'REQ-' . strtoupper(uniqid());
                $data['request_date'] = date('Y-m-d H:i:s');
                
                $columns = implode(', ', array_keys($data));
                $placeholders = ':' . implode(', :', array_keys($data));
                
                $sql = "INSERT INTO requests ($columns) VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                
                foreach ($data as $key => $value) {
                    $stmt->bindValue(":$key", $value);
                }
                
                $stmt->execute();
                
                $_SESSION['success_message'] = 'Request added successfully';
                header('Location: /admin/?page=requests');
                exit();
            }
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Handle request status update
if (isset($_POST['update_status']) && $requestId) {
    try {
        $newStatus = $_POST['new_status'];
        $adminNotes = $_POST['admin_notes'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE requests SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->execute([$newStatus, $adminNotes, $requestId]);
        
        $_SESSION['success_message'] = 'Request status updated successfully';
        header("Location: /admin/?page=requests&action=view&id=$requestId");
        exit();
        
    } catch (PDOException $e) {
        $error = 'Error updating request status: ' . $e->getMessage();
    }
}

// Handle request deletion
if ($action === 'delete' && $requestId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ?");
        $stmt->execute([$requestId]);
        
        $_SESSION['success_message'] = 'Request deleted successfully';
        header('Location: /admin/?page=requests');
        exit();
        
    } catch (PDOException $e) {
        $error = 'Error deleting request: ' . $e->getMessage();
    }
}

// Get request data for edit/view
$request = null;
if (($action === 'edit' || $action === 'view') && $requestId) {
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        $_SESSION['error_message'] = 'Request not found';
        header('Location: /admin/?page=requests');
        exit();
    }
}
?>

<?php if ($action === 'list'): ?>
    <!-- Request List -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Blood Requests</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="?page=requests&action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Request
            </a>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <input type="hidden" name="page" value="requests">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by patient or reference" 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select name="blood_type" class="form-select">
                        <option value="">All Blood Types</option>
                        <option value="A+" <?= ($_GET['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= ($_GET['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= ($_GET['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= ($_GET['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                        <option value="AB+" <?= ($_GET['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= ($_GET['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                        <option value="O+" <?= ($_GET['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= ($_GET['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Patient</th>
                            <th>Blood Type</th>
                            <th>Units</th>
                            <th>Hospital</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Build query
                        $where = [];
                        $params = [];
                        
                        if (!empty($_GET['search'])) {
                            $where[] = "(patient_name LIKE ? OR reference_number LIKE ? OR hospital_name LIKE ?)";
                            $searchTerm = "%" . $_GET['search'] . "%";
                            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
                        }
                        
                        if (!empty($_GET['blood_type'])) {
                            $where[] = "blood_type_needed = ?";
                            $params[] = $_GET['blood_type'];
                        }
                        
                        if (!empty($_GET['status'])) {
                            $where[] = "status = ?";
                            $params[] = $_GET['status'];
                        }
                        
                        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                        
                        // Get requests
                        $sql = "SELECT * FROM requests $whereClause ORDER BY request_date DESC LIMIT 100";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $requests = $stmt->fetchAll();
                        
                        if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No requests found. <a href="?page=requests&action=add">Create a new request</a>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): 
                                $statusClass = [
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-info',
                                    'completed' => 'bg-success',
                                    'cancelled' => 'bg-secondary',
                                    'rejected' => 'bg-danger'
                                ][$req['status']] ?? 'bg-secondary';
                                ?>
                                <tr>
                                    <td>
                                        <span class="text-muted"><?= htmlspecialchars($req['reference_number']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($req['patient_name']) ?></td>
                                    <td>
                                        <span class="badge bg-danger"><?= htmlspecialchars($req['blood_type_needed']) ?></span>
                                    </td>
                                    <td><?= (int)$req['units_required'] ?></td>
                                    <td><?= htmlspecialchars($req['hospital_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= ucfirst($req['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($req['request_date'])) ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?page=requests&action=view&id=<?= $req['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?page=requests&action=edit&id=<?= $req['id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    title="Delete"
                                                    onclick="if(confirm('Are you sure you want to delete this request?')) { 
                                                        window.location.href='?page=requests&action=delete&id=<?= $req['id'] ?>' 
                                                    }">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Add/Edit/View Request Form -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <?= $action === 'add' ? 'New Blood Request' : ($action === 'view' ? 'View Request' : 'Edit Request') ?>
            <?php if ($action === 'view' && isset($request['reference_number'])): ?>
                <small class="text-muted">#<?= $request['reference_number'] ?></small>
            <?php endif; ?>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="?page=requests" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="patient_name" class="form-label">Patient Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="patient_name" name="patient_name" 
                                   value="<?= htmlspecialchars($request['patient_name'] ?? '') ?>" 
                                   <?= $action === 'view' ? 'readonly' : '' ?> required>
                            <div class="invalid-feedback">Please enter the patient's name.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="blood_type_needed" class="form-label">Blood Type Needed <span class="text-danger">*</span></label>
                                    <select class="form-select" id="blood_type_needed" name="blood_type_needed" 
                                            <?= $action === 'view' ? 'disabled' : '' ?> required>
                                        <option value="">Select Blood Type</option>
                                        <option value="A+" <?= ($request['blood_type_needed'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                        <option value="A-" <?= ($request['blood_type_needed'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                        <option value="B+" <?= ($request['blood_type_needed'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                        <option value="B-" <?= ($request['blood_type_needed'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                        <option value="AB+" <?= ($request['blood_type_needed'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                        <option value="AB-" <?= ($request['blood_type_needed'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                        <option value="O+" <?= ($request['blood_type_needed'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                        <option value="O-" <?= ($request['blood_type_needed'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                                    </select>
                                    <?php if ($action === 'view'): ?>
                                        <input type="hidden" name="blood_type_needed" value="<?= htmlspecialchars($request['blood_type_needed'] ?? '') ?>">
                                    <?php endif; ?>
                                    <div class="invalid-feedback">Please select a blood type.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="units_required" class="form-label">Units Required <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="units_required" name="units_required" 
                                           min="1" max="10" value="<?= $request['units_required'] ?? 1 ?>" 
                                           <?= $action === 'view' ? 'readonly' : '' ?> required>
                                    <div class="invalid-feedback">Please enter a valid number of units (1-10).</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="hospital_name" class="form-label">Hospital Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="hospital_name" name="hospital_name" 
                                   value="<?= htmlspecialchars($request['hospital_name'] ?? '') ?>" 
                                   <?= $action === 'view' ? 'readonly' : '' ?> required>
                            <div class="invalid-feedback">Please enter the hospital name.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="hospital_address" class="form-label">Hospital Address</label>
                            <input type="text" class="form-control" id="hospital_address" name="hospital_address" 
                                   value="<?= htmlspecialchars($request['hospital_address'] ?? '') ?>" 
                                   <?= $action === 'view' ? 'readonly' : '' ?>>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                           value="<?= htmlspecialchars($request['contact_person'] ?? '') ?>" 
                                           <?= $action === 'view' ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                           value="<?= htmlspecialchars($request['contact_phone'] ?? '') ?>" 
                                           <?= $action === 'view' ? 'readonly' : '' ?>>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" <?= $action === 'view' ? 'disabled' : '' ?> required>
                                <option value="pending" <?= ($request['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= ($request['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="completed" <?= ($request['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= ($request['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <?php if ($action === 'view'): ?>
                                <input type="hidden" name="status" value="<?= htmlspecialchars($request['status'] ?? 'pending') ?>">
                            <?php endif; ?>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" 
                                      <?= $action === 'view' ? 'readonly' : '' ?>><?= htmlspecialchars($request['notes'] ?? '') ?></textarea>
                        </div>
                        
                        <?php if ($action === 'view' && !empty($request['admin_notes'])): ?>
                            <div class="alert alert-info">
                                <h6>Admin Notes:</h6>
                                <?= nl2br(htmlspecialchars($request['admin_notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="text-end mt-4">
                        <a href="?page=requests" class="btn btn-outline-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> 
                            <?= $action === 'add' ? 'Create Request' : 'Save Changes' ?>
                        </button>
                    </div>
                <?php endif; ?>
            </form>
            
            <?php if ($action === 'view'): ?>
                <!-- Status Update Form -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Update Request Status</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="update_status" value="1">
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="new_status" class="form-label">New Status</label>
                                        <select class="form-select" id="new_status" name="new_status" required>
                                            <option value="pending" <?= $request['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="approved" <?= $request['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="completed" <?= $request['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="cancelled" <?= $request['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="admin_notes" class="form-label">Admin Notes</label>
                                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="2" 
                                                  placeholder="Add any notes about this status update"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-sync-alt me-1"></i> Update
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Request History -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Request History</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Request Created</h6>
                                    <small class="text-muted"><?= date('M j, Y g:i A', strtotime($request['request_date'])) ?></small>
                                </div>
                                <p class="mb-1">Request was created and is now pending review.</p>
                            </div>
                            <?php if ($request['status'] !== 'pending'): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Status Updated to <?= ucfirst($request['status']) ?></h6>
                                        <small class="text-muted">
                                            <?= !empty($request['updated_at']) ? date('M j, Y g:i A', strtotime($request['updated_at'])) : 'N/A' ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($request['admin_notes'])): ?>
                                        <p class="mb-1"><?= nl2br(htmlspecialchars($request['admin_notes'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
// Form validation
(function () {
    'use strict'
    
    var forms = document.querySelectorAll('.needs-validation')
    
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>
