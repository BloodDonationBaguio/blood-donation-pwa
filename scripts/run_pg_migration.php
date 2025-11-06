<?php
// scripts/run_pg_migration.php
// Execute a PostgreSQL migration SQL file using the app's PDO connection

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

// Prefer Postgres via DATABASE_URL; otherwise fall back to more lenient db.php
try {
    if (getenv('DATABASE_URL')) {
        require_once $root . '/db_production.php'; // root version expects DATABASE_URL
    } else {
        // Use the more flexible bootstrap that won't die on missing env
        if (file_exists($root . '/blood-donation-pwa/db.php')) {
            require_once $root . '/blood-donation-pwa/db.php';
        } elseif (file_exists($root . '/db.php')) {
            require_once $root . '/db.php';
        } elseif (file_exists($root . '/blood-donation-pwa/db_production.php')) {
            require_once $root . '/blood-donation-pwa/db_production.php';
        }
    }
} catch (Throwable $e) {
    // ignore and check $pdo below
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection not available. Configure DATABASE_URL or DB_* env vars.\n");
    exit(1);
}

// Ensure we're connected to PostgreSQL
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'pgsql') {
        fwrite(STDERR, "Connected driver is '{$driver}'. This migration targets PostgreSQL.\n".
            "Set 'DATABASE_URL' or 'DB_HOST/DB_NAME/DB_USER/DB_PASS' for Postgres and retry.\n");
        exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Could not determine PDO driver: " . $e->getMessage() . "\n");
    exit(1);
}

// Accept optional CLI arg for migration path; default to the align-schema file
$defaultPath = __DIR__ . '/migrations/2025-11-06-align-schema.sql';
$migrationPath = $argv[1] ?? $defaultPath;

if (!file_exists($migrationPath)) {
    fwrite(STDERR, "Migration file not found: {$migrationPath}\n");
    exit(1);
}

$sql = file_get_contents($migrationPath);
if ($sql === false) {
    fwrite(STDERR, "Failed to read migration file: {$migrationPath}\n");
    exit(1);
}

echo "Running migration: {$migrationPath}\n";

try {
    // PDO Postgres supports multiple statements in one exec when separated by semicolons
    $pdo->exec($sql);
    echo "\n✅ Migration applied successfully.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "\n❌ Migration failed: " . $e->getMessage() . "\n");
    // Provide a hint if common issues occur
    fwrite(STDERR, "Hint: Ensure source tables exist and environment connects to PostgreSQL.\n");
    exit(1);
}

// Optional: basic verification
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

echo "\nVerification:\n";
echo "- View blood_inventory_summary: " . (viewExists($pdo, 'blood_inventory_summary') ? 'OK' : 'missing') . "\n";
echo "- View expiring_blood_units: " . (viewExists($pdo, 'expiring_blood_units') ? 'OK' : 'missing') . "\n";
echo "- View admin_audit_log_compat: " . (viewExists($pdo, 'admin_audit_log_compat') ? 'OK' : 'missing') . "\n";
echo "- donors.reference_number: " . (columnExists($pdo, 'donors', 'reference_number') ? 'OK' : 'missing') . "\n";
echo "- admin_users.updated_at: " . (columnExists($pdo, 'admin_users', 'updated_at') ? 'OK' : 'missing') . "\n";
echo "- donor_medical_screening_simple.hemoglobin_level: " . (columnExists($pdo, 'donor_medical_screening_simple', 'hemoglobin_level') ? 'OK' : 'missing') . "\n";
echo "- blood_units.rh_factor: " . (columnExists($pdo, 'blood_units', 'rh_factor') ? 'OK' : 'missing') . "\n";
echo "- donations_new.unit_id: " . (columnExists($pdo, 'donations_new', 'unit_id') ? 'OK' : 'missing') . "\n";
echo "- donations_new.donated_at: " . (columnExists($pdo, 'donations_new', 'donated_at') ? 'OK' : 'missing') . "\n";

exit(0);