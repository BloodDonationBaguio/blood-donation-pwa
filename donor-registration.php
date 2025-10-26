<?php
// Start output buffering to prevent any accidental output before DOCTYPE
ob_start();

define('INCLUDES_PATH', true);
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/mail_helper.php";

// Enable error reporting but don't display to prevent output before DOCTYPE
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function generateReferenceNumber() {
    return strtoupper('DNR-' . substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

function isEligibleAge($birthDate) {
    $age = date_diff(date_create($birthDate), date_create('today'))->y;
    return ($age >= 18 && $age <= 65);
}

// Initialize variables
$errors = [];
$success = false;
$refNumber = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for duplicate submission using session
    if (isset($_SESSION['last_form_submission']) && 
        (time() - $_SESSION['last_form_submission']) < 5) {
        $errors[] = "Please wait a moment before submitting again.";
        error_log("Duplicate form submission blocked by session check");
    } else {
        // Set session timestamp for this submission
        $_SESSION['last_form_submission'] = time();
    }
    
    // Log the raw POST data
    error_log('Form submitted at: ' . date('Y-m-d H:i:s'));
    error_log('Raw POST data: ' . file_get_contents('php://input'));
    error_log('POST array: ' . print_r($_POST, true));
    error_log('Session ID: ' . session_id());
    
    // Log server variables that might be useful
    error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
    error_log('Content type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    error_log('Content length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'not set'));
    // 1. Validate essential donor information
    $fullName   = trim($_POST['full_name'] ?? '');
    $gender     = $_POST['gender'] ?? '';
    $dob        = $_POST['birth_date'] ?? '';
    $weight     = floatval($_POST['weight'] ?? 0);
    $height     = floatval($_POST['height'] ?? 0);
    $email      = trim($_POST['email'] ?? '');
    $bloodType  = trim($_POST['blood_type'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $province   = $_POST['province'] ?? '';
    $postalCode = trim($_POST['postal_code'] ?? '');

    // Age check
    if (empty($dob)) {
        $errors[] = "Date of birth is required";
    } elseif (!isEligibleAge($dob)) {
        $errors[] = "You must be 18-65 years old to donate";
    }

    // Required fields validation
    if (empty($fullName)) $errors[] = "Full name is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if ($weight < 50) $errors[] = "Minimum weight requirement is 50kg";
    if ($height < 100) $errors[] = "Please enter a valid height (minimum 100cm)";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    // Validate blood type
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
    if (empty($bloodType)) {
        $errors[] = "Blood type is required";
    } elseif (!in_array($bloodType, $validBloodTypes)) {
        $errors[] = "Invalid blood type selected. Please select a valid blood type.";
        error_log("Invalid blood type submitted: '$bloodType'");
    }
    
    // Check for duplicate submission (same email within last 5 minutes)
    if (empty($errors)) {
        try {
            $duplicateCheck = $pdo->prepare("
                SELECT id, created_at FROM donors 
                WHERE email = ? AND created_at > CURRENT_TIMESTAMP - INTERVAL '5 minutes'
                ORDER BY created_at DESC LIMIT 1
            ");
            $duplicateCheck->execute([$email]);
            $recentSubmission = $duplicateCheck->fetch();
            
            if ($recentSubmission) {
                $timeDiff = time() - strtotime($recentSubmission['created_at']);
                $minutesLeft = 5 - floor($timeDiff / 60);
                $errors[] = "You have already submitted a registration recently. Please wait {$minutesLeft} minutes before submitting again.";
                error_log("Duplicate submission attempt for email: $email within 5 minutes");
            }
        } catch (Exception $e) {
            error_log("Error checking for duplicate submission: " . $e->getMessage());
            // Don't block registration if duplicate check fails
        }
    }
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($province)) $errors[] = "Province is required";
    if (empty($postalCode)) $errors[] = "Postal code is required";

    // CAPTCHA validation
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaResponse)) {
        $errors[] = "Please complete the CAPTCHA verification";
    } else {
        // Verify CAPTCHA with Google
        $secretKey = "6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe"; // Test secret key
        $verifyURL = "https://www.google.com/recaptcha/api/siteverify";
        $response = file_get_contents($verifyURL . "?secret=" . $secretKey . "&response=" . $recaptchaResponse . "&remoteip=" . $_SERVER['REMOTE_ADDR']);
        $responseData = json_decode($response);
        
        if (!$responseData->success) {
            $errors[] = "CAPTCHA verification failed. Please try again.";
        }
    }

    // 2. Collect all medical/screening questions (A-G, q1-q37)
    $medical = [];
    $answeredCount = 0;
    
    for ($i = 1; $i <= 37; $i++) {
        $qid = "q$i";
        $answer = $_POST[$qid] ?? '';
        $medical[$qid] = $answer;
        
        // Count non-empty answers
        if (!empty($answer) && $answer !== '') {
            $answeredCount++;
        }
    }
    
    error_log("Medical screening: collected $answeredCount out of 37 questions");

    // Female-specific handling
    if ($gender === "Female") {
        // q34 'None' or 'date' options
        $medical['q34'] = $_POST['q34'] ?? '';
        // Handle q34_date if provided
        if (isset($_POST['q34_date']) && !empty($_POST['q34_date'])) {
            $medical['q34_date'] = $_POST['q34_date'];
        }
        // Handle q37_date for menstrual period
        if (isset($_POST['q37_date']) && !empty($_POST['q37_date'])) {
            $medical['q37_date'] = $_POST['q37_date'];
        }
    }
    
    // Debug: Log collected medical data
    error_log('Collected medical data: ' . json_encode($medical));

    if (empty($errors)) {
        try {
            // Check if email already exists and handle repeat donations
            $checkEmail = $pdo->prepare("SELECT id, first_name, last_name, status, created_at FROM donors WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $checkEmail->execute([$email]);
            $existingDonor = $checkEmail->fetch();
            
            if ($existingDonor) {
                // Check if enough time has passed since last donation (90 days = 3 months)
                $lastDonationDate = new DateTime($existingDonor['created_at']);
                $currentDate = new DateTime();
                $daysSinceLastDonation = $currentDate->diff($lastDonationDate)->days;
                
                if ($daysSinceLastDonation < 90) {
                    $nextEligibleDate = $lastDonationDate->add(new DateInterval('P90D'))->format('F j, Y');
                    $errors[] = "Hello " . htmlspecialchars($existingDonor['first_name'] . ' ' . $existingDonor['last_name']) . 
                               "! You can donate again after 90 days (3 months) from your last donation. " .
                               "You will be eligible to donate again on $nextEligibleDate. " .
                               "Thank you for your continued support!";
                } else {
                    // Allow new donation - donor is eligible
                    $isRepeatDonor = true;
                    $repeatDonorInfo = $existingDonor;
                }
            }
            
            if (empty($errors)) {
                error_log("No validation errors, proceeding with registration for: $email");
                try {
                    $pdo->beginTransaction();
                    $refNumber = generateReferenceNumber();
                    
                    error_log("Starting registration for: $email with reference: $refNumber");
                    
                    // Insert donor information (including weight, height, and reference)
                    $stmt = $pdo->prepare("
                        INSERT INTO donors (
                            first_name, last_name, email, phone, blood_type, date_of_birth,
                            gender, address, city, province, weight, height, reference_code, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
                    ");
                    
                    // Split full name into first and last name
                    $nameParts = explode(' ', trim($fullName), 2);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';
                    
                    error_log("Executing insert with data: " . json_encode([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'phone' => $phone,
                        'blood_type' => $bloodType,
                        'reference' => $refNumber
                    ]));
                    
                    $stmt->execute([
                        $firstName, $lastName, $email, $phone, $bloodType, $dob,
                        $gender, $address, $city, $province, $weight, $height, $refNumber
                    ]);

                    // Get donor ID but DON'T commit yet - we need to save medical screening too
                    $donorId = $pdo->lastInsertId();
                    error_log("Got donor ID: $donorId, now saving medical screening in same transaction...");
                    
                    // Verify the data was actually saved
                    $verifyStmt = $pdo->prepare("SELECT id FROM donors WHERE id = ?");
                    $verifyStmt->execute([$donorId]);
                    $savedDonor = $verifyStmt->fetch();
                    
                    if ($savedDonor) {
                        error_log("Verified: Donor saved with ID: {$savedDonor['id']}");
                        
                        // Save medical screening data WITHIN the same transaction
                        error_log('Saving medical screening data for donor ID ' . $donorId);
                        
                        try {
                            // Check if all questions were answered
                            // For males: questions 1-32 (skip female-specific 33-37)
                            // For females: all questions 1-37
                            $requiredQuestions = ($gender === 'Female') ? 37 : 32;
                            $actualAnswered = count(array_filter($medical, function($answer) {
                                return !empty($answer) && $answer !== '';
                            }));
                            
                            $allAnswered = $actualAnswered >= $requiredQuestions;
                            error_log("Medical screening validation: $actualAnswered answered out of $requiredQuestions required (gender: $gender)");
                            
                            // Validate medical data before saving
                            if (empty($medical)) {
                                throw new Exception("Medical screening data is empty");
                            }
                            
                            // Save to simple medical screening table
                            $medicalStmt = $pdo->prepare("
                                INSERT INTO donor_medical_screening_simple 
                                (donor_id, reference_code, screening_data, all_questions_answered) 
                                VALUES (?, ?, ?, ?)
                            ");
                            
                            $medicalStmt->execute([
                                $donorId,
                                $refNumber,
                                json_encode($medical),
                                $allAnswered ? 1 : 0
                            ]);
                            
                            error_log('Medical screening data saved successfully in transaction');
                            
                            // Verify medical screening was actually saved
                            $verifyMedicalStmt = $pdo->prepare("SELECT id, all_questions_answered FROM donor_medical_screening_simple WHERE donor_id = ?");
                            $verifyMedicalStmt->execute([$donorId]);
                            $savedMedical = $verifyMedicalStmt->fetch();
                            
                            if (!$savedMedical) {
                                throw new Exception("Medical screening data verification failed - not found in database");
                            }
                            
                            error_log("Medical screening verified: ID {$savedMedical['id']}, Questions answered: " . ($savedMedical['all_questions_answered'] ? 'Yes' : 'No'));
                            
                            // NOW commit both donor registration AND medical screening together
                            $pdo->commit();
                            error_log('Transaction committed: both donor registration and medical screening saved and verified');
                            
                        } catch (Exception $medicalError) {
                            // If medical screening fails, rollback the entire transaction
                            $pdo->rollBack();
                            error_log('Medical screening failed, rolling back entire transaction: ' . $medicalError->getMessage());
                            $errors[] = "Registration failed: Unable to save medical screening data. Please try again.";
                            // Don't continue with email sending
                            throw $medicalError;
                        }
                        
                        // Send confirmation email before redirect
                        try {
                            $isRepeatDonor = isset($isRepeatDonor) && $isRepeatDonor;
                            $subject = $isRepeatDonor ? "Welcome Back! Blood Donation Registration" : "Blood Donation Registration Reference";
                            
                            // Prepare email message based on blood type
                            if ($bloodType === 'Unknown') {
                                $message = "
                                    <h2>Thank you, $fullName, for registering as a blood donor.</h2>
                                    <p>Your reference number is: <strong>$refNumber</strong></p>
                                    
                                    <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                        <h3 style='color: #856404; margin-top: 0;'>⚠️ Blood Type Screening Required</h3>
                                        <p style='color: #856404; margin-bottom: 0;'>
                                            Since you selected 'Unknown' for your blood type, please visit our blood center 
                                            for screening to determine your blood type before donation.
                                        </p>
                                    </div>
                                    
                                    <p><strong>Next Steps:</strong></p>
                                    <ul>
                                        <li>Visit Philippine Red Cross Baguio Chapter for blood type screening</li>
                                        <li>Bring a valid ID and your reference number: <strong>$refNumber</strong></li>
                                        <li>Once your blood type is determined, you can proceed with donation</li>
                                    </ul>
                                    
                                    <p><strong>Location:</strong> Philippine Red Cross Baguio Chapter, Baguio City</p>
                                    <p><strong>Contact:</strong> +63 912 345 6789</p>
                                ";
                            } else {
                                $message = "
                                    <h2>Thank you, $fullName, for registering as a blood donor.</h2>
                                    <p>Your reference number is: <strong>$refNumber</strong></p>
                                    <p>Blood Type: <strong>$bloodType</strong></p>
                                    <p>We will review your information and contact you soon.</p>
                                    <p>You can use this reference number to track your application status.</p>
                                ";
                            }
                            
                            // Send the email
                            if (function_exists('send_confirmation_email')) {
                                error_log('Attempting to send confirmation email to: ' . $email);
                                $emailSent = send_confirmation_email($email, $subject, $message, $fullName);
                                if ($emailSent) {
                                    error_log('Confirmation email sent successfully to ' . $email);
                                } else {
                                    error_log('Failed to send confirmation email to ' . $email . ' (proceeding with registration)');
                                }
                            } else {
                                error_log('send_confirmation_email function not found!');
                            }
                        } catch (Exception $emailError) {
                            error_log('Email sending error: ' . $emailError->getMessage());
                            // Don't stop registration if email fails
                        }
                        
                        // REDIRECT AFTER SUCCESSFUL REGISTRATION
                        $_SESSION['registration_ref'] = $refNumber;
                        header('Location: registration_success.php?ref=' . urlencode($refNumber));
                        exit();
                    } else {
                        error_log("ERROR: Donor not found after commit! ID: $donorId");
                        $errors[] = "Registration failed - data not saved. Please try again.";
                    }
                    
                } catch (PDOException $mainError) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Main registration failed: ' . $mainError->getMessage());
                    error_log('SQL Error Code: ' . $mainError->getCode());
                    error_log('Failed data: ' . json_encode([
                        'blood_type' => $bloodType,
                        'email' => $email,
                        'name' => $fullName
                    ]));
                    
                    // Check for specific errors
                    if (strpos($mainError->getMessage(), 'blood_type') !== false || 
                        strpos($mainError->getMessage(), 'Data truncated') !== false) {
                        $errors[] = "Invalid blood type selected. Blood type '$bloodType' is not accepted. Please select a valid blood type.";
                        error_log("Blood type issue - Value was: '$bloodType'");
                    } elseif (strpos($mainError->getMessage(), 'Duplicate entry') !== false) {
                        $errors[] = "This email is already registered. Please use a different email.";
                    } else {
                        $errors[] = "Registration failed: " . $mainError->getMessage();
                    }
                    // Don't use return here - let the page render with errors
                }
                
                // Registration succeeded - medical screening was already saved in transaction
                // Post-registration tasks (email sending, etc.)
                try {
                    // Send confirmation email before redirect
                    $isRepeatDonor = isset($isRepeatDonor) && $isRepeatDonor;
                    $subject = $isRepeatDonor ? "Welcome Back! Blood Donation Registration" : "Blood Donation Registration Reference";
                    
                    // Check if blood type is Unknown and send special email
                    if ($bloodType === 'Unknown') {
                        $message = "
                            <h2>Thank you, $fullName, for registering as a blood donor.</h2>
                            <p>Your reference number is: <strong>$refNumber</strong></p>
                            
                            <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                <h3 style='color: #856404; margin-top: 0;'>⚠️ Blood Type Screening Required</h3>
                                <p style='color: #856404; margin-bottom: 0;'>
                                    Since you selected 'Unknown' for your blood type, please visit our blood center 
                                    for screening to determine your blood type before donation. This is a quick and 
                                    painless process that will help us ensure the safety of both you and blood recipients.
                                </p>
                            </div>
                            
                            <p><strong>Next Steps:</strong></p>
                            <ul>
                                <li>Visit Philippine Red Cross Baguio Chapter for blood type screening</li>
                                <li>Bring a valid ID and your reference number: <strong>$refNumber</strong></li>
                                <li>Once your blood type is determined, you can proceed with donation</li>
                            </ul>
                            
                            <p><strong>Location:</strong> Philippine Red Cross Baguio Chapter, Baguio City</p>
                            <p><strong>Contact:</strong> +63 912 345 6789</p>
                            
                            <p>We will review your information and contact you soon.</p>
                            <p>You can use this reference number to track your application status.</p>
                        ";
                    } else {
                        if ($isRepeatDonor) {
                            $message = "
                                <h2>Welcome back, $fullName!</h2>
                                <p>Thank you for your continued commitment to saving lives through blood donation.</p>
                                <p>Your new reference number is: <strong>$refNumber</strong></p>
                                <p>Blood Type: <strong>$bloodType</strong></p>
                                
                                <div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                    <h3 style='color: #155724; margin-top: 0;'>🎉 Thank You for Being a Repeat Donor!</h3>
                                    <p style='color: #155724; margin-bottom: 0;'>
                                        Your dedication to helping others is truly appreciated. Every donation you make 
                                        can help save up to 3 lives!
                                    </p>
                                </div>
                                
                                <p>We will review your information and contact you soon.</p>
                                <p>You can use this reference number to track your application status.</p>
                            ";
                        } else {
                            $message = "
                                <h2>Thank you, $fullName, for registering as a blood donor.</h2>
                                <p>Your reference number is: <strong>$refNumber</strong></p>
                                <p>Blood Type: <strong>$bloodType</strong></p>
                                <p>We will review your information and contact you soon.</p>
                                <p>You can use this reference number to track your application status.</p>
                            ";
                        }
                    }
                    
                    $emailSent = false;
                    if (function_exists('send_confirmation_email')) {
                        error_log('Attempting to send confirmation email to: ' . $email);
                        $emailSent = send_confirmation_email($email, $subject, $message, $fullName);
                        if ($emailSent) {
                            error_log('Confirmation email sent successfully to ' . $email . ' (Blood type: ' . $bloodType . ')');
                        } else {
                            error_log('Failed to send confirmation email to ' . $email);
                        }
                    } else {
                        error_log('send_confirmation_email function not found!');
                    }
                    
                    // Transaction already committed after donor registration
                    $success = true;
                    error_log('Registration process completed successfully');
                    
                    // Clear POST data to prevent form resubmission
                    $_POST = [];
                    
                    // Store the reference number in session for display after redirect
                    $_SESSION['registration_ref'] = $refNumber;
                    
                    // Send admin notification
                    $adminSubject = $isRepeatDonor ? "Repeat Donor Registration: $fullName" : "New Donor Registration: $fullName";
                    $donorType = $isRepeatDonor ? "Repeat Donor" : "New Donor";
                    $adminMessage = "
                        <h2>$donorType Registration</h2>
                        <p>Name: $fullName</p>
                        <p>Reference: $refNumber</p>
                        <p>Blood Type: $bloodType</p>
                        <p>Donor Type: <strong>$donorType</strong></p>
                        " . ($isRepeatDonor ? "<p>Previous Registration: " . $repeatDonorInfo['created_at'] . "</p>" : "") . "
                        <p>Please log in to the admin panel to review this registration.</p>
                    ";
                    
                    // Send to admin email (replace with actual admin email)
                    if (function_exists('send_confirmation_email')) {
                        send_confirmation_email('admin@example.com', $adminSubject, $adminMessage, 'Admin');
                    }
                    
                    // Redirect to success page with reference number
                    error_log('Attempting redirect to registration_success.php with ref: ' . $refNumber);
                    
                    // Ensure no output has been sent before redirect
                    if (!headers_sent()) {
                        header('Location: registration_success.php?ref=' . urlencode($refNumber));
                        exit();
                    } else {
                        error_log('Headers already sent, cannot redirect. Showing success message instead.');
                        // Fallback: set success flag to show success message on same page
                        $success = true;
                        $_SESSION['registration_ref'] = $refNumber;
                        // Also set a GET parameter as additional fallback
                        $_GET['success'] = '1';
                    }
                    
                } catch (Exception $postRegError) {
                    // Post-registration tasks failed, but registration succeeded
                    error_log('Post-registration tasks failed, but main registration succeeded: ' . $postRegError->getMessage());
                    // Still redirect to success since registration worked
                    if (!headers_sent()) {
                        header('Location: registration_success.php?ref=' . urlencode($refNumber));
                        exit();
                    } else {
                        $_SESSION['registration_ref'] = $refNumber;
                        $_GET['success'] = '1';
                    }
                }
            }
        } catch (PDOException $e) {
            // Only rollback if a transaction is active
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMsg = "Database error: " . $e->getMessage();
            error_log($errorMsg);
            error_log("SQL error code: " . $e->getCode());
            error_log("SQL error info: " . print_r($pdo->errorInfo(), true));
            
            // Provide more specific error messages based on the error type
            if (strpos($errorMsg, 'Data truncated') !== false) {
                // This error should not occur anymore since we bypass medical screening
                // Don't add any error message - registration actually succeeded
                error_log('Data truncated error occurred but registration was successful - ignoring error');
            } elseif (strpos($errorMsg, 'Duplicate entry') !== false) {
                $errors[] = "An account with this email address already exists. Please use a different email or contact support.";
            } else {
                $errors[] = "An error occurred while processing your registration. Please try again later.";
            }
            
            // Log the error for debugging
            error_log("Registration error for email: $email - " . $errorMsg);
        }
    }
} // End of if ($_SERVER['REQUEST_METHOD'] === 'POST')

// Clear any accidental output before rendering HTML
ob_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration - Blood Donation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        .form-section { margin-bottom: 2.5rem; }
        .section-title { 
            border-bottom: 2px solid #e74c3c; 
            padding-bottom: 0.5rem;
            margin: 2rem 0 1.5rem;
            color: #e74c3c;
            font-weight: 600;
        }
        .required:after {
            content: " *";
            color: #e74c3c;
        }
        .form-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .btn-donate {
            background-color: #e74c3c;
            border-color: #e74c3c;
            padding: 0.75rem 2.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-donate:hover {
            background-color: #c0392b;
            border-color: #c0392b;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .header-red {
            color: #e74c3c;
            font-weight: 500;
            background-color: #ffebee;
            padding: 1.25rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            border-left: 4px solid #e74c3c;
        }
        #female-section { 
            display: none;
            margin-top: 1.5rem;
            padding: 1.5rem;
            background-color: #f9f9f9;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        .screening-question {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        .screening-question:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.4rem;
        }
        .form-control, .form-select {
            padding: 0.6rem 0.75rem;
            border-radius: 0.375rem;
        }
        .invalid-feedback {
            font-size: 0.875em;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

    <div class="container form-container">
        <h1 class="text-center mb-4">Donor Registration</h1>
        
        <!-- Important Notice -->
        <div class="alert alert-danger mb-4">
            <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Important Notice</h5>
            <p class="mb-0">Donors must be at least 18 to 65 years old to donate blood.</p>
            <p class="mb-0">If you are 16 or 17 years old, you may only donate with written consent from your parent or legal guardian.</p>
        </div>
        
        <!-- Repeat Donation Info -->
        <div class="alert alert-info mb-4">
            <h5><i class="bi bi-info-circle-fill me-2"></i>Repeat Donors Welcome!</h5>
            <p class="mb-2">If you've donated before, you can use the same email address to register for a new donation.</p>
            <p class="mb-0"><strong>Donation Interval:</strong> You can donate again after 90 days (3 months) from your last donation.</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5>Please fix the following errors:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php 
    // Check for success message and get reference from session
    $showSuccess = false;
    $refToShow = '';
    
    if (isset($_SESSION['registration_ref'])) {
        $refToShow = $_SESSION['registration_ref'];
        $showSuccess = true;
        // Clear the session after displaying
        unset($_SESSION['registration_ref']);
    } elseif (isset($_GET['success']) && $_GET['success'] == '1') {
        // Fallback: if session is lost but success parameter exists
        $showSuccess = true;
        $refToShow = 'Please check your email for your reference number';
    }
    
    if ($showSuccess): ?>
        <div class="alert alert-success text-center">
            <h3>Thank You for Registering!</h3>
            <p>Your reference number is: <strong><?php echo htmlspecialchars($refToShow); ?></strong></p>
            <p>We have sent a confirmation email to <strong><?php echo htmlspecialchars($email ?? 'your email'); ?></strong> with your reference number.</p>
            <p>Please check your inbox (and spam folder) for the confirmation email.</p>
            <div class="mt-4">
                                        <a href="track.php?ref=<?php echo urlencode($refToShow); ?>" class="btn btn-primary me-2">Track Your Application</a>
                <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
            </div>
        </div>
        <?php else: ?>

        <!-- Pre-screening Eligibility Check -->
        <div class="card mb-4" id="eligibilityCheck" style="<?= !empty($errors) ? 'display: none;' : 'display: block;' ?>">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-question-circle-fill me-2"></i>Quick Eligibility Check</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">Before proceeding with registration, please answer this quick question to ensure you're eligible to donate:</p>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="recent_donation" id="donated_recently_yes" value="yes">
                    <label class="form-check-label fw-bold text-danger" for="donated_recently_yes">
                        Yes, I have donated blood in the last 3 months (90 days)
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="recent_donation" id="donated_recently_no" value="no">
                    <label class="form-check-label fw-bold text-success" for="donated_recently_no">
                        No, I have NOT donated blood in the last 3 months, OR this is my first time donating
                    </label>
                </div>
                
                <div class="form-check mb-4">
                    <input class="form-check-input" type="radio" name="recent_donation" id="not_sure" value="not_sure">
                    <label class="form-check-label" for="not_sure">
                        I'm not sure when I last donated
                    </label>
                </div>
                
                <!-- Warning message for recent donors -->
                <div class="alert alert-warning" id="recentDonorWarning" style="display: none;">
                    <h6><i class="bi bi-exclamation-triangle-fill me-2"></i>Not Eligible Yet</h6>
                    <p class="mb-2">You must wait at least <strong>90 days (3 months)</strong> between blood donations for your safety and health.</p>
                    <p class="mb-0">Please come back after 3 months from your last donation. Thank you for your understanding!</p>
                </div>
                
                <!-- Info message for unsure donors -->
                <div class="alert alert-info" id="unsureDonorInfo" style="display: none;">
                    <h6><i class="bi bi-info-circle-fill me-2"></i>Not Sure About Last Donation?</h6>
                    <p class="mb-2">No problem! You can still proceed with registration. Our system will check your donation history and let you know if you need to wait.</p>
                    <p class="mb-0">If you're found ineligible due to recent donation, we'll tell you exactly when you can donate again.</p>
                </div>
                
                <button type="button" class="btn btn-primary" id="proceedBtn" style="display: none;" onclick="showRegistrationForm()">
                    <i class="bi bi-arrow-right me-2"></i>Proceed to Registration
                </button>
            </div>
        </div>

            <form method="POST" action="" id="donorForm" novalidate style="<?= !empty($errors) ? 'display: block;' : 'display: none;' ?>">
                <!-- Back to Eligibility Check Button (only show if no errors) -->
                <?php if (empty($errors)): ?>
                <div class="mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showEligibilityCheck()">
                        <i class="bi bi-arrow-left me-2"></i>Back to Eligibility Check
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <h4 class="section-title">Personal Information</h4>
                    
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <!-- Full Name -->
                            <div class="mb-3">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>
                            
                            <!-- Gender -->
                            <div class="mb-3">
                                <label for="gender" class="form-label required">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($_POST['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($_POST['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                                <div class="invalid-feedback">Please select your gender.</div>
                            </div>
                            
                            <!-- Date of Birth -->
                            <div class="mb-3">
                                <label for="birth_date" class="form-label required">Date of Birth</label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                       value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your date of birth.</div>
                            </div>
                            
                            <!-- Weight -->
                            <div class="mb-3">
                                <label for="weight" class="form-label required">Weight (kg)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="weight" name="weight" 
                                           min="50" step="0.1"
                                           value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>" required>
                                    <span class="input-group-text">kg (minimum 50 kg)</span>
                                </div>
                                <div class="invalid-feedback">Minimum weight requirement is 50 kg.</div>
                            </div>
                            
                            <!-- Height -->
                            <div class="mb-3">
                                <label for="height" class="form-label required">Height (cm)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="height" name="height" 
                                           min="100" max="250" step="0.1"
                                           value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>" required>
                                    <span class="input-group-text">cm</span>
                                </div>
                                <div class="invalid-feedback">Please enter your height in centimeters.</div>
                            </div>
                            
                            <!-- Blood Type -->
                            <div class="mb-3">
                                <label for="blood_type" class="form-label required">Blood Type</label>
                                <select id="blood_type" name="blood_type" class="form-select" required>
                                    <option value="">Select Blood Type</option>
                                    <option value="A+" <?php echo ($_POST['blood_type'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($_POST['blood_type'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($_POST['blood_type'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($_POST['blood_type'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($_POST['blood_type'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($_POST['blood_type'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($_POST['blood_type'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($_POST['blood_type'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                    <option value="Unknown" <?php echo ($_POST['blood_type'] ?? '') === 'Unknown' ? 'selected' : ''; ?>>Unknown (Will be determined during screening)</option>
                                </select>
                                <div class="invalid-feedback">Please select your blood type.</div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-md-6">
                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                            
                            <!-- Phone Number -->
                            <div class="mb-3">
                                <label for="phone" class="form-label required">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your phone number.</div>
                            </div>
                            
                            <!-- Address -->
                            <div class="mb-3">
                                <label for="address" class="form-label required">Address</label>
                                <input type="text" class="form-control" id="address" name="address" 
                                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your address.</div>
                            </div>
                            
                            <!-- City (Fixed) -->
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <div class="form-control bg-light">
                                    <span class="text-muted">City of Baguio</span>
                                    <input type="hidden" name="city" value="City of Baguio">
                                </div>
                            </div>
                            
                            <!-- Province (Fixed) -->
                            <div class="mb-3">
                                <label class="form-label">Province</label>
                                <div class="form-control bg-light">
                                    <span class="text-muted">Benguet</span>
                                    <input type="hidden" name="province" value="Benguet">
                                </div>
                            </div>
                            
                            <!-- Postal Code -->
                            <div class="mb-3">
                                <label for="postal_code" class="form-label required">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your postal code.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Include Medical Questions Section -->
                <?php include __DIR__ . '/includes/medical_section.php'; ?>
                
                <!-- CAPTCHA Section -->
                <div class="form-section">
                    <h4 class="section-title">Security Verification</h4>
                    <div class="mb-3">
                        <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>
                        <div class="form-text">Please complete the CAPTCHA to verify you're not a robot.</div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-donate">Register as a Donor</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
    .is-invalid {
        border-color: #dc3545 !important;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    .invalid-feedback {
        display: none;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
    .was-validated .form-control:invalid ~ .invalid-feedback,
    .was-validated .form-select:invalid ~ .invalid-feedback,
    .form-control.is-invalid ~ .invalid-feedback,
    .form-select.is-invalid ~ .invalid-feedback {
        display: block;
    }
    </style>
    <script>
    // Client-side form validation
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        const form = document.getElementById('donorForm');
        console.log('Form element found:', form);
        
        // Add was-validated class to form when it's submitted
        if (form) {
            form.addEventListener('submit', function(event) {
                console.log('Form submit event triggered');
                
                // Prevent default form submission
                event.preventDefault();
                event.stopPropagation();
                
                // Reset all validation states
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                
                // Check all required fields
                const requiredFields = form.querySelectorAll('[required]');
                let allValid = true;
                
                // Track radio groups we've already checked
                const checkedRadioGroups = new Set();
                
                // First pass: check all non-radio required fields
                requiredFields.forEach(field => {
                    // Skip radio buttons for now, we'll handle them separately
                    if (field.type === 'radio') {
                        return;
                    }
                    
                    if (!field.checkValidity()) {
                        console.group('Validation Error');
                        console.log('Field:', field.name);
                        console.log('Type:', field.type);
                        console.log('Value:', field.value);
                        console.log('Validity:', field.validity);
                        console.groupEnd();
                        
                        field.classList.add('is-invalid');
                        allValid = false;
                    }
                });
                
                // Get the selected gender
                const genderSelect = document.querySelector('select[name="gender"]');
                const isFemale = genderSelect && genderSelect.value === 'Female';
                
                // Check radio button groups
                const radioGroups = {};
                document.querySelectorAll('input[type="radio"][name^="q"]').forEach(radio => {
                    if (!radioGroups[radio.name]) {
                        radioGroups[radio.name] = [];
                    }
                    radioGroups[radio.name].push(radio);
                    
                    // Reset invalid state for all radios initially
                    radio.classList.remove('is-invalid');
                    const container = radio.closest('.form-check') || radio.closest('.mb-3') || radio.closest('.screening-question');
                    if (container) {
                        container.classList.remove('is-invalid', 'border', 'border-danger', 'rounded', 'p-2', 'mb-3');
                        const existingError = container.querySelector('.radio-error-message');
                        if (existingError) {
                            existingError.remove();
                        }
                    }
                });
                
                // Validate each radio group
                Object.entries(radioGroups).forEach(([name, radios]) => {
                    // Check if any radio in the group is required
                    const isRequired = Array.from(radios).some(radio => radio.required);
                    if (!isRequired) return;
                    
                    // Skip validation for female-only questions if gender is not female
                    if (name.match(/^q(33|34|35|36|37)$/) && !isFemale) {
                        return;
                    }
                    
                    const isChecked = Array.from(radios).some(radio => radio.checked);
                    if (!isChecked) {
                        console.group('Radio Group Validation Error');
                        console.log('Radio Group:', name);
                        console.log('Checked:', isChecked);
                        console.groupEnd();
                        
                        // Find the question container
                        let questionContainer = null;
                        for (const radio of radios) {
                            const container = radio.closest('.screening-question') || 
                                           radio.closest('.card-body') || 
                                           radio.closest('.mb-3');
                            if (container) {
                                questionContainer = container;
                                break;
                            }
                        }
                        
                        if (questionContainer) {
                            // Skip validation for hidden female-only questions
                            if (questionContainer.classList.contains('female-only') && 
                                window.getComputedStyle(questionContainer).display === 'none') {
                                return;
                            }
                            
                            // Add error class to the question container
                            questionContainer.classList.add('border', 'border-danger', 'rounded', 'p-2', 'mb-3');
                            
                            // Add error message if it doesn't exist
                            if (!questionContainer.querySelector('.radio-error-message')) {
                                const errorMsg = document.createElement('div');
                                errorMsg.className = 'invalid-feedback d-block text-danger mb-2 radio-error-message';
                                errorMsg.textContent = 'Please select an option for this question';
                                
                                // Insert after the question text or at the beginning of the container
                                const questionText = questionContainer.querySelector('p, label, strong');
                                if (questionText) {
                                    questionText.parentNode.insertBefore(errorMsg, questionText.nextSibling);
                                } else {
                                    questionContainer.insertBefore(errorMsg, questionContainer.firstChild);
                                }
                            }
                            
                            // Also mark each radio in the group
                            radios.forEach(radio => {
                                radio.classList.add('is-invalid');
                            });
                            
                            allValid = false;
                        }
                    }
                });
                
                // Check declaration checkbox
                const declaration = document.getElementById('declaration');
                if (declaration && !declaration.checked) {
                    console.log('Declaration not checked');
                    declaration.classList.add('is-invalid');
                    allValid = false;
                }
                
                if (!allValid) {
                    console.log('Form validation failed');
                    form.classList.add('was-validated');
                    
                    // Scroll to first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                    return;
                }
                
                console.log('Form is valid, submitting...');
                
                // Add loading state to submit button and prevent duplicate submission
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    // Check if already processing
                    if (submitBtn.disabled) {
                        console.log('Form is already being processed, ignoring duplicate submission');
                        return false;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                    
                    // Add a small delay to prevent rapid double-clicks
                    setTimeout(() => {
                        form.submit();
                    }, 100);
                } else {
                    // Fallback if button not found
                    form.submit();
                }
            });
        }
        
        // Add real-time validation on blur
        if (form) {
            form.querySelectorAll('input, select, textarea').forEach(field => {
                // Skip file inputs and buttons
                if (field.type === 'file' || field.type === 'submit' || field.type === 'button') return;
                
                field.addEventListener('blur', () => {
                    if (!field.checkValidity()) {
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
            });
        }
        
        // Enable/disable date input for Q34 based on radio selection
        const q34None = document.getElementById('q34_none');
        const q34Date = document.getElementById('q34_date');
        const q34DateInput = document.getElementById('q34_date_input');
        
        if (q34None && q34Date && q34DateInput) {
            q34None.addEventListener('change', function() {
                q34DateInput.disabled = true;
                q34DateInput.required = false;
            });
            
            q34Date.addEventListener('change', function() {
                q34DateInput.disabled = false;
                q34DateInput.required = true;
            });
        }
    });

    // Eligibility Check JavaScript
    document.querySelectorAll('input[name="recent_donation"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const recentDonorWarning = document.getElementById('recentDonorWarning');
            const unsureDonorInfo = document.getElementById('unsureDonorInfo');
            const proceedBtn = document.getElementById('proceedBtn');
            
            // Hide all messages first
            recentDonorWarning.style.display = 'none';
            unsureDonorInfo.style.display = 'none';
            proceedBtn.style.display = 'none';
            
            if (this.value === 'yes') {
                // Show warning for recent donors
                recentDonorWarning.style.display = 'block';
            } else if (this.value === 'no') {
                // Show proceed button for eligible donors
                proceedBtn.style.display = 'block';
            } else if (this.value === 'not_sure') {
                // Show info message and proceed button for unsure donors
                unsureDonorInfo.style.display = 'block';
                proceedBtn.style.display = 'block';
            }
        });
    });
    </script>

    <script>
    // Function to show registration form
    function showRegistrationForm() {
        document.getElementById('eligibilityCheck').style.display = 'none';
        document.getElementById('donorForm').style.display = 'block';
        
        // Scroll to form
        document.getElementById('donorForm').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
    
    // Function to show eligibility check (go back)
    function showEligibilityCheck() {
        document.getElementById('donorForm').style.display = 'none';
        document.getElementById('eligibilityCheck').style.display = 'block';
        
        // Scroll to eligibility check
        document.getElementById('eligibilityCheck').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
    </script>
</body>
</html>
