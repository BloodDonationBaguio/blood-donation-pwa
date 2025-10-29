<?php
// cron/sync_completed_donors_to_inventory.php
// Synchronize donors marked as 'completed' in donors_new into blood_inventory.

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Local-first DB bootstrap: use db.php only (includes/config.php dies on failure)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $prevDatabaseUrl = getenv('DATABASE_URL') ?: null;
    if ($prevDatabaseUrl) {
        putenv('DATABASE_URL=');
    }
    if (file_exists(__DIR__ . '/../db.php')) {
        require_once __DIR__ . '/../db.php';
    } elseif (file_exists(__DIR__ . '/../db.example.php')) {
        require_once __DIR__ . '/../db.example.php';
    }
    if ($prevDatabaseUrl) {
        putenv('DATABASE_URL=' . $prevDatabaseUrl);
    }
}

// Bail out early if we still don't have a PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Could not connect to MySQL database. Check includes/config.php or db.php settings.\n");
    exit(1);
}

// Define helpers only if not provided by db bootstrap
if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $table): bool {
        try {
            // Prefer explicit schema filter for MySQL/MariaDB
            $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'pgsql') {
                $safe = str_replace("'", "''", $table);
                $stmt = $pdo->query("SELECT to_regclass('public." . $safe . "')");
                return $stmt->fetchColumn() !== null;
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

function mysqlNow(): string {
    return date('Y-m-d H:i:s');
}

// Reliable table existence check used within this script (avoids quoted LIKE bug)
function pdoTableExists(PDO $pdo, string $table): bool {
    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'pgsql') {
            $safe = str_replace("'", "''", $table);
            $stmt = $pdo->query("SELECT to_regclass('public." . $safe . "')");
            return $stmt->fetchColumn() !== null;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
        $stmt->execute([':t' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (Throwable $e2) {
            return false;
        }
    }
}

function ensureUsedDateColumn(PDO $pdo): void {
    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $colCheck = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'blood_inventory' AND column_name = 'used_date'");
        $hasCol = $colCheck && $colCheck->fetchColumn();
        if ($hasCol) {
            return;
        }
        if ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE blood_inventory ADD COLUMN used_date TIMESTAMPTZ NULL");
        } else {
            $pdo->exec("ALTER TABLE blood_inventory ADD COLUMN used_date TIMESTAMP NULL");
        }
    } catch (Throwable $e) {
        error_log('Could not add used_date column: ' . $e->getMessage());
    }
}

function generateUnitId(PDO $pdo, int $donorId): string {
    // Format: PRC-YYYYMMDD-<donorId>-<random4>
    $datePart = date('Ymd');
    $rand = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    return "PRC-$datePart-$donorId-$rand";
}

function getCompletedDonors(PDO $pdo): array {
    // Treat 'completed' and 'served' as eligible in donors_new. If donors table exists with 'served', include it.
    $donors = [];
    try {
        if (pdoTableExists($pdo, 'donors_new')) {
            $stmt = $pdo->query("SELECT id, blood_type, created_at FROM donors_new WHERE status IN ('completed','served')");
            $donors = array_merge($donors, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        // Optional: donors table fallback
        if (pdoTableExists($pdo, 'donors')) {
            $stmt = $pdo->query("SELECT id, blood_type, created_at FROM donors WHERE status = 'served'");
            $donors = array_merge($donors, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } catch (Throwable $e) {
        error_log('Failed to fetch completed/served donors: ' . $e->getMessage());
    }
    return $donors;
}

function bloodInventoryHasDonor(PDO $pdo, int $donorId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blood_inventory WHERE donor_id = ?");
    $stmt->execute([$donorId]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function insertInventoryForDonor(PDO $pdo, array $donor): bool {
    $donorId = (int)$donor['id'];
    $bloodType = $donor['blood_type'] ?? 'Unknown';
    $createdAt = $donor['created_at'] ?? mysqlNow();
    $collectionDate = date('Y-m-d', strtotime($createdAt));
    $expiryDate = date('Y-m-d', strtotime($collectionDate . ' +42 days'));
    $unitId = generateUnitId($pdo, $donorId);

    $sql = "INSERT INTO blood_inventory (
        unit_id, donor_id, blood_type, collection_date, expiry_date,
        status, collection_site, storage_location, notes, used_date, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $unitId,
        $donorId,
        $bloodType,
        $collectionDate,
        $expiryDate,
        'used', // reflect that donor is already served/completed
        'Main Center',
        'Storage A',
        'Auto-synced from donors_new completed/served donor',
        mysqlNow(), // used_date
        mysqlNow(),
        mysqlNow()
    ]);
}

function main(PDO $pdo): void {
    if (!pdoTableExists($pdo, 'blood_inventory')) {
        fwrite(STDERR, "blood_inventory table is missing in the CURRENT DB. Run migrate_simple_blood_inventory.php or ensure DB_NAME matches migrations.\n");
        exit(1);
    }
    ensureUsedDateColumn($pdo);

    $donors = getCompletedDonors($pdo);
    $inserted = 0;
    foreach ($donors as $donor) {
        $donorId = (int)$donor['id'];
        if (bloodInventoryHasDonor($pdo, $donorId)) {
            continue;
        }
        if (insertInventoryForDonor($pdo, $donor)) {
            $inserted++;
        }
    }

    echo "Synced $inserted completed/served donors into blood_inventory.\n";
}

main($pdo);