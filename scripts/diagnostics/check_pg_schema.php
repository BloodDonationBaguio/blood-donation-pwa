<?php
// scripts/diagnostics/check_pg_schema.php
// Validate that key PostgreSQL views and columns exist after migration

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__, 2); // from scripts/diagnostics -> project root
try {
    if (getenv('DATABASE_URL')) {
        require_once $root . '/db_production.php';
    } else {
        if (file_exists($root . '/blood-donation-pwa/db.php')) {
            require_once $root . '/blood-donation-pwa/db.php';
        } elseif (file_exists($root . '/db.php')) {
            require_once $root . '/db.php';
        } elseif (file_exists($root . '/blood-donation-pwa/db_production.php')) {
            require_once $root . '/blood-donation-pwa/db_production.php';
        }
    }
} catch (Throwable $e) {
    // ignore
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection not available. Configure DATABASE_URL or DB_* env vars.\n");
    exit(1);
}

function viewExists(PDO $pdo, string $view): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM pg_views WHERE schemaname = 'public' AND viewname = ?");
        $stmt->execute([$view]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

$checks = [
    ['type' => 'view',   'name' => 'blood_inventory_summary'],
    ['type' => 'view',   'name' => 'expiring_blood_units'],
    ['type' => 'view',   'name' => 'admin_audit_log_compat'],
    ['type' => 'column', 'table' => 'donors', 'name' => 'reference_number'],
    ['type' => 'column', 'table' => 'admin_users', 'name' => 'updated_at'],
    ['type' => 'column', 'table' => 'donor_medical_screening_simple', 'name' => 'hemoglobin_level'],
    ['type' => 'column', 'table' => 'donor_medical_screening_simple', 'name' => 'blood_pressure'],
    ['type' => 'column', 'table' => 'donor_medical_screening_simple', 'name' => 'medical_condition'],
    ['type' => 'column', 'table' => 'blood_units', 'name' => 'rh_factor'],
    ['type' => 'column', 'table' => 'blood_units', 'name' => 'collection_date'],
    ['type' => 'column', 'table' => 'donations_new', 'name' => 'unit_id'],
    ['type' => 'column', 'table' => 'donations_new', 'name' => 'donated_at'],
];

echo "PostgreSQL schema diagnostics:\n";
$fail = false;
foreach ($checks as $c) {
    if ($c['type'] === 'view') {
        $ok = viewExists($pdo, $c['name']);
        echo "- View " . $c['name'] . ": " . ($ok ? 'OK' : 'missing') . "\n";
        if (!$ok) { $fail = true; }
    } else {
        $ok = columnExists($pdo, $c['table'], $c['name']);
        echo "- Column " . $c['table'] . '.' . $c['name'] . ": " . ($ok ? 'OK' : 'missing') . "\n";
        if (!$ok) { $fail = true; }
    }
}

exit($fail ? 2 : 0);