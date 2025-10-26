<?php
// Simple script to show the last 20 lines of logs/fatal_errors.log for debugging
$log = __DIR__ . '/logs/fatal_errors.log';
if (!file_exists($log)) {
    echo "No fatal_errors.log file found.";
    exit;
}
$lines = file($log);
$tail = array_slice($lines, -20);
echo "<pre>" . htmlspecialchars(implode('', $tail)) . "</pre>";
