<?php
// Root-level wrapper: invoke submodule endpoint and show donor modal HTML
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__DIR__);
function firstExisting(array $paths): ?string {
    foreach ($paths as $p) { if (file_exists($p)) return $p; }
    return null;
}
// Pick donor id from query, fallback to latest that has screening
function pickDonorId(PDO $pdo): int {
    $donorId = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;
    if ($donorId > 0) return $donorId;
    try {
        $stmt = $pdo->query("SELECT donor_id FROM donor_medical_screening_simple ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['donor_id'])) return (int)$row['donor_id'];
    } catch (Exception $e) {}
    return 0;
}

// Prefer root db.php, then submodule fallbacks
$dbCandidates = [
    $base . '/db.php',
    $base . '/blood-donation-pwa/db.php',
    $base . '/legacy-pwa-4/blood-donation-pwa/db.php',
    $base . '/__zip_restore/blood-donation-pwa/db.php',
];
$dbFile = firstExisting($dbCandidates);
if (!$dbFile) {
    die('<div class="alert alert-danger">Unable to locate db.php. Checked: <code>' . htmlspecialchars(implode('</code>, <code>', array_map(fn($p)=>str_replace($base.'/', '', $p), $dbCandidates))) . '</code></div>');
}
require_once $dbFile;
$donorId = pickDonorId($pdo);

// Prepare GET for the endpoint and include from its directory to preserve relative requires
$endpointDirs = [
    $base . '/blood-donation-pwa',
    $base . '/legacy-pwa-4/blood-donation-pwa',
    $base . '/__zip_restore/blood-donation-pwa',
];
$endpointDir = firstExisting(array_map(fn($d)=> $d . '/simple_ajax_donor_details.php', $endpointDirs));
if (!$endpointDir) {
    die('<div class="alert alert-danger">Unable to locate <code>simple_ajax_donor_details.php</code> under expected directories.</div>');
}
chdir(dirname($endpointDir));
$_GET['action'] = 'get_donor_details';
$_GET['donor_id'] = $donorId;

// Capture endpoint output
ob_start();
include 'simple_ajax_donor_details.php';
$html = ob_get_clean();

?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Test Donor Modal HTML</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Test Donor Modal HTML</h1>
  <p class="text-muted">Donor ID: <?= htmlspecialchars((string)$donorId) ?></p>
  <div class="card">
    <div class="card-header">Endpoint Output</div>
    <div class="card-body">
      <?= $html ?>
    </div>
  </div>
  <p class="mt-2 text-muted">DB: <code><?= htmlspecialchars(str_replace($base.'/', '', $dbFile)) ?></code></p>
  <p class="mt-3 text-muted">Use <code>?donor_id=24</code> to choose a donor.</p>
</body>
</html>