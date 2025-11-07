<?php
// DB Connection Tester: shows exact SQL/connection errors and DSN info
header('Content-Type: text/html; charset=utf-8');

$details = [
    'included_file' => null,
    'db_type' => null,
    'dsn' => null,
    'status' => 'unknown',
    'error' => null,
    'server_version' => null,
];

function tryIncludeDb(): ?PDO {
    global $details;
    $pdo = null;
    $candidates = [
        __DIR__ . '/../../blood-donation-pwa/db.php',
        __DIR__ . '/../../db.php',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            $details['included_file'] = $path;
            try {
                require_once $path; // should populate $pdo
            } catch (Throwable $t) {
                $details['error'] = 'Include error: ' . $t->getMessage();
            }
            break;
        }
    }
    // $pdo may be set in included db.php
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    // Fallback: try DATABASE_URL env
    $url = getenv('DATABASE_URL');
    if ($url) {
        try {
            $parts = parse_url($url);
            if (isset($parts['scheme']) && $parts['scheme'] === 'postgres') {
                $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $parts['host'], $parts['port'] ?? 5432, ltrim($parts['path'], '/'));
                $pdo = new PDO($dsn, $parts['user'] ?? '', $parts['pass'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $details['dsn'] = $dsn;
                $details['db_type'] = 'pgsql';
                return $pdo;
            }
        } catch (Throwable $t) {
            $details['error'] = 'DATABASE_URL fallback failed: ' . $t->getMessage();
        }
    }
    return null;
}

function runChecks(PDO $pdo) {
    global $details;
    try {
        // Determine DB type via driver
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $details['db_type'] = $driver;
        // Basic query
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();
        $details['status'] = 'connected';
        // Server/version info
        if ($driver === 'pgsql') {
            $details['server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } elseif ($driver === 'mysql') {
            $details['server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } else {
            $details['server_version'] = 'n/a';
        }
        // Optional: probe a known table if present
        try {
            $pdo->query('SELECT COUNT(*) FROM donors');
        } catch (Throwable $t) {
            // Non-fatal
        }
    } catch (Throwable $t) {
        $details['status'] = 'error';
        $details['error'] = $t->getMessage();
    }
}

$pdo = tryIncludeDb();
if ($pdo) {
    runChecks($pdo);
} else {
    $details['status'] = 'error';
    $details['error'] = $details['error'] ?: 'No PDO available from includes and no DATABASE_URL fallback';
}

function row($k, $v) {
    $v = is_string($v) ? $v : json_encode($v);
    echo '<tr><th style="text-align:left; padding:6px 12px;">' . htmlspecialchars($k) . '</th><td style="padding:6px 12px;">' . htmlspecialchars($v) . '</td></tr>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DB Connection Tester</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; }
        .ok { background: #e6ffed; border: 1px solid #b7eb8f; padding: 10px; }
        .err { background: #fff1f0; border: 1px solid #ffa39e; padding: 10px; }
        table { border-collapse: collapse; border: 1px solid #ddd; }
        th { background: #fafafa; }
    </style>
</head>
<body>
    <h1>DB Connection Tester</h1>
    <?php if ($details['status'] === 'connected'): ?>
    <div class="ok">Connected successfully.</div>
    <?php else: ?>
    <div class="err">Error connecting: <?php echo htmlspecialchars($details['error'] ?? 'Unknown error'); ?></div>
    <?php endif; ?>

    <table>
        <?php row('Included file', $details['included_file']); ?>
        <?php row('DB Type (driver)', $details['db_type']); ?>
        <?php row('DSN (if fallback)', $details['dsn']); ?>
        <?php row('Server version', $details['server_version']); ?>
        <?php row('Status', $details['status']); ?>
    </table>

    <p><a href="index.php">Back to Diagnostics</a></p>
</body>
</html>