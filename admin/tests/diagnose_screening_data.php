<?php
// Admin-only diagnostic tool to inspect a donor's screening data using admin DB/session.
// Usage: /admin/tests/diagnose_screening_data.php?id=123 or ?ref=DNR-XXXXXX

session_start();
require_once __DIR__ . '/../includes/admin_auth.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo 'Admin login required. Please login and retry.';
    exit;
}

require_once __DIR__ . '/../includes/db.php';
if (!isset($pdo) || !$pdo instanceof PDO) { http_response_code(500); echo 'Database connection not available.'; exit; }

function tableExists(PDO $pdo, string $table): bool {
    try { $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'); $stmt->execute([$table]); return (bool)$stmt->fetchColumn(); }
    catch (Throwable $e) { try { $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1"); return true; } catch (Throwable $e2) { return false; } }
}
function donorsTable(PDO $pdo): string { return tableExists($pdo, 'donors_new') ? 'donors_new' : 'donors'; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ref = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';
$table = donorsTable($pdo);

if ($id <= 0 && $ref === '') { http_response_code(400); echo 'Provide ?id=<number> or ?ref=DBR-XXXXXX'; exit; }

// Load donor
if ($id > 0) { $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?"); $stmt->execute([$id]); }
else { $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE reference_code = ?"); $stmt->execute([$ref]); }
$donor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$donor) { http_response_code(404); echo 'Donor not found'; exit; }

// Fetch screening rows
$rows = [ 'simple' => null, 'fixed' => null ];
if (tableExists($pdo, 'donor_medical_screening_simple')) {
    $st = $pdo->prepare('SELECT * FROM donor_medical_screening_simple WHERE donor_id = ?');
    $st->execute([(int)$donor['id']]);
    $rows['simple'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (tableExists($pdo, 'donor_medical_screening_fixed')) {
    $st = $pdo->prepare('SELECT * FROM donor_medical_screening_fixed WHERE donor_id = ?');
    $st->execute([(int)$donor['id']]);
    $rows['fixed'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function decodeJson($json) { if (!is_string($json) || $json==='') { return null; } $d=json_decode($json,true); return is_array($d)?$d:null; }
$donorScreening = decodeJson($donor['screening_data'] ?? '') ?: null;

// Load questions
$questionsFile = __DIR__ . '/../../includes/medical_questions.php';
if (!file_exists($questionsFile)) { $questionsFile = __DIR__ . '/../includes/medical_questions.php'; }
$questions = include $questionsFile;
$sections = $questions['sections'] ?? [];

// Normalize data priority: simple -> fixed -> donor.screening_data
$data = null;
if ($rows['simple'] && !empty($rows['simple']['screening_data'])) { $data = decodeJson($rows['simple']['screening_data']); }
if (!$data && $rows['fixed'] && !empty($rows['fixed']['screening_data'])) { $data = decodeJson($rows['fixed']['screening_data']); }
if (!$data && $donorScreening) { $data = $donorScreening; }
$data = is_array($data) ? $data : [];

?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Admin Diagnostic: Donor Screening</title>
<style>body{font-family:system-ui,sans-serif;margin:20px}code{background:#f5f5f5;padding:2px 4px;border-radius:3px}table{border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px}</style>
</head>
<body>
  <h2>Donor</h2>
  <ul>
    <li><strong>ID:</strong> <?= h($donor['id']) ?></li>
    <li><strong>Reference:</strong> <code><?= h($donor['reference_code'] ?? '') ?></code></li>
    <li><strong>Name:</strong> <?= h(($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? '')) ?></li>
    <li><strong>Gender:</strong> <?= h($donor['gender'] ?? '') ?></li>
    <li><strong>Status:</strong> <?= h($donor['status'] ?? '') ?></li>
  </ul>

  <h3>Screening Rows</h3>
  <p><strong>simple:</strong> <?= $rows['simple'] ? 'present' : 'none' ?>, <strong>fixed:</strong> <?= $rows['fixed'] ? 'present' : 'none' ?>, <strong>donor.screening_data:</strong> <?= $donorScreening ? 'present' : 'none' ?></p>

  <h3>Answers (flattened)</h3>
  <table>
    <thead><tr><th>Key</th><th>Value</th></tr></thead>
    <tbody>
      <?php foreach ($data as $k=>$v): ?>
        <tr><td><?= h($k) ?></td><td><?= h(is_array($v)?json_encode($v):$v) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p><a href="/get_medical_screening.php?donor_id=<?= h($donor['id']) ?>">Open API response</a> (should work under admin session)</p>
</body>
</html>