<?php
/**
 * API Endpoint for Medical Screening Details
 * Returns detailed medical screening information for a donor
 */

// Start session for authentication
session_start();

// Robust DB include: prefer production if present, else local
$__dbIncluded = false;
foreach ([__DIR__ . '/db_production.php', __DIR__ . '/db.php', __DIR__ . '/../db.php'] as $__candidate) {
    if (file_exists($__candidate)) { require_once $__candidate; $__dbIncluded = true; break; }
}

// Ensure tableExists helper is available for dynamic table resolution
if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            try { $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1"); return true; } catch (Throwable $e2) { return false; }
        }
    }
}
// Note: admin_auth.php and enhanced_donor_management.php may not be needed for this endpoint

// Check database connection
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Check if user is authenticated as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Check if this is a valid request
if (!isset($_GET['donor_id']) || !is_numeric($_GET['donor_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid donor ID']);
    exit;
}

$donorId = (int)$_GET['donor_id'];

try {
    // Resolve donors table dynamically (prefer donors_new when available)
    $donorsTable = tableExists($pdo, 'donors_new') ? 'donors_new' : 'donors';
    // Get donor basic information
    $donorStmt = $pdo->prepare("SELECT first_name, last_name, reference_code, gender, created_at, screening_data FROM {$donorsTable} WHERE id = ?");
    $donorStmt->execute([$donorId]);
    $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        http_response_code(404);
        echo json_encode(['error' => 'Donor not found']);
        exit;
    }
    
    // Get medical screening data from multiple possible sources
    $screeningData = null; $screeningDate = null; $completed = false;
    // 1) donor_medical_screening_simple (guarded by table existence to avoid 500s)
    if (function_exists('tableExists') ? tableExists($pdo, 'donor_medical_screening_simple') : true) {
        try {
            $stmt = $pdo->prepare("SELECT screening_data, all_questions_answered, created_at FROM donor_medical_screening_simple WHERE donor_id = ?");
            $stmt->execute([$donorId]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['screening_data'])) {
                    $data = json_decode($row['screening_data'], true);
                    if (is_array($data)) { $screeningData = $data; $screeningDate = $row['created_at'] ?? $donor['created_at'] ?? null; $completed = !empty($row['all_questions_answered']); }
                }
            }
        } catch (Throwable $e) {
            // Silently continue to next source if table/query fails
            error_log('get_medical_screening: simple source failed: ' . $e->getMessage());
        }
    }
    // 2) donor_medical_screening_fixed (fallback)
    if (!$screeningData && function_exists('tableExists') && tableExists($pdo, 'donor_medical_screening_fixed')) {
        $st2 = $pdo->prepare("SELECT screening_data, all_questions_answered, created_at FROM donor_medical_screening_fixed WHERE donor_id = ?");
        $st2->execute([$donorId]);
        if ($row2 = $st2->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row2['screening_data'])) {
                $data2 = json_decode($row2['screening_data'], true);
                if (is_array($data2)) { $screeningData = $data2; $screeningDate = $row2['created_at'] ?? $donor['created_at'] ?? null; $completed = !empty($row2['all_questions_answered']); }
            }
        }
    }
    // 3) donors.screening_data (legacy)
    if (!$screeningData && !empty($donor['screening_data'])) {
        $data3 = json_decode($donor['screening_data'], true);
        if (is_array($data3)) {
            $screeningData = $data3; $screeningDate = $donor['created_at'] ?? null;
            $required = (strtolower($donor['gender']) === 'female') ? 37 : 32; $ans = 0; foreach ($data3 as $k=>$v){ if($v!=='' && $v!==null){ $ans++; } } $completed = ($ans >= $required);
        }
    }
    if (!$screeningData) {
        http_response_code(404);
        echo json_encode(['error' => 'Medical screening not found']);
        exit;
    }
    
    // Load medical questions with robust path resolution
    $medicalQuestions = null;
    foreach ([__DIR__ . '/includes/medical_questions.php', __DIR__ . '/../includes/medical_questions.php'] as $__qFile) {
        if (file_exists($__qFile)) { $medicalQuestions = include $__qFile; break; }
    }
    $sections = $medicalQuestions['sections'] ?? [];
    
    // Build response
    $response = [
        'donor' => [
            'name' => $donor['first_name'] . ' ' . $donor['last_name'],
            'reference_code' => $donor['reference_code'],
            'gender' => $donor['gender']
        ],
        'screening' => [
            'completed' => (bool)$completed,
            'date' => $screeningDate,
            'data' => $screeningData
        ],
        'questions' => [],
        'summary' => [
            'yes_count' => 0,
            'no_count' => 0,
            'not_answered' => 0
        ]
    ];
    
    // Process questions and answers
    foreach ($sections as $sectionKey => $section) {
        // Skip female-only section for non-female donors
        if ($sectionKey === 'female_only' && strtolower($donor['gender']) !== 'female') {
            continue;
        }
        
        $sectionData = [
            'title' => $section['title'],
            'questions' => []
        ];
        
        foreach ($section['questions'] as $questionKey => $questionText) {
            // Default answer handling
            $answer = $screeningData[$questionKey] ?? 'not_answered';

            // Special handling for date-type female questions
            if ($questionKey === 'q34') {
                // Last childbirth: either 'none' or 'date' with q34_date value
                $q34Type = $screeningData['q34'] ?? null; // 'none' or 'date'
                $q34Date = $screeningData['q34_date'] ?? null;
                if ($q34Type === 'none') {
                    $answer = 'None';
                } elseif ($q34Type === 'date' && !empty($q34Date)) {
                    $answer = $q34Date; // YYYY-MM-DD stored; let UI show as-is
                } else {
                    $answer = 'not_answered';
                }
            } elseif ($questionKey === 'q37') {
                // Last menstrual period: stored as q37_date
                $q37Date = $screeningData['q37_date'] ?? null;
                if (!empty($q37Date)) {
                    $answer = $q37Date;
                } else {
                    $answer = 'not_answered';
                }
            }

            $sectionData['questions'][] = [
                'key' => $questionKey,
                'question' => $questionText,
                'answer' => $answer
            ];
            
            // Update summary
            if ($answer === 'yes') {
                $response['summary']['yes_count']++;
            } elseif ($answer === 'no') {
                $response['summary']['no_count']++;
            } else {
                $response['summary']['not_answered']++;
            }
        }
        
        $response['questions'][] = $sectionData;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_medical_screening.php: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
?>
