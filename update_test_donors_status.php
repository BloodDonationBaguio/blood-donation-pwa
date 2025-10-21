<?php
/**
 * Update Test Donors Status to Served
 */

require_once 'db.php';

echo "=== Updating Test Donors Status ===\n";

try {
    // Update all test donors to served status
    $updateQuery = "UPDATE donors_new SET status = 'served' WHERE seed_flag = 1";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute();
    $updatedCount = $updateStmt->rowCount();
    
    echo "✅ Updated $updatedCount test donors to 'served' status\n";
    
    // Show current donor counts
    $totalQuery = "SELECT COUNT(*) as count FROM donors_new";
    $totalStmt = $pdo->query($totalQuery);
    $totalDonors = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $servedQuery = "SELECT COUNT(*) as count FROM donors_new WHERE status = 'served'";
    $servedStmt = $pdo->query($servedQuery);
    $servedDonors = $servedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "📊 Total donors: $totalDonors\n";
    echo "📊 Served donors: $servedDonors\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
