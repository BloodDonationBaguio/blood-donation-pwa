<?php
// Add Donor Tab Content
$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $required = ['full_name', 'blood_type', 'date_of_birth', 'gender', 'weight'];
        $data = [];
        
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
            $data[$field] = trim($_POST[$field]);
        }
        
        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }
        
        // Validate date of birth (must be at least 16 years old)
        $dob = new DateTime($data['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
        
        if ($age < 16) {
            throw new Exception("Donor must be at least 16 years old");
        }
        
        // Validate weight (at least 50 kg)
        if ($data['weight'] < 50) {
            throw new Exception("Donor must weigh at least 50 kg");
        }
        
        // Donation interval/email reuse
        if ($email !== '') {
            $checkTable = (function_exists('tableExists') && tableExists($pdo, 'donors_new')) ? 'donors_new' : 'donors';
            $stmt = $pdo->prepare("SELECT id, created_at FROM {$checkTable} WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$email]);
            $recent = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($recent && isset($_POST['recent_donation']) && $_POST['recent_donation'] === 'yes') {
                throw new Exception("Donor is not eligible yet: donated within last 90 days");
            }
            if ($recent && !empty($recent['created_at'])) {
                $last = new DateTime($recent['created_at']);
                $days = (new DateTime())->diff($last)->days;
                if ($days < 90) {
                    throw new Exception("Donor previously registered less than 90 days ago");
                }
            }
        }
        
        // Generate a reference code
        $referenceCode = 'DON' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $targetTable = (function_exists('tableExists') && tableExists($pdo, 'donors_new')) ? 'donors_new' : 'donors';
        $stmt = $pdo->prepare("
            INSERT INTO {$targetTable} (
                full_name, email, phone, blood_type, date_of_birth,
                gender, weight, last_donation_date, address, city,
                state, postal_code, country, reference_code, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            $data['full_name'],
            $email ?: null,
            $_POST['phone'] ?? '',
            $data['blood_type'],
            $data['date_of_birth'],
            $data['gender'],
            $data['weight'],
            $_POST['last_donation_date'] ?? null,
            $_POST['address'] ?? '',
            $_POST['city'] ?? '',
            $_POST['state'] ?? '',
            $_POST['postal_code'] ?? '',
            $_POST['country'] ?? 'Philippines',
            $referenceCode
        ]);

        // Save medical screening (q1–q37)
        try {
            $donorId = (int)$pdo->lastInsertId();
            $medical = [];
            for ($qi=1; $qi<=37; $qi++) { $medical['q'.$qi] = $_POST['q'.$qi] ?? ''; }
            if (strtolower($data['gender']) === 'female') {
                $medical['q34'] = $_POST['q34'] ?? '';
                if (!empty($_POST['q34_date'])) $medical['q34_date'] = $_POST['q34_date'];
                if (!empty($_POST['q37_date'])) $medical['q37_date'] = $_POST['q37_date'];
            }
            $requiredQuestions = (strtolower($data['gender']) === 'female') ? 37 : 32;
            $answered = 0; foreach ($medical as $ans) { if (!empty($ans)) $answered++; }
            $allAnswered = $answered >= $requiredQuestions ? 1 : 0;
            $ms = $pdo->prepare("INSERT INTO donor_medical_screening_simple (donor_id, reference_code, screening_data, all_questions_answered) VALUES (?, ?, ?, ?)");
            $ms->execute([$donorId, $referenceCode, json_encode($medical), $allAnswered]);
        } catch (Throwable $e) { /* ignore */ }
        
        $donorId = $pdo->lastInsertId();
        
        // Send welcome email (in a real app, this would be queued)
        // sendWelcomeEmail($data['email'], $data['full_name'], $referenceCode);
        
        $success = "Donor added successfully! Reference Code: " . $referenceCode;
        
        // Clear form
        $_POST = [];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Add New Donor</h2>
    <a href="?tab=donor-list" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= $error ?>
    </div>
<?php endif; ?>

<div class="card mb-3" id="eligibilityCheckAdmin">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">Quick Eligibility Check</h5>
    </div>
    <div class="card-body">
        <p class="mb-3">Before proceeding with registration, please answer:</p>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="recent_donation_admin" id="donated_recently_yes_admin" value="yes">
            <label class="form-check-label fw-bold text-danger" for="donated_recently_yes_admin">Yes, donated in the last 3 months (90 days)</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="recent_donation_admin" id="donated_recently_no_admin" value="no">
            <label class="form-check-label fw-bold text-success" for="donated_recently_no_admin">No, not donated in the last 3 months</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="recent_donation_admin" id="not_sure_admin" value="not_sure">
            <label class="form-check-label" for="not_sure_admin">Not sure when last donated</label>
        </div>
        <div class="alert alert-warning" id="recentDonorWarningAdmin" style="display:none;">
            <strong>Not eligible yet.</strong> Must wait at least 90 days.
        </div>
        <div class="alert alert-info" id="unsureDonorInfoAdmin" style="display:none;">
            You can proceed; the system will check history if email is provided.
        </div>
        <button type="button" class="btn btn-primary" id="proceedBtnAdmin" style="display:none;">Proceed to Registration</button>
    </div>
</div>

<div class="card" id="manualRegCard" style="display:none;">
    <div class="card-body">
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="full_name" required 
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="optional">
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" name="phone" required
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Blood Type <span class="text-danger">*</span></label>
                <select class="form-select" name="blood_type" required>
                    <option value="">Select Blood Type</option>
                    <option value="A+" <?= ($_POST['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                    <option value="A-" <?= ($_POST['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                    <option value="B+" <?= ($_POST['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                    <option value="B-" <?= ($_POST['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                    <option value="AB+" <?= ($_POST['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                    <option value="AB-" <?= ($_POST['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                    <option value="O+" <?= ($_POST['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                    <option value="O-" <?= ($_POST['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="date_of_birth" required
                       max="<?= date('Y-m-d', strtotime('-16 years')) ?>"
                       value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                <small class="text-muted">Must be at least 16 years old</small>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Gender <span class="text-danger">*</span></label>
                <select class="form-select" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= ($_POST['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Weight (kg) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="weight" min="50" step="0.1" required
                       value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>">
                <small class="text-muted">Minimum 50 kg</small>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Last Donation Date</label>
                <input type="date" class="form-control" name="last_donation_date"
                       max="<?= date('Y-m-d') ?>"
                       value="<?= htmlspecialchars($_POST['last_donation_date'] ?? '') ?>">
                <small class="text-muted">Leave empty if first time donor</small>
            </div>
            
            <div class="col-12">
                <h5 class="mt-4 mb-3">Address Information</h5>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" name="address"
                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>
            
            <div class="col-md-6">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city"
                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">State/Province</label>
                <input type="text" class="form-control" name="state"
                       value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Postal Code</label>
                <input type="text" class="form-control" name="postal_code"
                       value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Country</label>
                <input type="text" class="form-control" name="country" value="Philippines" readonly>
            </div>
            
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Donor
                </button>
                <button type="reset" class="btn btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i> Reset
                </button>
            </div>
            <input type="hidden" name="recent_donation" id="recent_donation_post" value="">
        </form>
    </div>
</div>

<div class="card mt-3" id="medicalScreeningAdmin" style="display:none;">
    <div class="card-header">
        <h5 class="mb-0">Medical Screening (Sections A–G)</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php for ($i=1; $i<=37; $i++): ?>
                <div class="col-md-4">
                    <label class="form-label">Question <?= $i ?></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="q<?= $i ?>" value="yes" id="q<?= $i ?>_y">
                        <label class="form-check-label" for="q<?= $i ?>_y">Yes</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="q<?= $i ?>" value="no" id="q<?= $i ?>_n">
                        <label class="form-check-label" for="q<?= $i ?>_n">No</label>
                    </div>
                </div>
            <?php endfor; ?>
            <div class="col-md-6">
                <label class="form-label">q34 (Female only)</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="q34" value="none" id="q34_none">
                    <label class="form-check-label" for="q34_none">None</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="q34" value="date" id="q34_date_opt">
                    <label class="form-check-label" for="q34_date_opt">Date</label>
                </div>
                <input type="date" class="form-control mt-2" name="q34_date">
            </div>
            <div class="col-md-6">
                <label class="form-label">q37 Date (Female only)</label>
                <input type="date" class="form-control" name="q37_date">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save Donor & Screening</button>
            </div>
        </div>
    </div>
</div>

<script>
const yes=document.getElementById('donated_recently_yes_admin');
const no=document.getElementById('donated_recently_no_admin');
const ns=document.getElementById('not_sure_admin');
const warn=document.getElementById('recentDonorWarningAdmin');
const info=document.getElementById('unsureDonorInfoAdmin');
const proceed=document.getElementById('proceedBtnAdmin');
const card=document.getElementById('manualRegCard');
const ms=document.getElementById('medicalScreeningAdmin');
const hidden=document.getElementById('recent_donation_post');
function upd(){
  warn.style.display='none'; info.style.display='none'; proceed.style.display='none'; card.style.display='none'; ms.style.display='none';
  let v='';
  if(yes && yes.checked){warn.style.display='block'; v='yes';}
  else if(no && no.checked){proceed.style.display='inline-block'; card.style.display='block'; ms.style.display='block'; v='no';}
  else if(ns && ns.checked){info.style.display='block'; proceed.style.display='inline-block'; card.style.display='block'; ms.style.display='block'; v='not_sure';}
  if(hidden) hidden.value=v;
}
[yes,no,ns].forEach(el=> el && el.addEventListener('change', upd));
proceed && proceed.addEventListener('click', function(){ document.getElementById('eligibilityCheckAdmin').style.display='none'; card.style.display='block'; ms.style.display='block'; });
</script>

<script>
// Client-side validation
const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function(e) {
        // Check required fields
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Check email format
        const emailField = form.querySelector('input[type="email"]');
        if (emailField && !emailField.validity.valid) {
            emailField.classList.add('is-invalid');
            isValid = false;
        }
        
        // Check date of birth (must be at least 16 years old)
        const dobField = form.querySelector('input[name="date_of_birth"]');
        if (dobField && dobField.value) {
            const dob = new Date(dobField.value);
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            
            if (age < 16) {
                dobField.classList.add('is-invalid');
                alert('Donor must be at least 16 years old');
                isValid = false;
            }
        }
        
        // Check weight (at least 50 kg)
        const weightField = form.querySelector('input[name="weight"]');
        if (weightField && parseFloat(weightField.value) < 50) {
            weightField.classList.add('is-invalid');
            alert('Donor must weigh at least 50 kg');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            // Scroll to first invalid field
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
    
    // Remove invalid class when user starts typing
    form.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
}
</script>
