<?php
// Full-page Manual Donor Registration (Admin)
// No eligibility, no CAPTCHA; includes medical section; posts to process_manual_donor.php
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Manual Donor Registration</h2>
    <a href="?tab=donor-list" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
  </div>
<div class="alert alert-info d-flex justify-content-between align-items-center py-2 mb-3">
  <div>
    <strong>Version:</strong> <?= date('Y-m-d H:i:s') ?>
    <span class="ms-3"><strong>Build:</strong> <?= filemtime(__FILE__) ?></span>
  </div>
  <a href="admin_flush_cache.php" class="btn btn-sm btn-warning">Flush Cache</a>
</div>
<form method="POST" action="process_manual_donor.php" id="adminManualDonorForm" novalidate>
  <div class="card mb-3"><div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label required">First Name</label>
              <input type="text" class="form-control" name="first_name" required>
              <div class="invalid-feedback">Please enter first name.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label required">Last Name</label>
              <input type="text" class="form-control" name="last_name" required>
              <div class="invalid-feedback">Please enter last name.</div>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Gender</label>
          <select class="form-select" name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
          <div class="invalid-feedback">Please select gender.</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Date of Birth</label>
          <input type="date" class="form-control" name="birth_date" required>
          <div class="invalid-feedback">Please enter date of birth.</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Weight (kg)</label>
          <div class="input-group">
            <input type="number" class="form-control" name="weight" min="50" step="0.1" required>
            <span class="input-group-text">kg (minimum 50 kg)</span>
          </div>
          <div class="invalid-feedback">Minimum weight is 50 kg.</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Height (cm)</label>
          <div class="input-group">
            <input type="number" class="form-control" name="height" min="100" max="250" step="0.1" required>
            <span class="input-group-text">cm</span>
          </div>
          <div class="invalid-feedback">Please enter height in cm.</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Blood Type</label>
          <select name="blood_type" class="form-select" required>
            <option value="">Select Blood Type</option>
            <option value="A+">A+</option>
            <option value="A-">A-</option>
            <option value="B+">B+</option>
            <option value="B-">B-</option>
            <option value="AB+">AB+</option>
            <option value="AB-">AB-</option>
            <option value="O+">O+</option>
            <option value="O-">O-</option>
            <option value="UNK">Unknown (Will be determined during screening)</option>
          </select>
          <div class="invalid-feedback">Please select blood type.</div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label required">Email</label>
          <input type="email" class="form-control" name="email" required>
          <div class="invalid-feedback">Please enter a valid email.</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Phone Number</label>
          <input type="tel" class="form-control" name="phone" required>
          <div class="invalid-feedback">Please enter phone number.</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Address</label>
          <input type="text" class="form-control" name="address" required>
          <div class="invalid-feedback">Please enter address.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">City</label>
          <div class="form-control bg-light">
            <span class="text-muted">City of Baguio</span>
            <input type="hidden" name="city" value="City of Baguio">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Province</label>
          <div class="form-control bg-light">
            <span class="text-muted">Benguet</span>
            <input type="hidden" name="province" value="Benguet">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Postal Code</label>
          <input type="text" class="form-control" name="postal_code" required>
          <div class="invalid-feedback">Please enter postal code.</div>
        </div>
      </div>
    </div>
  </div></div>
  <div class="card mt-3"><div class="card-body">
    <h4 class="section-title">Medical Screening</h4>
    <?php include dirname(__DIR__, 2) . '/includes/medical_section.php'; ?>
  </div></div>
  <div class="mt-3">
    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i> Register Donor</button>
  </div>
</form>
<script>
// Minimal client-side validation
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('adminManualDonorForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    const req = form.querySelectorAll('[required]');
    let ok = true;
    req.forEach(el=>{ if(!el.value.trim()){ el.classList.add('is-invalid'); ok=false; } else { el.classList.remove('is-invalid'); } });
    if(!ok){ e.preventDefault(); const first=form.querySelector('.is-invalid'); if(first) first.scrollIntoView({behavior:'smooth',block:'center'}); }
  });
});
</script>