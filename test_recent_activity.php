<?php
// Simple diagnostic to see what getAdminActionLog() returns
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/admin_actions.php';

try {
    $rows = getAdminActionLog($pdo, ['limit' => 10]);
    $count = is_array($rows) ? count($rows) : 0;

    echo "<h1>Recent Activity Diagnostic</h1>";
    echo "<p><strong>Rows from getAdminActionLog(limit=10):</strong> {$count}</p>";

    if ($count === 0) {
        echo "<p>No rows returned from admin_audit_log.</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>created_at</th><th>action_type</th><th>table_name</th><th>record_id</th><th>record_name</th><th>description</th></tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['created_at'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['action_type'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['table_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['record_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['record_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['description'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
