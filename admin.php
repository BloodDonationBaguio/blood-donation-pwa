<?php
// Load the full-featured admin dashboard from the restore bundle.
// This preserves all original functionality while keeping paths stable.
// If the restore file is missing, show a friendly error with guidance.

$restorePath = __DIR__ . '/../../__zip_restore/blood-donation-pwa/admin.php';
if (file_exists($restorePath)) {
    require_once $restorePath;
    return;
}

http_response_code(500);
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 6px;'>";
echo "<h2>Admin Dashboard Unavailable</h2>";
echo "<p>The restored admin dashboard source could not be found at:<br><code>" . htmlspecialchars($restorePath) . "</code></p>";
echo "<p>Please ensure the file exists, or copy the original admin.php from the restore bundle into this path.</p>";
echo "</div>";

