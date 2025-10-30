<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db_production.php';
require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';
require_once __DIR__ . '/includes/admin_auth.php';

header('Content-Type: application/json');

function isAuthorized() {
    // Use the existing admin auth system
    $loggedIn = isAdminLoggedIn();
    
    // Also allow token-based access for testing
    $tokenEnv = getenv('ADMIN_TEST_TOKEN');
    $tokenReq = $_GET['token'] ?? ($_POST['token'] ?? ($_SERVER['HTTP_X_ADMIN_TEST_TOKEN'] ?? null));
    $tokenOk = $tokenEnv && $tokenReq && hash_equals($tokenEnv, $tokenReq);
    
    return $loggedIn || $tokenOk;
}

if (!isAuthorized()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $unitId = trim($_POST['unit_id'] ?? ($_GET['unit_id'] ?? ''));
    $newStatus = trim($_POST['status'] ?? ($_GET['status'] ?? ''));
    $reason = trim($_POST['reason'] ?? ($_GET['reason'] ?? ''));

    if ($unitId === '' || $newStatus === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'unit_id and status are required']);
        exit;
    }

    $manager = new BloodInventoryManagerComplete($pdo);

    if (!$pdo) {
        throw new RuntimeException('Database connection not available');
    }
    $stmt1 = $pdo->prepare('SELECT unit_id, status, notes FROM blood_inventory WHERE unit_id = ?');
    $stmt1->execute([$unitId]);
    $before = $stmt1->fetch(PDO::FETCH_ASSOC) ?: null;

    $result = $manager->updateUnitStatus($unitId, $newStatus, $reason);

    // Query using original unit_id
    $stmt2 = $pdo->prepare('SELECT unit_id, status, notes FROM blood_inventory WHERE unit_id = ?');
    $stmt2->execute([$unitId]);
    $after = $stmt2->fetch(PDO::FETCH_ASSOC) ?: null;

    // If the unit was promoted (virtual -> real), also fetch by final unit id
    $finalUnitId = $result['final_unit_id'] ?? $unitId;
    $afterFinal = null;
    if ($finalUnitId && $finalUnitId !== $unitId) {
        $stmt3 = $pdo->prepare('SELECT unit_id, status, notes FROM blood_inventory WHERE unit_id = ?');
        $stmt3->execute([$finalUnitId]);
        $afterFinal = $stmt3->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode([
        'success' => (bool)($result['success'] ?? false),
        'message' => $result['message'] ?? null,
        'before' => $before,
        'after' => $after,
        'final_unit_id' => $finalUnitId,
        'promoted' => (bool)($result['promoted'] ?? false),
        'after_final' => $afterFinal
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>