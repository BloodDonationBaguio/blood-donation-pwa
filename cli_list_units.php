<?php
// List recent blood_inventory units
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

$stmt = $pdo->query('SELECT unit_id, status FROM blood_inventory ORDER BY id DESC LIMIT 10');
foreach ($stmt as $row) {
    echo $row['unit_id'] . " " . $row['status'] . "\n";
}
?>