<?php
// Pending Donors Schema and Query Diagnostics
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/utils.php';

t_section('Pending Donors Schema and Query Diagnostics');

if (!function_exists('columnSet')) {
    function columnSet(PDO $pdo, string $table): array {
        if (function_exists('getTableStructure')) {
            $struct = getTableStructure($pdo, $table);
            $cols = [];
            foreach ($struct as $row) {
                // MySQL DESCRIBE returns 'Field'; Pg/SQLite helpers return 'column_name'
                $name = $row['Field'] ?? $row['column_name'] ?? null;
                if ($name) { $cols[$name] = true; }
            }
            return $cols;
        }
        // Fallback: try information_schema where available
        try {
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ?");
            $stmt->execute([$table]);
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[$row['column_name']] = true;
            }
            return $cols;
        } catch (Throwable $e) {
            return [];
        }
    }
}

function tableExistsPortable(PDO $pdo, string $table): bool {
    return function_exists('tableExists') ? tableExists($pdo, $table) : true; // assume true if helper missing
}

$tables = [];
// Probe available donor tables, prioritizing 'donors_new' then falling back to legacy 'donors'
foreach (['donors_new','donors'] as $t) {
    // Prefer a direct COUNT(*) probe to avoid helper/driver quirks
    try {
        $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        $tables[] = $t;
    } catch (Throwable $e) {
        // not present
    }
}

if (empty($tables)) {
    t_fail('Neither donors_new nor donors table exists in current database.');
    t_result(0, 1, 0);
    return;
}

$expectedUnionCount = 0;
$passed = 0; $failed = 0; $skipped = 0;

foreach ($tables as $t) {
    t_section("-- Inspecting table: {$t} --");
    $cols = columnSet($pdo, $t);
    $hasStatus     = isset($cols['status']);
    $hasCreated    = isset($cols['created_at']) || isset($cols['created']);
    $hasRef        = isset($cols['reference_code']) || isset($cols['reference']);
    $hasBloodType  = isset($cols['blood_type']);

    t_pass("Columns: status=" . ($hasStatus?'yes':'no') . ", created_at=" . ($hasCreated?'yes':'no') . ", reference_code=" . ($hasRef?'yes':'no') . ", blood_type=" . ($hasBloodType?'yes':'no'));

    // Baseline total
    try {
        $total = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        t_pass("Total rows: {$total}");
        $passed += 1;
    } catch (Throwable $e) {
        t_fail("Failed to count rows in {$t}: " . $e->getMessage());
        $failed += 1;
        continue;
    }

    // Derive pending-like condition per admin.php semantics
    if ($hasStatus) {
        $pendingCond = "(status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))";
    } else if ($hasBloodType) {
        $pendingCond = "(blood_type IS NULL OR blood_type IN ('Unknown','UNK'))";
    } else {
        $pendingCond = "1=0"; // cannot infer pending without status or blood_type
    }

    try {
        $pending = (int)$pdo->query("SELECT COUNT(*) FROM {$t} WHERE {$pendingCond}")->fetchColumn();
        t_pass("Pending-like rows: {$pending}");
        $expectedUnionCount += $pending;
        $passed += 1;
    } catch (Throwable $e) {
        t_fail("Failed to count pending-like rows in {$t}: " . $e->getMessage());
        $failed += 1;
    }

    // Reference searchability
    if ($hasRef) {
        try {
            $refNonEmpty = (int)$pdo->query("SELECT COUNT(*) FROM {$t} WHERE COALESCE(reference_code,'') <> ''")->fetchColumn();
            t_pass("Rows with non-empty reference_code: {$refNonEmpty}");
            $passed += 1;
        } catch (Throwable $e) {
            t_fail("Failed to count non-empty reference_code in {$t}: " . $e->getMessage());
            $failed += 1;
        }
    } else {
        t_skip("Table {$t} has no reference_code column.");
        $skipped += 1;
    }
}

// Now run admin-equivalent union query with no search
$unionResults = [];
foreach ($tables as $t) {
    $cols = columnSet($pdo, $t);
    $hasStatus  = isset($cols['status']);
    $hasCreated = isset($cols['created_at']) || isset($cols['created']);
    $order      = $hasCreated ? " ORDER BY created_at DESC" : " ORDER BY id DESC";
    $where      = $hasStatus ? " WHERE (status IS NULL OR status IN ('pending','new','submitted','awaiting_review','in_review'))"
                             : (isset($cols['blood_type']) ? " WHERE (blood_type IS NULL OR blood_type IN ('Unknown','UNK'))" : " WHERE 1=0");
    try {
        $stmt = $pdo->query("SELECT * FROM {$t}{$where}{$order}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $unionResults = array_merge($unionResults, $rows);
    } catch (Throwable $e) {
        t_fail("Union subquery failed for {$t}: " . $e->getMessage());
        $failed += 1;
    }
}

// Sort newest-first coherently across tables
usort($unionResults, function($a, $b) {
    $ta = isset($a['created_at']) ? strtotime($a['created_at']) : (isset($a['id']) ? (int)$a['id'] : 0);
    $tb = isset($b['created_at']) ? strtotime($b['created_at']) : (isset($b['id']) ? (int)$b['id'] : 0);
    return $tb <=> $ta;
});

$unionCount = count($unionResults);
t_pass("Computed union pending count: {$unionCount}");
t_pass("Expected from per-table counts: {$expectedUnionCount}");

// Assert union >= expected (it should match or exceed due to permissive IS NULL checks)
if (t_assert($unionCount >= $expectedUnionCount, 'Union count covers all per-table pending-like rows')) {
    $passed += 1;
} else {
    $failed += 1;
}

// Light sanity: if expectedUnionCount > 0, ensure union results have required fields
if ($expectedUnionCount > 0 && $unionCount > 0) {
    $sample = $unionResults[0];
    $ok = true;
    $ok &= t_assert(isset($sample['email']) || isset($sample['phone']), 'Sample row has contact field');
    $ok &= t_assert(isset($sample['first_name']) || isset($sample['last_name']), 'Sample row has name field');
    if ($ok) { $passed += 2; } else { $failed += 1; }
} else if ($expectedUnionCount === 0) {
    t_skip('No pending-like rows detected — environment likely has no pending donors.');
    $skipped += 1;
}

t_result($passed, $failed, $skipped);

?>