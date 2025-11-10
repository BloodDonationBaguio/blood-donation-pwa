<?php
// Seed screening answers for an EXISTING donor by id or reference.
// Usage examples:
//  - /tests/seed_existing_donor_screening.php?id=24
//  - /tests/seed_existing_donor_screening.php?ref=DNR-E762E6
// Optional params:
//  - answers=all|partial (default all)
//  - yes_every=n (mark every nth question as yes; default 5)

$dbIncluded = false;
foreach ([__DIR__ . '/../db_production.php', __DIR__ . '/../db.php', __DIR__ . '/../blood-donation-pwa/db.php'] as $candidate) {
    if (file_exists($candidate)) { require_once $candidate; $dbIncluded = true; break; }
}
if (!$dbIncluded || !isset($pdo)) { http_response_code(500); echo 'DB config missing'; exit; }

function tableExists(PDO $pdo, string $table): bool {
    try { $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'); $stmt->execute([$table]); return (bool)$stmt->fetchColumn(); }
    catch (Throwable $e) { try { $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1"); return true; } catch (Throwable $e2) { return false; } }
}
function donorsTable(PDO $pdo): string { return tableExists($pdo, 'donors_new') ? 'donors_new' : 'donors'; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ref = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';
$mode = strtolower($_GET['answers'] ?? 'all'); // all or partial
$yesEvery = max(2, (int)($_GET['yes_every'] ?? 5));

$table = donorsTable($pdo);
if ($id <= 0 && $ref === '') { http_response_code(400); echo 'Provide ?id=<number> or ?ref=DBR-XXXXXX'; exit; }

// Load donor
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE reference_code = ?");
    $stmt->execute([$ref]);
}
$donor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$donor) { http_response_code(404); echo 'Donor not found'; exit; }

// Load questions
$questions = include __DIR__ . '/../includes/medical_questions.php';
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
            // Set patterned answers: yes every Nth, others no
            $answers[$qKey] = ($qIndex % $yesEvery === 0) ? 'yes' : 'no';
        } else {
            // Partial: answer roughly half
            if ($qIndex % 2 === 0) { $answers[$qKey] = 'no'; }
        }
    }
}
// Female date questions
if ($gender === 'female') {
    if ($mode === 'all') {
        $answers['q34'] = 'date';
        $answers['q34_date'] = date('Y-m-d', strtotime('-18 months'));
        $answers['q37_date'] = date('Y-m-d', strtotime('-28 days'));
    } else {
        $answers['q34'] = 'none'; // explicit none
        // leave q37_date empty
    }
}

// Completion heuristic
$required = ($gender === 'female') ? 37 : 32;
$actualAnswered = 0; foreach ($answers as $k => $v) { if ($v !== '' && $v !== null) { $actualAnswered++; } }
$allAnswered = ($mode === 'all') && ($actualAnswered >= ($required - 1));

// Upsert into donor_medical_screening_simple
if (!tableExists($pdo, 'donor_medical_screening_simple')) {
    http_response_code(500); echo 'Table donor_medical_screening_simple missing'; exit;
}

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
<head><meta charset="utf-8"><title>Seed Existing Donor Screening</title>
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
  <p><a href="/tests/diagnose_screening_data.php?id=<?= h($donor['id']) ?>">Open diagnostic for this donor</a></p>
  <p>Tip: Add <code>?answers=partial</code> or <code>?yes_every=4</code> to vary data.</p>
</body>
</html>