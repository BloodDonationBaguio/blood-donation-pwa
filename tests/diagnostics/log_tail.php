<?php
// Diagnostics: Tail error logs from root and submodule
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$paths = [
    __DIR__ . '/../../logs/error.log',
    __DIR__ . '/../../blood-donation-pwa/logs/error.log',
    __DIR__ . '/../../__zip_restore/blood-donation-pwa/logs/error.log',
];

$lines = isset($_GET['lines']) ? max(50, (int)$_GET['lines']) : 300;

function tailFile($file, $lines) {
    if (!file_exists($file)) { return "[Missing] $file\n"; }
    $content = @file($file, FILE_IGNORE_NEW_LINES);
    if ($content === false) { return "[Unreadable] $file\n"; }
    $start = max(0, count($content) - $lines);
    $slice = array_slice($content, $start);
    return "==== $file (last $lines lines) ====" . "\n" . implode("\n", $slice) . "\n";
}

foreach ($paths as $p) {
    echo tailFile($p, $lines);
}
?>