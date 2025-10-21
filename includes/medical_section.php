<?php
/**
 * Medical Screening Questions Section
 * This file contains the HTML and logic for the medical screening questions
 */

// Get medical questions from the configuration
$medicalQuestions = include __DIR__ . '/medical_questions.php';
$medicalQuestions = $medicalQuestions['sections'] ?? [];
?>

<!-- Medical Screening Section -->
<div class="form-section">
    <h2 class="section-title">Section 2: Medical Screening Questions</h2>
    <div class="alert alert-info mb-4">
        <strong>Important:</strong> Please answer all questions truthfully. Your responses will be kept confidential.
    </div>

    <?php foreach ($medicalQuestions as $sectionKey => $section): ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><?php echo htmlspecialchars($section['title']); ?></h5>
            </div>
            <div class="card-body">
                <?php foreach ($section['questions'] as $qid => $question): ?>
                    <div class="screening-question
                        <?php echo ($qid === 'q33' || $qid === 'q34' || $qid === 'q35' || $qid === 'q36' || $qid === 'q37') ? 'female-only' : ''; ?>">
                        
                        <?php if ($qid === 'q34'): ?>
                            <!-- Special handling for Q34 (last childbirth) -->
                            <div class="mb-3">
                                <p class="mb-2"><?php echo htmlspecialchars($question); ?></p>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q34" id="q34_none" value="none" 
                                        <?php echo (isset($_POST['q34']) && $_POST['q34'] === 'none') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q34_none">None</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q34" id="q34_date" value="date"
                                        <?php echo (isset($_POST['q34']) && $_POST['q34'] === 'date') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q34_date">Date:</label>
                                </div>
                                <input type="date" class="form-control d-inline-block ms-2" id="q34_date_input" name="q34_date" 
                                    style="width: auto; display: inline-block;"
                                    value="<?php echo htmlspecialchars($_POST['q34_date'] ?? ''); ?>"
                                    <?php echo (!isset($_POST['q34']) || $_POST['q34'] !== 'date') ? 'disabled' : ''; ?>>
                            </div>
                            <input type="hidden" name="q34" value="<?php echo htmlspecialchars($_POST['q34'] ?? 'none'); ?>">
                        
                        <?php elseif ($qid === 'q37'): ?>
                            <!-- Special handling for Q37 (last menstrual period) -->
                            <div class="mb-3">
                                <label for="q37_date" class="form-label"><?php echo htmlspecialchars($question); ?></label>
                                <input type="date" class="form-control" id="q37_date" name="q37_date" 
                                    value="<?php echo htmlspecialchars($_POST['q37_date'] ?? ''); ?>">
                            </div>
                        
                        <?php else: ?>
                            <!-- Standard yes/no questions -->
                            <div class="mb-3">
                                <p class="mb-2"><?php echo htmlspecialchars($question); ?></p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="<?php echo $qid; ?>" id="<?php echo $qid; ?>_yes" value="yes" required
                                        <?php echo (isset($_POST[$qid]) && $_POST[$qid] === 'yes') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="<?php echo $qid; ?>_yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="<?php echo $qid; ?>" id="<?php echo $qid; ?>_no" value="no" required
                                        <?php echo (isset($_POST[$qid]) && $_POST[$qid] === 'no') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="<?php echo $qid; ?>_no">No</label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Declaration -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="declaration" name="declaration" required
                    <?php echo (isset($_POST['declaration'])) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="declaration">
                    I declare that all the information provided is true and complete to the best of my knowledge. 
                    I understand that providing false information may have serious health consequences for 
                    recipients of my blood.
                </label>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide female-only questions based on gender selection
document.addEventListener('DOMContentLoaded', function() {
    const genderSelect = document.getElementById('gender');
    const femaleQuestions = document.querySelectorAll('.female-only');
    const q34DateInput = document.getElementById('q34_date_input');
    const q34DateRadio = document.getElementById('q34_date');
    const q34NoneRadio = document.getElementById('q34_none');

    // Toggle female questions based on gender
    function toggleFemaleQuestions() {
        const isFemale = genderSelect && genderSelect.value === 'Female';
        femaleQuestions.forEach(el => {
            el.style.display = isFemale ? 'block' : 'none';
        });
    }

    // Toggle date input for Q34
    function toggleQ34DateInput() {
        if (q34DateInput && q34DateRadio && q34NoneRadio) {
            q34DateInput.disabled = !q34DateRadio.checked;
            if (q34DateRadio.checked && !q34DateInput.value) {
                // Set default to 1 year ago if empty
                const oneYearAgo = new Date();
                oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
                q34DateInput.value = oneYearAgo.toISOString().split('T')[0];
            }
        }
    }

    // Initialize
    if (genderSelect) {
        genderSelect.addEventListener('change', toggleFemaleQuestions);
        toggleFemaleQuestions(); // Initial check
    }

    // Q34 date input handling
    if (q34DateRadio && q34NoneRadio) {
        q34DateRadio.addEventListener('change', toggleQ34DateInput);
        q34NoneRadio.addEventListener('change', toggleQ34DateInput);
        toggleQ34DateInput(); // Initial check
    }

    // Form validation
    const form = document.getElementById('donorForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>
