<?php
// Centralize admin DB includes to the main app connection
// This reuses env-aware connection (PostgreSQL/MySQL/SQLite) and shared helpers
require_once dirname(__DIR__, 2) . '/includes/db.php';

// Robust helpers that tolerate table name differences across environments
if (!function_exists('getDonorCount')) {
    function getDonorCount(): int {
        global $pdo;
        try {
            $donorTable = tableExists($pdo, 'donors') ? 'donors' : (tableExists($pdo, 'donors_new') ? 'donors_new' : null);
            if (!$donorTable) return 0;
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$donorTable}");
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('getDonorCount failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('getPendingRequestCount')) {
    function getPendingRequestCount(): int {
        global $pdo;
        try {
            $requestsTable = tableExists($pdo, 'blood_requests') ? 'blood_requests' : (tableExists($pdo, 'requests') ? 'requests' : null);
            if (!$requestsTable) return 0;
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$requestsTable} WHERE status = 'pending'");
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('getPendingRequestCount failed: ' . $e->getMessage());
            return 0;
        }
    }
}
?>
