<?php
/**
 * Seed Test Data Script
 * Creates 50 synthetic test donors and 3 sample blood units for testing
 * All seeded data is clearly marked with seed_flag = 1
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

echo "Starting seed data creation...\n";

try {
    $pdo->beginTransaction();
    
    // Blood types distribution
    $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
    $bloodTypeWeights = [30, 6, 25, 4, 5, 1, 25, 4, 0]; // Realistic distribution
    
    // Generate 50 test donors
    $testDonors = [];
    for ($i = 1; $i <= 50; $i++) {
        $firstName = generateRandomName('first');
        $lastName = generateRandomName('last');
        $email = "donor{$i}@test.local";
        $phone = generateRandomPhone();
        $bloodType = getWeightedRandomBloodType($bloodTypes, $bloodTypeWeights);
        $referenceCode = "TEST-" . str_pad($i, 4, '0', STR_PAD_LEFT);
        
        $testDonors[] = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'blood_type' => $bloodType,
            'reference_code' => $referenceCode,
            'status' => 'approved', // All test donors are approved
            'seed_flag' => 1,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Insert test donors
    $donorStmt = $pdo->prepare("
        INSERT INTO donors_new (
            first_name, last_name, email, phone, blood_type, 
            reference_code, status, seed_flag, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertedDonorIds = [];
    foreach ($testDonors as $donor) {
        $donorStmt->execute([
            $donor['first_name'],
            $donor['last_name'],
            $donor['email'],
            $donor['phone'],
            $donor['blood_type'],
            $donor['reference_code'],
            $donor['status'],
            $donor['seed_flag'],
            $donor['created_at'],
            $donor['updated_at']
        ]);
        $insertedDonorIds[] = $pdo->lastInsertId();
    }
    
    echo "Inserted " . count($testDonors) . " test donors\n";
    
    // Generate 3 sample blood units linked to some of the seeded donors
    $sampleBloodUnits = [];
    $selectedDonorIds = array_slice($insertedDonorIds, 0, 3); // Take first 3 donors
    
    for ($i = 0; $i < 3; $i++) {
        $donorId = $selectedDonorIds[$i];
        $donor = $testDonors[$i];
        
        $unitId = "TEST-" . date('Ymd') . "-" . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
        $collectionDate = date('Y-m-d', strtotime('-' . rand(1, 10) . ' days'));
        $expiryDate = date('Y-m-d', strtotime($collectionDate . ' +42 days'));
        
        $sampleBloodUnits[] = [
            'unit_id' => $unitId,
            'donor_id' => $donorId,
            'blood_type' => $donor['blood_type'],
            'collection_date' => $collectionDate,
            'expiry_date' => $expiryDate,
            'status' => 'available',
            'collection_site' => 'Test Collection Center',
            'storage_location' => 'Test Storage A',
            'volume_ml' => 450,
            'screening_status' => 'passed',
            'notes' => 'Test blood unit for demonstration purposes',
            'seed_flag' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Insert sample blood units
    $bloodUnitStmt = $pdo->prepare("
        INSERT INTO blood_inventory (
            unit_id, donor_id, blood_type, collection_date, expiry_date,
            status, collection_site, storage_location, volume_ml, 
            screening_status, notes, seed_flag, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($sampleBloodUnits as $unit) {
        $bloodUnitStmt->execute([
            $unit['unit_id'],
            $unit['donor_id'],
            $unit['blood_type'],
            $unit['collection_date'],
            $unit['expiry_date'],
            $unit['status'],
            $unit['collection_site'],
            $unit['storage_location'],
            $unit['volume_ml'],
            $unit['screening_status'],
            $unit['notes'],
            $unit['seed_flag'],
            $unit['created_at'],
            $unit['updated_at']
        ]);
    }
    
    echo "Inserted " . count($sampleBloodUnits) . " test blood units\n";
    
    $pdo->commit();
    
    echo "\n✅ Seed data creation completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Test Donors: " . count($testDonors) . "\n";
    echo "   - Test Blood Units: " . count($sampleBloodUnits) . "\n";
    echo "   - All data marked with seed_flag = 1\n";
    echo "   - Test donors have (TEST) in their names\n";
    echo "   - Test blood units have TEST- prefix in unit IDs\n\n";
    
    echo "🔍 You can now:\n";
    echo "   1. View test donors in Admin → Donor List\n";
    echo "   2. View test blood units in Admin → Blood Inventory\n";
    echo "   3. Test pagination functionality\n";
    echo "   4. Run cleanup script to remove test data when done\n\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error creating seed data: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Generate random first or last name
 */
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

/**
 * Generate random phone number
 */
function generateRandomPhone() {
    $areaCodes = ['555', '123', '456', '789', '321', '654', '987', '147', '258', '369'];
    $areaCode = $areaCodes[array_rand($areaCodes)];
    $exchange = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    $number = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    return "{$areaCode}-{$exchange}-{$number}";
}

/**
 * Get weighted random blood type
 */
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
    
    return $bloodTypes[0]; // Fallback
}

// If running from web, show success message
if (!$isCommandLine) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Seed Data Created</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-8'>
                    <div class='card'>
                        <div class='card-header bg-success text-white'>
                            <h4 class='mb-0'>✅ Seed Data Created Successfully</h4>
                        </div>
                        <div class='card-body'>
                            <p>Test data has been created for development and testing purposes.</p>
                            <div class='alert alert-info'>
                                <strong>Summary:</strong><br>
                                • Test Donors: " . count($testDonors) . "<br>
                                • Test Blood Units: " . count($sampleBloodUnits) . "<br>
                                • All data marked with seed_flag = 1<br>
                                • Test donors have (TEST) in their names<br>
                                • Test blood units have TEST- prefix in unit IDs
                            </div>
                            <div class='d-flex gap-2'>
                                <a href='admin.php?tab=donor-list' class='btn btn-primary'>View Donor List</a>
                                <a href='admin_blood_inventory.php' class='btn btn-info'>View Blood Inventory</a>
                                <a href='cleanup_test_data.php' class='btn btn-warning'>Cleanup Test Data</a>
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
