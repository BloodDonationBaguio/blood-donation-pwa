<?php
/**
 * Simple AJAX Handler for Donor Details
 */

require_once 'db.php';
require_once 'includes/enhanced_donor_management.php';

// Only handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_donor_details') {
    $donorId = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;
    
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
            echo "<tr><td><strong>Emergency Contact:</strong></td><td>" . htmlspecialchars($donor['emergency_contact'] ?? 'Not specified') . "</td></tr>";
            echo "<tr><td><strong>Emergency Phone:</strong></td><td>" . htmlspecialchars($donor['emergency_phone'] ?? 'Not specified') . "</td></tr>";
            echo "</table>";
            echo "</div>";
            echo "</div>";
            
            // Medical Information Section
            echo "<div class='row mt-3'>";
            echo "<div class='col-md-6'>";
            echo "<h4><i class='fas fa-heartbeat me-2'></i>Medical Information</h4>";
            echo "<table class='table table-sm'>";
            echo "<tr><td><strong>Medical Conditions:</strong></td><td>" . (!empty($donor['medical_conditions']) ? htmlspecialchars($donor['medical_conditions']) : 'None reported') . "</td></tr>";
            echo "<tr><td><strong>Current Medications:</strong></td><td>" . (!empty($donor['medications']) ? htmlspecialchars($donor['medications']) : 'None reported') . "</td></tr>";
            echo "</table>";
            echo "</div>";

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
            
            // Check for medical screening data including weight/height
            $medicalScreeningStmt = $pdo->prepare("SELECT * FROM donor_medical_screening_simple WHERE donor_id = ?");
            $medicalScreeningStmt->execute([$donorId]);
            $medicalScreeningSimple = $medicalScreeningStmt->fetch();
            
            // Also check for detailed medical screening (if exists)
            $medicalScreeningStmt = $pdo->prepare("SELECT * FROM donor_medical_screening_fixed WHERE donor_id = ?");
            $medicalScreeningStmt->execute([$donorId]);
            $medicalScreening = $medicalScreeningStmt->fetch();
            
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
            if ($medicalScreeningSimple) {
                $screeningData = json_decode($medicalScreeningSimple['screening_data'], true);
                $allQuestionsAnswered = $medicalScreeningSimple['all_questions_answered'];
                $screeningStatus = $allQuestionsAnswered ? 'Completed' : 'Partially Completed';
                
                echo "<div class='alert alert-" . ($allQuestionsAnswered ? 'success' : 'warning') . "'>";
                echo "<i class='fas fa-info-circle me-2'></i>";
                echo "<strong>Medical Screening Status:</strong> ";
                echo "<span class='badge bg-" . ($allQuestionsAnswered ? 'success' : 'warning') . "'>";
                echo $screeningStatus;
                echo "</span>";
                echo "</div>";
                
                if ($allQuestionsAnswered) {
                    echo "<div class='alert alert-success'>";
                    echo "<i class='fas fa-check-circle me-2'></i>";
                    echo "<strong>Screening Complete:</strong> All medical questions have been answered.";
                    echo "<div class='mt-2'>";
                    echo "<button class='btn btn-sm btn-primary' onclick='viewMedicalScreening(" . $donorId . ")'>";
                    echo "<i class='fas fa-eye me-1'></i>View Screening Details";
                    echo "</button>";
                    echo "</div>";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-warning'>";
                    echo "<i class='fas fa-exclamation-triangle me-2'></i>";
                    echo "<strong>Screening Incomplete:</strong> Medical questionnaire is partially completed.";
                    echo "</div>";
                }
                
                // Show screening date
                if (!empty($medicalScreeningSimple['created_at'])) {
                    echo "<p><small class='text-muted'><i class='fas fa-calendar me-1'></i>Screening started on: " . date('M d, Y H:i', strtotime($medicalScreeningSimple['created_at'])) . "</small></p>";
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
                             $answerClass = '';
                             $answerIcon = '';
                             
                             if ($answer === 'yes') {
                                 $answerClass = 'text-danger';
                                 $answerIcon = '<i class="fas fa-times-circle text-danger me-1"></i>';
                             } elseif ($answer === 'no') {
                                 $answerClass = 'text-success';
                                 $answerIcon = '<i class="fas fa-check-circle text-success me-1"></i>';
                             } else {
                                 $answerClass = 'text-muted';
                                 $answerIcon = '<i class="fas fa-question-circle text-muted me-1"></i>';
                             }
                             
                             echo "<div class='mb-3 p-3 border rounded " . ($answer === 'yes' ? 'border-danger bg-light' : ($answer === 'no' ? 'border-success bg-light' : 'border-secondary')) . "'>";
                             echo "<div class='fw-bold mb-2'>{$questionText}</div>";
                             echo "<div class='{$answerClass}'>{$answerIcon}<strong>Answer:</strong> " . ucfirst($answer) . "</div>";
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