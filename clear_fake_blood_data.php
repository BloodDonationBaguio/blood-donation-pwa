<?php
/**
 * Clear all fake blood inventory data
 */

require_once 'db.php';

echo "Clearing fake blood inventory data...\n";
echo "====================================\n\n";

try {
    // Clear all blood inventory data
    $stmt = $pdo->prepare("DELETE FROM blood_inventory");
    $stmt->execute();
    
    echo "✅ Cleared all blood inventory data\n";
    
    // Clear audit logs
    $stmt = $pdo->prepare("DELETE FROM blood_inventory_audit");
    $stmt->execute();
    
    echo "✅ Cleared all audit logs\n";
    
    // Reset auto increment
// Reset sequence for PostgreSQL or AUTO_INCREMENT for MySQL
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'pgsql') {
    // Default sequence name for SERIAL id is blood_inventory_id_seq
    $stmt = $pdo->prepare("ALTER SEQUENCE blood_inventory_id_seq RESTART WITH 1");
} else {
    $stmt = $pdo->prepare("ALTER TABLE blood_inventory AUTO_INCREMENT = 1");
}
    $stmt->execute();
    
    echo "✅ Reset auto increment\n\n";
    
    echo "🎉 All fake blood inventory data has been cleared!\n";
    echo "The system is now ready to work only with real donors.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
