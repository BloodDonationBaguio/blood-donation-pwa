<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/utils.php';

t_section('Inventory Status Invariants');

function hasTable($pdo, $table) { try { $pdo->query("SELECT 1 FROM {$table} LIMIT 1"); return true; } catch (Throwable $e) { return false; } }
function columnExists($pdo, $table, $column) {
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$table, $column]);
        return ((int)$q->fetchColumn()) > 0;
    } catch (Throwable $e) { return false; }
}

$allowedStatuses = ['available','used','expired','quarantined'];

// 1) Inventory statuses should be within allowed set
try {
    if (!hasTable($pdo, 'blood_inventory')) { t_skip('No blood_inventory table; skipping status checks.'); }
    else {
        $distinct = $pdo->query("SELECT DISTINCT status FROM blood_inventory")->fetchAll(PDO::FETCH_COLUMN);
        $invalid = [];
        foreach ($distinct as $s) {
            $v = strtolower(trim((string)$s));
            if (!in_array($v, $allowedStatuses)) { $invalid[] = $s; }
        }
        if (count($invalid) === 0) { t_pass('All blood_inventory statuses are valid.'); }
        else { t_fail('Invalid blood_inventory statuses detected: ' . json_encode(array_values(array_unique($invalid)))); }
    }
} catch (Throwable $e) { t_fail('Inventory status validation error: ' . $e->getMessage()); }

// 2) Donors not served should not have available inventory units
try {
    $donorTable = null;
    if (hasTable($pdo, 'donors_new')) { $donorTable = 'donors_new'; }
    elseif (hasTable($pdo, 'donors')) { $donorTable = 'donors'; }
    if ($donorTable && columnExists($pdo, $donorTable, 'status') && hasTable($pdo, 'blood_inventory')) {
        $sql = "SELECT COUNT(*) FROM {$donorTable} d JOIN blood_inventory bi ON bi.donor_id = d.id WHERE LOWER(d.status) <> 'served' AND LOWER(bi.status) = 'available'";
        $bad = (int)$pdo->query($sql)->fetchColumn();
        if ($bad === 0) { t_pass('No available units linked to non-served donors.'); }
        else { t_fail("Found {$bad} available units linked to non-served donors."); }
    } else {
        t_skip('Skipping donor-status linkage check due to missing donors/status or inventory.');
    }
} catch (Throwable $e) { t_fail('Donor-status linkage check error: ' . $e->getMessage()); }

// 3) Available units must have known blood type (not unknown/empty)
try {
    if (hasTable($pdo, 'blood_inventory') && columnExists($pdo, 'blood_inventory', 'blood_type')) {
        $sql = "SELECT COUNT(*) FROM blood_inventory WHERE LOWER(status) = 'available' AND (
                    blood_type IS NULL OR TRIM(blood_type) = '' OR UPPER(TRIM(blood_type)) IN ('UNKNOWN','UNK','N/A','NA','?')
                )";
        $cnt = (int)$pdo->query($sql)->fetchColumn();
        if ($cnt === 0) { t_pass('All available units have known blood types.'); }
        else { t_fail("Found {$cnt} available units with unknown/empty blood type."); }
    } else {
        t_skip('Skipping available-unit blood type check due to missing inventory or blood_type column.');
    }
} catch (Throwable $e) { t_fail('Available-unit blood type check error: ' . $e->getMessage()); }

// 4) No future collection_date
try {
    if (hasTable($pdo, 'blood_inventory') && columnExists($pdo, 'blood_inventory', 'collection_date')) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM blood_inventory WHERE collection_date > NOW()")->fetchColumn();
        if ($cnt === 0) { t_pass('No inventory units collected in the future.'); }
        else { t_fail("Found {$cnt} units with collection_date in the future."); }
    } else { t_skip('No collection_date column; skipping future-date check.'); }
} catch (Throwable $e) { t_fail('Future collection_date check error: ' . $e->getMessage()); }

// 5) unit_id uniqueness (no duplicates)
try {
    if (hasTable($pdo, 'blood_inventory') && columnExists($pdo, 'blood_inventory', 'unit_id')) {
        $dups = $pdo->query("SELECT unit_id, COUNT(*) c FROM blood_inventory WHERE COALESCE(TRIM(unit_id),'') <> '' GROUP BY unit_id HAVING c > 1 ORDER BY c DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        if (count($dups) === 0) { t_pass('No duplicate unit_id values in blood_inventory.'); }
        else { t_fail('Duplicate unit_id values detected: ' . json_encode($dups)); }
    } else { t_skip('No unit_id column; skipping uniqueness check.'); }
} catch (Throwable $e) { t_fail('unit_id uniqueness check error: ' . $e->getMessage()); }

?>