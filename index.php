<?php 
// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Production features
require_once 'includes/ssl_config.php';
require_once 'includes/local_ssl_config.php';
require_once 'includes/accessibility.php';
require_once 'includes/cache_system.php';

// Check if we're on localhost and adjust SSL settings accordingly
$isLocalhost = (
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
    strpos($_SERVER['HTTP_HOST'], 'xampp') !== false
);

if (!$isLocalhost) {
    // Only enforce HTTPS and SSL on production
    SSLConfig::enforceHTTPS();
    SSLConfig::setSecurityHeaders();
} else {
    // For localhost, just set basic security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
} 

try {
    require_once __DIR__ . '/includes/educational_content.php';
    echo "<!-- Educational content loaded successfully -->\n";
} catch (Exception $e) {
    die("Error loading educational content: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Donation System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <?php
    // Add accessibility features
    if (AccessibilityHelper::getConfig()['enabled']) {
        echo AccessibilityHelper::generateSkipLinks();
        echo '<style>' . AccessibilityHelper::generateCSS() . '</style>';
    }
    ?>
    <link rel="manifest" href="manifest.json?v=2.0.0">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.svg">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Removed reference to missing css/modern-redesign.css to fix MIME error -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
      if ('serviceWorker' in navigator) {
        // Force update of the Service Worker by bumping version
        navigator.serviceWorker.register('sw.js?v=9').then(function(registration) {
          console.log('Service Worker registered successfully');
        }).catch(function(error) {
          console.log('Service Worker registration failed:', error);
        });
      }
    </script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            padding-top: 76px !important;
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        /* Ensure all content is visible */
        .container, .hero-section, .hero-content {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
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
            color: white !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            z-index: 10;
            position: relative;
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            font-weight: 400;
            opacity: 1 !important;
            max-width: 600px;
            margin: 0 auto 2rem;
            color: white !important;
            display: block !important;
            visibility: visible !important;
            z-index: 10;
            position: relative;
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
        
        /* Navbar styles are now handled in navbar.php */
        
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
        
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        
        .card-header {
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .who-can-donate-card {
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        
        .who-can-donate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .testimonial {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            text-align: center;
            border-left: 4px solid #dc3545;
        }
        
        .mini-gallery {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .mini-gallery img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid #dc3545;
            transition: all 0.3s ease;
        }
        
        .mini-gallery img:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        .blood-fact {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.06);
            border-left: 4px solid #dc3545;
            transition: all 0.3s ease;
        }
        
        .blood-fact:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .hero-img {
            max-width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
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

<?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
<div class="alert alert-success alert-dismissible fade show m-3" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <strong>Logged out successfully!</strong> You have been safely logged out of your account.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center hero-content">
        <div class="d-flex justify-content-center mb-4">
            <div class="logo-circle shadow-sm" style="background: white; border-radius: 50%; padding: 20px; width: 120px; height: 120px; display: flex; align-items: center; justify-content: center;">
                <div style="background: #dc3545; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">
                    <i class="fas fa-heartbeat"></i>
                </div>
            </div>
        </div>
        <h1 class="hero-title fade-in">Blood Donation System</h1>
        <p class="hero-subtitle fade-in">Donate blood, save lives. Register as a donor and make a difference.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
            <a href="donor-registration.php" class="btn btn-light btn-custom d-flex align-items-center gap-2">
                <i class="bi bi-droplet-fill"></i> Donor Registration
            </a>
        </div>
    </div>
</section>

<div class="container" style="margin-top: 80px;">
  <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i>
      <strong>Logged out successfully!</strong> You have been securely logged out of your account.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i>
      <strong>Welcome back!</strong> You have been successfully logged in.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Blood Donation Facts Section -->
  <div class="section-card fade-in">
    <h3 class="section-title text-center"><i class="fas fa-info-circle text-danger me-2"></i>Did You Know?</h3>
    <div class="feature-grid">
      <?php $facts = getDonationFacts(); foreach ($facts as $fact): ?>
      <div class="feature-item">
        <i class="<?= $fact['icon'] ?> feature-icon-small"></i>
        <h4 style="font-weight: 600; margin-bottom: 15px;"><?= $fact['fact'] ?></h4>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Health Tips Section -->
  <div class="section-card fade-in">
    <h3 class="section-title text-center"><i class="fas fa-heartbeat text-danger me-2"></i>Health Tips for Donors</h3>
    <div class="feature-grid">
      <?php $healthTips = getHealthTips(); foreach ($healthTips as $tip): ?>
      <div class="feature-item">
        <div class="card-header bg-danger text-white text-center mb-3">
          <i class="<?= $tip['icon'] ?> me-2"></i><?= $tip['title'] ?>
        </div>
        <ul class="list-unstyled mb-0">
          <?php foreach ($tip['tips'] as $tipText): ?>
          <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><?= $tipText ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Donation Benefits Section -->
  <div class="section-card fade-in">
    <h3 class="section-title text-center"><i class="fas fa-star text-warning me-2"></i>Benefits of Blood Donation</h3>
    <div class="feature-grid">
      <?php $benefits = getDonationBenefits(); foreach ($benefits as $benefit): ?>
      <div class="feature-item">
        <div class="card-header bg-warning text-dark text-center mb-3">
          <i class="<?= $benefit['icon'] ?> me-2"></i><?= $benefit['title'] ?>
        </div>
        <ul class="list-unstyled mb-0">
          <?php foreach ($benefit['benefits'] as $benefitText): ?>
          <li class="mb-2"><i class="fas fa-arrow-right text-warning me-2"></i><?= $benefitText ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Who Can Donate Blood Section -->
  <div class="section-card fade-in">
    <h4 class="section-title text-center"><b>Who Can Donate Blood?</b></h4>
    <div class="feature-grid">
      <div class="feature-item">
        <i class="bi bi-droplet-fill feature-icon-small"></i>
        <h5 style="font-weight: 600; margin-bottom: 15px;">Age Requirement</h5>
        <p>You must be between 18 to 65 years old (for those ages 16-17 it's require parent or guardian consent).</p>
      </div>
      <div class="feature-item">
        <i class="bi bi-droplet-fill feature-icon-small"></i>
        <h5 style="font-weight: 600; margin-bottom: 15px;">Health Check</h5>
        <p>You must not be anemic and should pass the basic blood test.</p>
      </div>
      <div class="feature-item">
        <i class="bi bi-droplet-fill feature-icon-small"></i>
        <h5 style="font-weight: 600; margin-bottom: 15px;">General Health</h5>
        <p>You must be in general good health with no major illness.</p>
      </div>
      <div class="feature-item">
        <i class="bi bi-droplet-fill feature-icon-small"></i>
        <h5 style="font-weight: 600; margin-bottom: 15px;">Donation Interval</h5>
        <p>You can donate again only after 90 days (3 months).</p>
      </div>
    </div>
  </div>

  <!-- Collaborators Section -->
  <div class="section-card text-center fade-in">
    <h2 class="section-title">Our Collaborators</h2>
    <div class="highlight-box">
      <img src="https://benguetredcross.com/wp-content/uploads/2023/03/Logo_Philippine_Red_Cross-1536x1536.png" alt="Red Cross" width="80" style="background:#fff;border-radius:50%; margin-bottom: 20px;">
      <h4 class="text-danger" style="font-weight: 700; margin-bottom: 15px;">Philippine Red Cross - Baguio Chapter</h4>
      <p class="mb-0" style="font-size: 1.1rem; line-height: 1.7;">We are proud to work hand-in-hand with the Philippine Red Cross Baguio Chapter to enhance blood donation services and emergency response capabilities in the region.</p>
    </div>
  </div>

  <!-- Hero Section with Image -->
  <div class="section-card text-center fade-in">
    <img src="https://www.moh.gov.sa/HealthAwareness/EducationalContent/PublicHealth/PublishingImages/PublicHealthPages017.PNG" alt="Blood Type Compatibility Infographic" class="hero-img mb-4">
    <h2 class="section-title">Give Blood, Save Lives</h2>
    <p class="lead" style="font-size: 1.2rem; color: #6c757d;">Your single donation can help save up to three lives. Join our community of heroes today!</p>
    <a href="donor-registration.php" class="btn btn-danger btn-custom mt-3">
      <i class="fas fa-heart me-2"></i>Become a Donor
    </a>
  </div>

  <!-- Blood Facts Section -->
  <div class="section-card fade-in">
    <h3 class="section-title text-center">Blood Facts</h3>
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="blood-fact">
          <i class="bi bi-droplet me-2"></i> Every 2 seconds, someone in the world needs blood.
        </div>
        <div class="blood-fact">
          <i class="bi bi-heart-pulse me-2"></i> One donation can help save up to 3 lives.
        </div>
        <div class="blood-fact">
          <i class="bi bi-people-fill me-2"></i> Less than 10% of eligible people donate blood yearly.
        </div>
      </div>
    </div>
  </div>

  <!-- Testimonial Carousel -->
  <div class="section-card fade-in">
    <h3 class="section-title text-center">What Our Donors Say</h3>
    <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">
        <div class="carousel-item active">
          <div class="testimonial mx-auto" style="max-width:600px;">
            <i class="bi bi-chat-quote-fill mb-2" style="font-size: 2rem; color: #dc3545;"></i>
            <p class="mb-1" style="font-size: 1.1rem; line-height: 1.6;">"Donating blood was easy and rewarding. I encourage everyone to try it!"</p>
            <div class="fw-bold mt-2" style="color: #dc3545;">– Maria, Donor</div>
          </div>
        </div>
        <div class="carousel-item">
          <div class="testimonial mx-auto" style="max-width:600px;">
            <i class="bi bi-chat-quote-fill mb-2" style="font-size: 2rem; color: #dc3545;"></i>
            <p class="mb-1" style="font-size: 1.1rem; line-height: 1.6;">"A single donation saved my father's life. Thank you to all donors!"</p>
            <div class="fw-bold mt-2" style="color: #dc3545;">– John, Recipient Family</div>
          </div>
        </div>
      </div>
      <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
      </button>
    </div>
  </div>

  <!-- Mini Gallery of Donors -->
  <div class="section-card text-center fade-in">
    <h3 class="section-title">Our Donor Community</h3>
    <div class="mini-gallery">
      <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Donor 1">
      <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Donor 2">
      <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="Donor 3">
      <img src="https://randomuser.me/api/portraits/men/76.jpg" alt="Donor 4">
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

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="row align-items-center mb-4">
        <div class="col-md-8 mb-2">
          <span style="font-size:2.2rem;vertical-align:middle;color:#d90429;">🩸</span>
          <span class="ms-2 fs-4 fw-bold text-danger">Ready to get started?</span>
          <a href="donor-registration.php" class="btn btn-danger btn-lg ms-3">Become a Donor</a>
        </div>
        <div class="col-md-4 text-md-end mb-2">
          <a href="donor-registration.php" class="btn btn-outline-light">Donate</a>
        </div>
      </div>
      <div class="row text-center text-md-start">
        <div class="col-md-3 mb-4">
          <h6 class="text-uppercase fw-bold mb-3">Contact</h6>
          <div class="mb-1"><i class="bi bi-telephone me-2"></i> <span>+63 912 345 6789</span></div>
          <div class="mb-1"><i class="bi bi-envelope me-2"></i> <span>info@redcrossbaguio.org</span></div>
          <div><i class="bi bi-geo-alt me-2"></i> <span>Baguio City, Philippines</span></div>
        </div>
        <div class="col-md-3 mb-4">
          <h6 class="text-uppercase fw-bold mb-3">Quick Links</h6>
          <a href="index.php" class="text-danger d-block mb-1">Home</a>
          <a class="nav-link" href="about.php">About Us</a>
          <a href="findus.html" class="text-danger d-block mb-1">Find Us</a>
          <a href="donor-registration.php" class="text-danger d-block mb-1">Donor Registration</a>
        </div>
        <div class="col-md-3 mb-4">
          <h6 class="text-uppercase fw-bold mb-3">Follow Us</h6>
          <div class="social-icons">
            <a href="#" class="me-3"><i class="bi bi-facebook"></i></a>
            <a href="#" class="me-3"><i class="bi bi-twitter"></i></a>
            <a href="#"><i class="bi bi-instagram"></i></a>
          </div>
        </div>
        <div class="col-md-3 mb-4">
          <h6 class="text-uppercase fw-bold mb-3">Why Donate?</h6>
          <div class="d-flex align-items-start gap-2">
            <i class="bi bi-droplet fs-2 text-danger"></i>
            <div class="text-start small">Donating blood helps save lives, supports local hospitals, and brings hope to families in need. Be a hero—give blood today!</div>
          </div>
        </div>
      </div>
      <div class="text-center mt-4" style="font-size:0.95rem;color:#888;">
        &copy; 2025 Blood Donation System. All rights reserved.
      </div>
    </div>
  </footer>
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
</script>
<?php if (AccessibilityHelper::getConfig()['enabled']): ?>
<script>
<?php echo AccessibilityHelper::generateJavaScript(); ?>
</script>
<?php endif; ?>
<!-- Removed duplicate IntersectionObserver script to avoid const redeclaration errors -->
</body>
</html>