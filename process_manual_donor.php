<?php
// Server-side processing for admin manual donor registration
// Reuses validation logic from donor-registration.php and adds created_by_admin flag

define('INCLUDES_PATH', true);
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/session_manager.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mail_helper.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function generateReferenceNumber() { return strtoupper('DNR-' . substr(md5(uniqid(mt_rand(), true)), 0, 6)); }
function isEligibleAge($birthDate) { $age = date_diff(date_create($birthDate), date_create('today'))->y; return ($age >= 18 && $age <= 65); }
function isEmailNullable($pdo, $table) { try { $stmt = $pdo->prepare("SELECT is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = 'email'"); $stmt->execute([$table]); $val = strtolower((string)$stmt->fetchColumn()); return $val === 'yes'; } catch (Throwable $e) { return true; } }

/**
 * Resolve a donors table that supports the manual registration schema.
 * Prefers donors_new but will fall back to donors if needed.
 */
function resolveDonorsTableForManual(PDO $pdo) {
    $candidates = ['donors_new', 'donors'];
    foreach ($candidates as $table) {
        try {
            // Ensure expected columns exist; query will fail if they do not
            $pdo->query("SELECT first_name, last_name, reference_code FROM {$table} LIMIT 1");
            return $table;
        } catch (Throwable $e) {
            // Try next table
        }
    }
    throw new Exception('No compatible donors table found for manual registration');
}

$errors = [];
$success = false;
$refNumber = '';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin.php?tab=manual-register'); exit; }

    // 1. Validate essential donor information
    $firstName  = trim($_POST['first_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $fullName   = trim($firstName . ' ' . $lastName);
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

    if (empty($dob)) { $errors[] = "Date of birth is required"; }
    elseif (!isEligibleAge($dob)) { $errors[] = "You must be 18-65 years old to donate"; }
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if ($weight < 50) $errors[] = "Minimum weight requirement is 50kg";
    if ($height < 100) $errors[] = "Please enter a valid height (minimum 100cm)";
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown', 'UNK'];
    if (empty($bloodType)) { $errors[] = "Blood type is required"; }
    elseif (!in_array($bloodType, $validBloodTypes)) { $errors[] = "Invalid blood type selected. Please select a valid blood type."; }
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($province)) $errors[] = "Province is required";
    if (empty($postalCode)) $errors[] = "Postal code is required";

    // Decide which donors table to use for this registration
    $donorsTable = resolveDonorsTableForManual($pdo);

    // Duplicate recent registration check (5 minutes window)
    if (empty($errors) && $email !== '') {
        try {
            $checkTable = $donorsTable;
            // PostgreSQL-style interval; on MySQL this block is best-effort and safely ignored on error
            $duplicateCheck = $pdo->prepare("SELECT id, created_at FROM {$checkTable} WHERE email = ? AND created_at > CURRENT_TIMESTAMP - INTERVAL '5 minutes' ORDER BY created_at DESC LIMIT 1");
            $duplicateCheck->execute([$email]);
            $recentSubmission = $duplicateCheck->fetch();
            if ($recentSubmission) { $errors[] = "You have already submitted a registration recently. Please wait a few minutes and try again."; }
        } catch (Exception $e) { /* non-blocking */ }
    }

    // 2. Collect medical screening questions (q1-q37)
    $medical = [];
    $answeredCount = 0;
    for ($i = 1; $i <= 37; $i++) { $qid = "q$i"; $answer = $_POST[$qid] ?? ''; $medical[$qid] = $answer; if (!empty($answer)) $answeredCount++; }
    if ($gender === "Female") {
        $medical['q34'] = $_POST['q34'] ?? '';
        if (!empty($_POST['q34_date'])) { $medical['q34_date'] = $_POST['q34_date']; }
        if (!empty($_POST['q37_date'])) { $medical['q37_date'] = $_POST['q37_date']; }
    }

    if (!empty($errors)) { header('Location: admin.php?tab=manual-register&error=' . urlencode(implode('; ', $errors))); exit; }

    // 3. Insert donor and screening
    $pdo->beginTransaction();
    $refNumber = generateReferenceNumber();

    // Ensure created_by_admin column exists
    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'mysql');
        if ($driver === 'pgsql') {
            $pdo->exec("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = '{$donorsTable}' AND column_name = 'created_by_admin') THEN ALTER TABLE {$donorsTable} ADD COLUMN created_by_admin BOOLEAN DEFAULT FALSE; END IF; END $$;");
        } else {
            // MySQL/MariaDB
            $pdo->exec("ALTER TABLE {$donorsTable} ADD COLUMN IF NOT EXISTS created_by_admin TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) { /* ignore */ }

    // Blood type normalization
    $isUnknownSelected = ($bloodType === 'Unknown' || $bloodType === 'UNK');
    $dbBloodType = $isUnknownSelected ? null : $bloodType;

    // Insert donor
    $dbEmail = ($email !== '') ? $email : null;
    if ($dbEmail === null && !isEmailNullable($pdo, $donorsTable)) { $dbEmail = 'no-email+' . strtolower($refNumber) . '@donor.invalid'; }
    $stmt = $pdo->prepare("INSERT INTO {$donorsTable} (first_name, last_name, email, phone, blood_type, date_of_birth, gender, address, city, province, weight, height, reference_code, status, created_by_admin, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$firstName, $lastName, $dbEmail, $phone, $dbBloodType, $dob, $gender, $address, $city, $province, $weight, $height, $refNumber, 1]);
    $donorId = (int)$pdo->lastInsertId();

    // Save medical screening
    $requiredQuestions = ($gender === 'Female') ? 37 : 32;
    $actualAnswered = 0; foreach ($medical as $ans) { if (!empty($ans)) $actualAnswered++; }
    $allAnswered = $actualAnswered >= $requiredQuestions ? 1 : 0;

    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'mysql');
        if ($driver === 'pgsql') {
            $pdo->exec("DO $$
DECLARE
    seq_name text := 'donor_medical_screening_simple_id_seq';
    max_id bigint;
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_class WHERE relkind = 'S' AND relname = seq_name
    ) THEN
        EXECUTE format('CREATE SEQUENCE IF NOT EXISTS %I', seq_name);
    END IF;

    SELECT COALESCE(MAX(id), 0) INTO max_id FROM donor_medical_screening_simple;
    PERFORM setval(seq_name::regclass, GREATEST(max_id, 1), true);

    EXECUTE format(
        'ALTER TABLE donor_medical_screening_simple ALTER COLUMN id SET DEFAULT nextval(%L::regclass)',
        seq_name
    );
END
$$;");
        }
    } catch (Throwable $e) {
    }

    $medicalStmt = $pdo->prepare("INSERT INTO donor_medical_screening_simple (donor_id, reference_code, screening_data, all_questions_answered) VALUES (?, ?, ?, ?)");
    $medicalStmt->execute([$donorId, $refNumber, json_encode($medical), $allAnswered]);

    $pdo->commit();

    // Log admin audit entry for this manual donor creation (best-effort)
    try {
        require_once __DIR__ . '/includes/admin_actions.php';
        if (function_exists('ensureAuditLogTableExists')) {
            ensureAuditLogTableExists($pdo);
        }
        if (function_exists('logAdminAction')) {
            logAdminAction(
                $pdo,
                'donor_created',
                $donorsTable,
                $donorId,
                "Manual donor registration: {$fullName} ({$refNumber})",
                $_SESSION['admin_username'] ?? null
            );
        }
    } catch (Throwable $e) {
        // Ignore audit failures to avoid blocking registration
    }

    // Optional email
    try {
        $subject = "Blood Donation Registration Reference";
        $message = "<h2>Thank you, {$fullName}, for registering as a blood donor.</h2><p>Your reference number is: <strong>{$refNumber}</strong></p>";
        if ($email !== '' && function_exists('send_confirmation_email')) { @send_confirmation_email($email, $subject, $message, $fullName); }
    } catch (Throwable $e) { /* ignore */ }

    header('Location: admin.php?tab=manual-register&success=' . urlencode('Donor added successfully. Reference: ' . $refNumber) . '&ref=' . urlencode($refNumber));
    exit;
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    header('Location: admin.php?tab=manual-register&error=' . urlencode($e->getMessage()));
    exit;
}