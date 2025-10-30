<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/db_production.php';
require_once __DIR__ . '/includes/BloodInventoryManagerComplete.php';

function isAuthorized() {
    $loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $tokenEnv = getenv('ADMIN_TEST_TOKEN');
    $tokenReq = $_GET['token'] ?? ($_POST['token'] ?? ($_SERVER['HTTP_X_ADMIN_TEST_TOKEN'] ?? null));
    $tokenOk = $tokenEnv && $tokenReq && hash_equals($tokenEnv, $tokenReq);
    return $loggedIn || $tokenOk;
}

if (!isAuthorized()) {
    http_response_code(401);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Admin Update Status Test</title>';
    echo '<style>body{font-family:system-ui,Arial,sans-serif;padding:24px;background:#0b0b10;color:#e6e6e6}a{color:#9ad}input,select,textarea{width:100%;padding:8px;margin:6px 0;background:#141420;color:#e6e6e6;border:1px solid #2a2a3a;border-radius:6px}button{padding:10px 14px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer}button:hover{background:#1d4ed8}.card{background:#12121b;border:1px solid #222437;border-radius:10px;padding:18px;margin-top:18px}</style></head><body>';
    echo '<h1>Admin Update Status Test</h1>';
    echo '<div class="card"><p>Unauthorized. Please log in as admin or provide the correct token.</p>';
    echo '<p>Login: <a href="admin-login-working.php">admin-login-working.php</a></p>';
    echo '<p>Or set env <code>ADMIN_TEST_TOKEN</code> and pass it via query <code>?token=...</code> or header <code>X-Admin-Test-Token</code>.</p></div>';
    echo '</body></html>';
    exit;
}

$result = null;
$before = null;
$after = null;
$error = null;

try {
    $manager = new BloodInventoryManagerComplete($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $unitId = trim($_POST['unit_id'] ?? '');
        $newStatus = trim($_POST['status'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($unitId === '' || $newStatus === '') {
            throw new Exception('unit_id and status are required');
        }

        $stmt1 = $pdo->prepare('SELECT unit_id, status, notes FROM blood_inventory WHERE unit_id = ?');
        $stmt1->execute([$unitId]);
        $before = $stmt1->fetch(PDO::FETCH_ASSOC) ?: null;

        $result = $manager->updateUnitStatus($unitId, $newStatus, $reason);

        $stmt2 = $pdo->prepare('SELECT unit_id, status, notes FROM blood_inventory WHERE unit_id = ?');
        $stmt2->execute([$unitId]);
        $after = $stmt2->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Admin Update Status Test</title>';
echo '<style>body{font-family:system-ui,Arial,sans-serif;padding:24px;background:#0b0b10;color:#e6e6e6}a{color:#9ad}input,select,textarea{width:100%;padding:8px;margin:6px 0;background:#141420;color:#e6e6e6;border:1px solid #2a2a3a;border-radius:6px}.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}.card{background:#12121b;border:1px solid #222437;border-radius:10px;padding:18px;margin-top:18px}button{padding:10px 14px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer}button:hover{background:#1d4ed8}pre{background:#0f0f17;border:1px solid #222437;border-radius:8px;padding:12px;overflow:auto}</style></head><body>';
echo '<h1>Admin Update Status Test</h1>';

echo '<div class="card">';
echo '<form method="post" action="">
        <label>Unit ID</label>
        <input type="text" name="unit_id" placeholder="e.g., TEST-110-2" required>
        <label>New Status</label>
        <select name="status" required>
            <option value="available">available</option>
            <option value="reserved">reserved</option>
            <option value="used">used</option>
            <option value="expired">expired</option>
            <option value="quarantined">quarantined</option>
            <option value="discarded">discarded</option>
        </select>
        <label>Reason / Notes (optional)</label>
        <textarea name="reason" rows="3" placeholder="Reason for status change..."></textarea>
        <button type="submit">Run Update</button>
      </form>';
echo '</div>';

echo '<div class="row">';
echo '<div class="card"><h3>Before</h3>';
echo '<pre>' . htmlspecialchars(json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';

echo '<div class="card"><h3>After</h3>';
echo '<pre>' . htmlspecialchars(json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
echo '</div>';

echo '<div class="card"><h3>Result</h3>';
echo '<pre>' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
if ($error) {
    echo '<p style="color:#f99">Error: ' . htmlspecialchars($error) . '</p>';
}
echo '</div>';

echo '</body></html>';
?>