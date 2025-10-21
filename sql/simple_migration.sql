-- Simple Migration Script for Blood Donation PWA
-- Compatible with MariaDB/MySQL

-- Add seed_flag column to donors_new table
ALTER TABLE donors_new 
ADD COLUMN seed_flag TINYINT(1) DEFAULT 0 COMMENT 'Flag to identify seeded test data';

-- Add seed_flag column to blood_inventory table  
ALTER TABLE blood_inventory 
ADD COLUMN seed_flag TINYINT(1) DEFAULT 0 COMMENT 'Flag to identify seeded test data';

-- Create indexes for better performance
CREATE INDEX idx_donors_seed_flag ON donors_new(seed_flag);
CREATE INDEX idx_blood_inventory_seed_flag ON blood_inventory(seed_flag);
CREATE INDEX idx_donors_status_created ON donors_new(status, created_at DESC);
CREATE INDEX idx_blood_inventory_status_created ON blood_inventory(status, created_at DESC);

-- Ensure foreign key constraint exists for blood_inventory.donor_id
-- First check if constraint exists and drop it if it does
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'blood_inventory' 
    AND COLUMN_NAME = 'donor_id' 
    AND REFERENCED_TABLE_NAME = 'donors_new'
    LIMIT 1
);

-- Drop existing foreign key if it exists
SET @sql = IF(@constraint_name IS NOT NULL, 
    CONCAT('ALTER TABLE blood_inventory DROP FOREIGN KEY ', @constraint_name), 
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add the foreign key constraint
ALTER TABLE blood_inventory 
ADD CONSTRAINT fk_blood_inventory_donor_id 
FOREIGN KEY (donor_id) REFERENCES donors_new(id) 
ON DELETE RESTRICT ON UPDATE CASCADE;
