<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/settings.php';
$settings = get_site_settings();
$contact_info = isset($settings['contact']) ? $settings['contact'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Find Us - Blood Donation System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 50%, #a71e2a 100%);
            color: white;
            padding: 120px 0 100px;
            margin-bottom: 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            font-weight: 400;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto 2rem;
        }
        
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            margin-bottom: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #dc3545, #ff6b6b, #dc3545);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .section-card:hover::before {
            transform: scaleX(1);
        }
        
        .section-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 80px rgba(0,0,0,0.12);
        }
        
        .contact-item {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        
        .contact-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }
        
        .contact-item:hover::before {
            opacity: 0.05;
        }
        
        .contact-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            border-color: #dc3545;
        }
        
        .contact-icon {
            font-size: 3rem;
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            display: inline-block;
            position: relative;
            z-index: 1;
        }
        
        .contact-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #212529;
            position: relative;
            z-index: 1;
        }
        
        .contact-text {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        .contact-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .contact-link:hover {
            color: #c82333;
            text-decoration: underline;
        }
        
        .map-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        
        .map-placeholder {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.1rem;
            border: 2px dashed #dee2e6;
        }
        
        .hours-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }
        
        .hours-table th {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 20px;
            font-weight: 600;
        }
        
        .hours-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f8f9fa;
            vertical-align: middle;
        }
        
        .hours-table tr:last-child td {
            border-bottom: none;
        }
        
        .hours-table .day {
            font-weight: 600;
            color: #495057;
        }
        
        .hours-table .time {
            color: #6c757d;
        }
        
        .hours-table .status {
            font-weight: 600;
        }
        
        .status-open {
            color: #28a745;
        }
        
        .status-closed {
            color: #dc3545;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: all 0.6s ease;
        }
        
        .slide-in-left.visible {
            opacity: 1;
            transform: translateX(0);
        }
        
        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.6s ease;
        }
        
        .slide-in-right.visible {
            opacity: 1;
            transform: translateX(0);
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0 60px;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section-card {
                padding: 30px;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center hero-content">
        <h1 class="hero-title fade-in">Find Us</h1>
        <p class="hero-subtitle fade-in">Visit our blood donation center and make a difference in your community</p>
    </div>
</section>

<div class="container">
    <!-- Contact Information -->
    <div class="section-card fade-in">
        <h2 class="section-title">Contact Information</h2>
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt contact-icon"></i>
                    <h3 class="contact-title">Address</h3>
                    <p class="contact-text">
                        Philippine Red Cross<br>
                        Red Cross Building, 39 Harrison Rd<br>
                        Baguio City, Benguet, Philippines
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-item">
                    <i class="fas fa-phone contact-icon"></i>
                    <h3 class="contact-title">Phone</h3>
                    <p class="contact-text">
                        <a href="tel:<?= isset($contact_info['phone']) ? htmlspecialchars($contact_info['phone']) : '+63-74-442-1234' ?>" class="contact-link">
                            <?= isset($contact_info['phone']) ? htmlspecialchars($contact_info['phone']) : '+63-74-442-1234' ?>
                        </a>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-item">
                    <i class="fas fa-envelope contact-icon"></i>
                    <h3 class="contact-title">Email</h3>
                    <p class="contact-text">
                        <a href="mailto:<?= isset($contact_info['email']) ? htmlspecialchars($contact_info['email']) : 'baguio@redcross.org.ph' ?>" class="contact-link">
                            <?= isset($contact_info['email']) ? htmlspecialchars($contact_info['email']) : 'baguio@redcross.org.ph' ?>
                        </a>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-item">
                    <i class="fas fa-clock contact-icon"></i>
                    <h3 class="contact-title">Operating Hours</h3>
                    <p class="contact-text">
                        <strong>Monday - Friday: 8:00 AM - 5:00 PM</strong><br>
                        <strong>Saturday - Sunday: 8:00 AM - 5:00 PM</strong><br>
                        Emergency services available by appointment
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-item">
                    <i class="fas fa-globe contact-icon"></i>
                    <h3 class="contact-title">Website</h3>
                    <p class="contact-text">
                        <a href="https://www.redcross.org.ph" class="contact-link" target="_blank">
                            www.redcross.org.ph
                        </a>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-item">
                    <i class="fas fa-heart contact-icon"></i>
                    <h3 class="contact-title">Emergency</h3>
                    <p class="contact-text">
                        For urgent blood needs, call our emergency hotline: <br>
                        <a href="tel:+63-74-442-9999" class="contact-link">+63-74-442-9999</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section -->
    <div class="map-container fade-in">
        <h2 class="section-title">Our Location</h2>
        <div class="map-placeholder">
            <div class="text-center">
                <i class="fas fa-map-marked-alt fa-3x mb-3 text-muted"></i>
                <p><strong>Philippine Red Cross</strong></p>
                <p class="mb-2">Red Cross Building, 39 Harrison Rd</p>
                <small class="text-muted">Baguio City, Benguet, Philippines</small>
                <div class="mt-3">
                    <a href="https://maps.google.com/?q=Red+Cross+Building+39+Harrison+Road+Baguio+City+Philippines" 
                       target="_blank" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i>Open in Google Maps
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Operating Hours Table -->
    <div class="section-card fade-in">
        <h2 class="section-title">Operating Hours</h2>
        <div class="table-responsive">
            <table class="table hours-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar-alt me-2"></i>Day</th>
                        <th><i class="fas fa-clock me-2"></i>Hours</th>
                        <th><i class="fas fa-info-circle me-2"></i>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="day">Monday</td>
                        <td class="time">8:00 AM - 5:00 PM</td>
                        <td class="status status-open">Open</td>
                    </tr>
                    <tr>
                        <td class="day">Tuesday</td>
                        <td class="time">8:00 AM - 5:00 PM</td>
                        <td class="status status-open">Open</td>
                    </tr>
                    <tr>
                        <td class="day">Wednesday</td>
                        <td class="time">8:00 AM - 5:00 PM</td>
                        <td class="status status-open">Open</td>
                    </tr>
                    <tr>
                        <td class="day">Thursday</td>
                        <td class="time">8:00 AM - 5:00 PM</td>
                        <td class="status status-open">Open</td>
                    </tr>
                    <tr>
                        <td class="day">Friday</td>
                        <td class="time">8:00 AM - 5:00 PM</td>
                        <td class="status status-open">Open</td>
                    </tr>
                    <tr>
                        <td class="day">Saturday</td>
                        <td class="time">8:00 AM - 5:00 PM</td>
                        <td class="status status-opened">Open</td>
                    </tr>
                    <tr>
                        <td class="day">Sunday</td>
                        <td class="time">8:00 AM - 5:00 PM</td>
                        <td class="status status-open">Open</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="section-card fade-in text-center">
        <h2 class="section-title">Ready to Donate?</h2>
        <p class="lead mb-4" style="font-size: 1.2rem; color: #6c757d;">
            Visit our center during operating hours or schedule an appointment online
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="donor-registration.php" class="btn btn-danger btn-lg px-4 py-3" style="border-radius: 12px;">
                <i class="fas fa-heartbeat me-2"></i>Register as Donor
            </a>
            <a href="track.php" class="btn btn-outline-danger btn-lg px-4 py-3" style="border-radius: 12px;">
                <i class="fas fa-search me-2"></i>Track Application
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, observerOptions);

// Observe all animated elements
document.addEventListener('DOMContentLoaded', function() {
    const animatedElements = document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right');
    animatedElements.forEach(el => observer.observe(el));
});
</script>
</body>
</html>
