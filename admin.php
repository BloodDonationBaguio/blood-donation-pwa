<?php
// Canonical redirect to the legacy admin dashboard
header('Location: /legacy-pwa-4/blood-donation-pwa/admin.php', true, 302);
exit;

// Fallback content if headers already sent (should not happen)
?><!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="0;url=/legacy-pwa-4/blood-donation-pwa/admin.php"></head>
<body>
<p>Redirecting to <a href="/legacy-pwa-4/blood-donation-pwa/admin.php">Legacy Admin Dashboard</a>...</p>
</body>
</html>
