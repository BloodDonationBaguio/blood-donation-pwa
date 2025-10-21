<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once(__DIR__ . '/../db.php');

    $full_name = trim($_POST["full_name"]);
    $phone = trim($_POST["phone"]);
    $email = trim($_POST["email"]);
    $blood_type = trim($_POST["blood_type"]);
    $city = trim($_POST["city"]);
    $chapter = isset($_POST["chapter"]) ? trim($_POST["chapter"]) : '';
    $age = intval($_POST["age"]);
    $donated_before = $_POST["donated_before"];
    $last_donation_date = ($donated_before === "yes" && !empty($_POST["last_donation_date"])) ? $_POST["last_donation_date"] : null;

    // Validate city, chapter, and phone
    if ($city !== "Baguio") {
        header("Location: ../admin-dashboard.php?tab=add-donor&adddonor_error=".urlencode("Registration is only allowed for Baguio residents."));
        exit();
    }
    if ($chapter !== "Philippine Red Cross - Baguio Chapter") {
        header("Location: ../admin-dashboard.php?tab=add-donor&adddonor_error=".urlencode("Please select the correct Red Cross chapter."));
        exit();
    }
    if (!preg_match('/^(09\\d{9}|\\+639\\d{9})$/', $phone)) {
        header("Location: ../admin-dashboard.php?tab=add-donor&adddonor_error=".urlencode("Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX)."));
        exit();
    }

    if ($full_name && $phone && $email && $blood_type && $city && $age >= 18 && $donated_before) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Generate a reference code
            $ref_code = 'DON-' . str_pad(0, 5, '0', STR_PAD_LEFT); // Will be updated after insert
            
            // Insert donor record with role and status
            $stmt = $pdo->prepare("INSERT INTO donors (full_name, phone, email, blood_type, city, age, last_donation_date, status, role, reference_code)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', 'donor', ?)");
            $stmt->execute([$full_name, $phone, $email, $blood_type, $city, $age, $last_donation_date, $ref_code]);
            
            // Update with actual reference code
            $donor_id = $pdo->lastInsertId();
            $ref_code = 'DON-' . str_pad($donor_id, 5, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE donors SET reference_code = ? WHERE id = ?");
            $stmt->execute([$ref_code, $donor_id]);
            
            // Commit transaction
            $pdo->commit();
            
            header("Location: ../admin-dashboard.php?tab=add-donor&adddonor_success=1");
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Admin donor registration error: " . $e->getMessage());
            header("Location: ../admin-dashboard.php?tab=add-donor&adddonor_error=".urlencode("An error occurred while saving the donor record. Please try again."));
            exit();
        }
    } else {
        $error = "";
        if (!$full_name) $error .= "Full name is required. ";
        if (!$phone) $error .= "Phone number is required. ";
        if (!$email) $error .= "Email is required. ";
        if (!$blood_type) $error .= "Blood type is required. ";
        if (!$city) $error .= "City is required. ";
        if ($age < 18) $error .= "Donor must be at least 18 years old. ";
        if (!$donated_before) $error .= "Please specify if donor has donated before. ";
        
        header("Location: ../admin-dashboard.php?tab=add-donor&adddonor_error=".urlencode(trim($error)));
        exit();
    }
} else {
    header("Location: ../admin-dashboard.php?tab=add-donor");
    exit();
}
?>
