<?php
// Test: markMultipleDonorsServed bulk flow creates units for multiple donors

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

if (!function_exists('dbDriver')) {
    function dbDriver(PDO $pdo) { try { return strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); } catch (Throwable $e) { return 'mysql'; } }
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

if (!function_exists('ensureMinimalTables')) {
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
}

// Counters
$passed = 0; $failed = 0; $skipped = 0;
t_section('Bulk Served — markMultipleDonorsServed creates units for multiple donors');

if (!isset($pdo) || !$pdo instanceof PDO) {
    t_assert(false, 'Database connection (PDO) is available');
    t_result(0, 1, 0);
    return;
}

ensureMinimalTables($pdo);

require_once dirname(__DIR__) . '/includes/enhanced_donor_management.php';

$date = '2025-01-03';

// Create three approved donors with different blood types
$types = ['O-', 'B+', 'AB-'];
$donorIds = [];
foreach ($types as $i => $bt) {
    $uniqueEmail = 'bulk_donor_' . ($i+1) . '_' . bin2hex(random_bytes(4)) . '@example.com';
    $stmt = $pdo->prepare("INSERT INTO donors (first_name, last_name, email, blood_type, status, created_at) VALUES (?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
    $stmt->execute(['Bulk', 'Donor' . ($i+1), $uniqueEmail, $bt]);
    $donorIds[] = (int)$pdo->lastInsertId();
}

// Run bulk served helper and ignore incidental output
ob_start();
$res = markMultipleDonorsServed($pdo, $donorIds, $date, $_SESSION['admin_id'] ?? null);
ob_end_clean();

// Validate blood_inventory has exactly one unit per donor
$expectUnits = 0;
foreach ($donorIds as $idx => $id) {
    $unit = 'UNIT-' . $types[$idx] . '-' . str_replace('-', '', $date) . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    $q = $pdo->prepare('SELECT COUNT(*) FROM blood_inventory WHERE unit_id = ?');
    $q->execute([$unit]);
    $cnt = (int)$q->fetchColumn();
if (t_assert($cnt === 1, 'bulk: blood_inventory contains unit ' . $unit)) { $passed += 1; } else { $failed += 1; }
    $expectUnits += $cnt;
}
if (t_assert($expectUnits === count($donorIds), 'bulk: created one unit per donor')) { $passed += 1; } else { $failed += 1; }

// Re-run bulk served to verify idempotency (no duplicate units)
ob_start();
markMultipleDonorsServed($pdo, $donorIds, $date, $_SESSION['admin_id'] ?? null);
ob_end_clean();

$actualUnits = 0;
foreach ($donorIds as $idx => $id) {
    $unit = 'UNIT-' . $types[$idx] . '-' . str_replace('-', '', $date) . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    $q = $pdo->prepare('SELECT COUNT(*) FROM blood_inventory WHERE unit_id = ?');
    $q->execute([$unit]);
    $actualUnits += (int)$q->fetchColumn();
}
if (t_assert($actualUnits === $expectUnits, 'bulk: idempotent, no duplicate units on second call')) { $passed += 1; } else { $failed += 1; }

// Summarize
t_result($passed, $failed, $skipped);

// Cleanup inserted test data to avoid impacting other tests
try {
    if (!empty($donorIds)) {
        // Remove inventory units and donation rows for these donors
        $placeholders = implode(',', array_fill(0, count($donorIds), '?'));
        $delInv = $pdo->prepare('DELETE FROM blood_inventory WHERE donor_id IN (' . $placeholders . ')');
        $delInv->execute($donorIds);
        $delDon = $pdo->prepare('DELETE FROM donations_new WHERE donor_id IN (' . $placeholders . ')');
        $delDon->execute($donorIds);
        $delDonors = $pdo->prepare('DELETE FROM donors WHERE id IN (' . $placeholders . ')');
        $delDonors->execute($donorIds);
    }
} catch (Throwable $e) {}

?>