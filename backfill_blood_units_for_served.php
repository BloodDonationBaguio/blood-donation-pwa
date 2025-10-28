<?php
// Backfill blood units for already-served donors who don't have units yet
// Usage:
// - CLI: php backfill_blood_units_for_served.php [--dry-run]
// - Web: http://localhost:8000/backfill_blood_units_for_served.php?dry=1

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';

function isCli() {
    return php_sapi_name() === 'cli';
}

// Parse dry-run flag
$dryRun = false;
if (isCli()) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run') { $dryRun = true; }
    }
} else {
    $dryRun = isset($_GET['dry']) && $_GET['dry'] === '1';
}

// Ensure required table exists
try {
    if (!function_exists('tableExists') || !tableExists($pdo, 'blood_inventory')) {
        $msg = 'blood_inventory table does not exist. Please run a migration (e.g., migrate_simple_blood_inventory.php) first.';
        if (isCli()) {
            fwrite(STDERR, "[ERROR] $msg\n");
        } else {
            header('Content-Type: text/plain');
            echo "[ERROR] $msg\n";
        }
        exit(1);
    }
} catch (Exception $e) {
    $msg = 'Unable to verify blood_inventory table: ' . $e->getMessage();
    if (isCli()) {
        fwrite(STDERR, "[ERROR] $msg\n");
    } else {
        header('Content-Type: text/plain');
        echo "[ERROR] $msg\n";
    }
    exit(1);
}

// Resolve donors table (prefer donors_new when available)
$donorTable = 'donors';
try {
    if (function_exists('tableExists') && tableExists($pdo, 'donors_new')) {
        $donorTable = 'donors_new';
    }
} catch (Exception $e) {
    // Default remains 'donors'
}

// Detect optional columns
$servedDateColumnExists = false;
$seedFlagColumnExists = false;
try {
    if (function_exists('getTableStructure')) {
        $structure = getTableStructure($pdo, $donorTable);
        $columns = array_map(function($c){ return strtolower($c['column_name']); }, $structure['columns'] ?? []);
        $servedDateColumnExists = in_array('served_date', $columns, true);
        $seedFlagColumnExists = in_array('seed_flag', $columns, true);
    }
} catch (Exception $e) {
    // Proceed without optional columns
}

// Build query for served donors without units
$conditions = ["status = 'served'"];
if ($seedFlagColumnExists) {
    $conditions[] = '(seed_flag IS NULL OR seed_flag = 0)';
}
$where = implode(' AND ', $conditions);

$query = "SELECT id, first_name, last_name, blood_type" . ($servedDateColumnExists ? ", served_date" : "") . " FROM {$donorTable} WHERE {$where}";

$stmt = $pdo->query($query);
$servedDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inventoryManager = new BloodInventoryManagerComplete($pdo);

$totalServed = count($servedDonors);
$alreadyHaveUnits = 0;
$createdUnits = 0;
$errors = 0;

foreach ($servedDonors as $donor) {
    try {
        // Check if donor already has any units
        $checkStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM blood_inventory WHERE donor_id = ?');
        $checkStmt->execute([$donor['id']]);
        $count = (int)($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        if ($count > 0) {
            $alreadyHaveUnits++;
            continue;
        }

        // Prepare blood unit data
        $collectionDate = date('Y-m-d');
        if ($servedDateColumnExists && !empty($donor['served_date'])) {
            // Use served_date if available
            $collectionDate = date('Y-m-d', strtotime($donor['served_date']));
        }

        $data = [
            'donor_id' => $donor['id'],
            'collection_date' => $collectionDate,
            'collection_site' => 'Main Center',
            'storage_location' => 'Storage A'
        ];

        if ($dryRun) {
            // Dry-run: do not create, just log
            if (isCli()) {
                echo "[DRY] Would create unit for donor #{$donor['id']} ({$donor['first_name']} {$donor['last_name']}) on {$collectionDate}\n";
            }
            continue;
        }

        $result = $inventoryManager->addBloodUnit($data);
        if (!empty($result['success'])) {
            $createdUnits++;
            if (isCli()) {
                echo "[OK] Created unit {$result['unit_id']} for donor #{$donor['id']} ({$donor['first_name']} {$donor['last_name']})\n";
            }
        } else {
            $errors++;
            $msg = $result['message'] ?? 'Unknown error';
            if (isCli()) {
                fwrite(STDERR, "[ERROR] Donor #{$donor['id']}: {$msg}\n");
            }
        }
    } catch (Exception $e) {
        $errors++;
        if (isCli()) {
            fwrite(STDERR, "[ERROR] Donor #{$donor['id']}: " . $e->getMessage() . "\n");
        }
    }
}

$summary = "\nSummary:\n" .
           "Total served donors: {$totalServed}\n" .
           "Already had units: {$alreadyHaveUnits}\n" .
           "Units created: {$createdUnits}\n" .
           "Errors: {$errors}\n";

if (isCli()) {
    echo $summary;
} else {
    header('Content-Type: text/plain');
    echo $summary;
}

?>