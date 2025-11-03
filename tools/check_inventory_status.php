<?php
// tools/check_inventory_status.php
// Quick database verification for blood_inventory deletion behavior

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db_production.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "❌ Database connection not established. Check db_production.php configuration.\n");
    exit(1);
}

echo "\n=== Blood Inventory Status Check ===\n";

try {
    // Totals
    $total = (int)$pdo->query("SELECT COUNT(*) AS c FROM blood_inventory")->fetch(PDO::FETCH_ASSOC)['c'];
    // Prefer deleted_at indicator if present; fallback to status='deleted'
    $hasDeletedAt = false;
    try {
        if (function_exists('getTableStructure')) {
            $cols = getTableStructure($pdo, 'blood_inventory');
            foreach ($cols as $col) {
                $name = strtolower($col['column_name'] ?? $col['Field'] ?? '');
                if ($name === 'deleted_at') { $hasDeletedAt = true; break; }
            }
        }
    } catch (Exception $e) { /* ignore */ }
    if ($hasDeletedAt) {
        $deleted = (int)$pdo->query("SELECT COUNT(*) AS c FROM blood_inventory WHERE deleted_at IS NOT NULL")->fetch(PDO::FETCH_ASSOC)['c'];
    } else {
        $deleted = (int)$pdo->query("SELECT COUNT(*) AS c FROM blood_inventory WHERE status = 'deleted'")->fetch(PDO::FETCH_ASSOC)['c'];
    }
    $available = (int)$pdo->query("SELECT COUNT(*) AS c FROM blood_inventory WHERE status = 'available'")->fetch(PDO::FETCH_ASSOC)['c'];
    $used = (int)$pdo->query("SELECT COUNT(*) AS c FROM blood_inventory WHERE status = 'used'")->fetch(PDO::FETCH_ASSOC)['c'];
    $expired = (int)$pdo->query("SELECT COUNT(*) AS c FROM blood_inventory WHERE status = 'expired'")->fetch(PDO::FETCH_ASSOC)['c'];

    echo "Total rows:        {$total}\n";
    echo "Available rows:    {$available}\n";
    echo "Used rows:         {$used}\n";
    echo "Expired rows:      {$expired}\n";
    echo "Deleted rows:      {$deleted}\n";

    // Detect order column
    $orderCol = 'created_at';
    if (function_exists('getTableStructure')) {
        $cols = getTableStructure($pdo, 'blood_inventory');
        foreach ($cols as $col) {
            $name = $col['column_name'] ?? $col['Field'] ?? '';
            if (strtolower($name) === 'updated_at') { $orderCol = 'updated_at'; break; }
        }
    }

    // Show last 10 rows including deleted
    $stmt = $pdo->query("SELECT unit_id, status, donor_id, blood_type, COALESCE(updated_at, created_at) AS ts FROM blood_inventory ORDER BY " . $orderCol . " DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nRecent 10 units (newest first):\n";
    foreach ($rows as $r) {
        echo sprintf("- %s | %s | donor %s | type %s | %s\n", $r['unit_id'], $r['status'], $r['donor_id'], $r['blood_type'], $r['ts']);
    }

    // If a unitId is provided, show its details
    $unitId = $_GET['unit_id'] ?? ($_SERVER['argv'][1] ?? null);
    if ($unitId) {
        $s = $pdo->prepare("SELECT * FROM blood_inventory WHERE unit_id = ?");
        $s->execute([$unitId]);
        $u = $s->fetch(PDO::FETCH_ASSOC);
        echo "\nUnit details for {$unitId}:\n";
        if ($u) {
            foreach ($u as $k => $v) { echo "  $k: " . (is_scalar($v) ? $v : json_encode($v)) . "\n"; }
        } else {
            echo "  Not found.\n";
        }
    }
} catch (Exception $e) {
    fwrite(STDERR, "Error while checking inventory: " . $e->getMessage() . "\n");
    exit(2);
}

echo "\nDone.\n";
?>