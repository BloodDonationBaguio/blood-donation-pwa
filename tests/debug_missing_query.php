<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../db.php';

function q($pdo, $sql, $params = []) {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Lightweight presence probe to avoid hard failures when tables are missing
function table_present(PDO $pdo, string $table): bool {
  try {
    $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
    return true;
  } catch (Throwable $e) {
    return false;
  }
}

echo "\n-- donors_new eligibility/missing --\n";
if (table_present($pdo, 'donors_new')) {
  try {
    $rows = q($pdo, "SELECT COUNT(*) AS c FROM donors_new");
    echo "donors_new rows: " . ($rows[0]['c'] ?? 0) . "\n";
    $eligible = q($pdo, "SELECT COUNT(*) AS c FROM donors_new WHERE status IN ('served','completed')");
    echo "eligible: " . ($eligible[0]['c'] ?? 0) . "\n";
    $missing = q($pdo, "SELECT d.id, d.status FROM donors_new d LEFT JOIN blood_inventory bi ON bi.donor_id = d.id AND bi.status = 'available' WHERE d.status IN ('served','completed') AND bi.id IS NULL ORDER BY d.id DESC LIMIT 10");
    echo "missing rows: " . count($missing) . "\n";
    foreach ($missing as $r) { echo json_encode($r) . "\n"; }
  } catch (Throwable $e) { echo "donors_new checks failed: " . $e->getMessage() . "\n"; }
} else {
  echo "donors_new table not present — skipping donors_new diagnostics.\n";
}

echo "\n-- donors eligibility/missing --\n";
try {
  $rows = q($pdo, "SELECT COUNT(*) AS c FROM donors");
  echo "donors rows: " . ($rows[0]['c'] ?? 0) . "\n";
  // Some deployments use only 'served' in legacy donors; tolerate 'completed' if present
  $eligible = q($pdo, "SELECT COUNT(*) AS c FROM donors WHERE status IN ('served','completed')");
  echo "eligible: " . ($eligible[0]['c'] ?? 0) . "\n";
  $missing = q($pdo, "SELECT d.id, d.status FROM donors d LEFT JOIN blood_inventory bi ON bi.donor_id = d.id AND bi.status = 'available' WHERE d.status IN ('served','completed') AND bi.id IS NULL ORDER BY d.id DESC LIMIT 10");
  echo "missing rows: " . count($missing) . "\n";
  foreach ($missing as $r) { echo json_encode($r) . "\n"; }
} catch (Throwable $e) { echo "donors checks failed: " . $e->getMessage() . "\n"; }

echo "\n-- cross-check baseline used in tests --\n";
try {
  $servedEligible = q($pdo, "SELECT COUNT(*) AS c FROM donors WHERE status = 'served'");
  $availServed = q($pdo, "SELECT COUNT(DISTINCT d.id) AS c FROM donors d JOIN blood_inventory bi ON bi.donor_id = d.id AND bi.status = 'available' WHERE d.status = 'served'");
  echo "served eligible: " . ($servedEligible[0]['c'] ?? 0) . "; donorsWithAvailable: " . ($availServed[0]['c'] ?? 0) . "\n";
} catch (Throwable $e) { echo "baseline check failed: " . $e->getMessage() . "\n"; }

?>