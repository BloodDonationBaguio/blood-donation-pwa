<?php
/**
 * View All Donors - No Filtering
 * Shows all donors in the database for debugging
 */

require_once 'db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>All Donors in Database</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container mt-4'>";

echo "<h1>All Donors in Database</h1>";

try {
    // Get all donors
    $stmt = $pdo->query("
        SELECT 
            id, first_name, last_name, email, phone, blood_type, 
            status, reference_code, created_at, seed_flag
        FROM donors_new 
        ORDER BY id DESC
    ");
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='alert alert-info'>";
    echo "<strong>Total donors found:</strong> " . count($donors);
    echo "</div>";
    
    if (empty($donors)) {
        echo "<div class='alert alert-warning'>No donors found in database!</div>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead class='table-dark'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Name</th>";
        echo "<th>Email</th>";
        echo "<th>Phone</th>";
        echo "<th>Blood Type</th>";
        echo "<th>Status</th>";
        echo "<th>Reference</th>";
        echo "<th>Seed Flag</th>";
        echo "<th>Created</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($donors as $donor) {
            $seedFlagText = $donor['seed_flag'] == 1 ? 'TEST' : 'REAL';
            $seedFlagClass = $donor['seed_flag'] == 1 ? 'badge bg-warning' : 'badge bg-success';
            
            echo "<tr>";
            echo "<td>{$donor['id']}</td>";
            echo "<td>{$donor['first_name']} {$donor['last_name']}</td>";
            echo "<td>{$donor['email']}</td>";
            echo "<td>{$donor['phone']}</td>";
            echo "<td><span class='badge bg-danger'>{$donor['blood_type']}</span></td>";
            echo "<td><span class='badge bg-primary'>{$donor['status']}</span></td>";
            echo "<td><code>{$donor['reference_code']}</code></td>";
            echo "<td><span class='$seedFlagClass'>$seedFlagText</span></td>";
            echo "<td>" . date('M d, Y', strtotime($donor['created_at'])) . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }
    
    // Show database info
    echo "<div class='mt-4'>";
    echo "<h3>Database Information</h3>";
    
    // Check if seed_flag column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM donors_new LIKE 'seed_flag'");
    $seedFlagExists = $stmt->fetch();
    
    echo "<div class='alert alert-info'>";
    echo "<strong>seed_flag column exists:</strong> " . ($seedFlagExists ? 'YES' : 'NO') . "<br>";
    echo "<strong>Database:</strong> " . DB_NAME . "<br>";
    echo "<strong>Table:</strong> donors_new<br>";
    echo "</div>";
    
    // Count by seed_flag
    if ($seedFlagExists) {
        $stmt = $pdo->query("SELECT seed_flag, COUNT(*) as count FROM donors_new GROUP BY seed_flag");
        $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Count by Seed Flag:</h4>";
        echo "<ul>";
        foreach ($counts as $count) {
            $type = $count['seed_flag'] == 1 ? 'TEST DATA' : 'REAL DATA';
            echo "<li><strong>$type:</strong> {$count['count']} donors</li>";
        }
        echo "</ul>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<div class='mt-4'>";
echo "<h3>Quick Actions</h3>";
echo "<a href='add_test_donors.php' class='btn btn-primary me-2'>Add 50 Test Donors</a>";
echo "<a href='admin/pages/donors_simple.php' class='btn btn-info me-2'>View Paginated Donors</a>";
echo "<a href='check_test_data.php' class='btn btn-warning me-2'>Check Test Data</a>";
echo "<a href='admin.php' class='btn btn-secondary'>Back to Admin</a>";
echo "</div>";

echo "</div></body></html>";
?>
