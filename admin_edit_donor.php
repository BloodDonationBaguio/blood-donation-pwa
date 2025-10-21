<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
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

// Fetch donor data
$stmt = $pdo->prepare("SELECT * FROM donors_new WHERE id = ?");
$stmt->execute([$donorId]);
$donor = $stmt->fetch(PDO::FETCH_ASSOC);

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
    $status = $_POST['status'];
    
    try {
        // Store original data for audit log
        $originalData = json_encode($donor);
        
        // Update donor
        $updateStmt = $pdo->prepare("
            UPDATE donors_new SET 
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
            INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description, ip_address, user_agent)
            VALUES (?, 'donor_edited', 'donors_new', ?, ?, ?, ?)
        ");
        
        $logStmt->execute([
            $_SESSION['admin_username'] ?? 'admin',
            $donorId,
            "Donor information updated: {$firstName} {$lastName}",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
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
                        
                        <form method="POST">
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
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Weight (kg)</label>
                                    <input type="number" name="weight" class="form-control" step="0.1"
                                           value="<?= htmlspecialchars($donor['weight']) ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Height (cm)</label>
                                    <input type="number" name="height" class="form-control" step="0.1"
                                           value="<?= htmlspecialchars($donor['height']) ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?= $donor['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= $donor['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="served" <?= $donor['status'] == 'served' ? 'selected' : '' ?>>Served</option>
                                        <option value="rejected" <?= $donor['status'] == 'rejected' ? 'selected' : '' ?>>Deferred</option>
                                        <option value="suspended" <?= $donor['status'] == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="admin.php?tab=donor-list" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
