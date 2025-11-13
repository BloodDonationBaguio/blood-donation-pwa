<?php
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/db.php';
    $driver = 'mysql';
    try { $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); } catch (Throwable $e) {}
    $yearExpr = function($col) use ($driver) {
        if ($driver === 'pgsql') { return "EXTRACT(YEAR FROM $col) = EXTRACT(YEAR FROM CURRENT_DATE)"; }
        return "YEAR($col) = YEAR(CURRENT_DATE)";
    };
    $table = null;
    if (function_exists('tableExists')) {
        try {
            if (tableExists($pdo, 'donors_new')) { $table = 'donors_new'; }
            elseif (tableExists($pdo, 'donors')) { $table = 'donors'; }
        } catch (Throwable $e) {}
    }
    $count = 0;
    if ($table) {
        $dateCol = ($table === 'donors_new') ? 'COALESCE(served_date, created_at)' : 'COALESCE(served_date, created_at)';
        $sql = "SELECT COUNT(*) AS cnt FROM $table WHERE status = 'served' AND " . $yearExpr($dateCol);
        $stmt = $pdo->query($sql);
        $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    } else {
        $col = 'donation_date';
        $sql = "SELECT COUNT(*) AS cnt FROM donations_new WHERE status = 'completed' AND " . $yearExpr($col);
        $stmt = $pdo->query($sql);
        $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }
    echo json_encode(['count' => $count]);
} catch (Throwable $e) {
    echo json_encode(['count' => 0]);
}
