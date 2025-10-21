<?php
/**
 * Blood Unit Details API
 * Returns detailed information about a specific blood unit
 */

require_once 'db.php';
require_once __DIR__ . '/admin/includes/admin_auth.php';
require_once 'includes/BloodInventoryManagerSimple.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get unit ID
$unitId = $_GET['unit_id'] ?? '';

if (empty($unitId)) {
    echo json_encode(['success' => false, 'message' => 'Unit ID is required']);
    exit;
}

try {
    // Initialize Blood Inventory Manager
    $inventoryManager = new BloodInventoryManagerSimple($pdo);
    
    // Get unit details
    $result = $inventoryManager->getBloodUnit($unitId);
    
    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => $result['message']]);
        exit;
    }
    
    $unit = $result['data'];
    
    if (!$unit) {
        echo json_encode(['success' => false, 'message' => 'Blood unit not found']);
        exit;
    }
    
    // Format the data for display
    $formattedUnit = [
        'unit_id' => $unit['unit_id'],
        'blood_type' => $unit['blood_type'],
        'donor_blood_type' => $unit['donor_blood_type'],
        'collection_date' => date('F d, Y', strtotime($unit['collection_date'])),
        'expiry_date' => date('F d, Y', strtotime($unit['expiry_date'])),
        'days_to_expire' => $unit['days_to_expire'],
        'status' => ucfirst($unit['status']),
        'volume_ml' => $unit['volume_ml'],
        'collection_site' => $unit['collection_site'] ?? 'Not specified',
        'storage_location' => $unit['storage_location'] ?? 'Not specified',
        'screening_status' => ucfirst($unit['screening_status']),
        'donor_info' => [
            'name' => $unit['first_name'] . ' ' . $unit['last_name'],
            'reference_code' => $unit['reference_code'],
            'email' => $unit['email'] ?? 'Not available',
            'phone' => $unit['phone'] ?? 'Not available'
        ],
        'test_results' => $unit['test_results'] ? json_decode($unit['test_results'], true) : null,
        'notes' => $unit['notes'] ?? '',
        'created_at' => date('F d, Y H:i', strtotime($unit['created_at'])),
        'updated_at' => date('F d, Y H:i', strtotime($unit['updated_at']))
    ];
    
    echo json_encode([
        'success' => true,
        'unit' => $formattedUnit
    ]);
    
} catch (Exception $e) {
    error_log("Blood unit details error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve unit details: ' . $e->getMessage()
    ]);
}
?>
