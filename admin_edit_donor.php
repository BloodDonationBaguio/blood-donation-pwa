<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/authorization_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Enforce authorization gate for edit actions
$authValid = isset($_SESSION['authorization_verified_expires']) && $_SESSION['authorization_verified_expires'] >= time();
if (!$authValid) {
    header('Location: admin.php?tab=donor-list&error=Authorization required to edit donor records');
    exit();
}

$error = '';
$success = '';

// Get donor ID
$donorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($donorId <= 0) {
    header('Location: admin.php?tab=donor-list&error=Invalid donor ID');
    exit();
}

// Choose donors table dynamically and fetch safely
$driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? '');
if ($driver === 'pgsql') {
    $donorsTable = 'donors';
} else {
    $donorsTable = (function_exists('tableExists') && tableExists($pdo, 'donors_new')) ? 'donors_new' : 'donors';
}
try {
    $stmt = $pdo->prepare("SELECT * FROM {$donorsTable} WHERE id = ?");
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('admin_edit_donor.php: donor fetch failed - ' . $e->getMessage());
    header('Location: admin.php?tab=donor-list&error=Database error loading donor');
    exit();
}

if (!$donor) {
    header('Location: admin.php?tab=donor-list&error=Donor not found');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $bloodType = $_POST['blood_type'];
    $dateOfBirth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $weight = floatval($_POST['weight']);
    $height = floatval($_POST['height']);
    // Status is no longer editable from this page; keep existing value
    $status = $donor['status'];
    
    try {
        // Store original data for audit log
        $originalData = json_encode($donor);
        
        // Update donor
        $updateStmt = $pdo->prepare("
            UPDATE {$donorsTable} SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, 
                blood_type = ?, date_of_birth = ?, gender = ?, 
                address = ?, city = ?, province = ?, 
                weight = ?, height = ?, status = ?
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $firstName, $lastName, $email, $phone, 
            $bloodType, $dateOfBirth, $gender, 
            $address, $city, $province, 
            $weight, $height, $status, 
            $donorId
        ]);

        // Automatically create a blood unit if status becomes served/completed
        if (in_array(strtolower($status), ['served', 'completed'])) {
            try {
                // Avoid duplicate available units for this donor
                $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM blood_inventory WHERE donor_id = ? AND status = 'available'");
                $dupCheck->execute([$donorId]);
                $hasAvailable = (int)$dupCheck->fetchColumn() > 0;
            } catch (Exception $e) {
                // If table is missing or check fails, proceed optimistically
                $hasAvailable = false;
                error_log('admin_edit_donor: duplicate unit check failed - ' . $e->getMessage());
            }

            if (!$hasAvailable) {
                try {
                    // Robust include to avoid fatal errors on production when paths differ
                    $managerIncluded = false;
                    $candidatePaths = [
                        __DIR__ . '/includes/BloodInventoryManagerComplete.php',
                        __DIR__ . '/blood-donation-pwa/includes/BloodInventoryManagerComplete.php'
                    ];
                    foreach ($candidatePaths as $path) {
                        if (file_exists($path)) { require_once $path; $managerIncluded = true; break; }
                    }

                    if ($managerIncluded && class_exists('BloodInventoryManagerComplete')) {
                        $inventoryManager = new BloodInventoryManagerComplete($pdo);

                        $unitData = [
                            'donor_id' => $donorId,
                            'collection_date' => date('Y-m-d'),
                            'collection_site' => 'Main Center',
                            'storage_location' => 'Storage A'
                        ];

                        $createResult = $inventoryManager->addBloodUnit($unitData);
                        if (!empty($createResult['success'])) {
                            $success .= ($success ? ' ' : '') . 'Blood unit auto-created.';
                        } else {
                            error_log('admin_edit_donor: auto-create blood unit failed - ' . ($createResult['message'] ?? 'unknown error'));
                        }
                    } else {
                        // Prevent HTTP 500 by skipping when file/class is missing
                        error_log('admin_edit_donor: BloodInventoryManagerComplete not available; skipped auto-create.');
                    }
                } catch (Exception $e) {
                    error_log('admin_edit_donor: error auto-creating blood unit - ' . $e->getMessage());
                }
            }
        }
        
        // Log the change
        $newData = json_encode([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'blood_type' => $bloodType,
            'status' => $status
        ]);
        
        $logStmt = $pdo->prepare("
            INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description, ip_address)
            VALUES (?, 'donor_edited', ?, ?, ?, ?)
        ");
        
        $logStmt->execute([
            $_SESSION['admin_username'] ?? 'admin',
            $donorsTable,
            $donorId,
            "Donor information updated: {$firstName} {$lastName}",
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $success = "Donor information updated successfully!";
        
        // Refresh donor data
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = "Error updating donor: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Donor - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-user-edit"></i> Edit Donor Information
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="editDonorForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?= htmlspecialchars($donor['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?= htmlspecialchars($donor['last_name']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($donor['email']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($donor['phone']) ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Blood Type</label>
                                    <select name="blood_type" class="form-select" required>
                                        <option value="A+" <?= $donor['blood_type'] == 'A+' ? 'selected' : '' ?>>A+</option>
                                        <option value="A-" <?= $donor['blood_type'] == 'A-' ? 'selected' : '' ?>>A-</option>
                                        <option value="B+" <?= $donor['blood_type'] == 'B+' ? 'selected' : '' ?>>B+</option>
                                        <option value="B-" <?= $donor['blood_type'] == 'B-' ? 'selected' : '' ?>>B-</option>
                                        <option value="AB+" <?= $donor['blood_type'] == 'AB+' ? 'selected' : '' ?>>AB+</option>
                                        <option value="AB-" <?= $donor['blood_type'] == 'AB-' ? 'selected' : '' ?>>AB-</option>
                                        <option value="O+" <?= $donor['blood_type'] == 'O+' ? 'selected' : '' ?>>O+</option>
                                        <option value="O-" <?= $donor['blood_type'] == 'O-' ? 'selected' : '' ?>>O-</option>
                                        <option value="Unknown" <?= $donor['blood_type'] == 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" 
                                           value="<?= htmlspecialchars($donor['date_of_birth']) ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male" <?= $donor['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $donor['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= $donor['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control" 
                                       value="<?= htmlspecialchars($donor['address']) ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" 
                                           value="<?= htmlspecialchars($donor['city']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Province</label>
                                    <input type="text" name="province" class="form-control" 
                                           value="<?= htmlspecialchars($donor['province']) ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Weight (kg)</label>
                                    <input type="number" name="weight" class="form-control" step="0.1"
                                           value="<?= htmlspecialchars($donor['weight']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Height (cm)</label>
                                    <input type="number" name="height" class="form-control" step="0.1"
                                           value="<?= htmlspecialchars($donor['height']) ?>">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="admin.php?tab=donor-list" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                                <button type="submit" class="btn btn-primary" id="saveChangesBtn">
                                    <i class="fas fa-save"></i> <span class="save-text">Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add loading state to Save Changes button on submit
    (function() {
        const form = document.getElementById('editDonorForm');
        const saveBtn = document.getElementById('saveChangesBtn');
        if (form && saveBtn) {
            form.addEventListener('submit', function() {
                if (saveBtn.disabled) return; // prevent double-click
                saveBtn.disabled = true;
                // Preserve original content to allow potential reuse if needed
                const originalHTML = saveBtn.innerHTML;
                saveBtn.setAttribute('data-original', originalHTML);
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
            });
        }
    })();
    </script>
</body>
</html>
