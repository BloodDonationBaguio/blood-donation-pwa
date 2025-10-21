<?php
/**
 * Create Blood Units for All Donors
 */

require_once 'db.php';

echo "=== Creating Blood Units for All Donors ===\n";

try {
    // Get all donors
    $donorsQuery = "SELECT id, first_name, last_name, blood_type, status FROM donors_new ORDER BY id";
    $donorsStmt = $pdo->query($donorsQuery);
    $allDonors = $donorsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Found " . count($allDonors) . " total donors\n";
    
    $statuses = ['available', 'used', 'expired', 'quarantined'];
    $collectionSites = ['Main Blood Center', 'Mobile Unit A', 'Mobile Unit B', 'Hospital Collection', 'Community Center'];
    $storageLocations = ['Refrigerator A1', 'Refrigerator A2', 'Refrigerator B1', 'Freezer C1', 'Freezer C2'];
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($allDonors as $donor) {
        // Check if this donor already has blood units
        $existingQuery = "SELECT COUNT(*) as count FROM blood_inventory WHERE donor_id = ?";
        $existingStmt = $pdo->prepare($existingQuery);
        $existingStmt->execute([$donor['id']]);
        $existingCount = $existingStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($existingCount > 0) {
            $skippedCount++;
            continue; // Skip if donor already has blood units
        }
        
        // Create 1-2 blood units per donor
        $unitsToCreate = rand(1, 2);
        
        for ($i = 0; $i < $unitsToCreate; $i++) {
            $unitId = 'PRC-' . date('Ymd') . '-' . str_pad($donor['id'], 4, '0', STR_PAD_LEFT) . '-' . ($i + 1);
            
            // Collection date: random date in the last 30 days
            $collectionDate = date('Y-m-d', strtotime('-' . rand(0, 30) . ' days'));
            
            // Expiry date: 42 days after collection
            $expiryDate = date('Y-m-d', strtotime($collectionDate . ' +42 days'));
            
            // Status: mostly available, some used/expired
            $status = 'available';
            if (rand(1, 10) <= 3) {
                $status = $statuses[array_rand($statuses)];
            }
            
            // If expired status, make sure expiry date is in the past
            if ($status === 'expired') {
                $expiryDate = date('Y-m-d', strtotime('-' . rand(1, 10) . ' days'));
            }
            
            $volume = rand(400, 500);
            $collectionSite = $collectionSites[array_rand($collectionSites)];
            $storageLocation = $storageLocations[array_rand($storageLocations)];
            $screeningStatus = ['passed', 'pending'][array_rand([0, 0, 0, 1])]; // Mostly passed
            $notes = "Blood unit for " . $donor['first_name'] . " " . $donor['last_name'];
            
            // Determine if this is test data
            $isTestData = $donor['status'] === 'served' ? 1 : 0;
            
            $insertQuery = "
                INSERT INTO blood_inventory (
                    unit_id, donor_id, blood_type, collection_date, expiry_date, 
                    status, collection_site, storage_location, volume_ml, 
                    screening_status, notes, seed_flag, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([
                $unitId, $donor['id'], $donor['blood_type'], $collectionDate, $expiryDate,
                $status, $collectionSite, $storageLocation, $volume,
                $screeningStatus, $notes, $isTestData
            ]);
            
            $addedCount++;
        }
    }
    
    echo "✅ Added $addedCount blood units\n";
    echo "⏭️  Skipped $skippedCount donors (already have blood units)\n";
    
    // Show final counts
    $totalUnitsQuery = "SELECT COUNT(*) as count FROM blood_inventory";
    $totalUnitsStmt = $pdo->query($totalUnitsQuery);
    $totalUnits = $totalUnitsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $testUnitsQuery = "SELECT COUNT(*) as count FROM blood_inventory WHERE seed_flag = 1";
    $testUnitsStmt = $pdo->query($testUnitsQuery);
    $testUnits = $testUnitsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $realUnits = $totalUnits - $testUnits;
    
    echo "\n=== Final Summary ===\n";
    echo "📊 Total donors: " . count($allDonors) . "\n";
    echo "🩸 Total blood units: $totalUnits\n";
    echo "🩸 Test blood units: $testUnits\n";
    echo "🩸 Real blood units: $realUnits\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
