<?php
/**
 * Simple AJAX Handler for Donor Details
 */

require_once 'db.php';
require_once 'includes/enhanced_donor_management.php';

// Only handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_donor_details') {
    $donorId = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;
    // Debugging: request and donor id
    error_log("=== DONOR DETAILS REQUEST ===");
    error_log("Donor ID: " . ($_GET['donor_id'] ?? ''));
    
    if ($donorId > 0) {
        $donor = getDonorDetails($pdo, $donorId);
        
        if ($donor) {
            echo "<div class='row'>";
            echo "<div class='col-md-6'>";
            echo "<h4><i class='fas fa-user me-2'></i>Personal Information</h4>";
            echo "<table class='table table-sm'>";
            echo "<tr><td><strong>Name:</strong></td><td>" . htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) . "</td></tr>";
            echo "<tr><td><strong>Email:</strong></td><td>" . htmlspecialchars($donor['email']) . "</td></tr>";
            echo "<tr><td><strong>Phone:</strong></td><td>" . htmlspecialchars($donor['phone'] ?? 'N/A') . "</td></tr>";
            echo "<tr><td><strong>Blood Type:</strong></td><td><span class='badge bg-danger'>" . htmlspecialchars($donor['blood_type']) . "</span></td></tr>";
            echo "<tr><td><strong>Gender:</strong></td><td>" . (!empty($donor['gender']) ? htmlspecialchars($donor['gender']) : 'Not specified') . "</td></tr>";
            echo "<tr><td><strong>Date of Birth:</strong></td><td>" . (!empty($donor['date_of_birth']) ? date('M d, Y', strtotime($donor['date_of_birth'])) : 'Not specified') . "</td></tr>";
            echo "<tr><td><strong>Status:</strong></td><td><span class='badge bg-" . getDonorStatusColor($donor['status']) . "'>" . getDonorDisplayStatus($donor['status']) . "</span></td></tr>";
            echo "<tr><td><strong>Reference Code:</strong></td><td><code>" . htmlspecialchars($donor['reference_code'] ?? 'N/A') . "</code></td></tr>";
            echo "<tr><td><strong>Registration Date:</strong></td><td>" . date('M d, Y H:i', strtotime($donor['created_at'])) . "</td></tr>";
            echo "<tr><td><strong>Last Donation:</strong></td><td>" . (!empty($donor['last_donation_date']) ? date('M d, Y', strtotime($donor['last_donation_date'])) : 'Never donated') . "</td></tr>";
            echo "</table>";
            echo "</div>";
            
            echo "<div class='col-md-6'>";
            echo "<h4><i class='fas fa-map-marker-alt me-2'></i>Location & Contact Information</h4>";
            echo "<table class='table table-sm'>";
            echo "<tr><td><strong>Address:</strong></td><td>" . htmlspecialchars($donor['address'] ?? 'Not specified') . "</td></tr>";
            echo "<tr><td><strong>City:</strong></td><td>" . htmlspecialchars($donor['city'] ?? 'Not specified') . "</td></tr>";
            echo "<tr><td><strong>Province:</strong></td><td>" . htmlspecialchars($donor['province'] ?? 'Not specified') . "</td></tr>";
            // Only show emergency rows when values exist
            $emContact = trim($donor['emergency_contact'] ?? '');
            if ($emContact !== '') {
                echo "<tr><td><strong>Emergency Contact:</strong></td><td>" . htmlspecialchars($emContact) . "</td></tr>";
            }
            $emPhone = trim($donor['emergency_phone'] ?? '');
            if ($emPhone !== '') {
                echo "<tr><td><strong>Emergency Phone:</strong></td><td>" . htmlspecialchars($emPhone) . "</td></tr>";
            }
            echo "</table>";
            echo "</div>";
            echo "</div>";

            // Medical Information section removed per request
            echo "<div class='row mt-3'>";

            // Admin Notes section
            $notes = getDonorNotes($pdo, $donorId);
            echo "<div class='mt-4'>";
            echo "<h4><i class='fas fa-sticky-note me-2'></i>Admin Remarks</h4>";
            if (!empty($notes)) {
                echo "<div class='list-group'>";
                foreach ($notes as $n) {
                    echo "<div class='list-group-item'>";
                    echo "<div class='d-flex w-100 justify-content-between'>";
                    echo "<h6 class='mb-1'>" . htmlspecialchars($n['created_by'] ?: 'Admin') . "</h6>";
                    echo "<small class='text-muted'>" . date('M d, Y H:i', strtotime($n['created_at'])) . "</small>";
                    echo "</div>";
                    echo "<p class='mb-1'>" . nl2br(htmlspecialchars($n['note'])) . "</p>";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<div class='alert alert-light border'>No remarks yet.</div>";
            }
            echo "</div>";
            
            // Fetch medical screening (simple) using PostgreSQL syntax when applicable
            $medicalScreeningSimple = null;
            try {
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'pgsql') {
                    $tableCheck = $pdo->query("SELECT EXISTS ( SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'donor_medical_screening_simple' )")->fetchColumn();
                    error_log("Table exists: " . ($tableCheck ? 'YES' : 'NO'));
                    if ($tableCheck) {
                        $screeningQuery = "SELECT screening_data, all_questions_answered, created_at FROM donor_medical_screening_simple WHERE donor_id = $1";
                        $screeningStmt = $pdo->prepare($screeningQuery);
                        $screeningStmt->execute([$donorId]);
                        error_log("Screening query executed");
                        $medicalScreeningSimple = $screeningStmt->fetch(PDO::FETCH_ASSOC);
                        error_log("Screening data found: " . ($medicalScreeningSimple ? 'YES' : 'NO'));
                        if ($medicalScreeningSimple && !empty($medicalScreeningSimple['screening_data'])) {
                            $decoded = json_decode($medicalScreeningSimple['screening_data'], true);
                            error_log("Decoded answers: " . print_r($decoded, true));
                        }
                    }
                } else {
                    // MySQL fallback for local development
                    $tableCheckMy = false;
                    try {
                        $tableCheckMy = $pdo->query("SHOW TABLES LIKE 'donor_medical_screening_simple'")->rowCount() > 0;
                    } catch (Exception $inner) {
                        error_log("MySQL table check error: " . $inner->getMessage());
                    }
                    error_log("Table exists (MySQL): " . ($tableCheckMy ? 'YES' : 'NO'));
                    if ($tableCheckMy) {
                        $screeningStmt = $pdo->prepare("SELECT screening_data, all_questions_answered, created_at FROM donor_medical_screening_simple WHERE donor_id = ?");
                        $screeningStmt->execute([$donorId]);
                        error_log("Screening query executed (MySQL)");
                        $medicalScreeningSimple = $screeningStmt->fetch(PDO::FETCH_ASSOC);
                        error_log("Screening data found: " . ($medicalScreeningSimple ? 'YES' : 'NO'));
                        if ($medicalScreeningSimple && !empty($medicalScreeningSimple['screening_data'])) {
                            $decoded = json_decode($medicalScreeningSimple['screening_data'], true);
                            error_log("Decoded answers: " . print_r($decoded, true));
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Screening query error: " . $e->getMessage());
                $medicalScreeningSimple = null;
            }

            // Also check for detailed medical screening (if exists)
            $medicalScreening = null;
            try {
                if (tableExists($pdo, 'donor_medical_screening_fixed')) {
                    $medicalScreeningStmt = $pdo->prepare("SELECT * FROM donor_medical_screening_fixed WHERE donor_id = ?");
                    $medicalScreeningStmt->execute([$donorId]);
                    $medicalScreening = $medicalScreeningStmt->fetch();
                }
            } catch (Exception $e) {
                error_log('screening_fixed query error: ' . $e->getMessage());
            }
            
            echo "<div class='col-md-6'>";
            echo "<h4><i class='fas fa-ruler me-2'></i>Physical Measurements</h4>";
            echo "<table class='table table-sm'>";
            
            // Get weight and height from donors_new table
            $weight = $donor['weight'] ?? null;
            $height = $donor['height'] ?? null;
            
            echo "<tr><td><strong>Weight:</strong></td><td>" . ($weight ? $weight . ' kg' : 'Not recorded') . "</td></tr>";
            echo "<tr><td><strong>Height:</strong></td><td>" . ($height ? $height . ' cm' : 'Not recorded') . "</td></tr>";
            
            // Calculate BMI if both weight and height are available
            if ($weight && $height) {
                $heightInMeters = $height / 100;
                $bmi = $weight / ($heightInMeters * $heightInMeters);
                $bmiCategory = '';
                if ($bmi < 18.5) $bmiCategory = 'Underweight';
                elseif ($bmi < 25) $bmiCategory = 'Normal';
                elseif ($bmi < 30) $bmiCategory = 'Overweight';
                else $bmiCategory = 'Obese';
                
                echo "<tr><td><strong>BMI:</strong></td><td>" . number_format($bmi, 1) . " ({$bmiCategory})</td></tr>";
            }
            
            echo "</table>";
            echo "</div>";
            echo "</div>";
            
            // Medical Screening Status Section
            echo "<div class='mt-4'>";
            echo "<h4><i class='fas fa-stethoscope me-2'></i>Medical Screening Information</h4>";
            
            // Check simple screening first
            if (!empty($medicalScreeningSimple) && !empty($medicalScreeningSimple['screening_data'])) {
                $answers = json_decode($medicalScreeningSimple['screening_data'], true);
                $allQuestionsAnswered = !empty($medicalScreeningSimple['all_questions_answered']);
                
                // Status badge
                echo "<div class='screening-status'>";
                echo "<strong>Status:</strong> ";
                if ($allQuestionsAnswered) {
                    echo "<span class='badge bg-success'>Completed</span>";
                } else {
                    echo "<span class='badge bg-warning'>Partially Completed</span>";
                }
                echo "</div>";
                
                // Screening date
                if (!empty($medicalScreeningSimple['created_at'])) {
                    echo "<div class='screening-date'>";
                    echo "<strong>Screening Date:</strong> " . date('M d, Y', strtotime($medicalScreeningSimple['created_at']));
                    echo "</div>";
                }

                // Questions block
                echo "<div class='screening-answers mt-3'>";
                echo "<h5>Screening Questions & Answers:</h5>";
                
                // Define questions (expects these keys in screening_data)
                $questions = [
                    'chronic_conditions' => 'Do you have any chronic medical conditions?',
                    'medications' => 'Are you currently taking any medications?',
                    'recent_illness' => 'Have you been ill in the past 2 weeks?',
                    'recent_travel' => 'Have you traveled internationally in the past 6 months?',
                    'tattoos_piercings' => 'Have you gotten any tattoos or piercings in the past 12 months?',
                    'pregnancy' => 'Are you currently pregnant or breastfeeding?',
                    'blood_disorders' => 'Do you have any blood disorders?',
                    'infectious_diseases' => 'Have you been diagnosed with hepatitis, HIV, or other infectious diseases?',
                    'recent_vaccines' => 'Have you received any vaccines in the past 4 weeks?',
                    'weight_requirement' => 'Do you weigh at least 110 pounds (50 kg)?'
                ];

                foreach ($questions as $key => $question) {
                    if (isset($answers[$key])) {
                        $val = $answers[$key];
                        echo "<div class='question-item'>";
                        echo "<strong>" . htmlspecialchars($question) . "</strong>";
                        echo "<p class='answer'>" . htmlspecialchars($val) . "</p>";
                        if (isset($answers[$key . '_details']) && !empty($answers[$key . '_details'])) {
                            echo "<p class='details'><em>Details: " . htmlspecialchars($answers[$key . '_details']) . "</em></p>";
                        }
                        echo "</div>";
                    }
                }
                echo "</div>"; // .screening-answers

                // Inline CSS simplified (no icons or colored borders)
                echo "<style>.question-item{padding:8px;margin:6px 0}.question-item .answer{margin:4px 0}.question-item .details{font-size:.9em;color:#666}</style>";
            } elseif (!empty($donor['screening_data'])) {
                // Fallback: use joined donor.screening_data when standalone row not found
                $screeningData = json_decode($donor['screening_data'], true);
                if (!is_array($screeningData)) { $screeningData = []; }
                $allQuestionsAnswered = (int)($donor['all_questions_answered'] ?? 0) === 1;

                // Status line simplified, no summary
                echo "<div class='screening-status'><strong>Status:</strong> " . ($allQuestionsAnswered ? '<span class=\'badge bg-success\'>Completed</span>' : '<span class=\'badge bg-warning\'>Partially Completed</span>') . "</div>";

                // Build summary and inline details using includes/medical_questions.php
                $medicalQuestions = include __DIR__ . '/includes/medical_questions.php';
                if (!is_array($medicalQuestions) || empty($medicalQuestions['sections'])) {
                    $medicalQuestions = include __DIR__ . '/includes/medical_questions_new.php';
                }
                $sections = is_array($medicalQuestions) ? ($medicalQuestions['sections'] ?? []) : [];

                $yesAnswers = 0;
                $noAnswers = 0;
                $notAnswered = 0;
                $donorGender = $donor['gender'] ?? '';

                foreach ($sections as $sectionKey => $section) {
                    if ($sectionKey === 'female_only' && strtolower($donorGender) !== 'female') {
                        continue;
                    }
                    foreach ($section['questions'] as $questionKey => $questionText) {
                        $answer = $screeningData[$questionKey] ?? 'not_answered';
                        if ($questionKey === 'q34') {
                            $q34Type = $screeningData['q34'] ?? null;
                            $q34Date = $screeningData['q34_date'] ?? null;
                            if ($q34Type === 'none') {
                                $answer = 'None';
                            } elseif ($q34Type === 'date' && !empty($q34Date)) {
                                $answer = $q34Date;
                            } else {
                                $answer = 'not_answered';
                            }
                        } elseif ($questionKey === 'q37') {
                            $q37Date = $screeningData['q37_date'] ?? null;
                            $answer = !empty($q37Date) ? $q37Date : 'not_answered';
                        }

                        if ($answer === 'yes') $yesAnswers++;
                        elseif ($answer === 'no') $noAnswers++;
                        else $notAnswered++;
                    }
                }

                // Screening Summary removed per request

                // Inline detailed Q&A accordion
                echo "<div class='mt-4'>";
                echo "<h5><i class='fas fa-clipboard-list me-2'></i>Medical Screening Questions & Answers</h5>";
                echo "<div class='alert alert-info mb-3'>";
                echo "<i class='fas fa-info-circle me-2'></i>";
                echo "<strong>Note:</strong> Click on each section to view the detailed questions and answers.";
                echo "</div>";

                if (!empty($sections)) {
                    echo "<div class='accordion' id='medicalScreeningAccordion'>";
                    $questionCounter = 0;

                    foreach ($sections as $sectionKey => $section) {
                        $sectionTitle = $section['title'];
                        $questions = $section['questions'];
                        $sectionId = 'section-' . str_replace(' ', '-', strtolower($sectionTitle));

                        if ($sectionKey === 'female_only' && strtolower($donorGender) !== 'female') {
                            continue;
                        }

                        echo "<div class='accordion-item'>";
                        echo "<h2 class='accordion-header' id='heading-{$sectionId}'>";
                        echo "<button class='accordion-button " . ($questionCounter === 0 ? '' : 'collapsed') . "' type='button' data-bs-toggle='collapse' data-bs-target='#collapse-{$sectionId}' aria-expanded='" . ($questionCounter === 0 ? 'true' : 'false') . "' aria-controls='collapse-{$sectionId}'>";
                        echo "<i class='fas fa-heartbeat me-2'></i>{$sectionTitle}";
                        echo "</button>";
                        echo "</h2>";

                        echo "<div id='collapse-{$sectionId}' class='accordion-collapse collapse " . ($questionCounter === 0 ? 'show' : '') . "' aria-labelledby='heading-{$sectionId}' data-bs-parent='#medicalScreeningAccordion'>";
                        echo "<div class='accordion-body'>";

                        foreach ($questions as $questionKey => $questionText) {
                            $answer = $screeningData[$questionKey] ?? 'not_answered';
                            if ($questionKey === 'q34') {
                                $q34Type = $screeningData['q34'] ?? null;
                                $q34Date = $screeningData['q34_date'] ?? null;
                                if ($q34Type === 'none') {
                                    $answer = 'None';
                                } elseif ($q34Type === 'date' && !empty($q34Date)) {
                                    $answer = $q34Date;
                                } else {
                                    $answer = 'not_answered';
                                }
                            } elseif ($questionKey === 'q37') {
                                $q37Date = $screeningData['q37_date'] ?? null;
                                $answer = !empty($q37Date) ? $q37Date : 'not_answered';
                            }

                            echo "<div class='mb-3'>";
                            echo "<div class='fw-bold mb-2'>" . htmlspecialchars($questionText) . "</div>";
                            echo "<div><strong>Answer:</strong> " . (in_array($answer, ['yes','no','not_answered']) ? ucfirst($answer) : htmlspecialchars($answer)) . "</div>";
                            echo "</div>";
                        }

                        echo "</div>";
                        echo "</div>";
                        echo "</div>";

                        $questionCounter++;
                    }

                    echo "</div>";
                } else {
                    echo "<div class='alert alert-info'>";
                    echo "<i class='fas fa-info-circle me-2'></i>";
                    echo "Medical screening questions not available.";
                    echo "</div>";
                }
                echo "</div>";

                // Show screening date if available from join
                if (!empty($donor['screening_date'])) {
                    echo "<p><small class='text-muted'><i class='fas fa-calendar me-1'></i>Screening started on: " . date('M d, Y H:i', strtotime($donor['screening_date'])) . "</small></p>";
                }
            } elseif ($medicalScreening) {
                // Calculate summary statistics (excluding female-specific questions for male donors)
                $yesAnswers = 0;
                $noAnswers = 0;
                $notAnswered = 0;
                
                $donorGender = $donor['gender'] ?? '';
                
                foreach ($medicalScreening as $key => $value) {
                    if (strpos($key, 'q') === 0) {
                        // Skip female-specific questions for male donors
                        if (strtolower($donorGender) !== 'female' && in_array($key, ['q33', 'q34', 'q35', 'q36', 'q37'])) {
                            continue;
                        }
                        
                        if ($value === 'yes') $yesAnswers++;
                        elseif ($value === 'no') $noAnswers++;
                        else $notAnswered++;
                    }
                }
                
                echo "<div class='alert alert-" . ($yesAnswers > 0 ? 'warning' : 'success') . "'>";
                echo "<i class='fas fa-info-circle me-2'></i>";
                echo "<strong>Medical Screening Status:</strong> ";
                echo "<span class='badge bg-" . ($yesAnswers > 0 ? 'warning' : 'success') . "'>";
                echo ($yesAnswers > 0 ? 'Review Required' : 'Passed');
                echo "</span>";
                echo "<span class='ms-3'>";
                echo "<small>Safe: {$noAnswers} | Risk: {$yesAnswers} | Not Answered: {$notAnswered}</small>";
                echo "</span>";
                echo "</div>";
                
                if ($yesAnswers > 0) {
                    echo "<div class='alert alert-warning'>";
                    echo "<i class='fas fa-exclamation-triangle me-2'></i>";
                    echo "<strong>Medical Review Required:</strong> This donor has {$yesAnswers} positive responses that require medical review before approval.";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-success'>";
                    echo "<i class='fas fa-check-circle me-2'></i>";
                    echo "<strong>Medical Screening Passed:</strong> All responses are negative or not applicable. Donor is medically eligible.";
                    echo "</div>";
                }
                
                // Show screening date
                if (!empty($medicalScreening['screening_date'])) {
                    echo "<p><small class='text-muted'><i class='fas fa-calendar me-1'></i>Screening completed on: " . date('M d, Y H:i', strtotime($medicalScreening['screening_date'])) . "</small></p>";
                }
                
                // Display detailed medical screening questions and answers
                echo "<div class='mt-4'>";
                echo "<h5><i class='fas fa-clipboard-list me-2'></i>Medical Screening Questions & Answers</h5>";
                echo "<div class='alert alert-info mb-3'>";
                echo "<i class='fas fa-info-circle me-2'></i>";
                echo "<strong>Note:</strong> Click on each section to view the detailed questions and answers.";
                echo "</div>";
                
                // Include medical questions
                $medicalQuestions = include __DIR__ . '/includes/medical_questions.php';
                $sections = $medicalQuestions['sections'] ?? [];
                
                if (!empty($sections)) {
                    echo "<div class='accordion' id='medicalScreeningAccordion'>";
                    $questionCounter = 0;
                    
                                         foreach ($sections as $sectionKey => $section) {
                         $sectionTitle = $section['title'];
                         $questions = $section['questions'];
                         $sectionId = 'section-' . str_replace(' ', '-', strtolower($sectionTitle));
                         
                         // Skip female-only section for male donors
                         if ($sectionKey === 'female_only') {
                             $donorGender = $donor['gender'] ?? '';
                             if (strtolower($donorGender) !== 'female') {
                                 continue; // Skip this section for non-female donors
                             }
                         }
                         
                         echo "<div class='accordion-item'>";
                         echo "<h2 class='accordion-header' id='heading-{$sectionId}'>";
                         echo "<button class='accordion-button " . ($questionCounter === 0 ? '' : 'collapsed') . "' type='button' data-bs-toggle='collapse' data-bs-target='#collapse-{$sectionId}' aria-expanded='" . ($questionCounter === 0 ? 'true' : 'false') . "' aria-controls='collapse-{$sectionId}'>";
                         echo "<i class='fas fa-heartbeat me-2'></i>{$sectionTitle}";
                         echo "</button>";
                         echo "</h2>";
                         
                         echo "<div id='collapse-{$sectionId}' class='accordion-collapse collapse " . ($questionCounter === 0 ? 'show' : '') . "' aria-labelledby='heading-{$sectionId}' data-bs-parent='#medicalScreeningAccordion'>";
                         echo "<div class='accordion-body'>";
                         
                         foreach ($questions as $questionKey => $questionText) {
                             $answer = $medicalScreening[$questionKey] ?? 'Not answered';
                             echo "<div class='mb-3'>";
                             echo "<div class='fw-bold mb-2'>" . htmlspecialchars($questionText) . "</div>";
                             echo "<div><strong>Answer:</strong> " . htmlspecialchars(ucfirst($answer)) . "</div>";
                             echo "</div>";
                         }
                         
                         echo "</div>";
                         echo "</div>";
                         echo "</div>";
                         
                         $questionCounter++;
                     }
                    
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-info'>";
                    echo "<i class='fas fa-info-circle me-2'></i>";
                    echo "Medical screening questions not available.";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<div class='alert alert-warning'>";
                echo "<i class='fas fa-exclamation-triangle me-2'></i>";
                echo "<strong>Medical Screening Status:</strong> ";
                echo "<span class='badge bg-secondary'>Not Completed</span>";
                echo "</div>";
                
                echo "<div class='alert alert-warning'>";
                echo "<i class='fas fa-exclamation-triangle me-2'></i>";
                echo "<strong>Medical screening questionnaire not completed.</strong><br>";
                echo "This donor has registered but has not completed the medical screening questionnaire yet. ";
                echo "The donor needs to complete the medical screening before they can be approved for donation.";
                echo "</div>";
                
                echo "<div class='mt-3'>";
                echo "<h6><i class='fas fa-clipboard-list me-2'></i>Next Steps:</h6>";
                echo "<ul>";
                echo "<li>Contact the donor to complete the medical screening questionnaire</li>";
                echo "<li>Ensure all required health questions are answered</li>";
                echo "<li>Review responses before approval</li>";
                echo "<li>Update donor status once screening is complete</li>";
                echo "</ul>";
                echo "</div>";
            }
            echo "</div>";
            
        } else {
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Donor not found.</strong></div>';
        }
    } else {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Invalid donor ID.</strong></div>';
    }
    exit;
}

// If not an AJAX request, show error
echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Invalid request.</strong></div>';
exit;
?>