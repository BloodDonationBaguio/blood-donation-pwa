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

<nav style="background: white; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 15px 0;">
  <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
    
    <!-- Brand -->
    <a href="index.php" style="text-decoration: none; display: flex; align-items: center;">
      <div style="background: #dc3545; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
        <span style="color: white; font-size: 20px; font-weight: bold;">♥</span>
      </div>
      <span style="color: #dc3545; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">Blood Donation</span>
    </a>
    
    <!-- Navigation Links -->
    <div style="display: flex; align-items: center; gap: 30px;">
      <?php 
      $current_page = basename($_SERVER['PHP_SELF']);
      $is_home = ($current_page == 'index.php');
      $is_about = ($current_page == 'about.php');
      $is_find_us = ($current_page == 'find-us.php');
      $is_track = ($current_page == 'track.php');
      ?>
      
      <a href="index.php" style="color: <?= $is_home ? '#dc3545' : '#666' ?>; text-decoration: none; font-weight: <?= $is_home ? '600' : '500' ?>; padding: 8px 0; <?= $is_home ? 'border-bottom: 2px solid #dc3545;' : '' ?> transition: all 0.3s ease;" onmouseover="this.style.color='#dc3545'" onmouseout="this.style.color='<?= $is_home ? '#dc3545' : '#666' ?>'">Home</a>
      
      <a href="about.php" style="color: <?= $is_about ? '#dc3545' : '#666' ?>; text-decoration: none; font-weight: <?= $is_about ? '600' : '500' ?>; padding: 8px 0; <?= $is_about ? 'border-bottom: 2px solid #dc3545;' : '' ?> transition: all 0.3s ease;" onmouseover="this.style.color='#dc3545'" onmouseout="this.style.color='<?= $is_about ? '#dc3545' : '#666' ?>'">About Us</a>
      
      <a href="find-us.php" style="color: <?= $is_find_us ? '#dc3545' : '#666' ?>; text-decoration: none; font-weight: <?= $is_find_us ? '600' : '500' ?>; padding: 8px 0; <?= $is_find_us ? 'border-bottom: 2px solid #dc3545;' : '' ?> transition: all 0.3s ease;" onmouseover="this.style.color='#dc3545'" onmouseout="this.style.color='<?= $is_find_us ? '#dc3545' : '#666' ?>'">Find Us</a>
      
      <a href="track.php" style="color: <?= $is_track ? '#dc3545' : '#666' ?>; text-decoration: none; font-weight: <?= $is_track ? '600' : '500' ?>; padding: 8px 0; <?= $is_track ? 'border-bottom: 2px solid #dc3545;' : '' ?> transition: all 0.3s ease;" onmouseover="this.style.color='#dc3545'" onmouseout="this.style.color='<?= $is_track ? '#dc3545' : '#666' ?>'">Track Application</a>
      
      <?php if ($user_id): ?>
        <div style="position: relative;">
          <a href="#" onclick="toggleDropdown(); return false;" style="color: #666; text-decoration: none; font-weight: 500; padding: 8px 16px; background: #f8f9fa; border-radius: 20px; display: flex; align-items: center; transition: all 0.3s ease;" onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='#f8f9fa'">
            <span style="color: #dc3545; margin-right: 8px;">👤</span> <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
          </a>
          <div id="userDropdown" style="position: absolute; top: 100%; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); padding: 8px 0; min-width: 160px; display: none; margin-top: 8px;">
            <a href="dashboard.php" style="color: #333; text-decoration: none; padding: 10px 16px; display: block; transition: all 0.3s ease;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">Dashboard</a>
            <a href="profile.php" style="color: #333; text-decoration: none; padding: 10px 16px; display: block; transition: all 0.3s ease;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">Profile</a>
            <hr style="margin: 8px 0; border-color: #e9ecef;">
            <a href="logout.php" style="color: #dc3545; text-decoration: none; padding: 10px 16px; display: block; transition: all 0.3s ease;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" style="color: #666; text-decoration: none; font-weight: 500; padding: 8px 0; transition: all 0.3s ease;" onmouseover="this.style.color='#dc3545'" onmouseout="this.style.color='#666'">Login</a>
        <a href="signup.php" style="color: #666; text-decoration: none; font-weight: 500; padding: 8px 16px; background: #f8f9fa; border-radius: 20px; transition: all 0.3s ease;" onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='#f8f9fa'">Sign Up</a>
      <?php endif; ?>
      
      <a href="donor-registration.php" style="background: #dc3545; color: white; text-decoration: none; font-weight: 600; padding: 12px 24px; border-radius: 25px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;" onmouseover="this.style.background='#c82333'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='#dc3545'; this.style.transform='translateY(0)'">
        <span>♥</span> Donate Now
      </a>
    </div>
  </div>
</nav>

<style>
/* Clean navbar styles */
body {
  padding-top: 80px !important;
}
</style>

<script>
// Dropdown functionality
function toggleDropdown() {
  const dropdown = document.getElementById('userDropdown');
  if (dropdown) {
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
  }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('[onclick*="toggleDropdown"]')) {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
      dropdown.style.display = 'none';
    }
  }
});
</script>