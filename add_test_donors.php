<?php
/**
 * Direct Test Donors Creation Script
 * Creates 50 test donors directly without complex migrations
 */

require_once 'db.php';

echo "=== Adding 50 Test Donors Directly ===\n\n";

try {
    // First, check if seed_flag column exists, if not add it
    echo "1. Checking/Adding seed_flag column...\n";
    
    try {
        $pdo->exec("ALTER TABLE donors_new ADD COLUMN seed_flag TINYINT(1) DEFAULT 0");
        echo "   ✅ Added seed_flag column to donors_new\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✅ seed_flag column already exists\n";
        } else {
            echo "   ⚠️  Warning: " . $e->getMessage() . "\n";
        }
    }
    
    // Check current donor count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new");
    $currentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Current donors in database: $currentCount\n";
    
    // Check if test donors already exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new WHERE email LIKE '%@test.local'");
    $testCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   Test donors already exist: $testCount\n";
    
    if ($testCount >= 50) {
        echo "\n✅ 50 test donors already exist! No need to create more.\n";
        echo "You can view them at: admin/pages/donors_simple.php\n";
        exit;
    }
    
    echo "\n2. Creating test donors...\n";
    
    // Create 50 test donors
    $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
    $bloodTypeWeights = [30, 6, 25, 4, 5, 1, 25, 4, 0];
    
    $createdCount = 0;
    $errorCount = 0;
    
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
                $firstName,
                $lastName,
                $email,
                $phone,
                $bloodType,
                $referenceCode,
                'approved',
                1, // seed_flag = 1 for test data
                date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
                date('Y-m-d H:i:s')
            ]);
            
            $createdCount++;
            if ($createdCount % 10 == 0) {
                echo "   Created $createdCount test donors...\n";
            }
            
        } catch (Exception $e) {
            $errorCount++;
            echo "   ⚠️  Error creating donor $i: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n3. Results:\n";
    echo "   ✅ Successfully created: $createdCount test donors\n";
    echo "   ❌ Errors: $errorCount\n";
    
    // Verify final count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new WHERE email LIKE '%@test.local'");
    $finalTestCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   📊 Total test donors now: $finalTestCount\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM donors_new");
    $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   📊 Total donors in database: $totalCount\n";
    
    if ($finalTestCount > 0) {
        echo "\n✅ SUCCESS! Test donors created.\n";
        echo "\nYou can now view them at:\n";
        echo "1. Simple paginated view: admin/pages/donors_simple.php\n";
        echo "2. Check all donors: check_test_data.php\n";
        
        // Show sample of created donors
        echo "\n4. Sample of created test donors:\n";
        $stmt = $pdo->query("
            SELECT first_name, last_name, email, reference_code, blood_type 
            FROM donors_new 
            WHERE email LIKE '%@test.local' 
            ORDER BY id DESC 
            LIMIT 5
        ");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($samples as $donor) {
            echo "   - {$donor['first_name']} {$donor['last_name']} ({$donor['email']}) - {$donor['blood_type']} - {$donor['reference_code']}\n";
        }
        
    } else {
        echo "\n❌ FAILED! No test donors were created.\n";
        echo "Check your database connection and table structure.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
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
