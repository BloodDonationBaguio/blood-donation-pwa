<?php
// Dump the medical questions definition so testers can cross-check keys vs answers.
$file = __DIR__ . '/../includes/medical_questions.php';
if (!file_exists($file)) { http_response_code(404); echo 'medical_questions.php not found'; exit; }
$questions = include $file;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html><head><meta charset="utf-8"><title>Medical Questions Dump</title>
<style>body{font-family:system-ui,sans-serif;margin:20px} .key{font-family:ui-monospace,monospace;color:#333}</style></head>
<body>
  <h2>Medical Questions</h2>
  <?php foreach (($questions['sections'] ?? []) as $sectionKey => $section): ?>
    <h3><?= h($section['title'] ?? $sectionKey) ?> <small class="key">(<?= h($sectionKey) ?>)</small></h3>
    <ol>
      <?php foreach ($section['questions'] as $qKey => $qText): ?>
        <li><span class="key"><?= h($qKey) ?></span> — <?= h($qText) ?></li>
      <?php endforeach; ?>
    </ol>
  <?php endforeach; ?>
  <p>Tip: Female-only section appears only for female donors.</p>
</body></html>