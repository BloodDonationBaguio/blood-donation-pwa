<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/utils.php';

t_section('Blood Type Normalization');

function hasTable($pdo, $table) { try { $pdo->query("SELECT 1 FROM {$table} LIMIT 1"); return true; } catch (Throwable $e) { return false; } }
function columnExists($pdo, $table, $column) {
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$table, $column]);
        return ((int)$q->fetchColumn()) > 0;
    } catch (Throwable $e) { return false; }
}

function normalize_blood_type($val) {
    if ($val === null) return 'UNKNOWN';
    $s = strtoupper(trim((string)$val));
    if ($s === '' || in_array($s, ['UNKNOWN','UNK','N/A','NA','?'])) return 'UNKNOWN';
    $s = str_replace(' ', '', $s);
    $allowed = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    if (in_array($s, $allowed)) return $s;
    return $s; // return raw for inspection
}

// Check donors table values
$donorTable = null;
if (hasTable($pdo, 'donors_new')) { $donorTable = 'donors_new'; }
elseif (hasTable($pdo, 'donors')) { $donorTable = 'donors'; }
else { t_skip('No donors table present; skipping donor normalization checks.'); }

if ($donorTable) {
    $btColumn = null;
    if (columnExists($pdo, $donorTable, 'blood_type')) { $btColumn = 'blood_type'; }
    elseif (columnExists($pdo, $donorTable, 'blood_group')) { $btColumn = 'blood_group'; }
    else { t_skip("Donors table '{$donorTable}' has no blood type/group column."); }

    if ($btColumn) {
        try {
            $values = $pdo->query("SELECT DISTINCT {$btColumn} AS bt FROM {$donorTable}")->fetchAll(PDO::FETCH_COLUMN);
            $invalid = [];
            foreach ($values as $v) {
                $norm = normalize_blood_type($v);
                if (!in_array($norm, ['A+','A-','B+','B-','AB+','AB-','O+','O-','UNKNOWN'])) {
                    $invalid[] = $v;
                }
            }
            if (count($invalid) === 0) { t_pass('All donor blood types normalized to known set.'); }
            else { t_fail('Invalid donor blood type values: ' . json_encode(array_values(array_unique($invalid)))); }
        } catch (Throwable $e) { t_fail('Donor blood type normalization error: ' . $e->getMessage()); }
    }
}

// Check inventory values
if (hasTable($pdo, 'blood_inventory')) {
    // Assume column blood_type exists; if not, skip
    if (!columnExists($pdo, 'blood_inventory', 'blood_type')) { t_skip('blood_inventory has no blood_type column; skipping inventory normalization.'); }
    else {
        try {
            $values = $pdo->query("SELECT DISTINCT blood_type FROM blood_inventory")->fetchAll(PDO::FETCH_COLUMN);
            $invalid = [];
            foreach ($values as $v) {
                $norm = normalize_blood_type($v);
                // For inventory, UNKNOWN should not be present for available units; but at distinct level we just flag invalid tokens
                if (!in_array($norm, ['A+','A-','B+','B-','AB+','AB-','O+','O-','UNKNOWN'])) { $invalid[] = $v; }
            }
            if (count($invalid) === 0) { t_pass('All inventory blood types normalized to known set.'); }
            else { t_fail('Invalid inventory blood type values: ' . json_encode(array_values(array_unique($invalid)))); }
        } catch (Throwable $e) { t_fail('Inventory blood type normalization error: ' . $e->getMessage()); }
    }
} else { t_skip('No blood_inventory table present; skipping inventory normalization.'); }

?>