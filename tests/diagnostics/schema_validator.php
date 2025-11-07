<?php
// Schema/Column Validator: detects wrong VARCHAR sizes like SQLSTATE[22001]
header('Content-Type: text/html; charset=utf-8');

// Attempt to include project DB
$pdo = null;
$included = null;
$paths = [__DIR__ . '/../../blood-donation-pwa/db.php', __DIR__ . '/../../db.php'];
foreach ($paths as $p) {
    if (file_exists($p)) { $included = $p; require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $url = getenv('DATABASE_URL');
    if ($url) {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') === 'postgres') {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $parts['host'], $parts['port'] ?? 5432, ltrim($parts['path'], '/'));
            $pdo = new PDO($dsn, $parts['user'] ?? '', $parts['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        }
    }
}

if (!($pdo instanceof PDO)) {
    echo '<div style="background:#fff1f0;border:1px solid #ffa39e;padding:10px">Unable to obtain PDO connection. Check DB settings.</div>';
    echo '<p><a href="index.php">Back to Diagnostics</a></p>';
    exit;
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// Expected widths (adjust to your schema names). These cover donors/requests/donations/users and new tables.
$expect = [
    ['table' => 'donors', 'column' => 'blood_type', 'expected_max' => 10],
    ['table' => 'donations', 'column' => 'blood_type', 'expected_max' => 10],
    ['table' => 'requests', 'column' => 'blood_type_needed', 'expected_max' => 10],
    ['table' => 'users', 'column' => 'blood_type', 'expected_max' => 10],
    // Alternate/table variants if used by your app
    ['table' => 'donations_new', 'column' => 'blood_type', 'expected_max' => 10],
    ['table' => 'blood_requests', 'column' => 'blood_type_needed', 'expected_max' => 10],
    ['table' => 'blood_units', 'column' => 'blood_type', 'expected_max' => 10],
];

function getColumnMax(PDO $pdo, string $driver, string $table, string $column): ?int {
    try {
        if ($driver === 'pgsql' || $driver === 'mysql') {
            $sql = "SELECT character_maximum_length FROM information_schema.columns WHERE table_name = :t AND column_name = :c";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':t' => $table, ':c' => $column]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['character_maximum_length'] !== null) {
                return (int)$row['character_maximum_length'];
            }
            return null; // non-character or unlimited
        } elseif ($driver === 'sqlite') {
            $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
            $stmt->execute();
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (strcasecmp($r['name'], $column) === 0) {
                    // type like VARCHAR(10) or TEXT
                    if (preg_match('/varchar\s*\((\d+)\)/i', $r['type'], $m)) {
                        return (int)$m[1];
                    }
                    return null;
                }
            }
            return null;
        }
    } catch (Throwable $t) {
        return null;
    }
    return null;
}

function row($cells) {
    echo '<tr>'; foreach ($cells as $c) { echo '<td style="padding:6px 10px;border-top:1px solid #eee">' . htmlspecialchars($c) . '</td>'; } echo '</tr>';
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Schema/Column Validator</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; }
        .ok { background: #e6ffed; border: 1px solid #b7eb8f; padding: 10px; }
        .warn { background: #fffbe6; border: 1px solid #ffe58f; padding: 10px; }
        .err { background: #fff1f0; border: 1px solid #ffa39e; padding: 10px; }
        table { border-collapse: collapse; border: 1px solid #ddd; min-width: 600px; }
        th { background: #fafafa; text-align: left; padding: 6px 10px; }
    </style>
</head>
<body>
    <h1>Schema/Column Validator</h1>
    <p>Driver: <strong><?php echo htmlspecialchars($driver); ?></strong>. Included: <code><?php echo htmlspecialchars($included ?? 'n/a'); ?></code></p>

    <table>
        <tr>
            <th>Table</th><th>Column</th><th>Expected Max</th><th>Actual Max</th><th>Status</th>
        </tr>
        <?php
        $issues = 0;
        foreach ($expect as $x) {
            $actual = getColumnMax($pdo, $driver, $x['table'], $x['column']);
            $status = 'OK';
            if ($actual === null) {
                $status = 'Unknown/Unlimited';
            } elseif ($actual < $x['expected_max']) {
                $status = 'Mismatch: too small';
                $issues++;
            } elseif ($actual > $x['expected_max']) {
                $status = 'Larger than expected';
            }
            row([$x['table'], $x['column'], (string)$x['expected_max'], (string)($actual ?? 'null'), $status]);
        }
        ?>
    </table>

    <?php if ($issues > 0): ?>
        <div class="err">Detected <?php echo $issues; ?> column(s) with widths smaller than expected. This can cause SQLSTATE[22001] truncation errors.</div>
        <p>Fix: ALTER TABLE to widen to VARCHAR(10). Example (PostgreSQL):<br>
        <code>ALTER TABLE donors ALTER COLUMN blood_type TYPE VARCHAR(10);</code></p>
    <?php else: ?>
        <div class="ok">All tracked columns meet expected widths or are unlimited.</div>
    <?php endif; ?>

    <p><a href="index.php">Back to Diagnostics</a></p>
</body>
</html>