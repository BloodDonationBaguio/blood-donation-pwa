<?php
// Load the full-featured admin dashboard from the restore bundle.
// Try multiple candidate locations to handle different deployments.

$docroot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : null;
$candidates = [];

// Common project-root candidate relative to this file (three levels up)
$candidates[] = __DIR__ . '/../../../__zip_restore/blood-donation-pwa/admin.php';
// Two-level up (some environments may place the legacy folder directly under root)
$candidates[] = __DIR__ . '/../../__zip_restore/blood-donation-pwa/admin.php';
// Directly under document root if available
if ($docroot) {
    $candidates[] = $docroot . '/__zip_restore/blood-donation-pwa/admin.php';
}

$chosen = null;
foreach ($candidates as $path) {
    $real = realpath($path) ?: $path; // fall back to raw path if realpath fails
    if (file_exists($real)) {
        $chosen = $real;
        break;
    }
}

if ($chosen) {
    require_once $chosen;
    return;
}

// Graceful fallback: if restore bundle is missing, redirect to root admin
if ($docroot) {
    $rootAdminIndex = '/admin/index.php';
    $rootAdminPhp   = '/admin.php';
    // Prefer index.php if it exists under the document root
    if (file_exists($docroot . $rootAdminIndex)) {
        header('Location: ' . $rootAdminIndex, true, 302);
        exit;
    }
    if (file_exists($docroot . $rootAdminPhp)) {
        header('Location: ' . $rootAdminPhp, true, 302);
        exit;
    }
}

http_response_code(500);
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 6px;'>";
echo "<h2>Admin Dashboard Unavailable</h2>";
echo "<p>The restored admin dashboard source could not be located. Tried:</p><ul>";
foreach ($candidates as $c) {
    echo "<li><code>" . htmlspecialchars($c) . "</code></li>";
}
echo "</ul>";
echo "<p>Please ensure the file exists in one of the locations above, or copy the original admin.php from the restore bundle.</p>";
echo "</div>";

