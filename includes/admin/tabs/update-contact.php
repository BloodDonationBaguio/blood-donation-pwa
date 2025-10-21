<?php
// Update Contact Tab Content
$success = '';
$error = '';

// Default contact information
$defaultContact = [
    'organization_name' => 'Philippine Red Cross',
    'address' => '37 EDSA corner Boni Avenue, Mandaluyong City, 1550',
    'city' => 'Mandaluyong',
    'province' => 'Metro Manila',
    'postal_code' => '1550',
    'country' => 'Philippines',
    'phone' => '+63 2 8790 2300',
    'email' => 'info@redcross.org.ph',
    'facebook' => 'phredcross',
    'twitter' => 'philredcross',
    'instagram' => 'philredcross',
    'office_hours' => 'Monday to Friday, 8:00 AM to 5:00 PM',
    'emergency_contact' => '143',
    'latitude' => '14.5816',
    'longitude' => '121.0488',
    'about_text' => 'The Philippine Red Cross is a premier humanitarian organization in the country that provides life-saving assistance to the most vulnerable.',
    'mission' => 'To provide quality life-saving services that protect the life and dignity especially of indigent Filipinos in vulnerable situations.',
    'vision' => 'To be the foremost humanitarian organization in the Philippines, committed to providing quality life-saving services that protect the life and dignity especially of indigent Filipinos in vulnerable situations.'
];

// Load existing contact info or use defaults
$contactFile = __DIR__ . '/../../../data/contact-info.json';
$contactInfo = [];

// Create data directory if it doesn't exist
if (!is_dir(dirname($contactFile))) {
    mkdir(dirname($contactFile), 0755, true);
}

// Load existing contact info if file exists
if (file_exists($contactFile)) {
    $contactInfo = json_decode(file_get_contents($contactFile), true) ?: [];
}

// Merge with defaults
$contactInfo = array_merge($defaultContact, $contactInfo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic validation
        $required = ['organization_name', 'address', 'phone', 'email'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
        }
        
        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }
        
        // Prepare data to save
        $dataToSave = [
            'organization_name' => trim($_POST['organization_name']),
            'address' => trim($_POST['address']),
            'city' => trim($_POST['city'] ?? ''),
            'province' => trim($_POST['province'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'country' => trim($_POST['country'] ?? 'Philippines'),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'facebook' => trim($_POST['facebook'] ?? ''),
            'twitter' => trim($_POST['twitter'] ?? ''),
            'instagram' => trim($_POST['instagram'] ?? ''),
            'office_hours' => trim($_POST['office_hours'] ?? ''),
            'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
            'latitude' => trim($_POST['latitude'] ?? ''),
            'longitude' => trim($_POST['longitude'] ?? ''),
            'about_text' => trim($_POST['about_text'] ?? ''),
            'mission' => trim($_POST['mission'] ?? ''),
            'vision' => trim($_POST['vision'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Save to file
        if (file_put_contents($contactFile, json_encode($dataToSave, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Failed to save contact information");
        }
        
        // Update in-memory data
        $contactInfo = $dataToSave;
        
        $success = "Contact information has been updated successfully!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Update Contact Information</h2>
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

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="post" id="contactForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Organization Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="organization_name" required
                                   value="<?= htmlspecialchars($contactInfo['organization_name']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required
                                   value="<?= htmlspecialchars($contactInfo['email']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required
                                   value="<?= htmlspecialchars($contactInfo['phone']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" name="emergency_contact"
                                   value="<?= htmlspecialchars($contactInfo['emergency_contact']) ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="address" required
                                   value="<?= htmlspecialchars($contactInfo['address']) ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city"
                                   value="<?= htmlspecialchars($contactInfo['city']) ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Province/State</label>
                            <input type="text" class="form-control" name="province"
                                   value="<?= htmlspecialchars($contactInfo['province']) ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code"
                                   value="<?= htmlspecialchars($contactInfo['postal_code']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country"
                                   value="<?= htmlspecialchars($contactInfo['country']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Office Hours</label>
                            <input type="text" class="form-control" name="office_hours"
                                   value="<?= htmlspecialchars($contactInfo['office_hours']) ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">About Text</label>
                            <textarea class="form-control" name="about_text" rows="3"><?= htmlspecialchars($contactInfo['about_text']) ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Mission</label>
                            <textarea class="form-control" name="mission" rows="3"><?= htmlspecialchars($contactInfo['mission']) ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Vision</label>
                            <textarea class="form-control" name="vision" rows="3"><?= htmlspecialchars($contactInfo['vision']) ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <h5 class="mt-4 mb-3">Social Media</h5>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="input-group">
                                <span class="input-group-text">facebook.com/</span>
                                <input type="text" class="form-control" name="facebook"
                                       value="<?= htmlspecialchars($contactInfo['facebook']) ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="input-group">
                                <span class="input-group-text">twitter.com/</span>
                                <input type="text" class="form-control" name="twitter"
                                       value="<?= htmlspecialchars($contactInfo['twitter']) ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="input-group">
                                <span class="input-group-text">instagram.com/</span>
                                <input type="text" class="form-control" name="instagram"
                                       value="<?= htmlspecialchars($contactInfo['instagram']) ?>">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <h5 class="mt-4 mb-3">Map Coordinates</h5>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="latitude"
                                   value="<?= htmlspecialchars($contactInfo['latitude']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="longitude"
                                   value="<?= htmlspecialchars($contactInfo['longitude']) ?>">
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Preview</h5>
            </div>
            <div class="card-body">
                <h4><?= htmlspecialchars($contactInfo['organization_name']) ?></h4>
                <p class="text-muted"><?= nl2br(htmlspecialchars($contactInfo['about_text'])) ?></p>
                
                <h6 class="mt-4"><i class="fas fa-map-marker-alt me-2 text-danger"></i> Address</h6>
                <p>
                    <?= nl2br(htmlspecialchars($contactInfo['address'])) ?><br>
                    <?= htmlspecialchars($contactInfo['city']) ?>, <?= htmlspecialchars($contactInfo['province']) ?><br>
                    <?= htmlspecialchars($contactInfo['postal_code']) ?>, <?= htmlspecialchars($contactInfo['country']) ?>
                </p>
                
                <h6 class="mt-4"><i class="fas fa-phone me-2 text-danger"></i> Contact</h6>
                <p>
                    <strong>Phone:</strong> <?= htmlspecialchars($contactInfo['phone']) ?><br>
                    <strong>Email:</strong> <?= htmlspecialchars($contactInfo['email']) ?><br>
                    <strong>Emergency:</strong> <?= htmlspecialchars($contactInfo['emergency_contact']) ?>
                </p>
                
                <h6 class="mt-4"><i class="fas fa-clock me-2 text-danger"></i> Office Hours</h6>
                <p><?= htmlspecialchars($contactInfo['office_hours']) ?></p>
                
                <h6 class="mt-4"><i class="fas fa-share-alt me-2 text-danger"></i> Follow Us</h6>
                <div class="social-links">
                    <?php if (!empty($contactInfo['facebook'])): ?>
                        <a href="https://facebook.com/<?= urlencode($contactInfo['facebook']) ?>" target="_blank" class="me-2">
                            <i class="fab fa-facebook fa-2x text-primary"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($contactInfo['twitter'])): ?>
                        <a href="https://twitter.com/<?= urlencode($contactInfo['twitter']) ?>" target="_blank" class="me-2">
                            <i class="fab fa-twitter fa-2x text-info"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($contactInfo['instagram'])): ?>
                        <a href="https://instagram.com/<?= urlencode($contactInfo['instagram']) ?>" target="_blank" class="me-2">
                            <i class="fab fa-instagram fa-2x text-danger"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($contactInfo['latitude']) && !empty($contactInfo['longitude'])): ?>
                    <div class="mt-4">
                        <div id="mapPreview" style="height: 200px; background-color: #f5f5f5; border-radius: 4px; overflow: hidden;">
                            <img src="https://maps.googleapis.com/maps/api/staticmap?center=<?= urlencode($contactInfo['latitude']) ?>,<?= urlencode($contactInfo['longitude']) ?>&zoom=15&size=600x300&maptype=roadmap&markers=color:red%7C<?= urlencode($contactInfo['latitude']) ?>,<?= urlencode($contactInfo['longitude']) ?>&key=YOUR_GOOGLE_MAPS_API_KEY" 
                                 alt="Map" class="img-fluid">
                        </div>
                        <small class="text-muted">Map preview (static image)</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-update preview when form changes
document.getElementById('contactForm').addEventListener('input', function(e) {
    const target = e.target;
    const name = target.name;
    const value = target.value;
    
    // Update preview based on changed field
    switch(name) {
        case 'organization_name':
            document.querySelector('.card-header h5').textContent = value;
            break;
        case 'about_text':
            document.querySelector('.card-body p.text-muted').textContent = value;
            break;
        case 'address':
        case 'city':
        case 'province':
        case 'postal_code':
        case 'country':
            // This is a simplified update - in a real app, you'd update each part individually
            const addressParts = [
                document.querySelector('input[name="address"]').value,
                document.querySelector('input[name="city"]').value,
                document.querySelector('input[name="province"]').value,
                document.querySelector('input[name="postal_code"]').value,
                document.querySelector('input[name="country"]').value
            ].filter(Boolean).join(', ');
            
            // Find the address paragraph and update it
            const addressParagraph = document.querySelector('.card-body p:not(.text-muted)');
            if (addressParagraph) {
                addressParagraph.innerHTML = addressParts.replace(/\n/g, '<br>');
            }
            break;
        case 'phone':
        case 'email':
        case 'emergency_contact':
            // This is a simplified update
            const contactInfo = document.querySelectorAll('.card-body p')[1];
            if (contactInfo) {
                contactInfo.innerHTML = `
                    <strong>Phone:</strong> ${document.querySelector('input[name="phone"]').value}<br>
                    <strong>Email:</strong> ${document.querySelector('input[name="email"]').value}<br>
                    <strong>Emergency:</strong> ${document.querySelector('input[name="emergency_contact"]').value}
                `;
            }
            break;
        case 'office_hours':
            const officeHours = document.querySelectorAll('.card-body p')[2];
            if (officeHours) {
                officeHours.textContent = value;
            }
            break;
        case 'facebook':
        case 'twitter':
        case 'instagram':
            // Update social media links
            const socialLink = document.querySelector(`a[href*="${name}.com/"]`);
            if (socialLink) {
                socialLink.href = `https://${name}.com/${encodeURIComponent(value)}`;
            }
            break;
        case 'latitude':
        case 'longitude':
            // Update map preview
            const lat = document.querySelector('input[name="latitude"]').value;
            const lng = document.querySelector('input[name="longitude"]').value;
            const mapImg = document.querySelector('#mapPreview img');
            if (mapImg && lat && lng) {
                mapImg.src = `https://maps.googleapis.com/maps/api/staticmap?center=${encodeURIComponent(lat)},${encodeURIComponent(lng)}&zoom=15&size=600x300&maptype=roadmap&markers=color:red%7C${encodeURIComponent(lat)},${encodeURIComponent(lng)}&key=YOUR_GOOGLE_MAPS_API_KEY`;
            }
            break;
    }
});

// Handle form reset
document.querySelector('button[type="reset"]').addEventListener('click', function() {
    if (confirm('Are you sure you want to reset all fields to their default values?')) {
        // In a real app, you would fetch the default values from the server
        // This is a simplified version that just reloads the page
        window.location.href = '?tab=update-contact';
    }
});
</script>