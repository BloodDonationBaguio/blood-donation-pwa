<?php
// Root-level wrapper: diagnose screening data across simple and fixed tables
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__DIR__);
require_once $base . '/blood-donation-pwa/db.php';

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

$donorId = pickDonorId($pdo);

// Load medical questions (sections and keys) from submodule
$primaryQuestions = $base . '/blood-donation-pwa/includes/medical_questions.php';
$fallbackQuestions = $base . '/blood-donation-pwa/includes/medical_questions_new.php';
$medicalQuestions = file_exists($primaryQuestions) ? include $primaryQuestions : [];
if (!is_array($medicalQuestions) || empty($medicalQuestions['sections'])) {
    $medicalQuestions = file_exists($fallbackQuestions) ? include $fallbackQuestions : [];
}
$sections = is_array($medicalQuestions) ? ($medicalQuestions['sections'] ?? []) : [];

// Pick donor table
$donor = null;
$donorTable = 'donors_new';
try { $pdo->query("SELECT id FROM donors_new LIMIT 1"); }
catch (Exception $e) { $donorTable = 'donors'; }
try {
    $st = $pdo->prepare("SELECT * FROM {$donorTable} WHERE id = ?");
    $st->execute([$donorId]);
    $donor = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$donorGender = strtolower($donor['gender'] ?? '');

// Fetch screening rows
$simple = null; $fixed = null;
try {
    $st = $pdo->prepare("SELECT * FROM donor_medical_screening_simple WHERE donor_id = ?");
    $st->execute([$donorId]);
    $simple = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
try {
    $st = $pdo->prepare("SELECT * FROM donor_medical_screening_fixed WHERE donor_id = ?");
    $st->execute([$donorId]);
    $fixed = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Decode simple JSON
$screeningData = [];
$jsonError = null;
if ($simple && !empty($simple['screening_data'])) {
    $screeningData = json_decode($simple['screening_data'], true);
    if (!is_array($screeningData)) { $screeningData = []; $jsonError = json_last_error_msg(); }
}

// Summaries
$yesAnswers = 0; $noAnswers = 0; $notAnswered = 0;
foreach ($sections as $sectionKey => $section) {
    if ($sectionKey === 'female_only' && $donorGender !== 'female') continue;
    foreach ($section['questions'] as $qKey => $qText) {
        $answer = $screeningData[$qKey] ?? 'not_answered';
        if ($qKey === 'q34') {
            $q34Type = $screeningData['q34'] ?? null;
            $q34Date = $screeningData['q34_date'] ?? null;
            if ($q34Type === 'none') { $answer = 'None'; }
            elseif ($q34Type === 'date' && !empty($q34Date)) { $answer = $q34Date; }
            else { $answer = 'not_answered'; }
        } elseif ($qKey === 'q37') {
            $q37Date = $screeningData['q37_date'] ?? null;
            $answer = !empty($q37Date) ? $q37Date : 'not_answered';
        }
        if ($answer === 'yes') $yesAnswers++;
        elseif ($answer === 'no') $noAnswers++;
        else $notAnswered++;
    }
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Diagnose Screening Data</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Diagnose Screening Data</h1>
  <p><strong>Donor ID:</strong> <?= htmlspecialchars((string)$donorId) ?> | <strong>Donor Table:</strong> <?= htmlspecialchars($donorTable) ?> | <strong>Gender:</strong> <?= htmlspecialchars($donor['gender'] ?? 'Unknown') ?></p>

  <div class="row">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">Simple Screening (JSON)</div>
        <div class="card-body">
          <?php if ($simple): ?>
            <p><strong>ID:</strong> <?= htmlspecialchars((string)($simple['id'] ?? '')) ?> | <strong>All Answered:</strong> <?= !empty($simple['all_questions_answered']) ? 'Yes' : 'No' ?></p>
            <?php if ($jsonError): ?><p class="text-danger">JSON Decode Error: <?= htmlspecialchars($jsonError) ?></p><?php endif; ?>
            <details><summary>Raw JSON</summary><pre><?= htmlspecialchars($simple['screening_data'] ?? '') ?></pre></details>
          <?php else: ?>
            <div class="alert alert-warning">No simple screening row found.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">Fixed Screening (column-per-question)</div>
        <div class="card-body">
          <?php if ($fixed): ?>
            <p><strong>ID:</strong> <?= htmlspecialchars((string)($fixed['id'] ?? '')) ?> | <strong>Date:</strong> <?= htmlspecialchars($fixed['screening_date'] ?? '') ?></p>
            <details><summary>Row Data</summary><pre><?php foreach ($fixed as $k=>$v) { echo htmlspecialchars($k) . ': ' . htmlspecialchars((string)$v) . "\n"; } ?></pre></details>
          <?php else: ?>
            <div class="alert alert-info">No fixed screening row found.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Summary (from JSON)</div>
    <div class="card-body">
      <p><strong>Safe (no):</strong> <?= $noAnswers ?> | <strong>Risk (yes):</strong> <?= $yesAnswers ?> | <strong>Not Answered:</strong> <?= $notAnswered ?></p>
      <p class="text-muted">Sections loaded: <?= count($sections) ?></p>
    </div>
  </div>

  <p class="mt-4 text-muted">Use <code>?donor_id=24</code> to specify a donor.</p>
</body>
</html>