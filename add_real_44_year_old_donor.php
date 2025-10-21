<?php
// add_real_44_year_old_donor.php
// This script will help add back the real 44-year-old donor

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Add Real 44-Year-Old Donor</h1>";
echo "<p>This script will help you add back the real 44-year-old donor who was lost.</p>";
echo "<hr>";

// Database configuration
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'blood_system';
$dbUser = 'root';
$dbPass = 'password112';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "<p style='color: green;'>✅ Connected to database successfully!</p>";

    // Check if we're adding via form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_donor'])) {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $bloodType = $_POST['blood_type'];
        $birthYear = $_POST['birth_year'];
        
        // Calculate age
        $currentYear = date('Y');
        $age = $currentYear - $birthYear;
        
        if ($age != 44) {
            echo "<p style='color: red;'>❌ Error: The age must be 44 years old. Please check the birth year.</p>";
        } else {
            // Generate reference code
            $referenceCode = 'REF-' . strtoupper(substr($firstName, 0, 2)) . strtoupper(substr($lastName, 0, 2)) . '-' . date('Ymd') . '-' . rand(100, 999);
            
            // Add the donor
            $stmt = $pdo->prepare("
                INSERT INTO donors_new (
                    first_name, last_name, email, phone, blood_type, 
                    date_of_birth, gender, status, reference_code, 
                    created_at, updated_at, seed_flag
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'served', ?, NOW(), NOW(), 0)
            ");
            
            $dateOfBirth = $birthYear . '-01-01'; // Use January 1st as default
            $gender = $_POST['gender'];
            
            if ($stmt->execute([$firstName, $lastName, $email, $phone, $bloodType, $dateOfBirth, $gender, $referenceCode])) {
                $donorId = $pdo->lastInsertId();
                
                // Add blood inventory entry for this donor
                $unitId = 'UNIT-' . $bloodType . '-' . str_pad($donorId, 3, '0', STR_PAD_LEFT);
                $collectionDate = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
                $expiryDate = date('Y-m-d', strtotime($collectionDate . ' +42 days'));
                
                $pdo->prepare("
                    INSERT INTO blood_inventory (
                        unit_id, donor_id, blood_type, collection_date, expiry_date, 
                        status, collection_site, storage_location, volume_ml, 
                        screening_status, units_available, created_at, updated_at, seed_flag
                    ) VALUES (?, ?, ?, ?, ?, 'available', 'Main Collection Center', 'A1', 450, 'passed', 1, NOW(), NOW(), 0)
                ")->execute([$unitId, $donorId, $bloodType, $collectionDate, $expiryDate]);
                
                echo "<p style='color: green;'>✅ Successfully added the real 44-year-old donor!</p>";
                echo "<p><strong>Donor Details:</strong></p>";
                echo "<ul>";
                echo "<li>Name: $firstName $lastName</li>";
                echo "<li>Email: $email</li>";
                echo "<li>Phone: $phone</li>";
                echo "<li>Blood Type: $bloodType</li>";
                echo "<li>Age: $age years old (born $birthYear)</li>";
                echo "<li>Reference Code: $referenceCode</li>";
                echo "<li>Status: served</li>";
                echo "</ul>";
                
                echo "<p style='color: green;'>✅ Blood inventory entry also created for this donor.</p>";
                echo "<p><a href='admin.php'>Go to Admin Dashboard</a> to see the updated data.</p>";
            } else {
                echo "<p style='color: red;'>❌ Error adding donor. Please try again.</p>";
            }
        }
    } else {
        // Show the form
        echo "<h2>Add the Real 44-Year-Old Donor</h2>";
        echo "<p>Please fill in the details of the real 44-year-old donor who was lost:</p>";
        
        echo "<form method='POST' style='max-width: 500px;'>";
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='first_name' style='display: block; margin-bottom: 5px;'><strong>First Name:</strong></label>";
        echo "<input type='text' id='first_name' name='first_name' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='last_name' style='display: block; margin-bottom: 5px;'><strong>Last Name:</strong></label>";
        echo "<input type='text' id='last_name' name='last_name' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='email' style='display: block; margin-bottom: 5px;'><strong>Email:</strong></label>";
        echo "<input type='email' id='email' name='email' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='phone' style='display: block; margin-bottom: 5px;'><strong>Phone:</strong></label>";
        echo "<input type='tel' id='phone' name='phone' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='blood_type' style='display: block; margin-bottom: 5px;'><strong>Blood Type:</strong></label>";
        echo "<select id='blood_type' name='blood_type' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "<option value=''>Select Blood Type</option>";
        echo "<option value='A+'>A+</option>";
        echo "<option value='A-'>A-</option>";
        echo "<option value='B+'>B+</option>";
        echo "<option value='B-'>B-</option>";
        echo "<option value='AB+'>AB+</option>";
        echo "<option value='AB-'>AB-</option>";
        echo "<option value='O+'>O+</option>";
        echo "<option value='O-'>O-</option>";
        echo "</select>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='birth_year' style='display: block; margin-bottom: 5px;'><strong>Birth Year (for 44 years old):</strong></label>";
        echo "<input type='number' id='birth_year' name='birth_year' value='1980' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "<small style='color: #666;'>This will make the donor 44 years old (born in 1980)</small>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 15px;'>";
        echo "<label for='gender' style='display: block; margin-bottom: 5px;'><strong>Gender:</strong></label>";
        echo "<select id='gender' name='gender' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "<option value=''>Select Gender</option>";
        echo "<option value='female'>Female</option>";
        echo "<option value='male'>Male</option>";
        echo "<option value='other'>Other</option>";
        echo "</select>";
        echo "</div>";
        
        echo "<div style='margin-bottom: 15px;'>";
        echo "<button type='submit' name='add_donor' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;'>Add Real 44-Year-Old Donor</button>";
        echo "</div>";
        echo "</form>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error!</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Unexpected Error!</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
