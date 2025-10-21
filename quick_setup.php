<?php
/**
 * Quick Setup - Add Test Data
 */

require_once 'db.php';

echo "=== Quick Setup - Adding Test Data ===\n";

try {
    // Step 1: Ensure seed_flag columns exist
    echo "1. Checking database structure...\n";
    $checkDonors = $pdo->query("SHOW COLUMNS FROM donors_new LIKE 'seed_flag'");
    $donorsHasSeedFlag = $checkDonors->fetch() !== false;
    
    $checkInventory = $pdo->query("SHOW COLUMNS FROM blood_inventory LIKE 'seed_flag'");
    $inventoryHasSeedFlag = $checkInventory->fetch() !== false;
    
    if (!$donorsHasSeedFlag || !$inventoryHasSeedFlag) {
        echo "   Adding seed_flag columns...\n";
        $migrationSql = file_get_contents('sql/simple_migration.sql');
        $pdo->exec($migrationSql);
        echo "   ✅ Migration completed\n";
    } else {
        echo "   ✅ Database structure is ready\n";
    }
    
    // Step 2: Add test donors
    echo "\n2. Adding test donors...\n";
    $donorsQuery = "SELECT COUNT(*) as count FROM donors_new WHERE seed_flag = 1";
    $donorsStmt = $pdo->query($donorsQuery);
    $testDonorsCount = $donorsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($testDonorsCount < 50) {
        // Add 50 test donors
        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Lisa', 'Robert', 'Maria', 'James', 'Anna', 'William', 'Emma', 'Richard', 'Olivia', 'Charles', 'Sophia', 'Thomas', 'Isabella', 'Christopher', 'Ava', 'Daniel', 'Mia', 'Matthew', 'Charlotte', 'Anthony', 'Amelia', 'Mark', 'Harper', 'Donald', 'Evelyn', 'Steven', 'Abigail', 'Paul', 'Emily', 'Andrew', 'Elizabeth', 'Joshua', 'Sofia', 'Kenneth', 'Avery', 'Kevin', 'Ella', 'Brian', 'Madison', 'George', 'Scarlett', 'Timothy', 'Victoria', 'Ronald', 'Aria'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores', 'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts'];
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        
        for ($i = 1; $i <= 50; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $bloodType = $bloodTypes[array_rand($bloodTypes)];
            $email = "test_donor_$i@test.local";
            $phone = "09" . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $referenceCode = "TEST-" . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            $insertQuery = "
                INSERT INTO donors_new (
                    first_name, last_name, email, phone, blood_type, 
                    reference_code, status, seed_flag, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'approved', 1, NOW(), NOW())
            ";
            
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([$firstName, $lastName, $email, $phone, $bloodType, $referenceCode]);
        }
        
        echo "   ✅ Added 50 test donors\n";
    } else {
        echo "   ✅ Found $testDonorsCount test donors\n";
    }
    
    // Step 3: Add test blood units
    echo "\n3. Adding test blood units...\n";
    $unitsQuery = "SELECT COUNT(*) as count FROM blood_inventory WHERE seed_flag = 1";
    $unitsStmt = $pdo->query($unitsQuery);
    $testUnitsCount = $unitsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($testUnitsCount < 50) {
        // Get test donors
        $donorsQuery = "SELECT id, first_name, last_name, blood_type FROM donors_new WHERE seed_flag = 1 ORDER BY id";
        $donorsStmt = $pdo->query($donorsQuery);
        $testDonors = $donorsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $statuses = ['available', 'used', 'expired', 'quarantined'];
        $collectionSites = ['Main Blood Center', 'Mobile Unit A', 'Mobile Unit B', 'Hospital Collection', 'Community Center'];
        $storageLocations = ['Refrigerator A1', 'Refrigerator A2', 'Refrigerator B1', 'Freezer C1', 'Freezer C2'];
        
        foreach ($testDonors as $donor) {
            // Create 1-2 blood units per donor
            $unitsToCreate = rand(1, 2);
            
            for ($i = 0; $i < $unitsToCreate; $i++) {
                $unitId = 'TEST-' . str_pad($donor['id'], 3, '0', STR_PAD_LEFT) . '-' . ($i + 1);
                
                // Collection date: random date in the last 30 days
                $collectionDate = date('Y-m-d', strtotime('-' . rand(0, 30) . ' days'));
                
                // Expiry date: 42 days after collection
                $expiryDate = date('Y-m-d', strtotime($collectionDate . ' +42 days'));
                
                // Status: mostly available
                $status = 'available';
                if (rand(1, 10) <= 2) {
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
                    $unitId, $donor['id'], $donor['blood_type'], $collectionDate, $expiryDate,
                    $status, $collectionSite, $storageLocation, $volume,
                    $screeningStatus, $notes
                ]);
            }
        }
        
        echo "   ✅ Added test blood units\n";
    } else {
        echo "   ✅ Found $testUnitsCount test blood units\n";
    }
    
    // Final counts
    $totalDonorsQuery = "SELECT COUNT(*) as count FROM donors_new";
    $totalDonorsStmt = $pdo->query($totalDonorsQuery);
    $totalDonors = $totalDonorsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $totalUnitsQuery = "SELECT COUNT(*) as count FROM blood_inventory";
    $totalUnitsStmt = $pdo->query($totalUnitsQuery);
    $totalUnits = $totalUnitsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "\n=== Setup Complete! ===\n";
    echo "📊 Total Donors: $totalDonors\n";
    echo "🩸 Total Blood Units: $totalUnits\n";
    echo "\n🌐 View at: http://localhost/blood-donation-pwa/admin_blood_inventory_modern.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
