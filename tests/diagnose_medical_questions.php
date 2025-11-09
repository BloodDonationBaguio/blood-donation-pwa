<?php
// Root-level wrapper: verify medical questions include files from submodule
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__DIR__);
$primaryPath = $base . '/blood-donation-pwa/includes/medical_questions.php';
$fallbackPath = $base . '/blood-donation-pwa/includes/medical_questions_new.php';

$source = null;
$questions = null;
try {
    if (file_exists($primaryPath)) {
        $questions = include $primaryPath;
        $source = 'blood-donation-pwa/includes/medical_questions.php';
    }
    if (!is_array($questions) || empty($questions['sections'])) {
        if (file_exists($fallbackPath)) {
            $questions = include $fallbackPath;
            $source = 'blood-donation-pwa/includes/medical_questions_new.php (fallback)';
        }
    }
} catch (Exception $e) {
    if (file_exists($fallbackPath)) {
        $questions = include $fallbackPath;
        $source = 'blood-donation-pwa/includes/medical_questions_new.php (fallback due to exception)';
    }
}

$sections = is_array($questions) ? ($questions['sections'] ?? []) : [];

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
    <p class="text-muted">Checked: <code><?= htmlspecialchars($primaryPath) ?></code> and fallback <code><?= htmlspecialchars($fallbackPath) ?></code></p>
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