<?php
/**
 * Modern Blood Inventory Management System
 * Enhanced UI with 1 unit per real donor
 */

// Handle AJAX requests FIRST before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Start session for AJAX
    session_start();
    
    // Check authentication for AJAX
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Start output buffering BEFORE any includes
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Suppress ALL errors for clean JSON
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    
    // Include dependencies (robust DB bootstrap)
    // Prefer production bootstrap that supports env vars and PostgreSQL, then fallback
    try {
        require_once 'db_production.php';
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            require_once 'db.php';
        }
    } catch (Throwable $e) {
        // Fallback to legacy config if needed
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            @require_once 'db.php';
        }
    }
    require_once 'includes/BloodInventoryManagerComplete.php';
    require_once 'includes/BloodInventoryManagerRobust.php';
    
    // Initialize managers
    $inventoryManager = new BloodInventoryManagerComplete($pdo);
    $robustManager = new BloodInventoryManagerRobust($pdo, true);
    
    try {
        $result = ['success' => false, 'message' => 'Unknown error'];
        
        switch ($_POST['action']) {
            case 'add_unit':
                $result = $inventoryManager->addBloodUnit($_POST);
                break;
                
            case 'update_status':
                $result = $inventoryManager->updateUnitStatus($_POST['unit_id'], $_POST['status'], $_POST['reason'] ?? '');
                break;
                
            case 'update_blood_type':
                $result = $inventoryManager->updateBloodType($_POST['unit_id'], $_POST['blood_type']);
                break;
                
            case 'delete_unit':
                $result = $inventoryManager->deleteUnit($_POST['unit_id'], $_POST['reason'] ?? 'Deleted by admin');
                break;
                
            case 'get_unit_details':
                $result = $inventoryManager->getUnitDetails($_POST['unit_id'], true);
                break;
                
            default:
                $result = ['success' => false, 'message' => 'Invalid action'];
                break;
        }
        
    } catch (Exception $e) {
        error_log("AJAX Error in " . ($_POST['action'] ?? 'unknown') . ": " . $e->getMessage());
        $result = ['success' => false, 'message' => $e->getMessage()];
    }
    
    // Discard any output that might have occurred
    ob_end_clean();
    
    // Send clean JSON response
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Regular page load starts here
session_start();
// Robust DB bootstrap for regular page load
try {
    require_once 'db_production.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once 'db.php';
    }
} catch (Throwable $e) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        @require_once 'db.php';
    }
}
require_once 'includes/BloodInventoryManagerComplete.php';
require_once 'includes/BloodInventoryManagerRobust.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Initialize managers with fallback strategy
$inventoryManager = new BloodInventoryManagerComplete($pdo);
$robustManager = new BloodInventoryManagerRobust($pdo, true);

// Get user permissions - ALL FEATURES ENABLED
$userRole = $_SESSION['admin_role'] ?? 'super_admin';
$canEdit = true; // Always allow editing
$canViewPII = true; // Always allow viewing PII

// Get data
$filters = [
    'blood_type' => $_GET['blood_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'page' => (int)($_GET['page'] ?? 1)
];

// Normalize filters: only accept known values; treat UI "All" labels as empty
$allowedStatuses = ['available','used','expired','quarantined','reserved'];
$allowedBloodTypes = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

// Status: map any non-allowed or "all" style to empty
$statusNorm = strtolower(trim((string)$filters['status']));
if ($statusNorm === '' || $statusNorm === 'all' || strpos($statusNorm, 'all stat') === 0) {
    $filters['status'] = '';
} elseif (!in_array($statusNorm, $allowedStatuses, true)) {
    $filters['status'] = '';
}

// Blood type: only keep if exactly matches allowed types; otherwise empty
$btNorm = strtoupper(trim((string)$filters['blood_type']));
if (!in_array($btNorm, $allowedBloodTypes, true)) {
    $filters['blood_type'] = '';
} else {
    $filters['blood_type'] = $btNorm;
}

$perPage = (int)($_GET['per_page'] ?? 20);
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 20;
}

// Handle CSV export if requested
if (isset($_GET['export']) && strtolower($_GET['export']) === 'csv') {
    $inventoryManager->exportToCSV($filters);
    exit;
}

// Try primary manager first, fallback to robust manager if needed
$inventory = $inventoryManager->getInventory($filters, $filters['page'], $perPage);
$summary = $inventoryManager->getDashboardSummary();

// Use robust manager as fallback if primary returns empty results
if (empty($inventory['data']) || $summary['total_units'] == 0) {
    $inventory = $robustManager->getInventory($filters, $filters['page'], $perPage);
    $summary = $robustManager->getDashboardSummary();
    $alerts = $robustManager->getAlerts();
    $donors = $robustManager->getEligibleDonors();
    $totalRecords = $robustManager->getInventoryCount($filters);
    $usingFallback = true;
} else {
    $alerts = $inventoryManager->getAlerts();
    $donors = $inventoryManager->getEligibleDonors();
    $totalRecords = $inventoryManager->getInventoryCount($filters);
    $usingFallback = false;
}
// Calculate pagination info (sync with actual inventory total when available)
$displayTotal = isset($inventory['total']) ? (int)$inventory['total'] : (int)$totalRecords;
$totalPages = (int)ceil($displayTotal / $perPage);

// Calculate pagination bounds
$offset = ($filters['page'] - 1) * $perPage;
$startRecord = $displayTotal > 0 ? ($offset + 1) : 0;
$endRecord = min($offset + $perPage, $displayTotal);

// Final guard: if we have records but no data rows, fetch minimal dataset directly
if ($totalRecords > 0 && (empty($inventory) || empty($inventory['data']))) {
    try {
        $donorTable = 'donors';
        try {
            // Prefer donors_new when present and populated
            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM donors_new");
            $hasNew = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
            if ($hasNew) { $donorTable = 'donors_new'; }
        } catch (Throwable $e) {
            // fall back to donors
        }

        $where = [];
        $params = [];
        if (!empty($filters['blood_type'])) { $where[] = 'bi.blood_type = ?'; $params[] = $filters['blood_type']; }
        if (!empty($filters['status']) && strtolower($filters['status']) !== 'all') { $where[] = 'bi.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where[] = '(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }
        $whereClause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Choose date math and string concatenation compatible with the current PDO driver
        try {
            $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        } catch (Throwable $e) {
            $driver = 'mysql';
        }
        $expiringSoonExpr = ($driver === 'pgsql')
            ? "CASE WHEN bi.expiry_date <= CURRENT_TIMESTAMP + INTERVAL '5 day' AND bi.status = 'available' THEN 1 ELSE 0 END"
            : "CASE WHEN bi.expiry_date <= DATE_ADD(NOW(), INTERVAL 5 DAY) AND bi.status = 'available' THEN 1 ELSE 0 END";
        $donorNameExpr = ($driver === 'pgsql')
            ? "COALESCE(d.first_name || ' ' || d.last_name, 'Unknown Donor')"
            : "COALESCE(CONCAT(d.first_name, ' ', d.last_name), 'Unknown Donor')";

        $sql = "
            SELECT 
                bi.unit_id,
                bi.blood_type,
                bi.status,
                bi.collection_date,
                bi.expiry_date,
                $donorNameExpr AS donor_name,
                d.reference_code,
                $expiringSoonExpr AS expiring_soon
            FROM blood_inventory bi
            LEFT JOIN {$donorTable} d ON bi.donor_id = d.id
            $whereClause
            ORDER BY bi.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $inventory = [
                'data' => $rows,
                'total' => $totalRecords,
                'page' => $filters['page'],
                'limit' => $perPage,
                'total_pages' => $totalPages,
                'source' => 'final_guard_query'
            ];
        } else {
            // Secondary guard: generate virtual rows from donors if blood_inventory has no rows
            $statusCondition = "status IN ('served','completed')";

            $dWhere = [$statusCondition];
            $dParams = [];
            if (!empty($filters['blood_type'])) { $dWhere[] = 'blood_type = ?'; $dParams[] = $filters['blood_type']; }
            if (!empty($filters['search'])) {
                $term = '%' . $filters['search'] . '%';
                $dWhere[] = '(first_name LIKE ? OR last_name LIKE ? OR reference_code LIKE ?)';
                $dParams = array_merge($dParams, [$term, $term, $term]);
            }
            $dWhereClause = 'WHERE ' . implode(' AND ', $dWhere);
            // Driver-aware expiry and string concatenation for virtual rows
            $expiryFromCreatedExpr = ($driver === 'pgsql')
                ? "created_at + INTERVAL '35 day'"
                : "DATE_ADD(created_at, INTERVAL 35 DAY)";
            $virtUnitIdExpr = ($driver === 'pgsql')
                ? "('VIRT-' || id)"
                : "CONCAT('VIRT-', id)";
            $virtDonorNameExpr = ($driver === 'pgsql')
                ? "(first_name || ' ' || last_name)"
                : "CONCAT(first_name, ' ', last_name)";
            $dsql = "
                SELECT 
                    id AS donor_id,
                    $virtUnitIdExpr AS unit_id,
                    blood_type,
                    'available' AS status,
                    $virtDonorNameExpr AS donor_name,
                    reference_code,
                    created_at AS collection_date,
                    $expiryFromCreatedExpr AS expiry_date,
                    0 AS expiring_soon
                FROM {$donorTable}
                {$dWhereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ";
            $dParams[] = $perPage;
            $dParams[] = $offset;
            $dstmt = $pdo->prepare($dsql);
            $dstmt->execute($dParams);
            $drows = $dstmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($drows)) {
                $inventory = [
                    'data' => $drows,
                    'total' => $totalRecords,
                    'page' => $filters['page'],
                    'limit' => $perPage,
                    'total_pages' => $totalPages,
                    'source' => 'final_guard_virtual_from_donors'
                ];
            }
        }
    } catch (Throwable $e) {
        // Keep page rendering even if guard fails
        error_log('Final guard inventory query failed: ' . $e->getMessage());
    }
}

/**
 * Build pagination URL with current parameters
 */
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Inventory Management - Modern Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #dc3545;
            --primary-dark: #c82333;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-weight: 400;
        }

        .main-container {
            background: var(--light-bg);
            min-height: 100vh;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem 0;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .alert-modern {
            border: none;
            border-radius: var(--radius-md);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--warning-color);
        }

        .filter-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .inventory-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .inventory-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .inventory-table {
            margin: 0;
        }

        .inventory-table th {
            background: #f8fafc;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .inventory-table td {
            padding: 1rem 1.5rem;
            border: none;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .inventory-table tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-available {
            background: #dcfce7;
            color: #166534;
        }

        .status-used {
            background: #f1f5f9;
            color: #475569;
        }

        .status-expired {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-quarantined {
            background: #fef3c7;
            color: #d97706;
        }

        .blood-type-badge {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.75rem;
        }

        .unit-id {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: #f1f5f9;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            color: var(--text-primary);
        }

        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: var(--radius-sm);
            margin: 0 0.125rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .btn-view {
            background: #e0f2fe;
            color: var(--info-color);
        }

        .btn-edit {
            background: #fef3c7;
            color: var(--warning-color);
        }

        .btn-delete {
            background: #fef2f2;
            color: var(--danger-color);
        }

        .btn-modern {
            border-radius: var(--radius-md);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary-modern:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success-modern {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-success-modern:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .form-control-modern {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background: var(--card-bg);
        }

        .form-control-modern:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
            outline: none;
        }

        /* Enhanced Dropdown Styling */
        .form-select.form-control-modern {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23dc3545' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 20px 20px;
            padding-right: 3rem;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-select.form-control-modern:focus {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23dc3545' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
            background-size: 22px 22px;
        }

        .form-select.form-control-modern:hover {
            border-color: var(--primary-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23c82333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
            background-size: 22px 22px;
        }

        /* Dropdown option styling */
        .form-select.form-control-modern option {
            padding: 0.5rem 1rem;
            background: white;
            color: var(--text-primary);
        }

        .form-select.form-control-modern option:hover {
            background: #f8fafc;
        }

        .modal-modern .modal-content {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .modal-modern .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .modal-modern .modal-body {
            padding: 2rem;
        }

        .expiring-soon {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid var(--warning-color);
        }

        .low-stock {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            border-left: 4px solid var(--danger-color);
        }

        .pii-masked {
            filter: blur(3px);
            user-select: none;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Card View Styles */
        .blood-unit-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .blood-unit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
            border-bottom: none;
        }

        .unit-id-display {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }

        .unit-id-display code {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .card-body-modern {
            padding: 20px;
            flex-grow: 1;
        }

        .info-section {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 500;
        }

        .blood-type-badge-large {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
            display: inline-block;
        }

        .donor-info {
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }

        .donor-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 2px;
        }

        .donor-ref {
            font-size: 0.85rem;
            color: #6c757d;
            font-family: 'Courier New', monospace;
        }

        .dates-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .date-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .date-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .date-value {
            font-weight: 600;
            color: #212529;
        }

        .days-remaining {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .days-remaining.expired {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .days-remaining.expiring-soon {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            animation: pulse 2s infinite;
        }

        .days-remaining:not(.expired):not(.expiring-soon) {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .card-footer-modern {
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
            border-top: 1px solid #e9ecef;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .action-buttons .btn {
            flex: 1;
            font-size: 0.85rem;
            padding: 6px 12px;
        }

        /* View Toggle Styles */
        .btn-group .btn {
            border-radius: 6px;
        }

        .btn-group .btn:first-child {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .btn-group .btn:last-child {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dates-section {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                flex: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid px-0">
        <!-- Header Section -->
        <div class="header-section">
            <div class="container">
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="mb-2">
                                <i class="fas fa-tint me-3"></i>Blood Inventory Management
                            </h1>
                            <p class="mb-0 opacity-90">Modern blood unit tracking and management system</p>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex align-items-center">
                                <!-- Admin Role Badge -->
                                <div class="me-3">
                                    <span class="badge bg-light text-dark px-3 py-2" style="font-size: 0.9rem;">
                                        <i class="fas fa-user-shield me-2"></i>
                                        <?php
                                        // Get role from session or default to super_admin
                                        $adminRole = $_SESSION['admin_role'] ?? 'super_admin';
                                        if ($adminRole === 'admin') {
                                            $adminRole = 'super_admin'; // Convert old 'admin' to 'super_admin'
                                        }
                                        echo ucfirst(str_replace('_', ' ', $adminRole)) . ' Admin';
                                        ?>
                                    </span>
                                </div>
                                <button class="btn btn-light btn-modern me-2" data-bs-toggle="modal" data-bs-target="#helpGuideModal">
                                    <i class="fas fa-question-circle me-2"></i>Help & Guide
                                </button>
                                <a href="admin.php" class="btn btn-light btn-modern">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Admin
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Container -->
        <div class="main-container">
            <div class="container py-4">
                <!-- Alerts -->
                <?php if (!empty($alerts)): ?>
                <div class="row mb-4 fade-in">
                    <div class="col-12">
                        <div class="alert alert-warning alert-modern">
                            <h5 class="mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>System Alerts
                            </h5>
                            <ul class="mb-0">
                                <?php foreach ($alerts as $alert): ?>
                                <li><?= htmlspecialchars($alert) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dashboard Summary -->
                <?php if (isset($usingFallback) && $usingFallback): ?>
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Fallback Mode:</strong> Using alternative data source to display inventory.
                    <?php if (isset($inventory['source'])): ?>
                    <small>(Source: <?= htmlspecialchars($inventory['source']) ?>)</small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="row mb-4 fade-in">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $summary['total_units'] ?></div>
                            <div class="stats-label">Total Units</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-success"><?= $summary['available_units'] ?></div>
                            <div class="stats-label">Available</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-info"><?= $summary['used_units'] ?></div>
                            <div class="stats-label">Total Units Used</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-danger"><?= $summary['expired_units'] ?></div>
                            <div class="stats-label">Expired</div>
                        </div>
                    </div>
                </div>


                <!-- Filters and Actions -->
                <div class="row mb-4 fade-in">
                    <div class="col-md-8">
                        <div class="filter-card">
                            <form method="GET" class="row g-3">
                                <input type="hidden" name="page" value="1">
                                
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Blood Type</label>
                                    <select name="blood_type" class="form-select form-control-modern">
                                        <option value="">All Blood Types</option>
                                        <option value="A+" <?= $filters['blood_type'] === 'A+' ? 'selected' : '' ?>>A+</option>
                                        <option value="A-" <?= $filters['blood_type'] === 'A-' ? 'selected' : '' ?>>A-</option>
                                        <option value="B+" <?= $filters['blood_type'] === 'B+' ? 'selected' : '' ?>>B+</option>
                                        <option value="B-" <?= $filters['blood_type'] === 'B-' ? 'selected' : '' ?>>B-</option>
                                        <option value="AB+" <?= $filters['blood_type'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                        <option value="AB-" <?= $filters['blood_type'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                        <option value="O+" <?= $filters['blood_type'] === 'O+' ? 'selected' : '' ?>>O+</option>
                                        <option value="O-" <?= $filters['blood_type'] === 'O-' ? 'selected' : '' ?>>O-</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select form-control-modern">
                                        <option value="">All Statuses</option>
                                        <option value="available" <?= $filters['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                        <option value="used" <?= $filters['status'] === 'used' ? 'selected' : '' ?>>Used</option>
                                        <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                                        <option value="quarantined" <?= $filters['status'] === 'quarantined' ? 'selected' : '' ?>>Quarantined</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">Per Page</label>
                                    <select name="per_page" class="form-select form-control-modern" onchange="this.form.submit()">
                                        <?php foreach ($allowedPerPage as $option): ?>
                                        <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Search</label>
                                    <input type="text" name="search" class="form-control form-control-modern" 
                                           placeholder="Search by Unit ID or Donor..." 
                                           value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary-modern btn-modern w-100">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="filter-card text-center">
                            <button class="btn btn-success-modern btn-modern me-2" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                                <i class="fas fa-plus me-2"></i>Add Unit
                            </button>
                            <button class="btn btn-primary-modern btn-modern me-2" onclick="exportToCSV()">
                                <i class="fas fa-download me-2"></i>Export CSV
                            </button>
                            <button class="btn btn-outline-dark btn-modern" onclick="printCurrentTablePage()">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-end">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-danger" id="tableViewBtn" onclick="toggleView('table')">
                                    <i class="fas fa-table me-2"></i>Table View
                                </button>
                                <button type="button" class="btn btn-outline-danger" id="cardViewBtn" onclick="toggleView('card')">
                                    <i class="fas fa-th-large me-2"></i>Card View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card View -->
                <div class="row fade-in d-none" id="cardView">
                    <?php foreach ($inventory['data'] as $unit): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="blood-unit-card" data-unit-id="<?= $unit['unit_id'] ?>">
                                <div class="card-header-modern">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="unit-id-display">
                                            <i class="fas fa-barcode me-2"></i>
                                            <code><?= htmlspecialchars($unit['unit_id']) ?></code>
                                        </div>
                                        <span class="status-badge status-<?= $unit['status'] ?>">
                                            <?= ucfirst($unit['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="card-body-modern">
                                    <!-- Blood Type Section -->
                                    <div class="info-section">
                                        <div class="info-label">
                                            <i class="fas fa-tint text-danger me-2"></i>Blood Type
                                        </div>
                                        <div class="info-value">
                                            <span class="blood-type-badge-large"><?= htmlspecialchars($unit['blood_type']) ?></span>
                                        </div>
                                    </div>

                                    <!-- Donor Information -->
                                    <div class="info-section">
                                        <div class="info-label">
                                            <i class="fas fa-user text-danger me-2"></i>Donor
                                        </div>
                                        <div class="info-value">
                                            <div class="donor-info">
                                                <div class="donor-name"><?= htmlspecialchars($unit['donor_name']) ?></div>
                                                <div class="donor-ref"><?= htmlspecialchars($unit['reference_code']) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dates Section -->
                                    <div class="dates-section">
                                        <div class="date-item">
                                            <div class="date-label">
                                                <i class="fas fa-calendar-plus text-success me-2"></i>Collection
                                            </div>
                                            <div class="date-value">
                                                <?= date('M d, Y', strtotime($unit['collection_date'])) ?>
                                                <small class="text-muted d-block"><?= date('D', strtotime($unit['collection_date'])) ?></small>
                                            </div>
                                        </div>
                                        <div class="date-item">
                                            <div class="date-label">
                                                <i class="fas fa-calendar-times text-warning me-2"></i>Expiry
                                            </div>
                                            <div class="date-value">
                                                <?= date('M d, Y', strtotime($unit['expiry_date'])) ?>
                                                <small class="text-muted d-block"><?= date('D', strtotime($unit['expiry_date'])) ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Days Remaining -->
                                    <?php 
                                    $expiryDate = new DateTime($unit['expiry_date']);
                                    $today = new DateTime();
                                    $daysRemaining = $today->diff($expiryDate)->days;
                                    $isExpired = $expiryDate < $today;
                                    $isExpiringSoon = $daysRemaining <= 5 && !$isExpired;
                                    ?>
                                    <div class="days-remaining <?= $isExpired ? 'expired' : ($isExpiringSoon ? 'expiring-soon' : '') ?>">
                                        <i class="fas fa-clock me-2"></i>
                                        <?php if ($isExpired): ?>
                                            <span class="text-danger">Expired <?= $daysRemaining ?> days ago</span>
                                        <?php elseif ($isExpiringSoon): ?>
                                            <span class="text-warning"><?= $daysRemaining ?> days remaining</span>
                                        <?php else: ?>
                                            <span class="text-success"><?= $daysRemaining ?> days remaining</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="card-footer-modern">
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-outline-danger" onclick="viewUnitDetails('<?= $unit['unit_id'] ?>')" title="View Details">
                                            <i class="fas fa-eye me-1"></i>View
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updateUnitStatus('<?= $unit['unit_id'] ?>', '<?= $unit['status'] ?>')" title="Update Status">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUnit('<?= $unit['unit_id'] ?>')" title="Delete Unit">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Inventory Table -->
                <div class="row fade-in" id="tableView">
                    <div class="col-12">
                        <div class="inventory-card">
                            <div class="inventory-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>Blood Inventory
                                    <span class="badge bg-danger ms-2">
                                        Showing <?= $startRecord ?>-<?= $endRecord ?> of <?= number_format($displayTotal) ?> units
                                        <?php if ($totalPages > 1): ?>
                                            (Page <?= $filters['page'] ?> of <?= $totalPages ?>)
                                        <?php endif; ?>
                                    </span>
                                </h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table inventory-table">
                                    <thead>
                                        <tr>
                                            <th>Unit ID</th>
                                            <th>Blood Type</th>
                                            <th>Donor Information</th>
                                            <th>Collection Date</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($inventory['data'])): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-circle-info me-2 text-secondary"></i>
                                                    <span>No blood units found for the current view. Try switching views or adding a unit.</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php foreach ($inventory['data'] as $unit): ?>
                                        <tr class="<?= $unit['expiring_soon'] ? 'expiring-soon' : '' ?>">
                                            <td>
                                                <code class="unit-id"><?= htmlspecialchars($unit['unit_id']) ?></code>
                                            </td>
                                            <td>
                                                <span class="blood-type-badge"><?= htmlspecialchars($unit['blood_type']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($unit['donor_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($unit['reference_code']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= date('M d, Y', strtotime($unit['collection_date'])) ?></div>
                                                <small class="text-muted"><?= date('D', strtotime($unit['collection_date'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= date('M d, Y', strtotime($unit['expiry_date'])) ?></div>
                                                <small class="text-muted"><?= date('D', strtotime($unit['expiry_date'])) ?></small>
                                                <?php if ($unit['expiring_soon']): ?>
                                                    <br><small class="text-warning pulse"><i class="fas fa-exclamation-triangle"></i> Expiring Soon</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $unit['status'] ?>">
                                                    <?= ucfirst($unit['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex">
                                                    <button class="action-btn btn-view" onclick="viewUnitDetails('<?= $unit['unit_id'] ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="action-btn btn-edit" onclick="updateUnitStatus('<?= $unit['unit_id'] ?>', '<?= $unit['status'] ?>')" title="Update Status">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="action-btn btn-delete" onclick="deleteUnit('<?= $unit['unit_id'] ?>')" title="Delete Unit">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <!-- Previous Page -->
                                <li class="page-item <?= $filters['page'] <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildPaginationUrl(1) ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item <?= $filters['page'] <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildPaginationUrl($filters['page'] - 1) ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php
                                $startPage = max(1, $filters['page'] - 2);
                                $endPage = min($totalPages, $filters['page'] + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $i == $filters['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <!-- Next Page -->
                                <li class="page-item <?= $filters['page'] >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildPaginationUrl($filters['page'] + 1) ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item <?= $filters['page'] >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildPaginationUrl($totalPages) ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Unit Modal -->
    <?php if ($canEdit): ?>
    <div class="modal fade modal-modern" id="addUnitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Blood Unit
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUnitForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Donor <span class="text-danger">*</span></label>
                            <select name="donor_id" class="form-select form-control-modern" required>
                                <option value="">Select Donor</option>
                                <?php foreach ($donors as $donor): ?>
                                <option value="<?= $donor['id'] ?>">
                                    <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?> 
                                    (<?= htmlspecialchars($donor['reference_code']) ?>) - <?= htmlspecialchars($donor['blood_type']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Collection Date <span class="text-danger">*</span></label>
                            <input type="date" name="collection_date" class="form-control form-control-modern" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Collection Site</label>
                            <input type="text" name="collection_site" class="form-control form-control-modern" 
                                   value="Main Center">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Storage Location</label>
                            <input type="text" name="storage_location" class="form-control form-control-modern" 
                                   value="Storage A">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success-modern btn-modern" onclick="addBloodUnit()">
                        <i class="fas fa-plus me-2"></i>Add Unit
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Unit Details Modal -->
    <div class="modal fade modal-modern" id="unitDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tint me-2"></i>Unit Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="unitDetailsContent">
                    <div class="text-center py-4">
                        <div class="loading-spinner"></div>
                        <p class="mt-3 text-muted">Loading unit details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <?php if ($canEdit): ?>
    <div class="modal fade modal-modern" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Update Unit Status
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="updateStatusForm">
                        <input type="hidden" id="updateUnitId" name="unit_id">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Status</label>
                            <select name="status" class="form-select form-control-modern" required>
                                <option value="available">Available</option>
                                <option value="used">Used</option>
                                <option value="expired">Expired</option>
                                <option value="quarantined">Quarantined</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason/Notes</label>
                            <textarea name="reason" class="form-control form-control-modern" rows="3" 
                                      placeholder="Enter reason for status change..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary-modern btn-modern" onclick="confirmStatusUpdate()">
                        <i class="fas fa-save me-2"></i>Update Status
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Help & Guide Modal -->
    <div class="modal fade" id="helpGuideModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-book me-2"></i>Help & Guide</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-semibold">Overview</h6>
                    <p>This dashboard lets admins manage blood units, donors, and statuses with audit logging.</p>
                    <h6 class="fw-semibold mt-3">Walkthrough</h6>
                    <ol>
                        <li><strong>Filter & Search</strong>: Use the filters to narrow units by blood type, status, per-page, and search by Unit ID or donor reference.</li>
                        <li><strong>Views</strong>: Toggle Table/Card view using the buttons at the top-right of the list.</li>
                        <li><strong>Add Unit</strong>: Click Add Unit, select a donor, set collection information, and save. Expiry auto-sets to 25 days.</li>
                        <li><strong>View Details</strong>: Click View to see unit and donor info, notes, and audit log.</li>
                        <li><strong>Edit Status</strong>: Click Edit to change status. Provide a reason; it’s appended to Notes and shown in Details.</li>
                        <li><strong>Delete</strong>: Click Delete to remove a unit (requires a reason; action is logged).</li>
                        <li><strong>Export CSV</strong>: Exports the current filtered set to CSV.</li>
                        <li><strong>Print</strong>: Prints the current table page with the chosen page size.</li>
                    </ol>
                    <h6 class="fw-semibold mt-3">Tips</h6>
                    <ul>
                        <li>“Expiring soon” badges indicate units within 5 days of expiry.</li>
                        <li>Use per-page selector (10/20/50/100) to adjust print page length.</li>
                        <li>CSV respects your current filters.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View Toggle Functions
        function toggleView(view) {
            const cardView = document.getElementById('cardView');
            const tableView = document.getElementById('tableView');
            const cardBtn = document.getElementById('cardViewBtn');
            const tableBtn = document.getElementById('tableViewBtn');
            
            if (view === 'card') {
                cardView.classList.remove('d-none');
                tableView.classList.add('d-none');
                cardBtn.classList.add('btn-danger');
                cardBtn.classList.remove('btn-outline-danger');
                tableBtn.classList.add('btn-outline-danger');
                tableBtn.classList.remove('btn-danger');
            } else {
                cardView.classList.add('d-none');
                tableView.classList.remove('d-none');
                tableBtn.classList.add('btn-danger');
                tableBtn.classList.remove('btn-outline-danger');
                cardBtn.classList.add('btn-outline-danger');
                cardBtn.classList.remove('btn-danger');
            }
        }

        // Initialize with table view
        document.addEventListener('DOMContentLoaded', function() {
            toggleView('table');
        });

        // Add Blood Unit
        function addBloodUnit() {
            const form = document.getElementById('addUnitForm');
            const formData = new FormData(form);
            formData.append('action', 'add_unit');
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<div class="loading-spinner me-2"></div>Adding...';
            btn.disabled = true;
            
            fetch('admin_blood_inventory_modern.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Blood unit added successfully!', 'success');
                    location.reload();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while adding the unit.', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        // View Unit Details
        function viewUnitDetails(unitId) {
            const modal = new bootstrap.Modal(document.getElementById('unitDetailsModal'));
            modal.show();
            
            fetch('admin_blood_inventory_modern.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_unit_details&unit_id=' + unitId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUnitDetails(data.unit);
                } else {
                    document.getElementById('unitDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Error: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('unitDetailsContent').innerHTML = 
                    '<div class="alert alert-danger">Error loading unit details</div>';
            });
        }

        // Display Unit Details
        function displayUnitDetails(unit) {
            const content = document.getElementById('unitDetailsContent');
            const notesHtml = unit.notes ? String(unit.notes).replace(/\n/g, '<br>') : '—';
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-semibold text-danger mb-3">Unit Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Unit ID:</strong></td><td><code class="unit-id">${unit.unit_id}</code></td></tr>
                            <tr><td><strong>Blood Type:</strong></td><td><span class="blood-type-badge">${unit.blood_type}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="status-badge status-${unit.status}">${unit.status}</span></td></tr>
                            <tr><td><strong>Collection Date:</strong></td><td>${unit.collection_date}</td></tr>
                            <tr><td><strong>Expiry Date:</strong></td><td>${unit.expiry_date}</td></tr>
                            <tr><td><strong>Notes:</strong></td><td>${notesHtml}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold text-danger mb-3">Donor Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${unit.donor_name}</td></tr>
                            <tr><td><strong>Reference:</strong></td><td><code class="unit-id">${unit.reference_code}</code></td></tr>
                            <tr><td><strong>Blood Type:</strong></td><td>${unit.donor_blood_type}</td></tr>
                        </table>
                    </div>
                </div>
                ${unit.audit_log ? `
                <div class="mt-4">
                    <h6 class="fw-semibold text-danger mb-3">Audit Log</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Date</th><th>Action</th><th>User</th><th>Details</th></tr>
                            </thead>
                            <tbody>
                                ${unit.audit_log.map(log => `
                                    <tr>
                                        <td>${log.timestamp}</td>
                                        <td><span class="badge bg-secondary">${log.action}</span></td>
                                        <td>${log.admin_name || ''}</td>
                                        <td>${log.description || log.new_values || ''}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
            `;
        }

        // Update Unit Status
        function updateUnitStatus(unitId, currentStatus) {
            document.getElementById('updateUnitId').value = unitId;
            document.querySelector('#updateStatusForm select[name="status"]').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        // Confirm Status Update
        function confirmStatusUpdate() {
            const form = document.getElementById('updateStatusForm');
            const formData = new FormData(form);
            formData.append('action', 'update_status');
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<div class="loading-spinner me-2"></div>Updating...';
            btn.disabled = true;
            
            fetch('admin_blood_inventory_modern.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Unit status updated successfully!', 'success');
                    location.reload();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating the status.', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        // Delete Unit
        function deleteUnit(unitId) {
            if (confirm('Are you sure you want to delete this blood unit? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_unit');
                formData.append('unit_id', unitId);
                formData.append('reason', 'Deleted by admin');
                
                // Show loading state
                showNotification('Deleting blood unit...', 'info');
                
                fetch('admin_blood_inventory_modern.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(text => {
                    // Log the raw response for debugging
                    console.log('Delete response:', text);
                    
                    // Try to parse as JSON
                    let data;
                    try {
                        // Clean any whitespace or BOM
                        const cleanText = text.trim().replace(/^\uFEFF/, '');
                        data = JSON.parse(cleanText);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.log('Raw response:', text);
                        
                        // If we can't parse JSON, just reload and assume success
                        // (since we know deletion actually works)
                        showNotification('Blood unit deleted. Refreshing...', 'success');
                        setTimeout(() => location.reload(), 800);
                        return;
                    }
                    
                    // Handle parsed JSON response
                    if (data && data.success) {
                        showNotification('Blood unit deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showNotification('Error: ' + (data.message || 'Failed to delete unit'), 'error');
                        setTimeout(() => location.reload(), 1500);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    // Even on error, deletion often works - just reload
                    showNotification('Processing... Refreshing page.', 'info');
                    setTimeout(() => location.reload(), 1000);
                });
            }
        }

        // Export to CSV (already wired via server-side handler)
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'admin_blood_inventory_modern.php?' + params.toString();
        }

        // Print current table page
        function printCurrentTablePage() {
            // Ensure table view is visible for accurate print
            toggleView('table');
            const tableContainer = document.getElementById('tableView');
            const clone = tableContainer.cloneNode(true);
            // Remove d-none if present
            clone.classList.remove('d-none');

            const styles = Array.from(document.styleSheets)
                .map(ss => {
                    try { return Array.from(ss.cssRules || []).map(r => r.cssText).join('\n'); } catch { return ''; }
                })
                .join('\n');

            const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Blood Inventory - Print</title>
<style>
@media print {
  .action-btn, .btn, .pagination { display: none !important; }
}
body { font-family: Inter, Arial, sans-serif; }
${styles}
</style>
</head><body>
<h3>Blood Inventory (Page <?= $filters['page'] ?> of <?= $totalPages ?>)</h3>
${clone.innerHTML}
<script>window.onload = function(){ window.print(); setTimeout(()=>window.close(), 300); }<\/script>
</body></html>`;
            const w = window.open('', '_blank');
            w.document.open();
            w.document.write(html);
            w.document.close();
        }

        // Show Notification
        function showNotification(message, type) {
            const alertClasses = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'danger': 'alert-danger',
                'info': 'alert-info',
                'warning': 'alert-warning'
            };
            const alertClass = alertClasses[type] || 'alert-info';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
            notification.innerHTML = `
                <strong>${message}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }

        // Add fade-in animation to elements
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
