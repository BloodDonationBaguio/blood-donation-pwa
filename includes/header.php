<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation - Philippine Red Cross Baguio Chapter</title>
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <script>
    // Unregister service worker if it exists
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for(let registration of registrations) {
                registration.unregister();
                console.log('ServiceWorker unregistered');
            }
        });
    }
    </script>
    <style>
        /* Inline critical CSS to avoid render-blocking */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f8f9fa; }
        .form-section { background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .section-title { color: #d9230f; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #f0f0f0; }
        .btn-donate { background-color: #d9230f; color: white; padding: 0.5rem 2rem; font-weight: 600; border: none; transition: all 0.3s ease; }
        .btn-donate:hover { background-color: #b51d0d; color: white; transform: translateY(-1px); }
        .is-invalid { border-color: #dc3545 !important; padding-right: calc(1.5em + 0.75rem); background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right calc(0.375em + 0.1875rem) center; background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem); }
        .invalid-feedback { display: none; width: 100%; margin-top: 0.25rem; font-size: 0.875em; color: #dc3545; }
        .was-validated .form-control:invalid ~ .invalid-feedback, .was-validated .form-select:invalid ~ .invalid-feedback, .form-control.is-invalid ~ .invalid-feedback, .form-select.is-invalid ~ .invalid-feedback { display: block; }
        .female-only { display: none; }
    </style>
    <!-- Defer non-critical CSS -->
    <link rel="preload" href="/blood-donation-pwa/assets/css/style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="/blood-donation-pwa/assets/css/style.css"></noscript>
    <style>
        .navbar-brand img {
            height: 40px;
            width: auto;
        }
        .btn-donate {
            background-color: #d9230f;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: 600;
        }
        .btn-donate:hover {
            background-color: #b51d0d;
            color: white;
        }
        .nav-link {
            font-weight: 500;
            color: #333;
            padding: 8px 15px;
        }
        .nav-link:hover, .nav-link.active {
            color: #d9230f;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="/blood-donation-pwa/index.php">
                <img src="https://benguetredcross.com/wp-content/uploads/2023/03/Logo_Philippine_Red_Cross-1536x1536.png" alt="Philippine Red Cross Logo" class="me-2">
                Blood Donation
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/blood-donation-pwa/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/blood-donation-pwa/about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/blood-donation-pwa/request.php">Request Blood</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/blood-donation-pwa/findus.php">Find Us</a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="nav-link btn btn-donate text-white px-3" href="/blood-donation-pwa/donor-registration.php">
                            Donate Now
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="py-4">
