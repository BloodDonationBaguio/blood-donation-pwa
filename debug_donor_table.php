<?php
require_once __DIR__ . '/includes/db.php';

try {
    $stmt = $pdo->query("PRAGMA table_info(donors)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in donors table:\n";
    foreach ($columns as $column) {
        echo $column['name'] . "\n";
    }
} catch (PDOException $e) {
    echo "Error describing table: " . $e->getMessage() . "\n";
}
?>