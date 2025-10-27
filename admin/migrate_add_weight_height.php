<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db.php';
echo '<h2>PostgreSQL Migration: Add weight & height columns to donors</h2>';
try {
    $pdo->exec("ALTER TABLE donors ADD COLUMN weight NUMERIC(5,2);");
    echo '<div style="color:green;">SUCCESS: weight column added.</div>';
} catch (Throwable $e) {
    echo '<div style="color:red;">ERROR (weight): ' . htmlspecialchars($e->getMessage()) . '</div>';
}
try {
    $pdo->exec("ALTER TABLE donors ADD COLUMN height NUMERIC(5,2);");
    echo '<div style="color:green;">SUCCESS: height column added.</div>';
} catch (Throwable $e) {
    echo '<div style="color:red;">ERROR (height): ' . htmlspecialchars($e->getMessage()) . '</div>';
}
