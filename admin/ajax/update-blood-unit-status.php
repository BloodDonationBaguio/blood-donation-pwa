<?php
// AJAX endpoint to update blood unit status (PostgreSQL-compatible)
// Expects: POST { unit_id, status, reason }

header('Content-Type: application/json');

try {
    // Prefer production DB (PostgreSQL via DATABASE_URL), fallback to local
    if (file_exists(__DIR__ . '/../../db_production.php')) {
        require_once __DIR__ . '/../../db_production.php';
    } else {
        require_once __DIR__ . '/../../db.php';
    }

    // PG compatibility helpers
    if (file_exists(__DIR__ . '/../../pg_compat.php')) {
        require_once __DIR__ . '/../../pg_compat.php';
    }

    // Load Complete manager which supports unit_id-based updates and audit logging
    require_once __DIR__ . '/../../includes/BloodInventoryManagerComplete.php';

    $unitId = $_POST['unit_id'] ?? $_POST['id'] ?? null;
    $newStatus = $_POST['status'] ?? $_POST['new_status'] ?? null;
    $reason = $_POST['reason'] ?? '';

    if (!$unitId || !$newStatus) {
        echo json_encode(['success' => false, 'message' => 'Missing unit_id or status']);
        exit;
    }

    // Instantiate manager
    $manager = new BloodInventoryManagerComplete($pdo);
    $result = $manager->updateUnitStatus($unitId, $newStatus, $reason);

    // Return JSON
    echo json_encode($result);
    exit;
} catch (Throwable $e) {
    error_log('Error in update-blood-unit-status.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error updating status']);
    exit;
}
?>