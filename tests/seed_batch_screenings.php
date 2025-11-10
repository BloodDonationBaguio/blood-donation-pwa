<?php
// Batch-create donors with varied screening completeness and genders.
// Usage: /tests/seed_batch_screenings.php?count=6

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
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$count = max(1, (int)($_GET['count'] ?? 5));
$questions = include __DIR__ . '/../includes/medical_questions.php';
$sections = $questions['sections'] ?? [];
$table = donorsTable($pdo);

$created = [];
for ($i = 0; $i < $count; $i++) {
    $gender = ($i % 2 === 0) ? 'male' : 'female';
    $complete = ($i % 3 !== 0); // two complete, one partial pattern
    $firstName = ($gender === 'female') ? 'Ella' : 'Noah';
    $lastName = 'Batch' . ($i + 1);
    $email = sprintf('batch_%s_%s_%d@example.com', $gender, $complete ? 'full' : 'partial', rand(1000,9999));
    $phone = '0917' . str_pad((string)rand(0,9999999), 7, '0', STR_PAD_LEFT);
    $blood = ['O+','A+','B+','AB+','O-'][($i % 5)];
    $ref = 'DBR-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

    // Build answers
    $answers = [];
    foreach ($sections as $sectionKey => $section) {
        foreach ($section['questions'] as $qKey => $qText) {
            if ($sectionKey === 'female_only' && $gender !== 'female') { continue; }
            if (in_array($qKey, ['q34','q37'], true)) { continue; }
            $num = (int)substr($qKey, 1);
            if ($complete) {
                $answers[$qKey] = ($num % 4 === 0) ? 'yes' : 'no';
            } else {
                if ($num % 3 === 0) { $answers[$qKey] = 'yes'; }
            }
        }
    }
    if ($gender === 'female') {
        $answers['q34'] = $complete ? 'date' : 'none';
        if ($complete) { $answers['q34_date'] = date('Y-m-d', strtotime('-1 year -' . $i . ' months')); }
        if ($complete || $i % 2 === 0) { $answers['q37_date'] = date('Y-m-d', strtotime('-' . (24 + $i) . ' days')); }
    }

    // Insert donor
    $stmt = $pdo->prepare("INSERT INTO `{$table}` (first_name,last_name,email,phone,blood_type,date_of_birth,gender,address,city,province,weight,height,reference_code,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $dob = ($gender === 'female') ? '1999-11-21' : '1998-02-13';
    $stmt->execute([$firstName,$lastName,$email,$phone,$blood,$dob,ucfirst($gender),'Test Address','City of Baguio','Benguet',60 + ($i%6), 160 + ($i%7), $ref,'pending']);
    $donorId = (int)$pdo->lastInsertId();

    // Completion calc
    $required = ($gender === 'female') ? 37 : 32;
    $actualAnswered = 0; foreach ($answers as $k=>$v){ if($v!=='' && $v!==null){ $actualAnswered++; }}
    $allAnswered = $actualAnswered >= $required;

    // Insert screening
    $st = $pdo->prepare('INSERT INTO donor_medical_screening_simple (donor_id, reference_code, screening_data, all_questions_answered) VALUES (?,?,?,?)');
    $st->execute([$donorId, $ref, json_encode($answers), $allAnswered ? 1 : 0]);

    $created[] = ['id'=>$donorId,'ref'=>$ref,'gender'=>$gender,'complete'=>$allAnswered,'answered'=>$actualAnswered,'required'=>$required];
}

?><!doctype html>
<html><head><meta charset="utf-8"><title>Seed Batch Screenings</title>
<style>body{font-family:system-ui,sans-serif;margin:20px} table{border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px}</style></head>
<body>
  <h2>Batch Seeded Donors</h2>
  <table>
    <thead><tr><th>ID</th><th>Ref</th><th>Gender</th><th>Answered</th><th>Required</th><th>Status</th><th>Diagnostic</th></tr></thead>
    <tbody>
      <?php foreach ($created as $c): ?>
        <tr>
          <td><?= h($c['id']) ?></td>
          <td><code><?= h($c['ref']) ?></code></td>
          <td><?= h($c['gender']) ?></td>
          <td><?= h($c['answered']) ?></td>
          <td><?= h($c['required']) ?></td>
          <td><?= $c['complete'] ? 'Completed' : 'Partial' ?></td>
          <td><a href="/tests/diagnose_screening_data.php?donor_id=<?= h($c['id']) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p>Tip: Change count with <code>?count=10</code>.</p>
</body></html>