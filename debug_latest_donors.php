<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');
$stmt = $pdo->query("SELECT id, reference_code, status, email, created_at FROM donors ORDER BY id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo '<h2>Latest Donors</h2><table border=1 cellpadding=6><tr><th>ID</th><th>Reference</th><th>Status</th><th>Email</th><th>Created At</th></tr>';
foreach ($rows as $r) {
    echo '<tr>';
    foreach ($r as $v) echo '<td>' . htmlspecialchars($v) . '</td>';
    echo '</tr>';
}
echo '</table>';
