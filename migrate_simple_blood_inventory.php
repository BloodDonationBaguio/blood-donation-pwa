<?php
// Minimal migration to create blood_inventory table compatible with BloodInventoryManagerComplete
// Usage: php migrate_simple_blood_inventory.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

function createBloodInventoryTableIfMissing(PDO $pdo) {
    // Check existing
    if (function_exists('tableExists') && tableExists($pdo, 'blood_inventory')) {
        echo "blood_inventory already exists.\n";
        return true;
    }
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'pgsql') {
        // PostgreSQL-compatible schema
        $statements = [
            // Table
            "CREATE TABLE IF NOT EXISTS blood_inventory (
                id SERIAL PRIMARY KEY,
                unit_id VARCHAR(50) UNIQUE NOT NULL,
                donor_id INTEGER NOT NULL,
                blood_type VARCHAR(10) NOT NULL,
                collection_date DATE NOT NULL,
                expiry_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'available',
                collection_site VARCHAR(100) DEFAULT 'Main Center',
                storage_location VARCHAR(100) DEFAULT 'Storage A',
                notes TEXT,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW()
            )",
            // Indexes
            "CREATE INDEX IF NOT EXISTS idx_unit_id ON blood_inventory(unit_id)",
            "CREATE INDEX IF NOT EXISTS idx_donor_id ON blood_inventory(donor_id)",
            "CREATE INDEX IF NOT EXISTS idx_status ON blood_inventory(status)",
            "CREATE INDEX IF NOT EXISTS idx_collection_date ON blood_inventory(collection_date)",
            "CREATE INDEX IF NOT EXISTS idx_expiry_date ON blood_inventory(expiry_date)",
            // Constraints
            "DO $$ BEGIN IF NOT EXISTS (
                SELECT 1 FROM information_schema.table_constraints 
                WHERE constraint_name = 'chk_expiry_after_collection' AND table_name = 'blood_inventory') THEN
                ALTER TABLE blood_inventory ADD CONSTRAINT chk_expiry_after_collection CHECK (expiry_date > collection_date);
            END IF; END $$;",
            "DO $$ BEGIN IF NOT EXISTS (
                SELECT 1 FROM information_schema.table_constraints 
                WHERE constraint_name = 'chk_status_valid' AND table_name = 'blood_inventory') THEN
                ALTER TABLE blood_inventory ADD CONSTRAINT chk_status_valid CHECK (status IN ('available','used','expired','quarantined'));
            END IF; END $$;"
        ];

        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        echo "Created blood_inventory table (PostgreSQL).\n";
    } else {
        // MySQL/MariaDB-compatible schema
        $sql = "
        CREATE TABLE IF NOT EXISTS blood_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            donor_id INT NOT NULL,
            blood_type VARCHAR(10) NOT NULL,
            collection_date DATE NOT NULL,
            expiry_date DATE NOT NULL,
            status ENUM('available','used','expired','quarantined') DEFAULT 'available',
            collection_site VARCHAR(100) DEFAULT 'Main Center',
            storage_location VARCHAR(100) DEFAULT 'Storage A',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_unit_id (unit_id),
            INDEX idx_donor_id (donor_id),
            INDEX idx_status (status),
            INDEX idx_collection_date (collection_date),
            INDEX idx_expiry_date (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $pdo->exec($sql);
        echo "Created blood_inventory table (MySQL/MariaDB).\n";
    }
    return true;
}

try {
    createBloodInventoryTableIfMissing($pdo);
    echo "Migration complete.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

?>
