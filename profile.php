<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
require_once 'includes/session_manager.php';
require_once 'db.php';

// Utility: ensure profile-related columns exist on users_new
function ensureUserProfileColumns(PDO $pdo) {
    try {
        if (!function_exists('getTableStructure')) {
            return; // safety
        }
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $struct = getTableStructure($pdo, 'users_new');
        $cols = [];
        foreach ($struct as $c) {
            if (isset($c['name'])) { $cols[] = $c['name']; }
            elseif (isset($c['column_name'])) { $cols[] = $c['column_name']; }
            elseif (isset($c['Field'])) { $cols[] = $c['Field']; }
        }
        $add = function($name, $mysqlSql, $pgsqlSql, $sqliteSql) use ($cols, $pdo, $driver) {
            if (!in_array($name, $cols)) {
                try {
                    if ($driver === 'pgsql') { $pdo->exec($pgsqlSql); }
                    elseif (strtolower($driver) === 'sqlite') { $pdo->exec($sqliteSql); }
                    else { $pdo->exec($mysqlSql); }
                } catch (Throwable $t) {
                    error_log("users_new add column '$name' failed: " . $t->getMessage());
                }
            }
        };
        // Core personal info fields mirroring registration form
        $add('gender',
            "ALTER TABLE users_new ADD COLUMN gender VARCHAR(10) NULL",
            "ALTER TABLE users_new ADD COLUMN gender VARCHAR(10) NULL",
            "ALTER TABLE users_new ADD COLUMN gender TEXT NULL"
        );
        $add('birth_date',
            "ALTER TABLE users_new ADD COLUMN birth_date DATE NULL",
            "ALTER TABLE users_new ADD COLUMN birth_date DATE NULL",
            "ALTER TABLE users_new ADD COLUMN birth_date TEXT NULL"
        );
        $add('phone',
            "ALTER TABLE users_new ADD COLUMN phone VARCHAR(20) NULL",
            "ALTER TABLE users_new ADD COLUMN phone VARCHAR(20) NULL",
            "ALTER TABLE users_new ADD COLUMN phone TEXT NULL"
        );
        $add('address',
            "ALTER TABLE users_new ADD COLUMN address TEXT NULL",
            "ALTER TABLE users_new ADD COLUMN address TEXT NULL",
            "ALTER TABLE users_new ADD COLUMN address TEXT NULL"
        );
        $add('city',
            "ALTER TABLE users_new ADD COLUMN city VARCHAR(100) NULL",
            "ALTER TABLE users_new ADD COLUMN city VARCHAR(100) NULL",
            "ALTER TABLE users_new ADD COLUMN city TEXT NULL"
        );
        $add('province',
            "ALTER TABLE users_new ADD COLUMN province VARCHAR(100) NULL",
            "ALTER TABLE users_new ADD COLUMN province VARCHAR(100) NULL",
            "ALTER TABLE users_new ADD COLUMN province TEXT NULL"
        );
        $add('postal_code',
            "ALTER TABLE users_new ADD COLUMN postal_code VARCHAR(20) NULL",
            "ALTER TABLE users_new ADD COLUMN postal_code VARCHAR(20) NULL",
            "ALTER TABLE users_new ADD COLUMN postal_code TEXT NULL"
        );
        $add('weight',
            "ALTER TABLE users_new ADD COLUMN weight DECIMAL(5,2) NULL",
            "ALTER TABLE users_new ADD COLUMN weight DECIMAL(5,2) NULL",
            "ALTER TABLE users_new ADD COLUMN weight REAL NULL"
        );
        $add('height',
            "ALTER TABLE users_new ADD COLUMN height DECIMAL(5,2) NULL",
            "ALTER TABLE users_new ADD COLUMN height DECIMAL(5,2) NULL",
            "ALTER TABLE users_new ADD COLUMN height REAL NULL"
        );
        $add('blood_type',
            "ALTER TABLE users_new ADD COLUMN blood_type VARCHAR(10) NULL",
            "ALTER TABLE users_new ADD COLUMN blood_type VARCHAR(10) NULL",
            "ALTER TABLE users_new ADD COLUMN blood_type TEXT NULL"
        );
    } catch (Throwable $t) {
        error_log('ensureUserProfileColumns failed: ' . $t->getMessage());
    }
}

// Check if user is logged in
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Ensure new columns exist before any CRUD
ensureUserProfileColumns($pdo);

$user_id = $_SESSION['user_id'];
$success = $error = '';

// Handle profile update (personal information)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $birth_date  = trim($_POST['birth_date'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $weight      = $_POST['weight'] !== '' ? floatval($_POST['weight']) : null;
    $height      = $_POST['height'] !== '' ? floatval($_POST['height']) : null;
    $blood_type  = trim($_POST['blood_type'] ?? '');
    // Default fixed location values (editable here if desired)
    $city        = trim($_POST['city'] ?? 'City of Baguio');
    $province    = trim($_POST['province'] ?? 'Benguet');

    if ($name && $email) {
        try {
            $stmt = $pdo->prepare('UPDATE users_new SET name = ?, email = ?, gender = ?, birth_date = ?, phone = ?, address = ?, city = ?, province = ?, postal_code = ?, weight = ?, height = ?, blood_type = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $ok = $stmt->execute([$name, $email, $gender ?: null, $birth_date ?: null, $phone ?: null, $address ?: null, $city ?: null, $province ?: null, $postal_code ?: null, $weight, $height, $blood_type ?: null, $user_id]);
            if ($ok) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $success = 'Profile updated successfully.';
            } else {
                $error = 'Failed to update profile.';
            }
        } catch (Throwable $t) {
            $error = 'Failed to update profile: ' . htmlspecialchars($t->getMessage());
        }
    } else {
        $error = 'Name and email are required.';
    }
}

// Handle password change (Settings tab)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $stmt = $pdo->prepare('SELECT password FROM users_new WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user && password_verify($current, $user['password'])) {
        if ($new === $confirm && strlen($new) >= 6) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users_new SET password = ? WHERE id = ?');
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

// Fetch user info (including new fields)
$stmt = $pdo->prepare('SELECT * FROM users_new WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">My Profile</div>
                <div class="card-body">
                    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

                    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">Profile</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">Settings</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3" id="profileTabsContent">
                        <!-- Profile Tab: Personal Information -->
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
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= (($user['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= (($user['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Blood Type</label>
                                        <select class="form-select" name="blood_type">
                                            <?php $bt = $user['blood_type'] ?? ''; ?>
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
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-12">
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

                        <!-- Settings Tab: Change Password -->
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
