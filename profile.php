<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
require_once 'includes/session_manager.php';
require_once 'db.php';

// Must be logged in
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = $error = '';

// Ensure extended profile columns exist (safe for MySQL)
function ensureUserProfileColumns(PDO $pdo) {
    $driver = 'mysql';
    try { $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'mysql'; } catch (Throwable $e) {}
    try {
        $structure = function_exists('getTableStructure') ? getTableStructure($pdo, 'users_new') : [];
        $existing = [];
        foreach ($structure as $col) {
            if (isset($col['Field'])) {
                $existing[] = strtolower($col['Field']);
            } elseif (isset($col['column_name'])) {
                $existing[] = strtolower($col['column_name']);
            } elseif (isset($col['name'])) {
                $existing[] = strtolower($col['name']);
            }
        }

        $defs = [
            'gender'       => 'VARCHAR(10) NULL',
            'date_of_birth'=> 'DATE NULL',
            'phone'        => 'VARCHAR(20) NULL',
            'address'      => 'VARCHAR(255) NULL',
            'city'         => 'VARCHAR(100) NULL',
            'province'     => 'VARCHAR(100) NULL',
            'postal_code'  => 'VARCHAR(20) NULL',
            'weight'       => 'DECIMAL(5,2) NULL',
            'height'       => 'DECIMAL(5,2) NULL',
            'blood_type'   => 'VARCHAR(3) NULL'
        ];

        foreach ($defs as $col => $type) {
            if (!in_array($col, $existing, true)) {
                $sql = $driver === 'mysql'
                    ? "ALTER TABLE `users_new` ADD COLUMN `$col` $type"
                    : "ALTER TABLE users_new ADD COLUMN $col $type";
                try { $pdo->exec($sql); } catch (Throwable $inner) { error_log('add column failed: ' . $inner->getMessage()); }
            }
        }
    } catch (Throwable $e) {
        error_log('ensureUserProfileColumns failed: ' . $e->getMessage());
    }
}

ensureUserProfileColumns($pdo);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $birth_date  = trim($_POST['birth_date'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $province    = trim($_POST['province'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $weight      = trim($_POST['weight'] ?? '');
    $height      = trim($_POST['height'] ?? '');
    $blood_type  = trim($_POST['blood_type'] ?? '');

    if ($name && $email) {
        try {
            $stmt = $pdo->prepare('UPDATE users_new SET name=?, email=?, gender=?, date_of_birth=?, phone=?, address=?, city=?, province=?, postal_code=?, weight=?, height=?, blood_type=? WHERE id=?');
            $ok = $stmt->execute([
                $name,
                $email,
                ($gender !== '' ? $gender : null),
                ($birth_date !== '' ? $birth_date : null),
                ($phone !== '' ? $phone : null),
                ($address !== '' ? $address : null),
                ($city !== '' ? $city : 'City of Baguio'),
                ($province !== '' ? $province : 'Benguet'),
                ($postal_code !== '' ? $postal_code : null),
                ($weight !== '' ? $weight : null),
                ($height !== '' ? $height : null),
                ($blood_type !== '' ? $blood_type : null),
                $user_id
            ]);
            if ($ok) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $success = 'Profile updated successfully.';
            } else {
                $error = 'Failed to update profile.';
            }
        } catch (Throwable $e) {
            $error = 'Failed to update profile.';
            error_log('Profile update error: ' . $e->getMessage());
        }
    } else {
        $error = 'Name and email are required.';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $stmt = $pdo->prepare('SELECT password FROM users_new WHERE id=?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($current, $row['password'])) {
        if ($new === $confirm && strlen($new) >= 6) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users_new SET password=? WHERE id=?');
            if ($stmt->execute([$hashed, $user_id])) {
                $success = 'Password changed successfully.';
            } else {
                $error = 'Failed to change password.';
            }
        } else {
            $error = 'Passwords do not match or are too short.';
        }
    } else {
        $error = 'Current password is incorrect.';
    }
}

// Fetch user info
$user = [];
try {
    $stmt = $pdo->prepare('SELECT name, email, gender, date_of_birth, phone, address, city, province, postal_code, weight, height, blood_type FROM users_new WHERE id=?');
    $stmt->execute([$user_id]);
    $tmp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($tmp)) { $user = $tmp; }
} catch (Throwable $e) {
    try {
        $stmt = $pdo->prepare('SELECT name, email FROM users_new WHERE id=?');
        $stmt->execute([$user_id]);
        $tmp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($tmp)) { $user = $tmp; }
    } catch (Throwable $e2) {
        error_log('Profile select error: ' . $e->getMessage());
        $user = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Profile page UI refinements */
        .profile-card .card-header { background-color: #fff; border-bottom: 0; }
        .profile-card .nav-tabs { border-bottom: 0; gap: .25rem; }
        .profile-card .nav-tabs .nav-link { padding: .45rem .9rem; border-top-left-radius: .4rem; border-top-right-radius: .4rem; }
        .profile-card .nav-tabs .nav-link.active { background-color: #fff; border-color: #dee2e6 #dee2e6 #fff; }
        .profile-card .tab-content { padding-top: 1rem; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm profile-card">
                <div class="card-header p-2">
                    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">Profile</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">Settings</button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                    <div class="tab-content" id="profileTabsContent">
                        <!-- Profile Tab -->
                        <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <?php $g = $user['gender'] ?? ''; ?>
                                            <option value="">Select Gender</option>
                                            <option value="Male"   <?= $g === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= $g === 'Female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Birth Date</label>
                                        <input type="date" class="form-control" name="birth_date" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Blood Type</label>
                                        <?php $bt = $user['blood_type'] ?? ''; ?>
                                        <select class="form-select" name="blood_type">
                                            <option value="">Select Blood Type</option>
                                            <option value="A+" <?= $bt === 'A+' ? 'selected' : '' ?>>A+</option>
                                            <option value="A-" <?= $bt === 'A-' ? 'selected' : '' ?>>A-</option>
                                            <option value="B+" <?= $bt === 'B+' ? 'selected' : '' ?>>B+</option>
                                            <option value="B-" <?= $bt === 'B-' ? 'selected' : '' ?>>B-</option>
                                            <option value="AB+" <?= $bt === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                            <option value="AB-" <?= $bt === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                            <option value="O+" <?= $bt === 'O+' ? 'selected' : '' ?>>O+</option>
                                            <option value="O-" <?= $bt === 'O-' ? 'selected' : '' ?>>O-</option>
                                            <option value="UNK" <?= ($bt === 'UNK' || $bt === 'Unknown') ? 'selected' : '' ?>>Unknown</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($user['city'] ?? 'City of Baguio') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Province</label>
                                        <input type="text" class="form-control" name="province" value="<?= htmlspecialchars($user['province'] ?? 'Benguet') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Weight (kg)</label>
                                        <input type="number" class="form-control" step="0.1" min="0" name="weight" value="<?= htmlspecialchars($user['weight'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Height (cm)</label>
                                        <input type="number" class="form-control" step="0.1" min="0" name="height" value="<?= htmlspecialchars($user['height'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                </div>
                            </form>
                        </div>

                        <!-- Settings Tab -->
                        <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                            <h5>Change Password</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-secondary">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
