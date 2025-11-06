<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/utils.php';

t_section('Data Integrity: Orphans & Duplicates');

function hasTable($pdo, $table) {
    try { $pdo->query("SELECT 1 FROM {$table} LIMIT 1"); return true; } catch (Throwable $e) { return false; }
}

// Detect donor table
$donorTable = null;
if (hasTable($pdo, 'donors_new')) { $donorTable = 'donors_new'; }
elseif (hasTable($pdo, 'donors')) { $donorTable = 'donors'; }
else { t_skip('No donors table present; skipping donor-based checks.'); }

// 1) Orphaned blood_inventory rows: donor_id set but donor missing
try {
    if ($donorTable) {
        $sql = "SELECT COUNT(*) FROM blood_inventory bi LEFT JOIN {$donorTable} d ON d.id = bi.donor_id WHERE bi.donor_id IS NOT NULL AND d.id IS NULL";
        $orphans = (int)$pdo->query($sql)->fetchColumn();
        if ($orphans === 0) { t_pass('No orphaned blood_inventory units with missing donors.'); }
        else { t_fail("Found {$orphans} orphaned blood_inventory units with missing donors."); }
    } else {
        t_skip('Cannot check orphans without donors table.');
    }
} catch (Throwable $e) { t_fail('Orphan check error: ' . $e->getMessage()); }

// 2) Duplicate donors by email
try {
    if ($donorTable) {
        $dupSql = "SELECT email, COUNT(*) c FROM {$donorTable} WHERE COALESCE(TRIM(email),'') <> '' GROUP BY email HAVING c > 1 ORDER BY c DESC LIMIT 10";
        $dups = $pdo->query($dupSql)->fetchAll(PDO::FETCH_ASSOC);
        if (count($dups) === 0) { t_pass('No duplicate donors by email.'); }
        else {
            t_fail('Duplicate donors by email detected. Top samples: ' . json_encode($dups));
        }
    }
} catch (Throwable $e) { t_fail('Duplicate email check error: ' . $e->getMessage()); }

// 3) Duplicate donors by phone
try {
    if ($donorTable) {
        $dupSql = "SELECT phone, COUNT(*) c FROM {$donorTable} WHERE COALESCE(TRIM(phone),'') <> '' GROUP BY phone HAVING c > 1 ORDER BY c DESC LIMIT 10";
        $dups = $pdo->query($dupSql)->fetchAll(PDO::FETCH_ASSOC);
        if (count($dups) === 0) { t_pass('No duplicate donors by phone.'); }
        else {
            t_fail('Duplicate donors by phone detected. Top samples: ' . json_encode($dups));
        }
    }
} catch (Throwable $e) { t_fail('Duplicate phone check error: ' . $e->getMessage()); }

// 4) blood_inventory: donor_id must be non-null for non-virtual units
try {
    $nullDonorUnits = (int)$pdo->query("SELECT COUNT(*) FROM blood_inventory WHERE donor_id IS NULL")->fetchColumn();
    if ($nullDonorUnits === 0) { t_pass('All blood_inventory units have donor_id set.'); }
    else { t_fail("Found {$nullDonorUnits} blood_inventory units with NULL donor_id."); }
} catch (Throwable $e) { t_fail('Null donor_id check error: ' . $e->getMessage()); }

?>