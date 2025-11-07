<?php
/**
 * Database Self-Heal Utility
 *
 * Checks critical tables/columns and attempts to add any that are missing.
 * Designed to support both MySQL/MariaDB and PostgreSQL deployments.
 */

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true
    ]);
}

if (empty($_SESSION['admin_logged_in'])) {
    $configuredToken = getenv('SELF_HEAL_TOKEN') ?: 'SUPER_SECRET_TOKEN';
    $providedToken = $_GET['token'] ?? '';

    if (!empty($configuredToken) && hash_equals($configuredToken, $providedToken)) {
        // Allow access via token when admin session is not available
        $_SESSION['self_heal_token_authenticated'] = true;
    } elseif (!empty($_SESSION['self_heal_token_authenticated'])) {
        // Already validated token this session
    } else {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/database_self_heal.php';
        header('Location: /admin_login.php');
        exit;
    }
}

$driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
$isPostgres = $driver === 'pgsql';
$isSqlite = $driver === 'sqlite';

if ($isPostgres) {
    $schema = 'public';
} elseif ($isSqlite) {
    $schema = 'main';
} else {
    $schema = $pdo->query('SELECT DATABASE()')->fetchColumn();
}

$results = [];

function addResult(&$results, $label, $status, $message)
{
    $results[] = [
        'label' => $label,
        'status' => $status,
        'message' => $message
    ];
}

function sh_table_exists(PDO $pdo, string $table, string $schema, bool $isPostgres, bool $isSqlite): bool
{
    if ($isPostgres) {
        $stmt = $pdo->prepare('SELECT to_regclass(:schema_table)');
        $stmt->execute(['schema_table' => $schema . '.' . $table]);
        return $stmt->fetchColumn() !== null;
    }

    if ($isSqlite) {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table");
        $stmt->execute([':table' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table');
    $stmt->execute(['schema' => $schema, 'table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function sh_column_exists(PDO $pdo, string $table, string $column, string $schema, bool $isPostgres, bool $isSqlite): bool
{
    if ($isSqlite) {
        try {
            $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            return in_array($column, $columns);
        } catch (PDOException $e) {
            // Table probably doesn't exist, which is handled before this function is called.
            return false;
        }
    }

    $sql = 'SELECT 1 FROM information_schema.columns WHERE table_name = :table AND column_name = :column';
    $params = ['table' => $table, 'column' => $column];

    if ($isPostgres) {
        $sql .= ' AND table_schema = :schema';
        $params['schema'] = $schema;
    } else {
        $sql .= ' AND table_schema = :schema';
        $params['schema'] = $schema;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function ensureTable(PDO $pdo, string $table, string $sql, array &$results, string $schema, bool $isPostgres, bool $isSqlite)
{
    if (sh_table_exists($pdo, $table, $schema, $isPostgres, $isSqlite)) {
        addResult($results, "Table: $table", 'ok', 'Already exists.');
        return;
    }

    try {
        $pdo->exec($sql);
        addResult($results, "Table: $table", 'fixed', 'Created missing table.');
    } catch (Throwable $e) {
        addResult($results, "Table: $table", 'error', 'Failed to create table: ' . $e->getMessage());
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition, array &$results, string $schema, bool $isPostgres, bool $isSqlite)
{
    if (!sh_table_exists($pdo, $table, $schema, $isPostgres, $isSqlite)) {
        addResult($results, "Column: $table.$column", 'error', 'Cannot add column because table is missing.');
        return;
    }

    if (sh_column_exists($pdo, $table, $column, $schema, $isPostgres, $isSqlite)) {
        addResult($results, "Column: $table.$column", 'ok', 'Already exists.');
        return;
    }

    try {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        addResult($results, "Column: $table.$column", 'fixed', 'Column added successfully.');
    } catch (Throwable $e) {
        addResult($results, "Column: $table.$column", 'error', 'Failed to add column: ' . $e->getMessage());
    }
}

function ensureVarcharWidth(PDO $pdo, string $table, string $column, int $minLength, array &$results, string $schema, bool $isPostgres, bool $isSqlite)
{
    if (!sh_table_exists($pdo, $table, $schema, $isPostgres, $isSqlite)) {
        addResult($results, "Width: $table.$column", 'error', 'Cannot check width because table is missing.');
        return;
    }

    if (!sh_column_exists($pdo, $table, $column, $schema, $isPostgres, $isSqlite)) {
        addResult($results, "Width: $table.$column", 'info', 'Column missing—skipped width check.');
        return;
    }

    if ($isSqlite) {
        addResult($results, "Width: $table.$column", 'info', 'SQLite uses dynamic typing; width check skipped.');
        return;
    }

    try {
        if ($isPostgres) {
            $stmt = $pdo->prepare(
                'SELECT data_type, character_maximum_length FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column'
            );
            $stmt->execute(['schema' => $schema, 'table' => $table, 'column' => $column]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $len = $row['character_maximum_length'] ?? null;

            if ($len !== null && (int)$len < $minLength) {
                $pdo->exec("ALTER TABLE $table ALTER COLUMN $column TYPE VARCHAR($minLength)");
                addResult($results, "Width: $table.$column", 'fixed', "Increased to VARCHAR($minLength).");
                return;
            }
        } else {
            // MySQL/MariaDB
            $stmt = $pdo->prepare(
                'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column'
            );
            $stmt->execute(['schema' => $schema, 'table' => $table, 'column' => $column]);
            $len = $stmt->fetchColumn();
            if ($len !== false && (int)$len < $minLength) {
                $pdo->exec("ALTER TABLE $table MODIFY $column VARCHAR($minLength)");
                addResult($results, "Width: $table.$column", 'fixed', "Increased to VARCHAR($minLength).");
                return;
            }
        }
        addResult($results, "Width: $table.$column", 'ok', 'Sufficient width.');
    } catch (Throwable $e) {
        addResult($results, "Width: $table.$column", 'error', 'Failed to adjust width: ' . $e->getMessage());
    }
}

// Ensure supporting tables exist (definitions vary per driver)
$donorNotesSql = $isPostgres
    ? "CREATE TABLE IF NOT EXISTS donor_notes (
            id SERIAL PRIMARY KEY,
            donor_id INT NOT NULL,
            note TEXT NOT NULL,
            created_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    : "CREATE TABLE IF NOT EXISTS donor_notes (
id SERIAL PRIMARY KEY,
            donor_id INT NOT NULL,
            note TEXT NOT NULL,
            created_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$donationsNewSql = $isPostgres
    ? "CREATE TABLE IF NOT EXISTS donations_new (
            id SERIAL PRIMARY KEY,
            donor_id INT NOT NULL,
            donation_date DATE NOT NULL,
            blood_type VARCHAR(10),
            units_donated INT DEFAULT 1,
            status VARCHAR(20) DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    : "CREATE TABLE IF NOT EXISTS donations_new (
id SERIAL PRIMARY KEY,
            donor_id INT NOT NULL,
            donation_date DATE NOT NULL,
            blood_type VARCHAR(10),
            units_donated INT DEFAULT 1,
            status ENUM('scheduled','completed','cancelled') DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_donor_id (donor_id),
            INDEX idx_donation_date (donation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$adminAuditSql = $isPostgres
    ? "CREATE TABLE IF NOT EXISTS admin_audit_log (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            admin_username VARCHAR(255),
            action_type VARCHAR(255) NOT NULL,
            table_name VARCHAR(255),
            record_id VARCHAR(255),
            description TEXT,
            ip_address VARCHAR(64)
        )"
    : "CREATE TABLE IF NOT EXISTS admin_audit_log (
id SERIAL PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            admin_username VARCHAR(255),
            action_type VARCHAR(255) NOT NULL,
            table_name VARCHAR(255),
            record_id VARCHAR(255),
            description TEXT,
            ip_address VARCHAR(64)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$bloodInventorySql = $isPostgres
    ? "CREATE TABLE IF NOT EXISTS blood_inventory (
            id SERIAL PRIMARY KEY,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            donor_id INT NOT NULL,
            blood_type VARCHAR(10) NOT NULL,
            collection_date DATE NOT NULL,
            expiry_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'available',
            collection_site VARCHAR(100) DEFAULT 'Main Center',
            storage_location VARCHAR(50) DEFAULT 'Storage A',
            volume_ml INT DEFAULT 450,
            screening_status VARCHAR(20) DEFAULT 'pending',
            test_results TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            updated_by INT
        )"
    : "CREATE TABLE IF NOT EXISTS blood_inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            donor_id INT NOT NULL,
            blood_type TEXT CHECK(blood_type IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown')) NOT NULL,
            collection_date DATE NOT NULL,
            expiry_date DATE NOT NULL,
            status TEXT CHECK(status IN ('available', 'used', 'expired', 'quarantined')) DEFAULT 'available',
            collection_site VARCHAR(100) DEFAULT 'Main Center',
            storage_location VARCHAR(50) DEFAULT 'Storage A',
            volume_ml INT DEFAULT 450,
            screening_status TEXT CHECK(screening_status IN ('pending', 'passed', 'failed')) DEFAULT 'pending',
            test_results TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            updated_by INT
        )";

$medicalSql = $isPostgres
    ? "CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
            id SERIAL PRIMARY KEY,
            donor_id INT NOT NULL,
            reference_code VARCHAR(50),
            screening_data JSONB,
            all_questions_answered BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    : "CREATE TABLE IF NOT EXISTS donor_medical_screening_simple (
id SERIAL PRIMARY KEY,
            donor_id INT NOT NULL,
            reference_code VARCHAR(50),
            screening_data JSON,
            all_questions_answered TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

ensureTable($pdo, 'donor_notes', $donorNotesSql, $results, $schema, $isPostgres, $isSqlite);
ensureTable($pdo, 'donations_new', $donationsNewSql, $results, $schema, $isPostgres, $isSqlite);
ensureTable($pdo, 'admin_audit_log', $adminAuditSql, $results, $schema, $isPostgres, $isSqlite);
ensureTable($pdo, 'donor_medical_screening_simple', $medicalSql, $results, $schema, $isPostgres, $isSqlite);
ensureTable($pdo, 'blood_inventory', $bloodInventorySql, $results, $schema, $isPostgres, $isSqlite);

// Donors table critical columns
$timestampType = $isPostgres ? 'TIMESTAMP NULL' : 'DATETIME NULL';
$decimalType = $isPostgres ? 'NUMERIC(5,2)' : 'DECIMAL(5,2)';

$donorColumns = [
    'status' => "VARCHAR(20) DEFAULT 'pending'",
    'reference_code' => 'VARCHAR(50)',
    'weight' => $decimalType,
    'height' => $decimalType,
    'rejection_reason' => 'TEXT',
    'unserved_reason' => 'TEXT',
    'served_date' => $timestampType,
    'last_donation_date' => $timestampType,
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => $isPostgres ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
];

foreach ($donorColumns as $column => $definition) {
    ensureColumn($pdo, 'donors', $column, $definition, $results, $schema, $isPostgres, $isSqlite);
}

// Ensure key blood_type fields are wide enough for values like 'Unknown'
ensureVarcharWidth($pdo, 'donors', 'blood_type', 10, $results, $schema, $isPostgres, $isSqlite);
ensureVarcharWidth($pdo, 'donors_new', 'blood_type', 10, $results, $schema, $isPostgres, $isSqlite);
ensureVarcharWidth($pdo, 'donations_new', 'blood_type', 10, $results, $schema, $isPostgres, $isSqlite);
ensureVarcharWidth($pdo, 'blood_requests', 'blood_type', 10, $results, $schema, $isPostgres, $isSqlite);
ensureVarcharWidth($pdo, 'blood_requests', 'blood_type_needed', 10, $results, $schema, $isPostgres, $isSqlite);

// Backfill missing reference_code for existing donors
try {
    if ($isPostgres) {
        $pdo->exec("UPDATE donors SET reference_code = 'DNR-' || substr(md5(random()::text || id::text || coalesce(created_at::text,'')),1,6) WHERE reference_code IS NULL OR reference_code = ''");
    } else {
        $pdo->exec("UPDATE donors SET reference_code = CONCAT('DNR-', SUBSTRING(MD5(RAND()),1,6)) WHERE reference_code IS NULL OR reference_code = ''");
    }
    addResult($results, 'Backfill: donors.reference_code', 'fixed', 'Generated codes for missing references.');
} catch (Throwable $e) {
    addResult($results, 'Backfill: donors.reference_code', 'error', $e->getMessage());
}

// Lightweight tests to ensure critical updates work
try {
    $pdo->query('SELECT status FROM donors LIMIT 1');
    addResult($results, 'Test: donors.status readable', 'ok', 'Column can be queried.');
} catch (Throwable $e) {
    addResult($results, 'Test: donors.status readable', 'error', $e->getMessage());
}

try {
    $stmt = $pdo->prepare('UPDATE donors SET status = status WHERE 1=0');
    $stmt->execute();
    addResult($results, 'Test: donors.status writable', 'ok', 'Update statement prepared successfully.');
} catch (Throwable $e) {
    addResult($results, 'Test: donors.status writable', 'error', $e->getMessage());
}

try {
    $pdo->query('SELECT rejection_reason FROM donors LIMIT 1');
    addResult($results, 'Test: donors.rejection_reason readable', 'ok', 'Column can be queried.');
} catch (Throwable $e) {
    addResult($results, 'Test: donors.rejection_reason readable', 'error', $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Self-Heal Utility</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #b30000; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f4f4f4; text-align: left; }
        .status-ok { color: #2f8f2f; font-weight: bold; }
        .status-fixed { color: #1d6fb8; font-weight: bold; }
        .status-error { color: #c0392b; font-weight: bold; }
        .info { background-color: #fef3c7; border: 1px solid #fcd34d; padding: 10px; border-radius: 4px; }
        .footer { margin-top: 30px; font-size: 0.9rem; color: #555; }
    </style>
</head>
<body>
    <h1>Database Self-Heal Utility</h1>
    <div class="info">
        <p><strong>Driver:</strong> <?= htmlspecialchars($driver) ?></p>
        <p><strong>Schema:</strong> <?= htmlspecialchars($schema ?? 'default') ?></p>
        <p>This tool checks critical tables and columns required for donor management. Missing items are added automatically when possible, and diagnostic tests confirm key queries.</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $row): ?>
            <?php
                $statusClass = 'status-' . $row['status'];
                $statusLabel = strtoupper($row['status']);
            ?>
            <tr>
                <td><?= htmlspecialchars($row['label']) ?></td>
                <td class="<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabel) ?></td>
                <td><?= htmlspecialchars($row['message']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Tip: Run this page after deploying to new environments or when errors mention missing columns/tables. The script is idempotent—safe to re-run anytime.</p>
    </div>
</body>
</html>
