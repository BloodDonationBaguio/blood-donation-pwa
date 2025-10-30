<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/db_production.php';
require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';

header('Content-Type: application/json');

function isAuthorized() {
    $loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
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

    $stmt1 = $pdo->prepare('SELECT unit_id, status, notes FROM blood_inventory WHERE unit_id = ?');
    $stmt1->execute([$unitId]);
    $before = $stmt1->fetch(PDO::FETCH_ASSOC) ?: null;

    $result = $manager->updateUnitStatus($unitId, $newStatus, $reason);

    $stmt2 = $pdo->prepare('SELECT unit_id, status, notes FROM blood_inventory WHERE unit_id = ?');
    $stmt2->execute([$unitId]);
    $after = $stmt2->fetch(PDO::FETCH_ASSOC) ?: null;

    echo json_encode([
        'success' => (bool)($result['success'] ?? false),
        'message' => $result['message'] ?? null,
        'before' => $before,
        'after' => $after
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>