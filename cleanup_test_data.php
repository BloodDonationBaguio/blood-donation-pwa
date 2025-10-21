<?php
/**
 * Cleanup Test Data Script
 * Removes all seeded test data (where seed_flag = 1)
 * Also removes test data identified by naming patterns
 */

require_once 'db.php';

// Check if running from command line or web
$isCommandLine = php_sapi_name() === 'cli';

if (!$isCommandLine) {
    // Web interface - check admin authentication
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        die('Access denied. Admin authentication required.');
    }
}

echo "Starting test data cleanup...\n";

try {
    $pdo->beginTransaction();
    
    // Count records before deletion
    $donorCountStmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new WHERE seed_flag = 1");
    $donorCount = $donorCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $bloodUnitCountStmt = $pdo->query("SELECT COUNT(*) as count FROM blood_inventory WHERE seed_flag = 1");
    $bloodUnitCount = $bloodUnitCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Also count by naming patterns (backup method)
    $patternDonorCountStmt = $pdo->query("
        SELECT COUNT(*) as count FROM donors_new 
        WHERE (first_name LIKE '%(TEST)%' OR last_name LIKE '%(TEST)%' 
               OR email LIKE '%@test.local' 
               OR reference_code LIKE 'TEST-%')
        AND seed_flag = 0
    ");
    $patternDonorCount = $patternDonorCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $patternBloodUnitCountStmt = $pdo->query("
        SELECT COUNT(*) as count FROM blood_inventory 
        WHERE unit_id LIKE 'TEST-%' 
        AND seed_flag = 0
    ");
    $patternBloodUnitCount = $patternBloodUnitCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Found test data to clean up:\n";
    echo "  - Donors with seed_flag = 1: {$donorCount}\n";
    echo "  - Blood units with seed_flag = 1: {$bloodUnitCount}\n";
    echo "  - Donors by naming pattern: {$patternDonorCount}\n";
    echo "  - Blood units by naming pattern: {$patternBloodUnitCount}\n\n";
    
    // Delete blood units first (due to foreign key constraints)
    if ($bloodUnitCount > 0) {
        $deleteBloodUnitsStmt = $pdo->prepare("DELETE FROM blood_inventory WHERE seed_flag = 1");
        $deleteBloodUnitsStmt->execute();
        echo "✅ Deleted {$bloodUnitCount} test blood units (seed_flag = 1)\n";
    }
    
    // Delete blood units by naming pattern
    if ($patternBloodUnitCount > 0) {
        $deletePatternBloodUnitsStmt = $pdo->prepare("DELETE FROM blood_inventory WHERE unit_id LIKE 'TEST-%' AND seed_flag = 0");
        $deletePatternBloodUnitsStmt->execute();
        echo "✅ Deleted {$patternBloodUnitCount} test blood units (naming pattern)\n";
    }
    
    // Delete donors
    if ($donorCount > 0) {
        $deleteDonorsStmt = $pdo->prepare("DELETE FROM donors_new WHERE seed_flag = 1");
        $deleteDonorsStmt->execute();
        echo "✅ Deleted {$donorCount} test donors (seed_flag = 1)\n";
    }
    
    // Delete donors by naming pattern
    if ($patternDonorCount > 0) {
        $deletePatternDonorsStmt = $pdo->prepare("
            DELETE FROM donors_new 
            WHERE (first_name LIKE '%(TEST)%' OR last_name LIKE '%(TEST)%' 
                   OR email LIKE '%@test.local' 
                   OR reference_code LIKE 'TEST-%')
            AND seed_flag = 0
        ");
        $deletePatternDonorsStmt->execute();
        echo "✅ Deleted {$patternDonorCount} test donors (naming pattern)\n";
    }
    
    // Clean up any orphaned blood units (shouldn't happen with proper foreign keys)
    $orphanedCountStmt = $pdo->query("
        SELECT COUNT(*) as count FROM blood_inventory bi 
        LEFT JOIN donors_new d ON bi.donor_id = d.id 
        WHERE d.id IS NULL
    ");
    $orphanedCount = $orphanedCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($orphanedCount > 0) {
        $deleteOrphanedStmt = $pdo->prepare("
            DELETE bi FROM blood_inventory bi 
            LEFT JOIN donors_new d ON bi.donor_id = d.id 
            WHERE d.id IS NULL
        ");
        $deleteOrphanedStmt->execute();
        echo "✅ Deleted {$orphanedCount} orphaned blood units\n";
    }
    
    $pdo->commit();
    
    $totalDeleted = $donorCount + $patternDonorCount + $bloodUnitCount + $patternBloodUnitCount + $orphanedCount;
    
    echo "\n✅ Test data cleanup completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Total records deleted: {$totalDeleted}\n";
    echo "   - Test donors removed: " . ($donorCount + $patternDonorCount) . "\n";
    echo "   - Test blood units removed: " . ($bloodUnitCount + $patternBloodUnitCount) . "\n";
    echo "   - Orphaned records cleaned: {$orphanedCount}\n\n";
    
    echo "🔍 Database is now clean of test data.\n";
    echo "   - All seed_flag = 1 records removed\n";
    echo "   - All TEST- prefixed records removed\n";
    echo "   - All @test.local email records removed\n";
    echo "   - All (TEST) name records removed\n\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

// If running from web, show success message
if (!$isCommandLine) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Test Data Cleaned</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-8'>
                    <div class='card'>
                        <div class='card-header bg-success text-white'>
                            <h4 class='mb-0'>✅ Test Data Cleanup Completed</h4>
                        </div>
                        <div class='card-body'>
                            <p>All test data has been successfully removed from the database.</p>
                            <div class='alert alert-success'>
                                <strong>Summary:</strong><br>
                                • Total records deleted: {$totalDeleted}<br>
                                • Test donors removed: " . ($donorCount + $patternDonorCount) . "<br>
                                • Test blood units removed: " . ($bloodUnitCount + $patternBloodUnitCount) . "<br>
                                • Orphaned records cleaned: {$orphanedCount}
                            </div>
                            <div class='d-flex gap-2'>
                                <a href='admin.php?tab=donor-list' class='btn btn-primary'>View Donor List</a>
                                <a href='admin_blood_inventory.php' class='btn btn-info'>View Blood Inventory</a>
                                <a href='seed_test_data.php' class='btn btn-warning'>Create New Test Data</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
}
?>
