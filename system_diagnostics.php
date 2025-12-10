<?php
// system_diagnostics.php
// Comprehensive diagnostics to scan database, tables, queries, and environment.
// Safe, read-only checks with JSON or HTML output.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Manila');
}

// Utility: output format
$format = (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json') ? 'json' : 'html';

// Utility: safe HTML
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Attempt to include a DB connector from common locations
$dbIncludePaths = [];
$hasSupabaseEnv = getenv('SUPABASE_URL') || getenv('SUPABASE_DB_HOST') || getenv('SUPABASE_DB_PASSWORD');
$hasPg = extension_loaded('pdo_pgsql');
$hasMy = extension_loaded('pdo_mysql');

if ($hasSupabaseEnv || ($hasPg && !$hasMy)) {
	$dbIncludePaths = [
		__DIR__ . '/supabase_db.php',
		__DIR__ . '/db.php',
		__DIR__ . '/blood-donation-pwa/db.php',
		__DIR__ . '/db_production.php',
		__DIR__ . '/blood-donation-pwa/db_production.php',
		__DIR__ . '/__zip_restore/blood-donation-pwa/db.php',
		__DIR__ . '/__zip_restore/blood-donation-pwa/db_production.php',
	];
} else {
	$dbIncludePaths = [
		__DIR__ . '/db.php',
		__DIR__ . '/db_production.php',
		__DIR__ . '/blood-donation-pwa/db.php',
		__DIR__ . '/blood-donation-pwa/db_production.php',
		__DIR__ . '/__zip_restore/blood-donation-pwa/db.php',
		__DIR__ . '/__zip_restore/blood-donation-pwa/db_production.php',
	];
}

$includedDb = false;
foreach ($dbIncludePaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $includedDb = true;
        break;
    }
}

if (!$includedDb) {
    die('Unable to locate a database connector (db.php or db_production.php).');
}

// Ensure $pdo exists
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Some db.php files expose connectWithRetry; try to connect if available
    if (function_exists('connectWithRetry')) {
        try {
            $pdo = connectWithRetry();
        } catch (Throwable $e) {
            die('Database connection error: ' . h($e->getMessage()));
        }
    } else {
        die('Database connection ($pdo) not initialized by included db script.');
    }
}

$driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

// Helper: tableExists wrapper (use project function if present)
function table_exists(PDO $pdo, string $table): bool {
    if (function_exists('tableExists')) {
        try { return tableExists($pdo, $table); } catch (Throwable $e) { return false; }
    }
    try {
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT to_regclass(:t) IS NOT NULL AS exists");
            $stmt->execute([':t' => $table]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($row) && (bool)$row['exists'];
        } elseif ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        } elseif ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:t");
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

// Helper: get columns
function table_columns(PDO $pdo, string $table): array {
    if (function_exists('getTableStructure')) {
        try {
            $structure = getTableStructure($pdo, $table);
            if (isset($structure['columns']) && is_array($structure['columns'])) {
                return array_map('strtolower', array_map('strval', $structure['columns']));
            }
        } catch (Throwable $e) {
            // fallthrough
        }
    }
    try {
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :t");
            $stmt->execute([':t' => $table]);
            return array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } elseif ($driver === 'mysql') {
            $stmt = $pdo->query("DESCRIBE `" . str_replace('`', '``', $table) . "`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(fn($r) => strtolower((string)$r['Field']), $rows);
        } elseif ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info('" . str_replace("'", "''", $table) . "')");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(fn($r) => strtolower((string)$r['name']), $rows);
        }
    } catch (Throwable $e) {
        // ignore
    }
    return [];
}

// Helper: run scalar COUNT(*) query safely
function count_rows(PDO $pdo, string $table): array {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM " . $table . "");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['ok' => true, 'count' => (int)($row['cnt'] ?? 0)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Helper: run generic SELECT 1 FROM <name> LIMIT 1 to test views or tables
function probe_one(PDO $pdo, string $name): array {
    try {
        $stmt = $pdo->query("SELECT 1 FROM " . $name . " LIMIT 1");
        $stmt->fetch();
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Expected tables and key columns (minimal set; adapt across environments)
// Exclude blood_requests-related tables/views as the system no longer handles requests
$expectedTables = [
    'admin_users' => ['id','username','password','role','created_at','updated_at'],
    'donors' => ['id','reference_number','first_name','last_name','email','status','created_at'],
    'donor_medical_screening_simple' => ['id','donor_id','hemoglobin_level','blood_pressure','medical_condition','created_at'],
    // Align to current schema: notifications uses is_read without type
    'notifications' => ['id','user_id','message','is_read','created_at'],
    'blood_units' => ['id','donor_id','blood_type','rh_factor','collection_date','status'],
    'blood_inventory' => ['id','unit_id','blood_type','status','expiry_date','created_at'],
    'users_new' => ['id','name','email','password','role','status','created_at'],
    'user_remember_tokens' => ['id','user_id','token','expires_at','created_at'],
    'donations_new' => ['id','donor_id','unit_id','status','donated_at'],
    // Align to current schema: admin_audit_log uses admin_username/action_type/etc.
    'admin_audit_log' => ['id','created_at','admin_username','action_type','table_name','record_id','description','ip_address'],
    // Align to current schema: blood_inventory_audit uses timestamp and value diffs
    'blood_inventory_audit' => ['id','unit_id','action','timestamp','old_values','new_values','admin_name','ip_address','user_agent'],
    // legacy/common names that appear in code
    'users' => ['id'],
    'admins' => ['id'],
];

// Views commonly defined in migration scripts
$expectedViews = [
    'blood_inventory_summary',
    'expiring_blood_units',
];

// Environment and extensions
$envInfo = [
    'php_version' => PHP_VERSION,
    'driver' => $driver,
    'extensions' => [
        'pdo' => extension_loaded('PDO'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    ],
    'ini' => [
        'display_errors' => ini_get('display_errors'),
        'log_errors' => ini_get('log_errors'),
        'error_log' => ini_get('error_log'),
    ],
];

// Writable directories check (root and common subdir)
$dirsToCheck = [
    __DIR__ . '/logs',
    __DIR__ . '/uploads',
    __DIR__ . '/cache',
    __DIR__ . '/blood-donation-pwa/logs',
];
// Optional remediation: create missing directories when explicitly requested
$autoFix = isset($_GET['auto_fix']) && ($_GET['auto_fix'] === '1' || strtolower((string)$_GET['auto_fix']) === 'true');
if ($autoFix) {
    foreach ($dirsToCheck as $d) {
        if (!file_exists($d)) {
            @mkdir($d, 0775, true);
            // Attempt to set permissions on non-Windows systems
            if (stripos(PHP_OS_FAMILY ?? php_uname('s'), 'Windows') === false) {
                @chmod($d, 0775);
            }
        }
    }
}
$dirStatus = [];
foreach ($dirsToCheck as $d) {
    $dirStatus[$d] = [
        'exists' => file_exists($d),
        'writable' => file_exists($d) ? is_writable($d) : false,
    ];
}

// Table checks
$tablesReport = [];
foreach ($expectedTables as $table => $columns) {
    $exists = table_exists($pdo, $table);
    $colReport = ['expected' => $columns, 'actual' => [], 'missing' => [], 'extra' => []];
    $countReport = null;
    if ($exists) {
        $actualCols = table_columns($pdo, $table);
        $colReport['actual'] = $actualCols;
        $colReport['missing'] = array_values(array_diff(array_map('strtolower', $columns), $actualCols));
        $colReport['extra'] = array_values(array_diff($actualCols, array_map('strtolower', $columns)));
        $countReport = count_rows($pdo, $table);
    }
    $tablesReport[$table] = [
        'exists' => $exists,
        'columns' => $colReport,
        'count' => $countReport,
    ];
}

// Views probe
$viewsReport = [];
foreach ($expectedViews as $view) {
    $viewsReport[$view] = probe_one($pdo, $view);
}

// Retired feature: Blood Requests — confirm absence and report residuals
$retiredReport = [];
// Tables that should no longer exist
$retiredTables = ['requests', 'blood_requests', 'blood_requests_inventory'];
foreach ($retiredTables as $rt) {
    $retiredReport[$rt] = [
        'type' => 'table',
        'exists' => table_exists($pdo, $rt),
    ];
}
// A possible view named 'requests' may exist; probe generically
$retiredReport['requests_view'] = [
    'type' => 'view',
    'ok' => probe_one($pdo, 'requests')['ok'],
];

// Summarize retired feature status
$retiredFeatureStatus = 'removed';
foreach ($retiredReport as $name => $rr) {
    if (($rr['type'] === 'table' && !empty($rr['exists'])) || ($rr['type'] === 'view' && !empty($rr['ok']))) {
        $retiredFeatureStatus = 'residuals_found';
        break;
    }
}

// Referential integrity checks (orphan detection)
$integrityReport = [];
// donor_notes.donor_id -> donors.id
if (table_exists($pdo, 'donor_notes') && table_exists($pdo, 'donors')) {
    try {
        $stmt = $pdo->query(
            'SELECT COUNT(*) AS orphan_count FROM donor_notes dn '
            . 'LEFT JOIN donors d ON dn.donor_id = d.id '
            . 'WHERE d.id IS NULL'
        );
        $integrityReport['donor_notes.donor_id->donors.id'] = [
            'ok' => true,
            'orphan_count' => (int)$stmt->fetchColumn()
        ];
    } catch (Throwable $e) {
        $integrityReport['donor_notes.donor_id->donors.id'] = [
            'ok' => false,
            'error' => $e->getMessage(),
        ];
    }
}

// Compose final result
$result = [
    'timestamp' => date('c'),
    'environment' => $envInfo,
    'directories' => $dirStatus,
    'database' => [
        'driver' => $driver,
    ],
    'tables' => $tablesReport,
    'views' => $viewsReport,
    'retired_requests' => [
        'status' => $retiredFeatureStatus,
        'artifacts' => $retiredReport,
        'remediation' => 'If residuals are found, run remove_blood_requests_feature.php',
    ],
    'integrity' => $integrityReport,
];

// Output
if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>System Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        h1 { margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        .card { border: 1px solid #ddd; border-radius: 6px; padding: 16px; margin: 12px 0; }
        .ok { color: #0a7; }
        .warn { color: #b70; }
        .err { color: #c00; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #eee; padding: 8px; text-align: left; }
        th { background: #fafafa; }
        code { background: #f7f7f7; padding: 2px 4px; border-radius: 3px; }
    </style>
 </head>
 <body>
    <h1>System Diagnostics</h1>
    <div class="meta">Generated: <?= h($result['timestamp']) ?> | Driver: <?= h($driver) ?> | PHP: <?= h(PHP_VERSION) ?> | <a href="?format=json">JSON</a> | <a href="?auto_fix=1">Auto-fix dirs</a></div>

    <div class="card">
        <h2>Retired Feature: Blood Requests</h2>
        <p>Status: <span class="<?= $result['retired_requests']['status'] === 'removed' ? 'ok' : 'warn' ?>">
            <?= h($result['retired_requests']['status']) ?></span></p>
        <table>
            <thead><tr><th>Name</th><th>Type</th><th>Present</th></tr></thead>
            <tbody>
                <?php foreach ($result['retired_requests']['artifacts'] as $name => $info): ?>
                    <tr>
                        <td><code><?= h($name) ?></code></td>
                        <td><?= h($info['type']) ?></td>
                        <td>
                            <?php if ($info['type'] === 'table'): ?>
                                <span class="<?= $info['exists'] ? 'warn' : 'ok' ?>"><?= $info['exists'] ? 'yes' : 'no' ?></span>
                            <?php else: ?>
                                <span class="<?= $info['ok'] ? 'warn' : 'ok' ?>"><?= $info['ok'] ? 'yes' : 'no' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($result['retired_requests']['status'] !== 'removed'): ?>
            <p class="warn">Residual request artifacts detected. Run <code>remove_blood_requests_feature.php</code> to clean up.</p>
        <?php else: ?>
            <p class="ok">No request-related tables or views detected.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Environment</h2>
        <ul>
            <li>PDO: <span class="<?= $envInfo['extensions']['pdo'] ? 'ok' : 'err' ?>"><?= $envInfo['extensions']['pdo'] ? 'enabled' : 'missing' ?></span></li>
            <li>pdo_pgsql: <span class="<?= $envInfo['extensions']['pdo_pgsql'] ? 'ok' : 'warn' ?>"><?= $envInfo['extensions']['pdo_pgsql'] ? 'enabled' : 'missing' ?></span></li>
            <li>pdo_mysql: <span class="<?= $envInfo['extensions']['pdo_mysql'] ? 'ok' : 'warn' ?>"><?= $envInfo['extensions']['pdo_mysql'] ? 'enabled' : 'missing' ?></span></li>
            <li>pdo_sqlite: <span class="<?= $envInfo['extensions']['pdo_sqlite'] ? 'ok' : 'warn' ?>"><?= $envInfo['extensions']['pdo_sqlite'] ? 'enabled' : 'missing' ?></span></li>
            <li>display_errors: <code><?= h((string)$envInfo['ini']['display_errors']) ?></code></li>
            <li>log_errors: <code><?= h((string)$envInfo['ini']['log_errors']) ?></code></li>
            <li>error_log: <code><?= h((string)$envInfo['ini']['error_log']) ?></code></li>
        </ul>
    </div>

    <div class="card">
        <h2>Directory Status</h2>
        <table>
            <thead><tr><th>Path</th><th>Exists</th><th>Writable</th></tr></thead>
            <tbody>
                <?php foreach ($dirStatus as $path => $st): ?>
                    <tr>
                        <td><code><?= h($path) ?></code></td>
                        <td class="<?= $st['exists'] ? 'ok' : 'err' ?>"><?= $st['exists'] ? 'yes' : 'no' ?></td>
                        <td class="<?= $st['writable'] ? 'ok' : 'warn' ?>"><?= $st['writable'] ? 'yes' : 'no' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Tables</h2>
        <table>
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Exists</th>
                    <th>Count</th>
                    <th>Missing Columns</th>
                    <th>Extra Columns</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tablesReport as $t => $rep): ?>
                    <tr>
                        <td><code><?= h($t) ?></code></td>
                        <td class="<?= $rep['exists'] ? 'ok' : 'err' ?>"><?= $rep['exists'] ? 'yes' : 'no' ?></td>
                        <td>
                            <?php if ($rep['count'] === null): ?>
                                <span class="warn">n/a</span>
                            <?php else: ?>
                                <?php if ($rep['count']['ok']): ?>
                                    <span class="ok"><?= (int)$rep['count']['count'] ?></span>
                                <?php else: ?>
                                    <span class="err">error: <?= h((string)$rep['count']['error']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rep['exists']): ?>
                                <?php $missing = $rep['columns']['missing']; ?>
                                <?php if (empty($missing)): ?>
                                    <span class="ok">none</span>
                                <?php else: ?>
                                    <code><?= h(implode(', ', $missing)) ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="warn">n/a</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rep['exists']): ?>
                                <?php $extra = $rep['columns']['extra']; ?>
                                <?php if (empty($extra)): ?>
                                    <span class="ok">none</span>
                                <?php else: ?>
                                    <code><?= h(implode(', ', $extra)) ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="warn">n/a</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Views</h2>
        <table>
            <thead><tr><th>View</th><th>Status</th><th>Error</th></tr></thead>
            <tbody>
                <?php foreach ($viewsReport as $v => $vr): ?>
                    <tr>
                        <td><code><?= h($v) ?></code></td>
                        <td class="<?= $vr['ok'] ? 'ok' : 'err' ?>"><?= $vr['ok'] ? 'ok' : 'error' ?></td>
                        <td><?php if (!$vr['ok']) echo '<code>' . h((string)$vr['error']) . '</code>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Referential Integrity</h2>
        <table>
            <thead><tr><th>Relation</th><th>Status</th><th>Orphans</th><th>Error</th></tr></thead>
            <tbody>
                <?php foreach ($integrityReport as $rel => $ir): ?>
                    <tr>
                        <td><code><?= h($rel) ?></code></td>
                        <td class="<?= $ir['ok'] ? 'ok' : 'err' ?>"><?= $ir['ok'] ? 'ok' : 'error' ?></td>
                        <td><?php if ($ir['ok']) echo (int)($ir['orphan_count'] ?? 0); else echo '<span class="warn">n/a</span>'; ?></td>
                        <td><?php if (!$ir['ok']) echo '<code>' . h((string)$ir['error']) . '</code>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="meta">All checks are read-only. To extend coverage, add more queries or tables into <code>system_diagnostics.php</code>.</p>
 </body>
 </html>