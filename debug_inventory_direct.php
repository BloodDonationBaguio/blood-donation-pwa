<?php
/**
 * Direct Blood Inventory Diagnostic
 * Bypasses complex joins to identify the root issue
 */

// Start with robust database connection
try {
    require_once 'db_production.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once 'db.php';
    }
} catch (Throwable $e) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        @require_once 'db.php';
    }
}

if (!isset($pdo)) {
    die("❌ Database connection failed");
}

echo "<h1>🩸 Blood Inventory Direct Diagnostic</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;}</style>";

// 1. Check if blood_inventory table exists
echo "<h2>📋 Table Structure Check</h2>";
try {
    $stmt = $pdo->query("DESCRIBE blood_inventory");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<div class='success'>✅ blood_inventory table exists</div>";
    echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<div class='error'>❌ blood_inventory table issue: " . $e->getMessage() . "</div>";
    
    // Try PostgreSQL syntax
    try {
        $stmt = $pdo->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'blood_inventory'");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($columns) {
            echo "<div class='success'>✅ blood_inventory table exists (PostgreSQL)</div>";
            echo "<table><tr><th>Column</th><th>Type</th><th>Nullable</th></tr>";
            foreach ($columns as $col) {
                echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td><td>{$col['is_nullable']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e2) {
        echo "<div class='error'>❌ PostgreSQL check also failed: " . $e2->getMessage() . "</div>";
    }
}

// 2. Check raw blood_inventory data
echo "<h2>🩸 Raw Blood Inventory Data</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM blood_inventory");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<div class='success'>📊 Total blood units: <strong>$total</strong></div>";
    
    if ($total > 0) {
        // Show sample data
        $stmt = $pdo->query("SELECT * FROM blood_inventory LIMIT 10");
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table><tr>";
        foreach (array_keys($units[0]) as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr>";
        foreach ($units as $unit) {
            echo "<tr>";
            foreach ($unit as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        
        // Status breakdown
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM blood_inventory GROUP BY status");
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>📈 Status Breakdown</h3><table><tr><th>Status</th><th>Count</th></tr>";
        foreach ($statuses as $status) {
            echo "<tr><td>{$status['status']}</td><td>{$status['count']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ No blood units found in inventory</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error checking blood_inventory: " . $e->getMessage() . "</div>";
}

// 3. Check donor tables
echo "<h2>👥 Donor Tables Check</h2>";
$donorTables = ['donors', 'donors_new'];
foreach ($donorTables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<div class='success'>✅ $table: <strong>$total</strong> records</div>";
        
        if ($total > 0) {
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM $table GROUP BY status");
            $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<table><tr><th>Status</th><th>Count</th></tr>";
            foreach ($statuses as $status) {
                echo "<tr><td>{$status['status']}</td><td>{$status['count']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ $table not accessible: " . $e->getMessage() . "</div>";
    }
}

// 4. Test the current BloodInventoryManagerComplete
echo "<h2>🔧 Manager Test</h2>";
try {
    require_once 'includes/BloodInventoryManagerComplete.php';
    $manager = new BloodInventoryManagerComplete($pdo);
    
    $summary = $manager->getDashboardSummary();
    echo "<div class='success'>✅ Manager initialized successfully</div>";
    echo "<pre>" . print_r($summary, true) . "</pre>";
    
    $inventory = $manager->getInventory([], 1, 5);
    echo "<div class='success'>✅ getInventory() returned:</div>";
    echo "<pre>" . print_r($inventory, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Manager error: " . $e->getMessage() . "</div>";
}

// 5. Simple direct query test
echo "<h2>🔍 Direct Query Test</h2>";
try {
    $stmt = $pdo->query("SELECT bi.*, 'direct_query' as source FROM blood_inventory bi LIMIT 5");
    $directResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<div class='success'>✅ Direct query returned " . count($directResults) . " results</div>";
    if ($directResults) {
        echo "<pre>" . print_r($directResults, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Direct query failed: " . $e->getMessage() . "</div>";
}

echo "<hr><p><strong>Diagnostic Complete</strong> - Check results above to identify the issue.</p>";
?>