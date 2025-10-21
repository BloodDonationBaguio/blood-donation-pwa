<?php
// Add Donor Tab Content
$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $required = ['full_name', 'email', 'phone', 'blood_type', 'date_of_birth', 'gender', 'weight', 'last_donation_date'];
        $data = [];
        
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
            $data[$field] = trim($_POST[$field]);
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
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
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM donors WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            throw new Exception("A donor with this email already exists");
        }
        
        // Generate a reference code
        $referenceCode = 'DON' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO donors (
                full_name, email, phone, blood_type, date_of_birth, 
                gender, weight, last_donation_date, address, city, 
                state, postal_code, country, reference_code, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, ?, 'active', NOW()
            )
        ");
        
        $stmt->execute([
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['blood_type'],
            $data['date_of_birth'],
            $data['gender'],
            $data['weight'],
            $data['last_donation_date'] ?: null,
            $_POST['address'] ?? '',
            $_POST['city'] ?? '',
            $_POST['state'] ?? '',
            $_POST['postal_code'] ?? '',
            $_POST['country'] ?? 'Philippines',
            $referenceCode
        ]);
        
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

<div class="card">
    <div class="card-body">
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="full_name" required 
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
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
        </form>
    </div>
</div>

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
