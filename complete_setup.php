<?php
/**
 * Complete Setup Script
 * Creates test donors and blood units with proper linking
 */

require_once 'db.php';

echo "=== Complete Blood Donation PWA Setup ===\n\n";

try {
    echo "1. Setting up database structure...\n";
    
    // Add seed_flag columns
    try {
        $pdo->exec("ALTER TABLE donors_new ADD COLUMN seed_flag TINYINT(1) DEFAULT 0");
        echo "   ✅ Added seed_flag to donors_new\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✅ seed_flag already exists in donors_new\n";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE blood_inventory ADD COLUMN seed_flag TINYINT(1) DEFAULT 0");
        echo "   ✅ Added seed_flag to blood_inventory\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✅ seed_flag already exists in blood_inventory\n";
        }
    }
    
    echo "\n2. Creating test donors...\n";
    
    // Check existing test donors
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new WHERE email LIKE '%@test.local'");
    $existingTestDonors = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($existingTestDonors >= 50) {
        echo "   ✅ $existingTestDonors test donors already exist\n";
    } else {
        // Create test donors
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
        $bloodTypeWeights = [30, 6, 25, 4, 5, 1, 25, 4, 0];
        
        $createdDonors = 0;
        for ($i = 1; $i <= 50; $i++) {
            $firstName = generateRandomName('first');
            $lastName = generateRandomName('last');
            $email = "donor{$i}@test.local";
            $phone = generateRandomPhone();
            $bloodType = getWeightedRandomBloodType($bloodTypes, $bloodTypeWeights);
            $referenceCode = "TEST-" . str_pad($i, 4, '0', STR_PAD_LEFT);
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO donors_new (
                        first_name, last_name, email, phone, blood_type, 
                        reference_code, status, seed_flag, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $firstName, $lastName, $email, $phone, $bloodType,
                    $referenceCode, 'approved', 1,
                    date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
                    date('Y-m-d H:i:s')
                ]);
                
                $createdDonors++;
                if ($createdDonors % 10 == 0) {
                    echo "   Created $createdDonors test donors...\n";
                }
            } catch (Exception $e) {
                // Skip if already exists
            }
        }
        
        echo "   ✅ Created $createdDonors test donors\n";
    }
    
    echo "\n3. Creating test blood units...\n";
    
    // Get test donors for blood unit creation
    $stmt = $pdo->query("SELECT id, first_name, last_name, blood_type FROM donors_new WHERE email LIKE '%@test.local' ORDER BY id LIMIT 20");
    $testDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check existing test blood units
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM blood_inventory WHERE unit_id LIKE 'TEST-%'");
    $existingTestUnits = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($existingTestUnits >= 20) {
        echo "   ✅ $existingTestUnits test blood units already exist\n";
    } else {
        $createdUnits = 0;
        
        // Create blood units for test donors
        for ($i = 0; $i < min(20, count($testDonors)); $i++) {
            $donor = $testDonors[$i];
            
            $unitId = "TEST-" . date('Ymd') . "-" . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $collectionDate = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
            $expiryDate = date('Y-m-d', strtotime($collectionDate . ' +42 days'));
            
            // Random status distribution
            $statuses = ['available', 'available', 'available', 'used', 'expired'];
            $status = $statuses[array_rand($statuses)];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO blood_inventory (
                        unit_id, donor_id, blood_type, collection_date, expiry_date,
                        status, collection_site, storage_location, volume_ml, 
                        screening_status, notes, seed_flag, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $unitId, $donor['id'], $donor['blood_type'], $collectionDate, $expiryDate,
                    $status, 'Test Collection Center', 'Test Storage ' . chr(65 + ($i % 3)),
                    450, 'passed', 'Test blood unit for ' . $donor['first_name'] . ' ' . $donor['last_name'],
                    1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
                ]);
                
                $createdUnits++;
                if ($createdUnits % 5 == 0) {
                    echo "   Created $createdUnits test blood units...\n";
                }
            } catch (Exception $e) {
                // Skip if already exists
            }
        }
        
        echo "   ✅ Created $createdUnits test blood units\n";
    }
    
    echo "\n4. Verification...\n";
    
    // Count all donors
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new");
    $totalDonors = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new WHERE email LIKE '%@test.local'");
    $testDonors = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count all blood units
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM blood_inventory");
    $totalUnits = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM blood_inventory WHERE unit_id LIKE 'TEST-%'");
    $testUnits = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "   📊 Total donors: $totalDonors (Test: $testDonors)\n";
    echo "   📊 Total blood units: $totalUnits (Test: $testUnits)\n";
    
    // Check donor-blood unit linking
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM blood_inventory bi 
        JOIN donors_new d ON bi.donor_id = d.id
    ");
    $linkedUnits = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   📊 Linked blood units: $linkedUnits\n";
    
    echo "\n✅ Setup completed successfully!\n";
    echo "\nYou can now view the data at:\n";
    echo "1. All donors: view_all_donors.php\n";
    echo "2. Paginated donors: admin/pages/donors_simple.php\n";
    echo "3. Paginated blood inventory: admin/pages/blood_inventory_simple.php\n";
    echo "4. Check test data: check_test_data.php\n";
    
    // Show sample data
    echo "\n5. Sample of created data:\n";
    
    echo "   Test Donors:\n";
    $stmt = $pdo->query("
        SELECT first_name, last_name, email, blood_type, reference_code 
        FROM donors_new 
        WHERE email LIKE '%@test.local' 
        ORDER BY id DESC 
        LIMIT 3
    ");
    $sampleDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sampleDonors as $donor) {
        echo "   - {$donor['first_name']} {$donor['last_name']} ({$donor['email']}) - {$donor['blood_type']}\n";
    }
    
    echo "   Test Blood Units:\n";
    $stmt = $pdo->query("
        SELECT bi.unit_id, bi.blood_type, bi.status, d.first_name, d.last_name
        FROM blood_inventory bi
        JOIN donors_new d ON bi.donor_id = d.id
        WHERE bi.unit_id LIKE 'TEST-%'
        ORDER BY bi.id DESC
        LIMIT 3
    ");
    $sampleUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sampleUnits as $unit) {
        echo "   - {$unit['unit_id']} ({$unit['blood_type']}) - {$unit['status']} - Donor: {$unit['first_name']} {$unit['last_name']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error during setup: " . $e->getMessage() . "\n";
}

// Helper functions
function generateRandomName($type) {
    $firstNames = [
        'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Jessica',
        'William', 'Ashley', 'James', 'Amanda', 'Christopher', 'Jennifer', 'Daniel',
        'Lisa', 'Matthew', 'Nancy', 'Anthony', 'Karen', 'Mark', 'Betty', 'Donald',
        'Helen', 'Steven', 'Sandra', 'Paul', 'Donna', 'Andrew', 'Carol', 'Joshua',
        'Ruth', 'Kenneth', 'Sharon', 'Kevin', 'Michelle', 'Brian', 'Laura', 'George',
        'Sarah', 'Edward', 'Kimberly', 'Ronald', 'Deborah', 'Timothy', 'Dorothy',
        'Jason', 'Lisa', 'Jeffrey', 'Nancy', 'Ryan', 'Karen', 'Jacob', 'Betty'
    ];
    
    $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
        'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
        'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker',
        'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill',
        'Flores', 'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell',
        'Mitchell', 'Carter', 'Roberts', 'Gomez', 'Phillips', 'Evans', 'Turner', 'Diaz'
    ];
    
    $names = $type === 'first' ? $firstNames : $lastNames;
    return $names[array_rand($names)];
}

function generateRandomPhone() {
    $areaCodes = ['555', '123', '456', '789', '321', '654', '987', '147', '258', '369'];
    $areaCode = $areaCodes[array_rand($areaCodes)];
    $exchange = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    $number = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    return "{$areaCode}-{$exchange}-{$number}";
}

function getWeightedRandomBloodType($bloodTypes, $weights) {
    $totalWeight = array_sum($weights);
    $random = rand(1, $totalWeight);
    
    $currentWeight = 0;
    for ($i = 0; $i < count($bloodTypes); $i++) {
        $currentWeight += $weights[$i];
        if ($random <= $currentWeight) {
            return $bloodTypes[$i];
        }
    }
    
    return $bloodTypes[0];
}
?>
