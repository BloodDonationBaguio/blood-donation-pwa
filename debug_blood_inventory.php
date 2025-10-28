<?php
// Use production database configuration
require_once 'db_production.php';

echo "<h2>Blood Inventory Debug Report</h2>";
echo "<p>Generated at: " . date('Y-m-d H:i:s') . "</p>";

// Check if database connection is established
if (!isset($pdo) || !$pdo) {
    echo "<p style='color: red;'>❌ Database connection not established!</p>";
    echo "<p><strong>DATABASE_URL set:</strong> " . (getenv('DATABASE_URL') ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>PDO PostgreSQL available:</strong> " . (extension_loaded('pdo_pgsql') ? 'YES' : 'NO') . "</p>";
    die("Cannot proceed without database connection.");
}

echo "<p style='color: green;'>✅ Database connection established</p>";

try {
    // Check if blood_inventory table exists
    echo "<h3>1. Table Existence Check</h3>";
    $tableCheck = $pdo->query("
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_name = 'blood_inventory'
    ");
    $tableExists = $tableCheck->fetch(PDO::FETCH_ASSOC)['table_exists'];
    echo "<p>Blood inventory table exists: " . ($tableExists > 0 ? "YES" : "NO") . "</p>";

    if ($tableExists > 0) {
        // Check table structure
        echo "<h3>2. Table Structure</h3>";
        $structureStmt = $pdo->query("
            SELECT column_name, data_type, is_nullable 
            FROM information_schema.columns 
            WHERE table_name = 'blood_inventory' 
            ORDER BY ordinal_position
        ");
        echo "<table border='1'>";
        echo "<tr><th>Column Name</th><th>Data Type</th><th>Nullable</th></tr>";
        while ($col = $structureStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td><td>{$col['is_nullable']}</td></tr>";
        }
        echo "</table>";

        // Check total record count
        echo "<h3>3. Record Count</h3>";
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM blood_inventory");
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>Total records in blood_inventory: <strong>{$totalRecords}</strong></p>";

        if ($totalRecords > 0) {
            // Check status distribution
            echo "<h3>4. Status Distribution</h3>";
            $statusStmt = $pdo->query("
                SELECT 
                    status, 
                    COUNT(*) as count 
                FROM blood_inventory 
                GROUP BY status 
                ORDER BY count DESC
            ");
            echo "<table border='1'>";
            echo "<tr><th>Status</th><th>Count</th></tr>";
            while ($status = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr><td>{$status['status']}</td><td>{$status['count']}</td></tr>";
            }
            echo "</table>";

            // Check blood type distribution
            echo "<h3>5. Blood Type Distribution</h3>";
            $bloodTypeStmt = $pdo->query("
                SELECT 
                    blood_type, 
                    COUNT(*) as count 
                FROM blood_inventory 
                GROUP BY blood_type 
                ORDER BY count DESC
            ");
            echo "<table border='1'>";
            echo "<tr><th>Blood Type</th><th>Count</th></tr>";
            while ($bloodType = $bloodTypeStmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr><td>{$bloodType['blood_type']}</td><td>{$bloodType['count']}</td></tr>";
            }
            echo "</table>";

            // Show sample records
            echo "<h3>6. Sample Records (First 5)</h3>";
            $sampleStmt = $pdo->query("SELECT * FROM blood_inventory LIMIT 5");
            $columns = [];
            echo "<table border='1'>";
            $first = true;
            while ($record = $sampleStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($first) {
                    echo "<tr>";
                    foreach (array_keys($record) as $col) {
                        echo "<th>{$col}</th>";
                        $columns[] = $col;
                    }
                    echo "</tr>";
                    $first = false;
                }
                echo "<tr>";
                foreach ($record as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p><strong>No records found in blood_inventory table!</strong></p>";
            echo "<p>This explains why the dashboard shows zero values.</p>";
        }
    }

    // Test the BloodInventoryManagerEnhanced class
    echo "<h3>7. BloodInventoryManagerEnhanced Test</h3>";
    require_once 'includes/BloodInventoryManagerEnhanced.php';
    $manager = new BloodInventoryManagerEnhanced($pdo);
    $summary = $manager->getDashboardSummary();
    echo "<p>Dashboard Summary Result:</p>";
    echo "<pre>" . json_encode($summary, JSON_PRETTY_PRINT) . "</pre>";

} catch (Exception $e) {
    echo "<h3>Error</h3>";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>