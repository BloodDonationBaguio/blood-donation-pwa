<?php
/**
 * Blood Inventory Management System
 * Tracks blood units, expiration dates, and stock levels
 */

function createBloodInventoryTable($pdo) {
    // Skip table creation to avoid tablespace conflicts
    // Tables will be created manually if needed
    return true;
}

function updateBloodInventory($pdo, $bloodType, $units = 1, $action = 'add') {
    try {
        // Check if inventory record exists
        $stmt = $pdo->prepare("SELECT * FROM blood_inventory WHERE blood_type = ?");
        $stmt->execute([$bloodType]);
        $inventory = $stmt->fetch();
        
        if (!$inventory) {
            // Create new inventory record
            $stmt = $pdo->prepare("INSERT INTO blood_inventory (blood_type, units_available) VALUES (?, ?)");
            $stmt->execute([$bloodType, $units]);
        } else {
            // Update existing inventory
            $newUnits = $action === 'add' ? $inventory['units_available'] + $units : $inventory['units_available'] - $units;
            $newUnits = max(0, $newUnits); // Prevent negative values
            
            $stmt = $pdo->prepare("UPDATE blood_inventory SET units_available = ? WHERE blood_type = ?");
            $stmt->execute([$newUnits, $bloodType]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating blood inventory: " . $e->getMessage());
        return false;
    }
}

function addBloodUnit($pdo, $donorId, $bloodType, $collectionDate = null) {
    try {
        if (!$collectionDate) {
            $collectionDate = date('Y-m-d');
        }
        
        // Calculate expiration date (42 days from collection)
        $expirationDate = date('Y-m-d', strtotime($collectionDate . ' + 42 days'));
        
        // Generate unique unit ID
        $unitId = 'BU-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $stmt = $pdo->prepare("
            INSERT INTO blood_units (blood_type, donor_id, collection_date, expiration_date, unit_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$bloodType, $donorId, $collectionDate, $expirationDate, $unitId]);
        
        // Update inventory
        updateBloodInventory($pdo, $bloodType, 1, 'add');
        
        return $unitId;
        error_log("Error adding blood unit: " . $e->getMessage());
        return false;
    }
}

function getBloodInventory($pdo, $bloodTypeFilter = null) {
    try {
        // Get current inventory levels from donors (simplified version)
        $sql = "
            SELECT 
                blood_type,
                COUNT(*) as units_available,
                0 as units_expired,
                0 as units_reserved,
                MAX(created_at) as last_updated
            FROM donors_new 
            WHERE status IN ('approved', 'served')";
        
        // Add blood type filter if specified
        if ($bloodTypeFilter) {
            $sql .= " AND blood_type = ?";
        }
        
        $sql .= " GROUP BY blood_type ORDER BY blood_type";
        
        $stmt = $pdo->prepare($sql);
        if ($bloodTypeFilter) {
            $stmt->execute([$bloodTypeFilter]);
        } else {
            $stmt->execute();
        }
        $inventory = $stmt->fetchAll();
        
        // If no inventory data, create default entries
        if (empty($inventory)) {
            $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
            $inventory = [];
            foreach ($bloodTypes as $type) {
                $inventory[] = [
                    'blood_type' => $type,
                    'units_available' => 0,
                    'units_expired' => 0,
                    'units_reserved' => 0,
                    'last_updated' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Get low stock alerts (less than 5 units)
        $lowStock = [];
        foreach ($inventory as $item) {
            if ($item['units_available'] < 5) {
                $lowStock[] = $item['blood_type'];
            }
        }
        
        return [
            'inventory' => $inventory,
            'expiring' => [], // No expiring data for now
            'low_stock' => $lowStock
        ];
    } catch (Exception $e) {
        error_log("Error getting blood inventory: " . $e->getMessage());
        return false;
    }
}

function checkExpiredUnits($pdo) {
    try {
        // Mark expired units
        $stmt = $pdo->prepare("
            UPDATE blood_units 
            SET status = 'expired' 
            WHERE expiration_date < CURDATE() AND status = 'available'
        ");
        $stmt->execute();
        
        // Update inventory counts
        $stmt = $pdo->prepare("
            UPDATE blood_inventory bi 
            SET units_expired = (
                SELECT COUNT(*) 
                FROM blood_units bu 
                WHERE bu.blood_type = bi.blood_type AND bu.status = 'expired'
            ),
            units_available = (
                SELECT COUNT(*) 
                FROM blood_units bu 
                WHERE bu.blood_type = bi.blood_type AND bu.status = 'available'
            )
        ");
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("Error checking expired units: " . $e->getMessage());
        return false;
    }
}

function reserveBloodUnit($pdo, $bloodType, $requestId) {
    try {
        // Find available unit
        $stmt = $pdo->prepare("
            SELECT id, unit_id 
            FROM blood_units 
            WHERE blood_type = ? AND status = 'available' AND expiration_date > CURDATE()
            ORDER BY expiration_date ASC 
            LIMIT 1
        ");
        $stmt->execute([$bloodType]);
        $unit = $stmt->fetch();
        
        if ($unit) {
            // Reserve the unit
            $stmt = $pdo->prepare("UPDATE blood_units SET status = 'reserved' WHERE id = ?");
            $stmt->execute([$unit['id']]);
            
            // Update inventory
            updateBloodInventory($pdo, $bloodType, 1, 'subtract');
            
            return $unit['unit_id'];
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error reserving blood unit: " . $e->getMessage());
        return false;
    }
}
?> 