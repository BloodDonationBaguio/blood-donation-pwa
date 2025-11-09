<?php
// Root-level wrapper: verify medical questions include files, preferring root-level includes
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__DIR__);

function firstExisting(array $paths): ?string {
    foreach ($paths as $p) { if (file_exists($p)) return $p; }
    return null;
}

// Prefer the app's root includes first, then submodule fallbacks
$candidates = [
    $base . '/includes/medical_questions.php',
    $base . '/blood-donation-pwa/includes/medical_questions.php',
    $base . '/blood-donation-pwa/includes/medical_questions_new.php',
    $base . '/legacy-pwa-4/blood-donation-pwa/includes/medical_questions.php',
    $base . '/__zip_restore/blood-donation-pwa/includes/medical_questions.php',
];

$picked = firstExisting($candidates);
$source = $picked ? str_replace($base . '/', '', $picked) : null;
$questions = $picked ? (include $picked) : null;

// Fallback: try the alternative root mapping if sections key is missing
if (!is_array($questions) || empty($questions['sections'])) {
    $alt = firstExisting([$base . '/includes/medical_screening_questions.php']);
    if ($alt) { $questions = include $alt; $source = str_replace($base . '/', '', $alt) . ' (fallback)'; }
}

$sections = is_array($questions) ? ($questions['sections'] ?? $questions) : [];

?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Diagnose Medical Questions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Diagnose Medical Questions</h1>
  <p><strong>Source:</strong> <?= htmlspecialchars($source ?? 'not found') ?></p>

  <?php if (empty($sections)): ?>
    <div class="alert alert-danger">Failed to load medical questions sections.</div>
    <p class="text-muted">Checked candidates:</p>
    <ul class="small">
      <?php foreach ($candidates as $c): ?>
        <li><code><?= htmlspecialchars(str_replace($base . '/', '', $c)) ?></code></li>
      <?php endforeach; ?>
      <li><code>includes/medical_screening_questions.php</code> (fallback)</li>
    </ul>
  <?php else: ?>
    <div class="alert alert-success">Loaded <?= count($sections) ?> sections.</div>
    <?php foreach ($sections as $sectionKey => $section): ?>
      <div class="card mb-3">
        <div class="card-header">
          Section: <?= htmlspecialchars($section['title'] ?? $sectionKey) ?> (key: <?= htmlspecialchars($sectionKey) ?>)
        </div>
        <div class="card-body">
          <p><strong>Question keys (first 10):</strong></p>
          <pre><?php
            $keys = array_keys($section['questions'] ?? []);
            $show = array_slice($keys, 0, 10);
            echo htmlspecialchars(implode("\n", $show));
          ?></pre>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <p class="text-muted">This root-level wrapper loads question definitions from the submodule.</p>
</body>
</html>