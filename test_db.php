<?php
// test_db.php - Live database connectivity diagnostics (non-sensitive)
// This file intentionally avoids printing credentials. Remove after use.

error_reporting(E_ALL);
ini_set('display_errors', 1);

$startedAt = microtime(true);
$status = [
    'time' => date('c'),
    'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'env'  => [
        'DB_TYPE' => getenv('DB_TYPE') ?: null,
        'DB_HOST_set' => getenv('DB_HOST') ? true : false,
        'DB_NAME' => getenv('DB_NAME') ?: null,
        'DB_USER_set' => getenv('DB_USER') ? true : false,
        'DATABASE_URL_set' => getenv('DATABASE_URL') ? true : false,
    ],
];

// Bring up the app's connection logic
try {
    require_once __DIR__ . '/db.php';
} catch (Throwable $e) {
    $status['error'] = 'Bootstrap failed: ' . $e->getMessage();
}

$driver = null;
$tables = [];
$details = [];
$errors = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $details['driver'] = $driver;
        $details['server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        // List tables depending on driver
        if ($driver === 'pgsql') {
            $stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else { // mysql
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Quick checks
        $checks = [];
        $checkTables = ['admin_users', 'blood_inventory', 'users_new', 'donors', 'requests'];
        foreach ($checkTables as $t) {
            try {
                if ($driver === 'pgsql') {
                    $safe = str_replace("'", "''", $t);
                    $exists = $pdo->query("SELECT to_regclass('public." . $safe . "')") ->fetchColumn() !== null;
                } elseif ($driver === 'sqlite') {
                    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
                    $stmt->execute([$t]);
                    $exists = (bool)$stmt->fetchColumn();
                } else {
                    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
                    $exists = $stmt->rowCount() > 0;
                }
            } catch (Throwable $te) {
                $exists = false;
            }
            $checks[$t] = $exists;
        }

        $details['tables_checked'] = $checks;

        // Try a trivial query
        $pdo->query('SELECT 1');
        $status['connection'] = 'ok';
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
        $status['connection'] = 'error';
    }
} else {
    $status['connection'] = 'no_pdo';
}

$elapsed = round((microtime(true) - $startedAt) * 1000);
$status['duration_ms'] = $elapsed;

// Render HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Diagnostics</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 24px; }
    .ok { color: #0a7d24; font-weight: 600; }
    .err { color: #b00020; font-weight: 600; }
    pre { background: #f7f7f9; padding: 12px; border: 1px solid #e1e1e6; border-radius: 6px; overflow-x: auto; }
    .card { border: 1px solid #e1e1e6; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
  </style>
  </head>
<body>
  <h1>Database Diagnostics</h1>

  <div class="card">
    <h3>Status</h3>
    <p>Connection: <?php echo $status['connection'] === 'ok' ? '<span class="ok">OK</span>' : '<span class="err">' . htmlspecialchars($status['connection']) . '</span>'; ?></p>
    <p>Driver: <?php echo htmlspecialchars($driver ?: 'unknown'); ?></p>
    <p>Duration: <?php echo (int)$status['duration_ms']; ?> ms</p>
  </div>

  <div class="card">
    <h3>Environment Signals</h3>
    <pre><?php echo htmlspecialchars(json_encode($status['env'], JSON_PRETTY_PRINT)); ?></pre>
    <p>Note: Passwords are never displayed.</p>
  </div>

  <div class="card">
    <h3>Tables</h3>
    <?php if ($tables): ?>
      <pre><?php echo htmlspecialchars(implode("\n", $tables)); ?></pre>
    <?php else: ?>
      <p>No tables listed or insufficient permissions.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Key Table Checks</h3>
    <pre><?php echo htmlspecialchars(json_encode($details['tables_checked'] ?? [], JSON_PRETTY_PRINT)); ?></pre>
  </div>

  <?php if ($errors): ?>
  <div class="card">
    <h3>Errors</h3>
    <pre><?php echo htmlspecialchars(implode("\n", $errors)); ?></pre>
  </div>
  <?php endif; ?>

  <p><a href="phpinfo.php">phpinfo()</a> · <a href="admin-login.php">Admin Login</a></p>
</body>
</html>