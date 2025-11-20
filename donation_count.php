<?php
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/db.php';

    $driver = 'mysql';
    try {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    } catch (Throwable $e) {}

    $yearExpr = function ($col) use ($driver) {
        if ($driver === 'pgsql') {
            return "EXTRACT(YEAR FROM $col) = EXTRACT(YEAR FROM CURRENT_DATE)";
        }
        return "YEAR($col) = YEAR(CURRENT_DATE)";
    };

    $count = 0;

    // 1) Primary metric: served donors this year from donors / donors_new
    try {
        $table = null;

        if (function_exists('tableExists')) {
            try {
                // Prefer main donors table first
                if (tableExists($pdo, 'donors')) {
                    $table = 'donors';
                } elseif (tableExists($pdo, 'donors_new')) {
                    $table = 'donors_new';
                }
            } catch (Throwable $e) {}
        }

        if ($table) {
            $dateCol = 'COALESCE(served_date, created_at)';
            $sql = "SELECT COUNT(*) AS cnt FROM {$table} WHERE status = 'served' AND " . $yearExpr($dateCol);
            $stmt = $pdo->query($sql);
            $count = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('donation_count: error counting served donors: ' . $e->getMessage());
    }

    // 2) Fallback: completed donations this year from donations_new
    if ($count === 0) {
        try {
            $hasDonationsNew = false;

            if (function_exists('tableExists')) {
                try {
                    $hasDonationsNew = tableExists($pdo, 'donations_new');
                } catch (Throwable $e) {}
            } else {
                if ($driver === 'pgsql') {
                    $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'donations_new')");
                    $hasDonationsNew = (bool) $stmt->fetchColumn();
                } else {
                    $stmt = $pdo->query("SHOW TABLES LIKE 'donations_new'");
                    $hasDonationsNew = $stmt->fetch() !== false;
                }
            }

            if ($hasDonationsNew) {
                $col = 'donation_date';
                $sql = "SELECT COUNT(*) AS cnt FROM donations_new WHERE status = 'completed' AND " . $yearExpr($col);
                $stmt = $pdo->query($sql);
                $count = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('donation_count: error counting donations_new: ' . $e->getMessage());
        }
    }

    echo json_encode(['count' => $count]);
} catch (Throwable $e) {
    error_log('donation_count: fatal error: ' . $e->getMessage());
    echo json_encode(['count' => 0]);
}
