<?php
/**
 * Add More Test Blood Units
 * Creates blood units for all test donors to match the donor list
 */

require_once 'db.php';

echo "=== Adding More Test Blood Units ===\n";

try {
    // Get all test donors
    $donorsQuery = "SELECT id, first_name, last_name, blood_type FROM donors_new WHERE seed_flag = 1 ORDER BY id";
    $donorsStmt = $pdo->query($donorsQuery);
    $testDonors = $donorsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($testDonors) . " test donors\n";
    
    if (empty($testDonors)) {
        echo "❌ No test donors found. Please run add_test_donors.php first.\n";
        exit;
    }
    
    // Blood types distribution
    $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $statuses = ['available', 'used', 'expired', 'quarantined'];
    $collectionSites = ['Main Blood Center', 'Mobile Unit A', 'Mobile Unit B', 'Hospital Collection', 'Community Center'];
    $storageLocations = ['Refrigerator A1', 'Refrigerator A2', 'Refrigerator B1', 'Freezer C1', 'Freezer C2'];
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($testDonors as $index => $donor) {
        // Check if this donor already has blood units
        $existingQuery = "SELECT COUNT(*) as count FROM blood_inventory WHERE donor_id = ?";
        $existingStmt = $pdo->prepare($existingQuery);
        $existingStmt->execute([$donor['id']]);
        $existingCount = $existingStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($existingCount > 0) {
            $skippedCount++;
            continue; // Skip if donor already has blood units
        }
        
        // Create 1-3 blood units per donor
        $unitsToCreate = rand(1, 3);
        
        for ($i = 0; $i < $unitsToCreate; $i++) {
            $unitId = 'TEST-' . str_pad($donor['id'], 3, '0', STR_PAD_LEFT) . '-' . ($i + 1);
            
            // Use donor's blood type or random if unknown
            $bloodType = $donor['blood_type'] !== 'Unknown' ? $donor['blood_type'] : $bloodTypes[array_rand($bloodTypes)];
            
            // Collection date: random date in the last 30 days
            $collectionDate = date('Y-m-d', strtotime('-' . rand(0, 30) . ' days'));
            
            // Expiry date: 42 days after collection
            $expiryDate = date('Y-m-d', strtotime($collectionDate . ' +42 days'));
            
            // Status: mostly available, some used/expired
            $status = 'available';
            if (rand(1, 10) <= 2) {
                $status = $statuses[array_rand($statuses)];
            }
            
            // If expired status, make sure expiry date is in the past
            if ($status === 'expired') {
                $expiryDate = date('Y-m-d', strtotime('-' . rand(1, 10) . ' days'));
            }
            
            $volume = rand(400, 500); // Standard blood donation volume
            $collectionSite = $collectionSites[array_rand($collectionSites)];
            $storageLocation = $storageLocations[array_rand($storageLocations)];
            
            $screeningStatus = ['passed', 'pending', 'failed'][array_rand([0, 0, 0, 1, 2])]; // Mostly passed
            $notes = "Test blood unit for " . $donor['first_name'] . " " . $donor['last_name'];
            
            $insertQuery = "
                INSERT INTO blood_inventory (
                    unit_id, donor_id, blood_type, collection_date, expiry_date, 
                    status, collection_site, storage_location, volume_ml, 
                    screening_status, notes, seed_flag, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ";
            
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([
                $unitId, $donor['id'], $bloodType, $collectionDate, $expiryDate,
                $status, $collectionSite, $storageLocation, $volume,
                $screeningStatus, $notes
            ]);
            
            $addedCount++;
        }
    }
    
    echo "✅ Added $addedCount test blood units\n";
    echo "⏭️  Skipped $skippedCount donors (already have blood units)\n";
    
    // Show summary
    $totalQuery = "SELECT COUNT(*) as total FROM blood_inventory WHERE seed_flag = 1";
    $totalStmt = $pdo->query($totalQuery);
    $totalTestUnits = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $realQuery = "SELECT COUNT(*) as total FROM blood_inventory WHERE seed_flag = 0 OR seed_flag IS NULL";
    $realStmt = $pdo->query($realQuery);
    $realUnits = $realStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "\n=== Summary ===\n";
    echo "Test blood units: $totalTestUnits\n";
    echo "Real blood units: $realUnits\n";
    echo "Total blood units: " . ($totalTestUnits + $realUnits) . "\n";
    
    echo "\n✅ Test blood units created successfully!\n";
    echo "View at: http://localhost/blood-donation-pwa/admin/pages/blood_inventory_all.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit;
}
?>
