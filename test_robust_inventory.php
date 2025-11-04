<?php
/**
 * Test page for the robust inventory manager
 */

// Database connection
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

require_once 'includes/BloodInventoryManagerRobust.php';

echo "<h1>🩸 Robust Inventory Manager Test</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;} th{background:#f2f2f2;}</style>";

// Initialize manager
$manager = new BloodInventoryManagerRobust($pdo, true);

// Test dashboard summary
echo "<h2>📊 Dashboard Summary Test</h2>";
$summary = $manager->getDashboardSummary();
echo "<div class='info'>Summary Result:</div>";
echo "<pre>" . print_r($summary, true) . "</pre>";

// Test inventory retrieval
echo "<h2>📋 Inventory Test</h2>";
$inventory = $manager->getInventory([], 1, 10);
echo "<div class='info'>Inventory Result (first 10):</div>";
echo "<p>Total: {$inventory['total']}, Source: {$inventory['source']}</p>";

if (!empty($inventory['data'])) {
    echo "<table><tr>";
    foreach (array_keys($inventory['data'][0]) as $header) {
        echo "<th>$header</th>";
    }
    echo "</tr>";
    
    foreach (array_slice($inventory['data'], 0, 5) as $unit) {
        echo "<tr>";
        foreach ($unit as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>No inventory data found</div>";
}

// Test count
echo "<h2>🔢 Count Test</h2>";
$count = $manager->getInventoryCount();
echo "<div class='info'>Total Count: $count</div>";

// Test alerts
echo "<h2>⚠️ Alerts Test</h2>";
$alerts = $manager->getAlerts();
echo "<div class='info'>Alerts:</div>";
echo "<pre>" . print_r($alerts, true) . "</pre>";

// Test eligible donors
echo "<h2>👥 Eligible Donors Test</h2>";
$donors = $manager->getEligibleDonors();
echo "<div class='info'>Found " . count($donors) . " eligible donors</div>";
if (!empty($donors)) {
    echo "<table><tr><th>ID</th><th>Name</th><th>Blood Type</th><th>Reference</th></tr>";
    foreach (array_slice($donors, 0, 5) as $donor) {
        echo "<tr><td>{$donor['id']}</td><td>{$donor['full_name']}</td><td>{$donor['blood_type']}</td><td>{$donor['reference_code']}</td></tr>";
    }
    echo "</table>";
}

echo "<hr><p><strong>Test Complete</strong></p>";
echo "<p><a href='admin_blood_inventory_modern.php'>→ Go to Modern Inventory Page</a></p>";
?>