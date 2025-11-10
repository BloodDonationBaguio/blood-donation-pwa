<?php
// Create one test donor and attach realistic screening_data so admin modal shows answers.
// Usage examples:
//  - /tests/seed_sample_screening.php                (defaults)
//  - /tests/seed_sample_screening.php?gender=female  (female with q34/q37 dates)
//  - /tests/seed_sample_screening.php?complete=no    (partial answers)
//  - /tests/seed_sample_screening.php?name=Test%20User&blood=O+&city=Baguio

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

$gender = strtolower($_GET['gender'] ?? 'male');
$complete = strtolower($_GET['complete'] ?? 'yes') !== 'no';
$firstName = $_GET['name'] ?? ($gender === 'female' ? 'Ava' : 'Liam');
$lastName = $_GET['lname'] ?? 'Test';
$blood = $_GET['blood'] ?? 'O+';
$city = $_GET['city'] ?? 'City of Baguio';
$province = $_GET['province'] ?? 'Benguet';
$email = sprintf('test_%s_%s_%d@example.com', $gender, $complete ? 'full' : 'partial', rand(1000,9999));
$phone = '0909000' . rand(1000,9999);
$ref = 'DBR-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

// Build screening answers aligned with includes/medical_questions.php
$questions = include __DIR__ . '/../includes/medical_questions.php';
$sections = $questions['sections'] ?? [];
$answers = [];

foreach ($sections as $sectionKey => $section) {
    foreach ($section['questions'] as $qKey => $qText) {
        // Skip female-only for male donor
        if ($sectionKey === 'female_only' && $gender !== 'female') { continue; }
        if (in_array($qKey, ['q34','q37'], true)) { continue; } // handled below
        // Generate deterministic but varied answers
        if ($complete) {
            // Alternate yes/no by question number for coverage
            $num = (int)substr($qKey, 1);
            $answers[$qKey] = ($num % 3 === 0) ? 'yes' : 'no';
        } else {
            // Partial: answer roughly half, leave others not_answered
            $num = (int)substr($qKey, 1);
            if ($num % 2 === 0) { $answers[$qKey] = 'no'; }
        }
    }
}

// Female date fields
if ($gender === 'female') {
    if ($complete) {
        $answers['q34'] = 'date';
        $answers['q34_date'] = date('Y-m-d', strtotime('-2 years'));
        $answers['q37_date'] = date('Y-m-d', strtotime('-28 days'));
    } else {
        $answers['q34'] = 'none'; // explicitly none
        // leave q37_date empty to simulate partial
    }
}

// Compute completion based on required count
$required = ($gender === 'female') ? 37 : 32; // matches donor-registration.php logic
$actualAnswered = 0;
foreach ($answers as $k => $v) { if ($v !== '' && $v !== null) { $actualAnswered++; } }
$allAnswered = $actualAnswered >= $required;

// Insert donor
$table = donorsTable($pdo);
$stmt = $pdo->prepare("INSERT INTO `{$table}` (first_name,last_name,email,phone,blood_type,date_of_birth,gender,address,city,province,weight,height,reference_code,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
$dob = ($gender === 'female') ? '1997-08-12' : '1995-03-04';
$address = 'km 3 Asin Road';
$weight = 62; $height = 168;
$stmt->execute([$firstName,$lastName,$email,$phone,$blood,$dob,ucfirst($gender),$address,$city,$province,$weight,$height,$ref,'pending']);
$donorId = (int)$pdo->lastInsertId();

// Insert screening
$stmt = $pdo->prepare("INSERT INTO donor_medical_screening_simple (donor_id, reference_code, screening_data, all_questions_answered) VALUES (?,?,?,?)");
$stmt->execute([$donorId, $ref, json_encode($answers), $allAnswered ? 1 : 0]);

// Output result
?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Seed: One Donor with Screening</title>
<style>body{font-family:system-ui,sans-serif;margin:20px}.ok{color:#05630d}.warn{color:#8a6d3b}.muted{color:#666}</style>
</head>
<body>
  <h2>Seeded Donor</h2>
  <ul>
    <li><strong>ID:</strong> <?= h($donorId) ?></li>
    <li><strong>Name:</strong> <?= h($firstName . ' ' . $lastName) ?></li>
    <li><strong>Gender:</strong> <?= h(ucfirst($gender)) ?></li>
    <li><strong>Reference:</strong> <code><?= h($ref) ?></code></li>
    <li><strong>Answers:</strong> <?= h($actualAnswered) ?> / <?= h($required) ?> (<?= $allAnswered ? '<span class="ok">Completed</span>' : '<span class="warn">Partial</span>' ?>)</li>
  </ul>
  <p>
    <a href="/tests/diagnose_screening_data.php?donor_id=<?= h($donorId) ?>">View diagnostic page for this donor</a>
  </p>
  <p class="muted">Tip: Use <code>?gender=female</code> or <code>?complete=no</code> to vary data.</p>
</body>
</html>