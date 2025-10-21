-- Enhanced Blood Inventory Management System
-- Database Migration Script

-- Create blood_inventory table
CREATE TABLE IF NOT EXISTS blood_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id VARCHAR(50) UNIQUE NOT NULL,
    donor_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') NOT NULL,
    collection_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('available', 'used', 'expired', 'quarantined') DEFAULT 'available',
    collection_center VARCHAR(100) DEFAULT 'Main Center',
    collection_staff VARCHAR(100),
    test_results JSON,
    location VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_unit_id (unit_id),
    INDEX idx_donor_id (donor_id),
    INDEX idx_blood_type (blood_type),
    INDEX idx_status (status),
    INDEX idx_collection_date (collection_date),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (donor_id) REFERENCES donors_new(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create blood_inventory_audit table for tracking changes
CREATE TABLE IF NOT EXISTS blood_inventory_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    details JSON,
    admin_username VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_unit_id (unit_id),
    INDEX idx_action_type (action_type),
    INDEX idx_admin_username (admin_username),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (unit_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create blood_requests_inventory table for tracking blood requests
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

-- Create blood_inventory_summary view for dashboard
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

-- Create expiring_blood_units view for alerts
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

-- Create stored procedure for automatic status updates
DELIMITER //

CREATE PROCEDURE UpdateExpiredUnits()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE unit_id INT;
    DECLARE unit_cursor CURSOR FOR 
        SELECT id FROM blood_inventory 
        WHERE status = 'available' AND expiry_date < NOW();
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN unit_cursor;
    
    read_loop: LOOP
        FETCH unit_cursor INTO unit_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Update status to expired
        UPDATE blood_inventory 
        SET status = 'expired', updated_at = NOW() 
        WHERE id = unit_id;
        
        -- Log audit
        INSERT INTO blood_inventory_audit (unit_id, action_type, description, admin_username, created_at)
        VALUES (unit_id, 'auto_expired', 'Unit automatically marked as expired', 'system', NOW());
        
    END LOOP;
    
    CLOSE unit_cursor;
END //

DELIMITER ;

-- Create event to run expired units update daily
CREATE EVENT IF NOT EXISTS update_expired_units
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  CALL UpdateExpiredUnits();

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- Insert sample data for testing (optional)
INSERT IGNORE INTO blood_inventory (
    unit_id, donor_id, blood_type, collection_date, expiry_date, 
    status, collection_center, collection_staff
) VALUES 
('PRC-20241201-0001', 1, 'O+', '2024-12-01', '2025-01-12', 'available', 'Main Center', 'Dr. Smith'),
('PRC-20241201-0002', 2, 'A+', '2024-12-01', '2025-01-12', 'available', 'Main Center', 'Dr. Smith'),
('PRC-20241201-0003', 3, 'B+', '2024-12-01', '2025-01-12', 'available', 'Main Center', 'Dr. Smith'),
('PRC-20241130-0001', 4, 'AB+', '2024-11-30', '2025-01-11', 'available', 'Main Center', 'Dr. Johnson'),
('PRC-20241130-0002', 5, 'O-', '2024-11-30', '2025-01-11', 'available', 'Main Center', 'Dr. Johnson'),
('PRC-20241129-0001', 6, 'A-', '2024-11-29', '2025-01-10', 'used', 'Main Center', 'Dr. Brown'),
('PRC-20241128-0001', 7, 'B-', '2024-11-28', '2025-01-09', 'quarantined', 'Main Center', 'Dr. Davis'),
('PRC-20241127-0001', 8, 'AB-', '2024-11-27', '2025-01-08', 'expired', 'Main Center', 'Dr. Wilson');

-- Create indexes for better performance
CREATE INDEX idx_blood_inventory_composite ON blood_inventory (status, blood_type, expiry_date);
CREATE INDEX idx_blood_inventory_search ON blood_inventory (unit_id, donor_id, collection_date);

-- Add constraints
ALTER TABLE blood_inventory 
ADD CONSTRAINT chk_expiry_after_collection 
CHECK (expiry_date > collection_date);

ALTER TABLE blood_inventory 
ADD CONSTRAINT chk_collection_date_not_future 
CHECK (collection_date <= CURDATE());

-- Create trigger to automatically update expiry date when collection date changes
DELIMITER //

CREATE TRIGGER update_expiry_date
BEFORE UPDATE ON blood_inventory
FOR EACH ROW
BEGIN
    IF NEW.collection_date != OLD.collection_date THEN
        SET NEW.expiry_date = DATE_ADD(NEW.collection_date, INTERVAL 42 DAY);
    END IF;
END //

DELIMITER ;

-- Create trigger to log status changes
DELIMITER //

CREATE TRIGGER log_status_change
AFTER UPDATE ON blood_inventory
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO blood_inventory_audit (unit_id, action_type, description, admin_username, created_at)
        VALUES (NEW.id, 'status_changed', 
                CONCAT('Status changed from ', OLD.status, ' to ', NEW.status), 
                USER(), NOW());
    END IF;
END //

DELIMITER ;
