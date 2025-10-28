<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/settings.php';
$settings = get_site_settings();
$about_content = isset($settings['pages']['about']) ? $settings['pages']['about'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us - Blood Donation System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="favicon-32.png">
    <link rel="manifest" href="manifest.json">
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
            margin: 0 auto;
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
        
        .feature-icon {
            font-size: 3rem;
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            display: inline-block;
        }
        
        .mission-vision-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 6px solid #dc3545;
            position: relative;
        }
        
        .mission-vision-card::after {
            content: '';
            position: absolute;
            top: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            border-radius: 50%;
            opacity: 0.1;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 35px;
            margin: 50px 0;
        }
        
        .feature-item {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 2px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .feature-item::before {
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
        
        .feature-item:hover::before {
            opacity: 0.05;
        }
        
        .feature-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            border-color: #dc3545;
        }
        
        .feature-item > * {
            position: relative;
            z-index: 1;
        }
        
        .feature-icon-small {
            font-size: 2.2rem;
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .stats-section {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 50%, #a71e2a 100%);
            color: white;
            padding: 80px 0;
            margin: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
        }
        
        .stat-item {
            text-align: center;
            padding: 30px;
            position: relative;
            z-index: 2;
        }
        
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .stat-label {
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.9;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 50%, #17a2b8 100%);
            color: white;
            padding: 80px 0;
            margin: 80px 0;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .btn-custom {
            padding: 15px 35px;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .btn-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-custom:hover::before {
            left: 100%;
        }
        
        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .section-title {
            color: #dc3545;
            font-weight: 700;
            margin-bottom: 35px;
            position: relative;
            font-size: 2.2rem;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #dc3545, #ff6b6b);
            border-radius: 2px;
        }
        
        .section-title.text-center::after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .highlight-box {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%);
            border: 3px solid #dc3545;
            border-radius: 16px;
            padding: 40px;
            margin: 30px 0;
            position: relative;
            overflow: hidden;
        }
        
        .highlight-box::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(220,53,69,0.05) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .partner-logo {
            max-width: 180px;
            height: auto;
            margin: 25px 0;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }
        
        .partner-logo:hover {
            transform: scale(1.05);
        }
        
        .benefit-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .benefit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            border-color: #dc3545;
        }
        
        .help-item {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid #dc3545;
        }
        
        .help-item:hover {
            transform: translateX(5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.1);
        }
        
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95) !important;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: #dc3545 !important;
            transform: translateY(-1px);
        }
        
        .nav-link.active {
            color: #dc3545 !important;
            font-weight: 600;
        }
        
        .btn-donate {
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-donate:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220,53,69,0.3);
            color: white;
        }
        
        .footer {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            color: white;
            padding: 40px 0;
            margin-top: 80px;
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
            
            .feature-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
        }
        
        /* Animation classes */
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
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center hero-content">
        <h1 class="hero-title fade-in">About Us</h1>
        <p class="hero-subtitle fade-in">Empowering life-saving connections through technology and compassion</p>
    </div>
</section>

<div class="container" style="margin-top: 80px;">
    <!-- Who We Are Section -->
    <div class="section-card fade-in">
        <div class="row align-items-center">
            <div class="col-lg-8 slide-in-left">
                <h2 class="section-title">Who We Are</h2>
                <p class="lead mb-4" style="font-size: 1.2rem; color: #6c757d;">The Blood Donation Management System is a web-based platform developed to support and partner with the Philippine Red Cross – Baguio Chapter.</p>
                <p class="mb-0" style="font-size: 1.1rem; line-height: 1.8;">Our goal is to make blood donation services more accessible, faster, and more efficient, especially during emergencies. We are proud to collaborate with the Red Cross Baguio in promoting life-saving blood donations across the community.</p>
            </div>
            <div class="col-lg-4 text-center slide-in-right">
                <i class="fas fa-heartbeat feature-icon"></i>
            </div>
        </div>
    </div>

    <!-- Mission & Vision Section -->
    <div class="row">
        <div class="col-lg-6">
            <div class="section-card mission-vision-card slide-in-left">
                <h3 class="section-title" style="font-size: 1.8rem;">Our Mission</h3>
                <p style="font-size: 1.1rem; line-height: 1.7;">To save lives by providing a centralized system that connects blood donors with those in urgent need. We aim to modernize the blood donation process and help the Red Cross Baguio improve its outreach and emergency response efforts.</p>
                <i class="fas fa-bullseye feature-icon-small"></i>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="section-card mission-vision-card slide-in-right">
                <h3 class="section-title" style="font-size: 1.8rem;">Our Vision</h3>
                <p style="font-size: 1.1rem; line-height: 1.7;">A future where every patient in need of blood receives timely help. We strive to build a strong community of volunteer donors, healthcare providers, and organizations working together to ensure blood availability anytime it's needed.</p>
                <i class="fas fa-eye feature-icon-small"></i>
            </div>
        </div>
    </div>

    <!-- What We Do Section -->
    <div class="section-card fade-in">
        <h2 class="section-title text-center mb-5">What We Do</h2>
        <div class="feature-grid">
            <div class="feature-item">
                <i class="fas fa-users feature-icon-small"></i>
                <h4 style="font-weight: 600; margin-bottom: 15px;">Connect Donors with Recipients</h4>
                <p style="line-height: 1.6;">We help match patients with eligible blood donors through our smart, location-based system.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-clipboard-list feature-icon-small"></i>
                <h4 style="font-weight: 600; margin-bottom: 15px;">Simplify Donor Registration</h4>
                <p style="line-height: 1.6;">Donors can register easily online, manage their records, and check when they're eligible to donate again.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line feature-icon-small"></i>
                <h4 style="font-weight: 600; margin-bottom: 15px;">Track Donations</h4>
                <p style="line-height: 1.6;">Our platform allows donors to track their donation history and status with real-time updates and admin support.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-handshake feature-icon-small"></i>
                <h4 style="font-weight: 600; margin-bottom: 15px;">Partnered with Red Cross Baguio</h4>
                <p style="line-height: 1.6;">In close collaboration with Philippine Red Cross – Baguio Chapter, we support donation drives and emergency blood needs.</p>
            </div>
        </div>
    </div>

    <!-- Partner Section -->
    <div class="section-card text-center fade-in">
        <h2 class="section-title">Our Partnership</h2>
        <div class="highlight-box">
            <img src="https://benguetredcross.com/wp-content/uploads/2023/03/Logo_Philippine_Red_Cross-1536x1536.png" alt="Philippine Red Cross Logo" class="partner-logo">
            <h4 class="text-danger" style="font-weight: 700; margin-bottom: 15px;">Philippine Red Cross – Baguio Chapter</h4>
            <p class="mb-0" style="font-size: 1.1rem; line-height: 1.7;">We are proud to work hand-in-hand with the Philippine Red Cross Baguio Chapter to enhance blood donation services and emergency response capabilities in the region.</p>
        </div>
    </div>

    <!-- Why Donate Section -->
    <div class="section-card fade-in">
        <h2 class="section-title text-center mb-5">Why Donate Blood?</h2>
        <div class="row">
            <div class="col-lg-4 text-center mb-4">
                <div class="benefit-card">
                    <i class="fas fa-heart feature-icon"></i>
                    <h4 style="font-weight: 600; margin-bottom: 15px;">Save Lives</h4>
                    <p style="line-height: 1.6;">One donation can help up to three people in need.</p>
                </div>
            </div>
            <div class="col-lg-4 text-center mb-4">
                <div class="benefit-card">
                    <i class="fas fa-dumbbell feature-icon"></i>
                    <h4 style="font-weight: 600; margin-bottom: 15px;">Boost Health</h4>
                    <p style="line-height: 1.6;">Donating regularly is linked to cardiovascular benefits.</p>
                </div>
            </div>
            <div class="col-lg-4 text-center mb-4">
                <div class="benefit-card">
                    <i class="fas fa-globe feature-icon"></i>
                    <h4 style="font-weight: 600; margin-bottom: 15px;">Build Community</h4>
                    <p style="line-height: 1.6;">Be part of a life-saving movement.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- How You Can Help Section -->
    <div class="section-card fade-in">
        <h2 class="section-title text-center mb-5">How You Can Help</h2>
        <div class="row">
            <div class="col-lg-6">
                <div class="help-item">
                    <i class="fas fa-user-plus feature-icon-small"></i>
                    <h5 style="font-weight: 600; margin-bottom: 10px;">Become a registered donor</h5>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="help-item">
                    <i class="fas fa-share-alt feature-icon-small"></i>
                    <h5 style="font-weight: 600; margin-bottom: 10px;">Encourage others to donate</h5>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="help-item">
                    <i class="fas fa-calendar-check feature-icon-small"></i>
                    <h5 style="font-weight: 600; margin-bottom: 10px;">Support and attend Red Cross donation drives</h5>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="help-item">
                    <i class="fas fa-bullhorn feature-icon-small"></i>
                    <h5 style="font-weight: 600; margin-bottom: 10px;">Share the importance of voluntary donation</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action Section -->
    <div class="cta-section text-center">
        <div style="position: relative; z-index: 2;">
            <h2 class="mb-4" style="font-weight: 700; font-size: 2.5rem;">Ready to Make a Difference?</h2>
            <p class="lead mb-4" style="font-size: 1.2rem; opacity: 0.95;">Join our community of life-savers today and help us build a stronger, healthier future for everyone.</p>
            <a href="donor-registration.php" class="btn btn-light btn-custom me-3">
                <i class="fas fa-heart me-2"></i>Donate Now
            </a>
            
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container text-center">
                 <p class="mb-0">&copy; 2025 Blood Donation Management System. Partnered with Philippine Red Cross – Baguio Chapter.</p>
    </div>
</footer>

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
    
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Navbar background on scroll
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.background = 'rgba(255,255,255,0.98)';
        navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.15)';
    } else {
        navbar.style.background = 'rgba(255,255,255,0.95)';
        navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
    }
});
</script>
</body>
</html>

