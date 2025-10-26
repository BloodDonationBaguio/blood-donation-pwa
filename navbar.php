<?php
// Include session configuration first - before any output
require_once __DIR__ . '/includes/session_config.php';
require_once 'includes/session_manager.php';
require_once 'db.php';

// Check and restore session if needed
checkRememberMeToken();
$user_id = $_SESSION['user_id'] ?? null;
$notif_count = 0;
$notifications = [];
if ($user_id) {
    try {
        // Check if notifications table exists
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll();
        $notif_count = 0;
        foreach ($notifications as $n) {
            if (!$n['is_read']) $notif_count++;
        }
    } catch (PDOException $e) {
        // If notifications table doesn't exist, just continue without notifications
        $notifications = [];
        $notif_count = 0;
    }
}
?>

<nav class="main-navbar">
  <div class="nav-container">
    
    <!-- Brand -->
    <a href="index.php" class="nav-brand">
      <div class="brand-icon">
        <span>♥</span>
      </div>
      <span class="brand-text">Blood Donation</span>
    </a>
    
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
      <span></span>
      <span></span>
      <span></span>
    </button>
    
    <!-- Navigation Links -->
    <div class="nav-menu" id="navMenu">
      <?php 
      $current_page = basename($_SERVER['PHP_SELF']);
      $is_home = ($current_page == 'index.php');
      $is_about = ($current_page == 'about.php');
      $is_find_us = ($current_page == 'find-us.php');
      $is_track = ($current_page == 'track.php');
      ?>
      
      <a href="index.php" class="nav-link <?= $is_home ? 'active' : '' ?>">Home</a>
      <a href="about.php" class="nav-link <?= $is_about ? 'active' : '' ?>">About Us</a>
      <a href="find-us.php" class="nav-link <?= $is_find_us ? 'active' : '' ?>">Find Us</a>
      <a href="track.php" class="nav-link <?= $is_track ? 'active' : '' ?>">Track</a>
      
      <?php if ($user_id): ?>
        <div class="nav-dropdown">
          <a href="#" onclick="toggleDropdown(); return false;" class="nav-link user-link">
            <span class="user-icon">👤</span> 
            <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
          </a>
          <div id="userDropdown" class="dropdown-menu">
            <a href="dashboard.php" class="dropdown-item">Dashboard</a>
            <a href="profile.php" class="dropdown-item">Profile</a>
            <hr class="dropdown-divider">
            <a href="logout.php" class="dropdown-item text-danger">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="nav-link">Login</a>
        <a href="signup.php" class="nav-link-btn secondary">Sign Up</a>
      <?php endif; ?>
      
      <a href="donor-registration.php" class="nav-link-btn primary">
        <span>♥</span> Donate Now
      </a>
    </div>
  </div>
</nav>

<style>
/* ========================================
   RESPONSIVE NAVBAR STYLES
   Works on ALL devices: Mobile, Tablet, Desktop
======================================== */

/* Base navbar styles */
.main-navbar {
  background: white;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  padding: 15px 0;
}

.nav-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* Brand */
.nav-brand {
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 12px;
  z-index: 1001;
}

.brand-icon {
  background: #dc3545;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.brand-icon span {
  color: white;
  font-size: 20px;
  font-weight: bold;
}

.brand-text {
  color: #dc3545;
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -0.5px;
}

/* Mobile menu toggle (hidden on desktop) */
.mobile-menu-toggle {
  display: none;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 5px;
  z-index: 1001;
}

.mobile-menu-toggle span {
  display: block;
  width: 25px;
  height: 3px;
  background: #dc3545;
  border-radius: 3px;
  transition: all 0.3s ease;
}

.mobile-menu-toggle.active span:nth-child(1) {
  transform: rotate(45deg) translate(7px, 7px);
}

.mobile-menu-toggle.active span:nth-child(2) {
  opacity: 0;
}

.mobile-menu-toggle.active span:nth-child(3) {
  transform: rotate(-45deg) translate(7px, -7px);
}

/* Navigation menu */
.nav-menu {
  display: flex;
  align-items: center;
  gap: 25px;
}

.nav-link {
  color: #666;
  text-decoration: none;
  font-weight: 500;
  padding: 8px 0;
  transition: all 0.3s ease;
  position: relative;
  white-space: nowrap;
}

.nav-link:hover {
  color: #dc3545;
}

.nav-link.active {
  color: #dc3545;
  font-weight: 600;
  border-bottom: 2px solid #dc3545;
}

/* User dropdown */
.nav-dropdown {
  position: relative;
}

.user-link {
  padding: 8px 16px !important;
  background: #f8f9fa;
  border-radius: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-link:hover {
  background: #e9ecef;
}

.user-icon {
  color: #dc3545;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  padding: 8px 0;
  min-width: 160px;
  display: none;
  margin-top: 8px;
}

.dropdown-item {
  color: #333;
  text-decoration: none;
  padding: 10px 16px;
  display: block;
  transition: all 0.3s ease;
}

.dropdown-item:hover {
  background: #f8f9fa;
}

.dropdown-item.text-danger {
  color: #dc3545;
}

.dropdown-divider {
  margin: 8px 0;
  border-color: #e9ecef;
}

/* Button links */
.nav-link-btn {
  text-decoration: none;
  font-weight: 600;
  padding: 10px 20px;
  border-radius: 25px;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.nav-link-btn.secondary {
  color: #666;
  background: #f8f9fa;
}

.nav-link-btn.secondary:hover {
  background: #e9ecef;
}

.nav-link-btn.primary {
  background: #dc3545;
  color: white;
}

.nav-link-btn.primary:hover {
  background: #c82333;
  transform: translateY(-1px);
}

/* Body padding for fixed navbar */
body {
  padding-top: 80px !important;
}

/* ========================================
   RESPONSIVE BREAKPOINTS
======================================== */

/* Tablet and below (max-width: 1024px) */
@media (max-width: 1024px) {
  .brand-text {
    font-size: 20px;
  }
  
  .nav-menu {
    gap: 15px;
  }
  
  .nav-link-btn {
    padding: 8px 16px;
    font-size: 14px;
  }
}

/* Mobile (max-width: 768px) */
@media (max-width: 768px) {
  /* Show mobile menu toggle */
  .mobile-menu-toggle {
    display: flex;
  }
  
  /* Hide brand text on very small screens */
  .brand-text {
    font-size: 18px;
  }
  
  /* Mobile menu */
  .nav-menu {
    position: fixed;
    top: 70px;
    left: 0;
    right: 0;
    background: white;
    flex-direction: column;
    gap: 0;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
  }
  
  .nav-menu.active {
    max-height: calc(100vh - 70px);
    overflow-y: auto;
  }
  
  .nav-link {
    width: 100%;
    padding: 15px 10px;
    border-bottom: 1px solid #f0f0f0;
  }
  
  .nav-link.active {
    border-bottom: 1px solid #dc3545;
  }
  
  .nav-dropdown {
    width: 100%;
  }
  
  .user-link {
    width: 100%;
    justify-content: flex-start;
  }
  
  .dropdown-menu {
    position: static;
    box-shadow: none;
    margin: 0;
    padding-left: 20px;
  }
  
  .nav-link-btn {
    width: 100%;
    justify-content: center;
    margin-top: 10px;
  }
}

/* Small mobile (max-width: 480px) */
@media (max-width: 480px) {
  .nav-container {
    padding: 0 15px;
  }
  
  .brand-icon {
    width: 35px;
    height: 35px;
  }
  
  .brand-text {
    font-size: 16px;
  }
  
  .brand-icon span {
    font-size: 18px;
  }
  
  .main-navbar {
    padding: 12px 0;
  }
  
  body {
    padding-top: 65px !important;
  }
}

/* Large desktop (min-width: 1200px) */
@media (min-width: 1200px) {
  .nav-menu {
    gap: 30px;
  }
}
</style>

<script>
// Mobile menu toggle
function toggleMobileMenu() {
  const navMenu = document.getElementById('navMenu');
  const toggleBtn = document.querySelector('.mobile-menu-toggle');
  
  if (navMenu && toggleBtn) {
    navMenu.classList.toggle('active');
    toggleBtn.classList.toggle('active');
  }
}

// Dropdown functionality
function toggleDropdown() {
  const dropdown = document.getElementById('userDropdown');
  if (dropdown) {
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
  }
}

// Close mobile menu when clicking a link
document.addEventListener('DOMContentLoaded', function() {
  const navLinks = document.querySelectorAll('.nav-link, .nav-link-btn');
  const navMenu = document.getElementById('navMenu');
  const toggleBtn = document.querySelector('.mobile-menu-toggle');
  
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      // Don't close if it's the user dropdown link
      if (!this.classList.contains('user-link')) {
        if (navMenu && navMenu.classList.contains('active')) {
          navMenu.classList.remove('active');
          if (toggleBtn) toggleBtn.classList.remove('active');
        }
      }
    });
  });
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  // Close user dropdown
  if (!e.target.closest('[onclick*="toggleDropdown"]')) {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
      dropdown.style.display = 'none';
    }
  }
  
  // Close mobile menu when clicking outside
  if (!e.target.closest('.nav-menu') && !e.target.closest('.mobile-menu-toggle')) {
    const navMenu = document.getElementById('navMenu');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');
    if (navMenu && navMenu.classList.contains('active')) {
      navMenu.classList.remove('active');
      if (toggleBtn) toggleBtn.classList.remove('active');
    }
  }
});

// Close mobile menu on window resize to desktop size
window.addEventListener('resize', function() {
  if (window.innerWidth > 768) {
    const navMenu = document.getElementById('navMenu');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');
    if (navMenu && navMenu.classList.contains('active')) {
      navMenu.classList.remove('active');
      if (toggleBtn) toggleBtn.classList.remove('active');
    }
  }
});
</script>