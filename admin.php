<?php
// Thin wrapper that delegates to the full-featured admin dashboard
// preserved in blood-donation-pwa/admin.php.
// That file already handles authentication, layout, and analytics,
// and uses its own db.php which we have updated to be Supabase-aware.

require_once __DIR__ . '/blood-donation-pwa/admin.php';
exit;
