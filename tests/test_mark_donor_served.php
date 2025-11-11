<?php
// Test: markDonorServed flow creates blood unit and updates donor
// Uses tests/utils.php helpers via tests/run_all_tests.php

// Safeguard: if run directly, include DB and utils
if (!isset($pdo) || !$pdo instanceof PDO) {
    $dbCandidates = [
        dirname(__DIR__) . '/db.php',
        dirname(__DIR__) . '/blood-donation-pwa/db.php'
    ];
    foreach ($dbCandidates as $dbPath) { if (file_exists($dbPath)) { require_once $dbPath; break; } }
    $utilsPath = __DIR__ . '/utils.php';
    if (file_exists($utilsPath)) { require_once $utilsPath; }
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $_SESSION['admin_username'] ?? 'admin';
    $_SESSION['admin_id'] = $_SESSION['admin_id'] ?? 1;
}

function dbDriver(PDO $pdo) {
    try { return strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); } catch (Throwable $e) { return 'mysql'; }
}

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, $table) {
        $driver = dbDriver($pdo);
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'pgsql') {
                $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)");
                $stmt->execute([':t' => $table]);
                return (bool)$stmt->fetchColumn();
            } else { // sqlite
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t");
                $stmt->execute([':t' => $table]);
                return $stmt->fetch() !== false;
            }
        } catch (Throwable $e) { return false; }
    }
}

function ensureMinimalTables(PDO $pdo) {
    $driver = dbDriver($pdo);
    if (!tableExists($pdo, 'donations_new')) {
        try {
            if ($driver === 'pgsql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS donations_new (
                    id SERIAL PRIMARY KEY,
                    donor_id INT NOT NULL,
                    donation_date DATE NOT NULL,
                    blood_type VARCHAR(10),
                    units_donated INT DEFAULT 1,
                    status VARCHAR(20) DEFAULT 'scheduled',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS donations_new (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    donor_id INT NOT NULL,
                    donation_date DATE NOT NULL,
                    blood_type VARCHAR(10),
                    units_donated INT DEFAULT 1,
                    status VARCHAR(20) DEFAULT 'scheduled',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_donor_id (donor_id),
                    INDEX idx_donation_date (donation_date)
                )");
            }
        } catch (Throwable $e) {}
    }
    if (!tableExists($pdo, 'admin_audit_log')) {
        try {
            if ($driver === 'pgsql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
                    id SERIAL PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    admin_username VARCHAR(255),
                    action_type VARCHAR(255) NOT NULL,
                    table_name VARCHAR(255),
                    record_id VARCHAR(255),
                    description TEXT,
                    ip_address VARCHAR(64)
                )");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    admin_username VARCHAR(255),
                    action_type VARCHAR(255) NOT NULL,
                    table_name VARCHAR(255),
                    record_id VARCHAR(255),
                    description TEXT,
                    ip_address VARCHAR(64)
                )");
            }
        } catch (Throwable $e) {}
    }
    if (!tableExists($pdo, 'blood_inventory')) {
        try {
            if ($driver === 'pgsql') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS blood_inventory (
                    id SERIAL PRIMARY KEY,
                    unit_id VARCHAR(50) UNIQUE NOT NULL,
                    donor_id INT NOT NULL,
                    blood_type VARCHAR(10) NOT NULL,
                    collection_date DATE NOT NULL,
                    expiry_date DATE NOT NULL,
                    status VARCHAR(20) DEFAULT 'available',
                    volume_ml INT DEFAULT 450,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS blood_inventory (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    unit_id VARCHAR(50) UNIQUE NOT NULL,
                    donor_id INT NOT NULL,
                    blood_type VARCHAR(10) NOT NULL,
                    collection_date DATE NOT NULL,
                    expiry_date DATE NOT NULL,
                    status VARCHAR(20) DEFAULT 'available',
                    volume_ml INT DEFAULT 450,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }
        } catch (Throwable $e) {}
    }
    try { $pdo->exec("ALTER TABLE donors ADD COLUMN status VARCHAR(20) DEFAULT 'pending'"); } catch (Throwable $e) {}
    try {
        if (dbDriver($pdo) === 'pgsql') { $pdo->exec("ALTER TABLE donors ADD COLUMN served_date TIMESTAMP NULL"); }
        else { $pdo->exec("ALTER TABLE donors ADD COLUMN served_date DATETIME NULL"); }
    } catch (Throwable $e) {}
}

t_section('Served Flow — markDonorServed creates unit and updates donor');

if (!isset($pdo) || !$pdo instanceof PDO) {
    t_assert(false, 'Database connection (PDO) is available');
    t_result(0, 1, 0);
    return;
}

ensureMinimalTables($pdo);

require_once dirname(__DIR__) . '/includes/enhanced_donor_management.php';

$date = '2025-01-01';
$stmt = $pdo->prepare("INSERT INTO donors (first_name, last_name, email, blood_type, status, created_at) VALUES (?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
$stmt->execute(['Unit', 'Flow', 'unit_id_test@example.com', 'A+']);
$donorId = (int)$pdo->lastInsertId();

ob_start();
$rv = markDonorServed($pdo, $donorId, $date, $_SESSION['admin_id'] ?? null);
ob_end_clean();

$ok = is_array($rv) && !empty($rv['success']);
t_assert($ok, 'markDonorServed returns success=true');

$expectedUnit = 'UNIT-' . 'A+' . '-' . str_replace('-', '', $date) . '-' . str_pad((string)$donorId, 5, '0', STR_PAD_LEFT);
t_assert(($rv['unit_id'] ?? '') === $expectedUnit, 'unit_id formatted correctly');

$q = $pdo->prepare('SELECT COUNT(*) FROM blood_inventory WHERE unit_id = ?');
$q->execute([$expectedUnit]);
$countUnits = (int)$q->fetchColumn();
t_assert($countUnits === 1, 'blood_inventory contains exactly one row for unit');

$qd = $pdo->prepare('SELECT status, served_date FROM donors WHERE id = ?');
$qd->execute([$donorId]);
$donorRow = $qd->fetch(PDO::FETCH_ASSOC) ?: [];
$servedDateStr = isset($donorRow['served_date']) ? (string)$donorRow['served_date'] : '';
$servedDateNorm = preg_match('/^\d{4}-\d{2}-\d{2}/', $servedDateStr, $m) ? $m[0] : '';
t_assert(($donorRow['status'] ?? '') === 'served', 'donor status updated to served');
t_assert($servedDateNorm === $date, 'served_date set to donation date');

$qd2 = $pdo->prepare("SELECT COUNT(*) FROM donations_new WHERE donor_id = ? AND donation_date = ? AND status = 'completed'");
$qd2->execute([$donorId, $date]);
$donationCount = (int)$qd2->fetchColumn();
t_assert($donationCount >= 1, 'donations_new has a completed record');

ob_start();
$rv2 = markDonorServed($pdo, $donorId, $date, $_SESSION['admin_id'] ?? null);
ob_end_clean();
$q2 = $pdo->prepare('SELECT COUNT(*) FROM blood_inventory WHERE unit_id = ?');
$q2->execute([$expectedUnit]);
$countUnitsAgain = (int)$q2->fetchColumn();
t_assert($countUnitsAgain === 1, 're-serving does not duplicate blood unit');

$stmt2 = $pdo->prepare("INSERT INTO donors (first_name, last_name, email, blood_type, status, created_at) VALUES (?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
$stmt2->execute(['Unknown', 'Type', 'unknown_type_test@example.com', '']);
$donorId2 = (int)$pdo->lastInsertId();

ob_start();
$rv3 = markDonorServed($pdo, $donorId2, $date, $_SESSION['admin_id'] ?? null);
ob_end_clean();

$expectedUnit2 = 'UNIT-UNK-' . str_replace('-', '', $date) . '-' . str_pad((string)$donorId2, 5, '0', STR_PAD_LEFT);
t_assert(is_array($rv3) && !empty($rv3['success']), 'markDonorServed succeeds for blank blood_type');
t_assert(($rv3['unit_id'] ?? '') === $expectedUnit2, 'unit_id uses UNK fallback for blank blood_type');

$q3 = $pdo->prepare('SELECT COUNT(*) FROM blood_inventory WHERE unit_id = ?');
$q3->execute([$expectedUnit2]);
$countUnits3 = (int)$q3->fetchColumn();
t_assert($countUnits3 === 1, 'blood_inventory row exists for UNK unit');

t_result();

?>