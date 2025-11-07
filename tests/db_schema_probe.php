<?php
// DB schema probe: list donor tables, columns, and pending counts
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/utils.php';
t_section('DB Schema Probe');

$driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

$tables = [];
foreach (['donors_new', 'donors'] as $t) {
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        $tables[$t] = $count;
        t_pass("Table {$t} rows={$count}");
    } catch (Throwable $e) {
        t_fail("Table {$t} missing or error: " . $e->getMessage());
    }
}

foreach (array_keys($tables) as $t) {
    try {
        $cols = [];
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$t})");
            foreach ($stmt->fetchAll() as $row) { $cols[] = $row['name']; }
        } else {
            // MySQL/MariaDB fallback
            $stmt = $pdo->query("DESCRIBE {$t}");
            foreach ($stmt->fetchAll() as $row) { $cols[] = $row['Field'] ?? $row['COLUMN_NAME'] ?? ''; }
        }
        t_pass("Columns in {$t}: " . implode(', ', array_filter($cols)));
    } catch (Throwable $e) {
        t_fail("Cannot introspect {$t}: " . $e->getMessage());
    }
}

// Pending condition across available tables
$expected = 0;
foreach (array_keys($tables) as $t) {
    try {
        // Prefer status column; fallback to blood_type unknown
        $cols = [];
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$t})");
            foreach ($stmt->fetchAll() as $row) { $cols[strtolower($row['name'])] = true; }
        } else {
            $stmt = $pdo->query("DESCRIBE {$t}");
            foreach ($stmt->fetchAll() as $row) { $name = strtolower($row['Field'] ?? $row['COLUMN_NAME'] ?? ''); if ($name) $cols[$name] = true; }
        }
        $hasStatus = isset($cols['status']);
        $hasBloodType = isset($cols['blood_type']);
        $cond = $hasStatus
            ? "(status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))"
            : ($hasBloodType ? "(blood_type IS NULL OR blood_type IN ('Unknown','UNK'))" : '1=0');
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM {$t} WHERE {$cond}")->fetchColumn();
        $expected += $cnt;
        t_pass("Pending candidates in {$t}: {$cnt}");
    } catch (Throwable $e) {
        t_fail("Pending count error in {$t}: " . $e->getMessage());
    }
}

t_pass("Total pending candidates across tables: {$expected}");
echo $t_output;
?>