<?php
// Print donors_new status distribution to guide pending filter adjustments
error_reporting(E_ALL);
ini_set('display_errors','1');

try {
    require_once __DIR__ . '/../db_production.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) { require_once __DIR__ . '/../db.php'; }
} catch (Throwable $e) {
    if (!isset($pdo) || !($pdo instanceof PDO)) { @require_once __DIR__ . '/../db.php'; }
}

function tableExists($pdo, $t) {
    try { $pdo->query("SELECT 1 FROM {$t} LIMIT 1"); return true; } catch (Throwable $e) { return false; }
}

if (!tableExists($pdo, 'donors_new')) {
    echo "donors_new table not found\n";
    exit(0);
}

$rows = $pdo->query("SELECT COALESCE(status,'NULL') AS status, COUNT(*) AS c FROM donors_new GROUP BY status ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['status'] . ':' . $r['c'] . "\n";
}