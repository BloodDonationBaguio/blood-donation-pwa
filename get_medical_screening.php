<?php
/**
 * API Endpoint for Medical Screening Details
 * Returns detailed medical screening information for a donor
 */

// Start session for authentication
session_start();

require_once 'db.php';
require_once __DIR__ . '/admin/includes/admin_auth.php';
require_once 'includes/enhanced_donor_management.php';

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
    // Get donor basic information
    $donorStmt = $pdo->prepare("SELECT first_name, last_name, reference_code, gender FROM donors_new WHERE id = ?");
    $donorStmt->execute([$donorId]);
    $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        http_response_code(404);
        echo json_encode(['error' => 'Donor not found']);
        exit;
    }
    
    // Get medical screening data
    $screeningStmt = $pdo->prepare("SELECT * FROM donor_medical_screening_simple WHERE donor_id = ?");
    $screeningStmt->execute([$donorId]);
    $screening = $screeningStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$screening) {
        http_response_code(404);
        echo json_encode(['error' => 'Medical screening not found']);
        exit;
    }
    
    // Parse screening data
    $screeningData = json_decode($screening['screening_data'], true);
    if (!$screeningData) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid screening data']);
        exit;
    }
    
    // Load medical questions
    $medicalQuestions = include __DIR__ . '/includes/medical_questions.php';
    $sections = $medicalQuestions['sections'] ?? [];
    
    // Build response
    $response = [
        'donor' => [
            'name' => $donor['first_name'] . ' ' . $donor['last_name'],
            'reference_code' => $donor['reference_code'],
            'gender' => $donor['gender']
        ],
        'screening' => [
            'completed' => (bool)$screening['all_questions_answered'],
            'date' => $screening['created_at'],
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
            $answer = $screeningData[$questionKey] ?? 'not_answered';
            
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
