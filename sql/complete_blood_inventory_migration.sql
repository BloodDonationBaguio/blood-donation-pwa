-- Complete Blood Inventory Management System
-- Database Migration Script

-- Create blood_inventory table (if not exists)
CREATE TABLE IF NOT EXISTS blood_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id VARCHAR(50) UNIQUE NOT NULL,
    donor_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') NOT NULL,
    collection_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('available', 'used', 'expired', 'quarantined') DEFAULT 'available',
    collection_site VARCHAR(100) DEFAULT 'Main Center',
    storage_location VARCHAR(50) DEFAULT 'Storage A',
    volume_ml INT DEFAULT 450,
    screening_status ENUM('pending', 'passed', 'failed') DEFAULT 'pending',
    test_results JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,
    
    INDEX idx_unit_id (unit_id),
    INDEX idx_donor_id (donor_id),
    INDEX idx_blood_type (blood_type),
    INDEX idx_status (status),
    INDEX idx_collection_date (collection_date),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (donor_id) REFERENCES donors_new(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create blood_inventory_audit table for complete audit trail
CREATE TABLE IF NOT EXISTS blood_inventory_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    old_values JSON,
    new_values JSON,
    admin_name VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_unit_id (unit_id),
    INDEX idx_action (action),
    INDEX idx_admin_name (admin_name),
    INDEX idx_timestamp (timestamp),
    
    FOREIGN KEY (unit_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create blood_requests_inventory table for tracking usage
CREATE TABLE IF NOT EXISTS blood_requests_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT,
    unit_id INT NOT NULL,
    blood_type VARCHAR(10) NOT NULL,
    quantity INT DEFAULT 1,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issued_by VARCHAR(100),
    notes TEXT,
    
    INDEX idx_request_id (request_id),
    INDEX idx_unit_id (unit_id),
    INDEX idx_blood_type (blood_type),
    INDEX idx_issued_date (issued_date),
    
    FOREIGN KEY (unit_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create views for reporting
CREATE OR REPLACE VIEW blood_inventory_summary AS
SELECT 
    blood_type,
    COUNT(*) as total_units,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_units,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_units,
    SUM(CASE WHEN status = 'quarantined' THEN 1 ELSE 0 END) as quarantined_units,
    AVG(DATEDIFF(expiry_date, NOW())) as avg_days_to_expiry
FROM blood_inventory
GROUP BY blood_type
ORDER BY blood_type;

-- Create view for expiring units
CREATE OR REPLACE VIEW expiring_blood_units AS
SELECT 
    bi.*,
    d.first_name,
    d.last_name,
    d.reference_code,
    DATEDIFF(bi.expiry_date, NOW()) as days_to_expiry
FROM blood_inventory bi
LEFT JOIN donors_new d ON bi.donor_id = d.id
WHERE bi.status = 'available' 
AND bi.expiry_date <= DATE_ADD(NOW(), INTERVAL 5 DAY)
AND bi.expiry_date > NOW()
ORDER BY bi.expiry_date ASC;

-- Create view for low stock alerts
CREATE OR REPLACE VIEW low_stock_blood_types AS
SELECT 
    blood_type,
    COUNT(*) as available_count
FROM blood_inventory
WHERE status = 'available'
GROUP BY blood_type
HAVING COUNT(*) < 5
ORDER BY available_count ASC;

-- Add constraints
ALTER TABLE blood_inventory 
ADD CONSTRAINT chk_expiry_after_collection 
CHECK (expiry_date > collection_date);

-- Create indexes for better performance
CREATE INDEX idx_blood_inventory_composite ON blood_inventory (status, blood_type, expiry_date);
CREATE INDEX idx_blood_inventory_search ON blood_inventory (unit_id, donor_id, collection_date);

-- Insert sample data (optional - for testing)
INSERT IGNORE INTO blood_inventory (
    unit_id, donor_id, blood_type, collection_date, expiry_date, 
    status, collection_site, storage_location, created_at
) VALUES 
('PRC-20241201-0001', 2, 'A+', '2024-12-01', '2025-01-12', 'available', 'Main Center', 'Storage A', NOW()),
('PRC-20241201-0002', 3, 'O+', '2024-12-01', '2025-01-12', 'available', 'Main Center', 'Storage B', NOW());
