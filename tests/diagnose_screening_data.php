<?php
// Diagnostic tool to inspect a donor's medical screening data and the derived summaries.
// Usage:
// - Browser: /tests/diagnose_screening_data.php?id=123
// - Or by reference code: /tests/diagnose_screening_data.php?ref=DBR-XXXXXX

// Robust DB include (prefer production config if available)
$dbIncluded = false;
foreach ([__DIR__ . '/../db_production.php', __DIR__ . '/../db.php', __DIR__ . '/../blood-donation-pwa/db.php'] as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $dbIncluded = true;
        break;
    }
}
if (!$dbIncluded) {
    http_response_code(500);
    echo 'Database configuration not found.';
    exit;
}

function getPdo(): PDO {
    // Reuse global $pdo if provided by included DB config; otherwise create from globals
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }
    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
        return $GLOBALS['db'];
    }
    // Fallback: try constructing from typical constants (if defined)
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
    throw new RuntimeException('No PDO instance available.');
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (Throwable $e2) {
            return false;
        }
    }
}

function resolveDonorsTable(PDO $pdo): string {
    return tableExists($pdo, 'donors_new') ? 'donors_new' : 'donors';
}

function loadMedicalQuestions(): array {
    // Prefer consolidated questions file if present
    $paths = [
        __DIR__ . '/../includes/medical_questions.php',
        __DIR__ . '/../includes/medical_questions_new.php',
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            $questions = include $file;
            if (is_array($questions)) {
                return $questions;
            }
        }
    }
    return [];
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function getParam(string $name, $default = null) {
    return $_GET[$name] ?? $default;
}

function getDonorByIdOrRef(PDO $pdo, int $id = 0, ?string $ref = null): ?array {
    $table = resolveDonorsTable($pdo);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { return $row; }
    }
    if ($ref) {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE reference_code = ?");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { return $row; }
    }
    return null;
}

function fetchScreeningRows(PDO $pdo, int $donorId): array {
    $rows = [
        'simple' => null,
        'fixed' => null,
    ];
    if (tableExists($pdo, 'donor_medical_screening_simple')) {
        $stmt = $pdo->prepare('SELECT * FROM donor_medical_screening_simple WHERE donor_id = ?');
        $stmt->execute([$donorId]);
        $rows['simple'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (tableExists($pdo, 'donor_medical_screening_fixed')) {
        $stmt = $pdo->prepare('SELECT * FROM donor_medical_screening_fixed WHERE donor_id = ?');
        $stmt->execute([$donorId]);
        $rows['fixed'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    return $rows;
}

function decodeJson($json) {
    if (!is_string($json) || $json === '') { return null; }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}

function normalizeScreeningData(?array $simpleRow, ?array $fixedRow, ?array $fallbackDonorScreening): array {
    // Priority: simple -> fixed -> donor.screening_data -> empty
    $data = null;
    if ($simpleRow && !empty($simpleRow['screening_data'])) {
        $data = decodeJson($simpleRow['screening_data']);
    }
    if (!$data && $fixedRow && !empty($fixedRow['screening_data'])) {
        $data = decodeJson($fixedRow['screening_data']);
    }
    if (!$data && $fallbackDonorScreening) {
        $data = $fallbackDonorScreening;
    }
    return is_array($data) ? $data : [];
}

function computeSummary(array $questions, array $screeningData, string $gender): array {
    $yes = 0; $no = 0; $na = 0; $dates = 0;
    foreach ($questions as $sectionKey => $section) {
        if (!isset($section['questions']) || !is_array($section['questions'])) { continue; }
        foreach ($section['questions'] as $qKey => $qDef) {
            $key = is_string($qKey) ? $qKey : (isset($qDef['key']) ? $qDef['key'] : null);
            if (!$key) { continue; }
            if (in_array($key, ['q34','q37'], true)) {
                // Female-only date questions, skip for male
                if (strtolower($gender) !== 'female') { continue; }
                $dateKey = $key . '_date';
                if (!empty($screeningData[$dateKey])) { $dates++; } else { $na++; }
                continue;
            }
            $val = $screeningData[$key] ?? null;
            if ($val === 'yes') { $yes++; }
            elseif ($val === 'no') { $no++; }
            else { $na++; }
        }
    }
    return [
        'yes' => $yes,
        'no' => $no,
        'not_answered' => $na,
        'dates' => $dates,
        'completed' => $na === 0, // simplistic check; UI may allow NA for skipped female-only
    ];
}

function deriveConditions(array $questions, array $screeningData): array {
    $conditions = [];
    // Prefer explicit chronic_illnesses section if present
    if (isset($questions['chronic_illnesses']['questions'])) {
        foreach ($questions['chronic_illnesses']['questions'] as $qKey => $qDef) {
            $key = is_string($qKey) ? $qKey : (isset($qDef['key']) ? $qDef['key'] : null);
            $text = is_array($qDef) ? ($qDef['text'] ?? $qDef['question'] ?? '') : (string)$qDef;
            if ($key && ($screeningData[$key] ?? null) === 'yes') {
                $conditions[] = $text ?: $key;
            }
        }
    } else {
        // Fallback: scan typical q26–q32 for chronic conditions
        foreach (range(26, 32) as $n) {
            $key = 'q' . $n;
            if (($screeningData[$key] ?? null) === 'yes') {
                $conditions[] = $key;
            }
        }
    }
    return $conditions;
}

function deriveMedications(array $screeningData): array {
    $meds = [];
    if (($screeningData['q22'] ?? null) === 'yes') { $meds[] = 'Medication affecting bleeding/clotting'; }
    if (($screeningData['q7'] ?? null) === 'yes') { $meds[] = 'Recent medication or vaccine'; }
    if (($screeningData['q6'] ?? null) === 'yes') { $meds[] = 'Aspirin or NSAIDs (last 3 days)'; }
    return $meds;
}

function fmtDate(?string $date): string {
    if (!$date) { return ''; }
    $ts = strtotime($date);
    return $ts ? date('M d, Y', $ts) : $date;
}

$pdo = getPdo();
$donorId = (int) getParam('id', 0);
$refCode = (string) getParam('ref', '');
$donor = getDonorByIdOrRef($pdo, $donorId, $refCode ?: null);

if (!$donor) {
    http_response_code(404);
    echo '<h2>Donor not found</h2>';
    echo '<p>Pass a valid parameter: <code>?id=123</code> or <code>?ref=DBR-XXXXXX</code>.</p>';
    exit;
}

$rows = fetchScreeningRows($pdo, (int)$donor['id']);
$donorScreeningFallback = decodeJson($donor['screening_data'] ?? '') ?: null;
$questions = loadMedicalQuestions();
$data = normalizeScreeningData($rows['simple'], $rows['fixed'], $donorScreeningFallback);

$gender = strtolower((string)($donor['gender'] ?? '')); // male/female
$summary = computeSummary($questions, $data, $gender);
$conditions = deriveConditions($questions, $data);
$medications = deriveMedications($data);

// Basic HTML output for easy inspection
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Screening Diagnostic - Donor <?= h((string)$donor['id']) ?></title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 20px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; }
    .muted { color: #666; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
    .badge-ok { background: #e6ffed; color: #05630d; border: 1px solid #b9f6c5; }
    .badge-warn { background: #fff8e1; color: #8a6d3b; border: 1px solid #ffe08a; }
    .badge-danger { background: #ffe6e6; color: #8a1f1f; border: 1px solid #ffb3b3; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #eee; padding: 8px; text-align: left; }
    th { background: #fafafa; }
    .ans-yes { color: #8a1f1f; font-weight: 600; }
    .ans-no { color: #05630d; }
    .ans-na { color: #666; font-style: italic; }
  </style>
</head>
<body>
  <h1>Screening Diagnostic</h1>
  <div class="grid">
    <div class="card">
      <h3>Donor</h3>
      <div><strong>Name:</strong> <?= h(($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? '')) ?></div>
      <div><strong>Email:</strong> <?= h($donor['email'] ?? '') ?></div>
      <div><strong>Phone:</strong> <?= h($donor['phone'] ?? '') ?></div>
      <div><strong>Gender:</strong> <?= h($donor['gender'] ?? '') ?></div>
      <div><strong>Blood Type:</strong> <?= h($donor['blood_type'] ?? 'Unknown') ?></div>
      <div><strong>Reference Code:</strong> <?= h($donor['reference_code'] ?? '') ?></div>
      <div><strong>Status:</strong> <?= h($donor['status'] ?? '') ?></div>
    </div>
    <div class="card">
      <h3>Screening Summary</h3>
      <div>
        <?php
          $statusBadge = $summary['completed'] ? '<span class="badge badge-ok">Completed</span>' : '<span class="badge badge-warn">Partially Completed</span>';
          echo $statusBadge;
        ?>
      </div>
      <ul>
        <li>Yes: <?= (int)$summary['yes'] ?></li>
        <li>No: <?= (int)$summary['no'] ?></li>
        <li>Not answered: <?= (int)$summary['not_answered'] ?></li>
        <?php if (strtolower($gender) === 'female'): ?>
          <li>Date fields: <?= (int)$summary['dates'] ?> (q34_date, q37_date)</li>
        <?php endif; ?>
      </ul>
      <div><strong>Derived Medical Conditions:</strong>
        <?php if (count($conditions) === 0): ?>
          <span class="muted">None reported</span>
        <?php else: ?>
          <?= h(implode(', ', $conditions)) ?>
        <?php endif; ?>
      </div>
      <div><strong>Derived Medications:</strong>
        <?php if (count($medications) === 0): ?>
          <span class="muted">None reported</span>
        <?php else: ?>
          <?= h(implode(', ', $medications)) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:16px">
    <h3>Raw Screening JSON</h3>
    <?php if (!empty($data)): ?>
      <pre><?= h(json_encode($data, JSON_PRETTY_PRINT)) ?></pre>
    <?php else: ?>
      <div class="muted">No screening data found.</div>
    <?php endif; ?>
    <details style="margin-top: 8px;">
      <summary>Row sources</summary>
      <div><strong>donor_medical_screening_simple:</strong> <?= $rows['simple'] ? 'present' : 'missing' ?></div>
      <div><strong>donor_medical_screening_fixed:</strong> <?= $rows['fixed'] ? 'present' : 'missing' ?></div>
      <div><strong>donor.screening_data:</strong> <?= $donorScreeningFallback ? 'present' : 'missing' ?></div>
    </details>
  </div>

  <div class="card" style="margin-top:16px">
    <h3>Detailed Q&A</h3>
    <table>
      <thead>
        <tr>
          <th style="width: 35%">Question</th>
          <th style="width: 20%">Key</th>
          <th style="width: 20%">Answer</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($questions as $sectionKey => $section): ?>
          <?php if (!isset($section['questions'])) { continue; } ?>
          <tr>
            <td colspan="4" style="background:#f6f8fa;font-weight:600;">Section: <?= h($section['title'] ?? $sectionKey) ?></td>
          </tr>
          <?php foreach ($section['questions'] as $qKey => $qDef): ?>
            <?php
              $key = is_string($qKey) ? $qKey : ($qDef['key'] ?? null);
              $text = is_array($qDef) ? ($qDef['text'] ?? $qDef['question'] ?? '') : (string)$qDef;
              if (!$key) { continue; }
              $notes = '';
              $ansHtml = '<span class="ans-na">not_answered</span>';
              if (in_array($key, ['q34','q37'], true)) {
                  if (strtolower($gender) !== 'female') {
                      $notes = 'Skipped (male donor)';
                  } else {
                      $dateKey = $key . '_date';
                      $dateVal = $data[$dateKey] ?? '';
                      $ansHtml = $dateVal ? '<span class="muted">' . h(fmtDate($dateVal)) . '</span>' : '<span class="ans-na">not_answered</span>';
                      $notes = ($key === 'q34') ? 'Last childbirth' : 'Last menstrual period';
                  }
              } else {
                  $val = $data[$key] ?? null;
                  if ($val === 'yes') { $ansHtml = '<span class="ans-yes">yes</span>'; }
                  elseif ($val === 'no') { $ansHtml = '<span class="ans-no">no</span>'; }
              }
            ?>
            <tr>
              <td><?= h($text ?: $key) ?></td>
              <td><code><?= h($key) ?></code></td>
              <td><?= $ansHtml ?></td>
              <td class="muted"><?= h($notes) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="muted" style="margin-top:12px">Tip: Pass <code>?id=</code> or <code>?ref=</code> in the URL to select a donor.</p>
</body>
</html>

// Root-level wrapper: diagnose screening data across simple and fixed tables
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__DIR__);

function firstExisting(array $paths): ?string {
    foreach ($paths as $p) { if (file_exists($p)) return $p; }
    return null;
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

// Load medical questions (sections and keys) preferring root includes
$questionCandidates = [
    $base . '/includes/medical_questions.php',
    $base . '/blood-donation-pwa/includes/medical_questions.php',
    $base . '/blood-donation-pwa/includes/medical_questions_new.php',
    $base . '/legacy-pwa-4/blood-donation-pwa/includes/medical_questions.php',
    $base . '/__zip_restore/blood-donation-pwa/includes/medical_questions.php',
];
$pickedQuestions = firstExisting($questionCandidates);
$medicalQuestions = $pickedQuestions ? include $pickedQuestions : [];
if (!is_array($medicalQuestions) || empty($medicalQuestions['sections'])) {
    $alt = firstExisting([$base . '/includes/medical_screening_questions.php']);
    $medicalQuestions = $alt ? include $alt : $medicalQuestions;
}
$sections = is_array($medicalQuestions) ? ($medicalQuestions['sections'] ?? $medicalQuestions) : [];

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
      <p class="text-muted">Sections loaded: <?= count($sections) ?> | DB: <code><?= htmlspecialchars(str_replace($base.'/', '', $dbFile)) ?></code></p>
    </div>
  </div>

  <p class="mt-4 text-muted">Use <code>?donor_id=24</code> to specify a donor.</p>
</body>
</html>