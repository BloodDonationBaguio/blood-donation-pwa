<?php
/**
 * Add real blood inventory data using only actual donors and their blood types
 */

require_once 'db.php';
require_once 'includes/BloodInventoryManagerSimple.php';

echo "Adding real blood inventory data...\n";
echo "==================================\n\n";

try {
    $inventoryManager = new BloodInventoryManagerSimple($pdo);
    
    // Get real approved/served donors
    $donors = $inventoryManager->getApprovedDonors(10);
    
    if (empty($donors)) {
        echo "❌ No approved or served donors found!\n";
        echo "Please approve some donors first before creating blood units.\n";
        exit;
    }
    
    echo "✅ Found " . count($donors) . " eligible donors:\n";
    foreach ($donors as $donor) {
        echo "   - {$donor['first_name']} {$donor['last_name']} ({$donor['blood_type']}) - {$donor['status']}\n";
    }
    echo "\n";
    
    // Create blood units for each donor (only using their actual blood type)
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($donors as $index => $donor) {
        // Create 1-3 blood units per donor with different collection dates
        $unitsToCreate = rand(1, 3);
        
        for ($i = 0; $i < $unitsToCreate; $i++) {
            $collectionDate = date('Y-m-d', strtotime('-' . rand(0, 30) . ' days'));
            
            $data = [
                'donor_id' => $donor['id'],
                'collection_date' => $collectionDate,
                'collection_center' => 'Main Center',
                'storage_location' => 'Storage ' . chr(65 + ($index % 3)), // Storage A, B, or C
                'collection_staff' => 'Dr. Smith'
            ];
            
            try {
                $result = $inventoryManager->createBloodUnit($data);
                
                if ($result['success']) {
                    $successCount++;
                    echo "✓ Created unit for {$donor['first_name']} {$donor['last_name']} ({$donor['blood_type']}) on {$collectionDate}\n";
                } else {
                    $errorCount++;
                    echo "✗ Error creating unit for {$donor['first_name']} {$donor['last_name']}: {$result['message']}\n";
                }
            } catch (Exception $e) {
                $errorCount++;
                echo "✗ Exception creating unit for {$donor['first_name']} {$donor['last_name']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Real Blood Data Summary:\n";
    echo "✓ Successfully created: $successCount units\n";
    echo "✗ Failed to create: $errorCount units\n\n";
    
    if ($successCount > 0) {
        echo "🎉 Real blood inventory data created successfully!\n";
        echo "All units are linked to real donors with their actual blood types.\n";
        
        // Show summary
        $summary = $inventoryManager->getDashboardSummary();
        echo "\nCurrent Inventory Summary:\n";
        echo "Total Units: " . $summary['total_units'] . "\n";
        echo "Available Units: " . $summary['available_units'] . "\n";
        echo "Blood Types: " . implode(', ', array_keys($summary['by_blood_type'])) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
