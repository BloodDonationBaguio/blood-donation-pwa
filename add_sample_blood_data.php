<?php
/**
 * Add sample blood inventory data for testing
 */

require_once 'db.php';

echo "Adding sample blood inventory data...\n\n";

try {
    // Check if we have any donors first
    $donorStmt = $pdo->query("SELECT id, first_name, last_name, blood_type, status FROM donors_new WHERE status IN ('approved', 'served') LIMIT 10");
    $donors = $donorStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($donors)) {
        echo "No approved/served donors found. Please approve some donors first.\n";
        exit;
    }
    
    echo "Found " . count($donors) . " eligible donors.\n\n";
    
    // Sample blood units
    $sampleUnits = [
        ['O+', '2024-12-01', 'available'],
        ['A+', '2024-12-01', 'available'],
        ['B+', '2024-12-01', 'available'],
        ['AB+', '2024-11-30', 'available'],
        ['O-', '2024-11-30', 'available'],
        ['A-', '2024-11-29', 'used'],
        ['B-', '2024-11-28', 'quarantined'],
        ['AB-', '2024-11-27', 'expired'],
        ['O+', '2024-11-26', 'available'],
        ['A+', '2024-11-25', 'available']
    ];
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($sampleUnits as $index => $unit) {
        $donor = $donors[$index % count($donors)]; // Cycle through available donors
        
        $unitId = 'PRC-' . date('Ymd') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
        $expiryDate = date('Y-m-d', strtotime($unit[1] . ' +42 days'));
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO blood_inventory (
                    unit_id, donor_id, blood_type, collection_date, expiry_date,
                    status, collection_site, storage_location, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $unitId,
                $donor['id'],
                $unit[0], // blood_type
                $unit[1], // collection_date
                $expiryDate,
                $unit[2], // status
                'Main Center',
                'Storage A'
            ]);
            
            $successCount++;
            echo "✓ Added unit: $unitId for {$donor['first_name']} {$donor['last_name']} ({$unit[0]})\n";
            
        } catch (Exception $e) {
            $errorCount++;
            echo "✗ Error adding unit $unitId: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nSample Data Summary:\n";
    echo "✓ Successfully added: $successCount units\n";
    echo "✗ Failed to add: $errorCount units\n\n";
    
    if ($successCount > 0) {
        echo "🎉 Sample blood inventory data added successfully!\n";
        echo "You can now test the inventory management interface.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
