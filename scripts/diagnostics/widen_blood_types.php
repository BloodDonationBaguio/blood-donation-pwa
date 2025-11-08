<?php
// Safe, idempotent fixer to widen blood_type columns to VARCHAR(10) on Postgres
header('Content-Type: text/html; charset=utf-8');

$included = null;
$paths = [__DIR__ . '/../../db.php', __DIR__ . '/../../blood-donation-pwa/db.php'];
foreach ($paths as $p) { if (file_exists($p)) { $included = $p; require_once $p; break; } }

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo '<div style="background:#fff1f0;border:1px solid #ffa39e;padding:10px">Unable to obtain PDO connection. Check DB settings.</div>';
    exit;
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver !== 'pgsql') {
    echo '<div style="background:#fffbe6;border:1px solid #ffe58f;padding:10px">This fixer is designed for PostgreSQL only. Detected driver: ' . htmlspecialchars($driver) . '</div>';
}

function getColumnMax(PDO $pdo, string $table, string $column): ?int {
    try {
        $sql = "SELECT character_maximum_length FROM information_schema.columns WHERE table_name = :t AND column_name = :c";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':t' => $table, ':c' => $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['character_maximum_length'] !== null) {
            return (int)$row['character_maximum_length'];
        }
        return null; // non-character or unlimited
    } catch (Throwable $t) {
        return null;
    }
}

function ensureWidth10(PDO $pdo, string $table, string $column, array &$log) {
    $max = getColumnMax($pdo, $table, $column);
    if ($max === null) {
        $log[] = "$table.$column: type is non-character or unlimited (no change)";
        return;
    }
    if ($max >= 10) {
        $log[] = "$table.$column: current max=$max (OK)";
        return;
    }
    try {
        $pdo->exec("ALTER TABLE \"$table\" ALTER COLUMN \"$column\" TYPE VARCHAR(10) USING \"$column\"::varchar(10)");
        $after = getColumnMax($pdo, $table, $column);
        if ($after !== null && $after >= 10) {
            $log[] = "$table.$column: widened from $max to $after (SUCCESS)";
        } else {
            $log[] = "$table.$column: attempted widen from $max, but now $after (CHECK)";
        }
    } catch (Throwable $t) {
        $log[] = "$table.$column: ERROR widening — " . $t->getMessage();
    }
}

$log = [];
$targets = [
    ['donors', 'blood_type'],
    ['blood_units', 'blood_type'],
    // Also check common related columns, will no-op if already unlimited
    ['requests', 'blood_type_needed'],
    ['donations', 'blood_type'],
    ['users', 'blood_type'],
    ['donations_new', 'blood_type'],
];

foreach ($targets as [$t, $c]) {
    ensureWidth10($pdo, $t, $c, $log);
}

function row($k, $v) { echo '<tr><th style="text-align:left;padding:6px 10px">' . htmlspecialchars($k) . '</th><td style="padding:6px 10px">' . htmlspecialchars($v) . '</td></tr>'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Widen Blood Type Columns (PostgreSQL)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; }
        .ok { background: #e6ffed; border: 1px solid #b7eb8f; padding: 10px; }
        .warn { background: #fffbe6; border: 1px solid #ffe58f; padding: 10px; }
        .err { background: #fff1f0; border: 1px solid #ffa39e; padding: 10px; }
        table { border-collapse: collapse; border: 1px solid #ddd; }
        th { background: #fafafa; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <h1>Widen Blood Type Columns (PostgreSQL)</h1>
    <p>Included DB config: <code><?php echo htmlspecialchars($included ?? 'n/a'); ?></code></p>

    <h2>Actions</h2>
    <ul>
        <li>Checks current widths for target columns.</li>
        <li>Widens to <code>VARCHAR(10)</code> when actual max &lt; 10.</li>
        <li>Idempotent: safe to re-run; no change if already ≥ 10 or unlimited.</li>
    </ul>

    <h2>Results</h2>
    <table>
        <?php foreach ($log as $line) { row('Status', $line); } ?>
    </table>

    <h2>Manual SQL (psql)</h2>
    <p>If you prefer running SQL manually, execute:</p>
    <pre><code>ALTER TABLE donors ALTER COLUMN blood_type TYPE VARCHAR(10) USING blood_type::varchar(10);
ALTER TABLE blood_units ALTER COLUMN blood_type TYPE VARCHAR(10) USING blood_type::varchar(10);
-- Optional (only if needed):
ALTER TABLE requests ALTER COLUMN blood_type_needed TYPE VARCHAR(10) USING blood_type_needed::varchar(10);
ALTER TABLE donations ALTER COLUMN blood_type TYPE VARCHAR(10) USING blood_type::varchar(10);
ALTER TABLE users ALTER COLUMN blood_type TYPE VARCHAR(10) USING blood_type::varchar(10);
ALTER TABLE donations_new ALTER COLUMN blood_type TYPE VARCHAR(10) USING blood_type::varchar(10);
    </code></pre>

    <p><a href="../../tests/diagnostics/schema_validator.php">Re-run Schema Validator</a> to confirm.</p>
</body>
</html>