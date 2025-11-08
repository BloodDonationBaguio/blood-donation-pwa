<?php
// Diagnostics: Inspect admin_users table schema and sample data
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../includes/db.php';

function safeEcho($label, $value) {
    echo $label . ': ' . (is_scalar($value) ? $value : json_encode($value)) . "\n";
}

function table_exists(PDO $pdo, string $table): bool {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = ?) AS exists");
            $stmt->execute([$table]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($row) && ($row['exists'] ?? false);
        } else { // sqlite and others
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        safeEcho('table_exists_error', $e->getMessage());
        return false;
    }
}

function get_columns(PDO $pdo, string $table): array {
    $cols = [];
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            foreach ($pdo->query('DESCRIBE `'.$table.'`') as $row) { $cols[] = strtolower($row['Field']); }
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position");
            $stmt->execute([$table]);
            foreach ($stmt as $row) { $cols[] = strtolower($row['column_name']); }
        } else { // sqlite
            foreach ($pdo->query('PRAGMA table_info('.$table.')') as $row) { $cols[] = strtolower($row['name']); }
        }
    } catch (Exception $e) {
        safeEcho('get_columns_error', $e->getMessage());
    }
    return $cols;
}

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    safeEcho('db_driver', $driver);
    safeEcho('db_connection', 'OK');

    $exists = table_exists($pdo, 'admin_users');
    safeEcho('admin_users_exists', $exists ? 'YES' : 'NO');
    if (!$exists) { exit(0); }

    $columns = get_columns($pdo, 'admin_users');
    safeEcho('columns', $columns);

    $expected = ['password','password_hash','reset_token','reset_token_expiry','is_active','updated_at','last_login'];
    foreach ($expected as $col) {
        safeEcho('has_'.$col, in_array($col, $columns, true) ? 'YES' : 'NO');
    }

    // Row count
    try {
        $cnt = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        safeEcho('admin_users_count', $cnt);
    } catch (Exception $e) {
        safeEcho('count_error', $e->getMessage());
    }

    // Sample rows
    try {
        $selectCols = 'id, username, email';
        if (in_array('is_active', $columns, true)) { $selectCols .= ', is_active'; }
        $stmt = $pdo->query('SELECT ' . $selectCols . ' FROM admin_users ORDER BY id LIMIT 5');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        safeEcho('sample_rows', $rows);
    } catch (Exception $e) {
        safeEcho('sample_rows_error', $e->getMessage());
    }
} catch (Exception $e) {
    safeEcho('fatal_error', $e->getMessage());
}
?>