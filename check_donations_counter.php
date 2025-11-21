<?php
// Quick diagnostic to check why the counter is showing 0
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

echo "<h1>Donations Counter Diagnostic</h1>";

// Get database driver
$driver = 'mysql';
try { 
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); 
} catch (Throwable $e) {
    echo "<p>Error getting driver: " . $e->getMessage() . "</p>";
}

echo "<p><strong>Database Driver:</strong> $driver</p>";

// Check if donations_new table exists
$tableExists = false;
try {
    if (function_exists('tableExists')) {
        $tableExists = tableExists($pdo, 'donations_new');
    } else {
        if ($driver === 'pgsql') {
            $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'donations_new')");
            $tableExists = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE 'donations_new'");
            $tableExists = $stmt->fetch() !== false;
        }
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error checking table: " . $e->getMessage() . "</p>";
}

echo "<p><strong>donations_new table exists:</strong> " . ($tableExists ? "YES" : "NO") . "</p>";

if ($tableExists) {
    // Count all records in donations_new
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM donations_new");
        $totalCount = $stmt->fetchColumn();
        echo "<p><strong>Total records in donations_new:</strong> $totalCount</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red;'>Error counting total: " . $e->getMessage() . "</p>";
    }
    
    // Count completed donations
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM donations_new WHERE status = 'completed'");
        $completedCount = $stmt->fetchColumn();
        echo "<p><strong>Completed donations (all time):</strong> $completedCount</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red;'>Error counting completed: " . $e->getMessage() . "</p>";
    }
    
    // Count completed donations in current year
    try {
        $currentYear = date('Y');
        if ($driver === 'pgsql') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND EXTRACT(YEAR FROM donation_date) = EXTRACT(YEAR FROM CURRENT_DATE)");
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM donations_new WHERE status = 'completed' AND YEAR(donation_date) = YEAR(CURRENT_DATE)");
        }
        $thisYearCount = $stmt->fetchColumn();
        echo "<p><strong>Completed donations in $currentYear:</strong> $thisYearCount</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red;'>Error counting this year: " . $e->getMessage() . "</p>";
    }
    
    // Show sample records
    try {
        $stmt = $pdo->query("SELECT id, donor_id, donation_date, blood_type, status FROM donations_new ORDER BY donation_date DESC LIMIT 5");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Records (last 5):</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Donor ID</th><th>Date</th><th>Blood Type</th><th>Status</th></tr>";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($record['id']) . "</td>";
            echo "<td>" . htmlspecialchars($record['donor_id']) . "</td>";
            echo "<td>" . htmlspecialchars($record['donation_date']) . "</td>";
            echo "<td>" . htmlspecialchars($record['blood_type']) . "</td>";
            echo "<td>" . htmlspecialchars($record['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Throwable $e) {
        echo "<p style='color:red;'>Error fetching records: " . $e->getMessage() . "</p>";
    }
}

// Check donors table
echo "<h2>Donors Table Check</h2>";
try {
    $currentYear = date('Y');
    
    // Count all served donors
    $stmt = $pdo->query("SELECT COUNT(*) FROM donors WHERE status = 'served'");
    $servedTotal = $stmt->fetchColumn();
    echo "<p><strong>Total served donors (all time):</strong> $servedTotal</p>";
    
    // Count served donors this year
    if ($driver === 'pgsql') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM donors WHERE status = 'served' AND EXTRACT(YEAR FROM COALESCE(served_date, created_at)) = EXTRACT(YEAR FROM CURRENT_DATE)");
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM donors WHERE status = 'served' AND YEAR(COALESCE(served_date, created_at)) = YEAR(CURRENT_DATE)");
    }
    $servedThisYear = $stmt->fetchColumn();
    echo "<p><strong>Served donors in $currentYear:</strong> $servedThisYear</p>";
    
    // Show sample served donors
    $stmt = $pdo->query("SELECT id, first_name, last_name, status, served_date, created_at FROM donors WHERE status = 'served' ORDER BY COALESCE(served_date, created_at) DESC LIMIT 5");
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Sample Served Donors (last 5):</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Served Date</th><th>Created At</th></tr>";
    foreach ($donors as $donor) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($donor['id']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['status']) . "</td>";
        echo "<td>" . htmlspecialchars($donor['served_date'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($donor['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error checking donors: " . $e->getMessage() . "</p>";
}

echo "<h2>What the Counter Should Show</h2>";
echo "<p>Based on the logic in index.php:</p>";
echo "<ol>";
echo "<li>If donations_new has completed donations in " . date('Y') . ": show that count</li>";
echo "<li>Otherwise, if donors has served donors in " . date('Y') . ": show that count</li>";
echo "<li>Otherwise: show 0</li>";
echo "</ol>";
?>
