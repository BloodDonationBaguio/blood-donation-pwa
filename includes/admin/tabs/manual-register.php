<?php
// Manual Registration — rebuilt
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
<form method="POST" action="process_manual_donor.php" id="manualRegForm" novalidate>
  <div class="card mb-3"><div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label required">Full Name</label>
          <input type="text" class="form-control" name="full_name" required>
          <div class="invalid-feedback">Required</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Gender</label>
          <select class="form-select" name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
          <div class="invalid-feedback">Required</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Date of Birth</label>
          <input type="date" class="form-control" name="birth_date" required>
          <div class="invalid-feedback">Required</div>
        </div>
        
        <div class="mb-3">
          <label class="form-label required">Blood Type</label>
          <select name="blood_type" class="form-select" required>
            <option value="">Select Blood Type</option>
            <option>A+</option><option>A-</option><option>B+</option><option>B-</option>
            <option>AB+</option><option>AB-</option><option>O+</option><option>O-</option>
          </select>
          <div class="invalid-feedback">Required</div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label required">Email</label>
          <input type="email" class="form-control" name="email" required>
          <div class="invalid-feedback">Enter a valid email</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Phone Number</label>
          <input type="tel" class="form-control" name="phone" required>
          <div class="invalid-feedback">Required</div>
        </div>
        <div class="mb-3">
          <label class="form-label required">Address</label>
          <input type="text" class="form-control" name="address" required>
          <div class="invalid-feedback">Required</div>
        </div>
        <div class="mb-3">
          <label class="form-label">City</label>
          <div class="form-control bg-light"><span class="text-muted">City of Baguio</span><input type="hidden" name="city" value="City of Baguio"></div>
        </div>
        <div class="mb-3">
          <label class="form-label">Province</label>
          <div class="form-control bg-light"><span class="text-muted">Benguet</span><input type="hidden" name="province" value="Benguet"></div>
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
document.addEventListener('DOMContentLoaded', function(){
  const form=document.getElementById('manualRegForm');
  if(!form) return;
  form.addEventListener('submit', function(e){
    let ok=true; form.querySelectorAll('[required]').forEach(el=>{ if(!el.value.trim()){ el.classList.add('is-invalid'); ok=false; } else { el.classList.remove('is-invalid'); } });
    const fullNameInput=form.querySelector('input[name="full_name"]');
    if(fullNameInput){
      const parts=fullNameInput.value.trim().split(/\s+/);
      const first=parts.shift()||'';
      const last=parts.join(' ');
      let fn=form.querySelector('input[name="first_name"]');
      let ln=form.querySelector('input[name="last_name"]');
      if(!fn){ fn=document.createElement('input'); fn.type='hidden'; fn.name='first_name'; form.appendChild(fn); }
      if(!ln){ ln=document.createElement('input'); ln.type='hidden'; ln.name='last_name'; form.appendChild(ln); }
      fn.value=first; ln.value=last;
    }
    if(!ok){ e.preventDefault(); const first=form.querySelector('.is-invalid'); if(first) first.scrollIntoView({behavior:'smooth',block:'center'}); }
  });
});
</script>