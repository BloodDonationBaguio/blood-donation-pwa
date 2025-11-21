<?php
// Test the exact query used in index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

echo "<h1>Testing Counter Query</h1>";

$driver = 'mysql';
try { 
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); 
} catch (Throwable $e) {}

echo "<p><strong>Driver:</strong> $driver</p>";

// Test the exact query from index.php
try {
    $sql = "SELECT COUNT(*) AS cnt FROM donors WHERE status = 'served' AND YEAR(COALESCE(served_date, created_at)) = YEAR(CURRENT_DATE)";
    echo "<p><strong>Query:</strong> <code>$sql</code></p>";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Result:</strong> " . print_r($result, true) . "</p>";
    echo "<p><strong>Count:</strong> " . ($result['cnt'] ?? 'NULL') . "</p>";
    
    $donationsThisYear = (int)($result['cnt'] ?? 0);
    echo "<p><strong>Final value:</strong> $donationsThisYear</p>";
    
} catch (Throwable $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Also test without YEAR filter
try {
    $sql2 = "SELECT COUNT(*) AS cnt FROM donors WHERE status = 'served'";
    echo "<hr><p><strong>Query without year filter:</strong> <code>$sql2</code></p>";
    
    $stmt2 = $pdo->query($sql2);
    $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Result:</strong> " . print_r($result2, true) . "</p>";
    echo "<p><strong>Count:</strong> " . ($result2['cnt'] ?? 'NULL') . "</p>";
    
} catch (Throwable $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

// Show the actual dates
try {
    $sql3 = "SELECT id, first_name, last_name, status, served_date, created_at, YEAR(COALESCE(served_date, created_at)) as year_value FROM donors WHERE status = 'served'";
    echo "<hr><p><strong>Served donors with year values:</strong></p>";
    
    $stmt3 = $pdo->query($sql3);
    $donors = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Served Date</th><th>Created At</th><th>Year Value</th></tr>";
    foreach ($donors as $donor) {
        echo "<tr>";
        echo "<td>" . $donor['id'] . "</td>";
        echo "<td>" . htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) . "</td>";
        echo "<td>" . ($donor['served_date'] ?? 'NULL') . "</td>";
        echo "<td>" . $donor['created_at'] . "</td>";
        echo "<td>" . $donor['year_value'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Current year:</strong> " . date('Y') . "</p>";
    
} catch (Throwable $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
