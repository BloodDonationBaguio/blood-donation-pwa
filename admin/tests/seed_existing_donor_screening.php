<?php
// Admin-only: Seed screening answers for an existing donor by id or reference.
// Requires admin login. Usage:
//   /admin/tests/seed_existing_donor_screening.php?id=24
//   /admin/tests/seed_existing_donor_screening.php?ref=DNR-E762E6
// Optional: ?answers=all|partial (default all), ?yes_every=5

// Let shared admin_auth manage the session; avoid duplicate session_start notices
require_once __DIR__ . '/../includes/admin_auth.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo 'Admin login required. Please login and retry.';
    exit;
}

// DB include via admin config (reuses central env-aware db.php)
require_once __DIR__ . '/../includes/db.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    echo 'Database connection not available.';
    exit;
}

// Prefer current donors table over legacy donors_new when both exist
function donorsTable(PDO $pdo): string {
    if (function_exists('tableExists')) {
        if (tableExists($pdo, 'donors')) {
            return 'donors';
        }
        if (tableExists($pdo, 'donors_new')) {
            return 'donors_new';
        }
    }
    return 'donors';
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ref = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';
$mode = strtolower($_GET['answers'] ?? 'all');
$yesEvery = max(2, (int)($_GET['yes_every'] ?? 5));

if ($id <= 0 && $ref === '') { http_response_code(400); echo 'Provide ?id=<number> or ?ref=DNR-XXXXXX'; exit; }

// Load donor, checking both donors and donors_new like the diagnostic script
$donor = null;
foreach (['donors', 'donors_new'] as $tbl) {
    try {
        if (function_exists('tableExists') && !tableExists($pdo, $tbl)) {
            continue;
        }
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM `{$tbl}` WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM `{$tbl}` WHERE reference_code = ?");
            $stmt->execute([$ref]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { $donor = $row; break; }
    } catch (Throwable $e) {
        error_log('seed_existing_donor_screening donor lookup failed on ' . $tbl . ': ' . $e->getMessage());
    }
}
unset($stmt, $row);

if (!$donor) { http_response_code(404); echo 'Donor not found'; exit; }

// Load questions (resolve from root includes)
$questionsFile = __DIR__ . '/../../includes/medical_questions.php';
if (!file_exists($questionsFile)) { $questionsFile = __DIR__ . '/../includes/medical_questions.php'; }
$questions = include $questionsFile;
$sections = $questions['sections'] ?? [];

$gender = strtolower($donor['gender'] ?? 'male');
$answers = [];
$qIndex = 0;
foreach ($sections as $sectionKey => $section) {
    $qs = $section['questions'] ?? [];
    foreach ($qs as $qKey => $qText) {
        if ($sectionKey === 'female_only' && $gender !== 'female') { continue; }
        if (in_array($qKey, ['q34','q37'], true)) { continue; }
        $qIndex++;
        if ($mode === 'all') {
            $answers[$qKey] = ($qIndex % $yesEvery === 0) ? 'yes' : 'no';
        } else {
            if ($qIndex % 2 === 0) { $answers[$qKey] = 'no'; }
        }
    }
}
if ($gender === 'female') {
    if ($mode === 'all') {
        $answers['q34'] = 'date';
        $answers['q34_date'] = date('Y-m-d', strtotime('-18 months'));
        $answers['q37_date'] = date('Y-m-d', strtotime('-28 days'));
    } else {
        $answers['q34'] = 'none';
    }
}

$required = ($gender === 'female') ? 37 : 32;
$actualAnswered = 0; foreach ($answers as $k => $v) { if ($v !== '' && $v !== null) { $actualAnswered++; } }
$allAnswered = ($mode === 'all') && ($actualAnswered >= ($required - 1));

if (!tableExists($pdo, 'donor_medical_screening_simple')) { http_response_code(500); echo 'Table donor_medical_screening_simple missing'; exit; }

$check = $pdo->prepare('SELECT id FROM donor_medical_screening_simple WHERE donor_id = ?');
$check->execute([(int)$donor['id']]);
$existingId = (int)($check->fetchColumn() ?: 0);

if ($existingId > 0) {
    $upd = $pdo->prepare('UPDATE donor_medical_screening_simple SET screening_data = ?, all_questions_answered = ? WHERE id = ?');
    $upd->execute([json_encode($answers), $allAnswered ? 1 : 0, $existingId]);
    $action = 'updated';
} else {
    $ins = $pdo->prepare('INSERT INTO donor_medical_screening_simple (donor_id, reference_code, screening_data, all_questions_answered) VALUES (?,?,?,?)');
    $ins->execute([(int)$donor['id'], $donor['reference_code'] ?? '', json_encode($answers), $allAnswered ? 1 : 0]);
    $action = 'inserted';
}

?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Admin: Seed Existing Donor Screening</title>
<style>body{font-family:system-ui,sans-serif;margin:20px}.ok{color:#05630d}.warn{color:#8a6d3b}code{background:#f5f5f5;padding:2px 4px;border-radius:3px}</style>
</head>
<body>
  <h2>Screening <?= h($action) ?> for Donor</h2>
  <ul>
    <li><strong>ID:</strong> <?= h($donor['id']) ?></li>
    <li><strong>Reference:</strong> <code><?= h($donor['reference_code'] ?? '') ?></code></li>
    <li><strong>Name:</strong> <?= h(($donor['first_name'] ?? '') . ' ' . ($donor['last_name'] ?? '')) ?></li>
    <li><strong>Gender:</strong> <?= h(ucfirst($gender)) ?></li>
    <li><strong>Answers:</strong> <?= h($actualAnswered) ?> / <?= h($required) ?> (<?= $allAnswered ? '<span class="ok">Completed</span>' : '<span class="warn">Partial</span>' ?>)</li>
  </ul>
  <p><a href="/tests/diagnose_screening_data.php?id=<?= h($donor['id']) ?>">Open diagnostic (root tests)</a></p>
  <p><a href="/admin/tests/diagnose_screening_data.php?id=<?= h($donor['id']) ?>">Open diagnostic (admin)</a></p>
  <p>Tip: Add <code>?answers=partial</code> or <code>?yes_every=4</code> to vary data.</p>
</body>
</html>