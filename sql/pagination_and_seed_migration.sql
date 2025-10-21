-- Pagination and Seed Data Migration Script
-- Blood Donation PWA System Enhancement
-- Adds seed_flag column and ensures proper foreign key constraints

-- Add seed_flag column to donors_new table
ALTER TABLE donors_new 
ADD COLUMN IF NOT EXISTS seed_flag TINYINT(1) DEFAULT 0 COMMENT 'Flag to identify seeded test data';

-- Add seed_flag column to blood_inventory table  
ALTER TABLE blood_inventory 
ADD COLUMN IF NOT EXISTS seed_flag TINYINT(1) DEFAULT 0 COMMENT 'Flag to identify seeded test data';

-- Ensure foreign key constraint exists for blood_inventory.donor_id
-- First, check if the constraint exists and drop it if it does
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'blood_inventory' 
    AND COLUMN_NAME = 'donor_id' 
    AND REFERENCED_TABLE_NAME = 'donors_new'
);

-- Drop existing foreign key if it exists
SET @sql = IF(@constraint_exists > 0, 
    'ALTER TABLE blood_inventory DROP FOREIGN KEY ' || (
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'blood_inventory' 
        AND COLUMN_NAME = 'donor_id' 
        AND REFERENCED_TABLE_NAME = 'donors_new'
        LIMIT 1
    ), 
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

-- Create indexes for better pagination performance
CREATE INDEX IF NOT EXISTS idx_donors_new_status_created ON donors_new(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_donors_new_seed_flag ON donors_new(seed_flag);
CREATE INDEX IF NOT EXISTS idx_blood_inventory_status_created ON blood_inventory(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_blood_inventory_seed_flag ON blood_inventory(seed_flag);
CREATE INDEX IF NOT EXISTS idx_blood_inventory_donor_status ON blood_inventory(donor_id, status);

-- Create a view for paginated donor queries
CREATE OR REPLACE VIEW donors_paginated AS
SELECT 
    d.id,
    d.first_name,
    d.last_name,
    d.email,
    d.phone,
    d.blood_type,
    d.status,
    d.reference_code,
    d.created_at,
    d.updated_at,
    d.seed_flag,
    CONCAT(d.first_name, ' ', d.last_name) as full_name,
    CASE 
        WHEN d.seed_flag = 1 THEN CONCAT(d.first_name, ' (TEST)')
        ELSE d.first_name
    END as display_name
FROM donors_new d
WHERE d.email NOT LIKE 'test_%' 
  AND d.email NOT LIKE '%@example.com'
  AND d.first_name != 'Test'
  AND d.last_name != 'User'
  AND (d.reference_code NOT LIKE 'TEST-%' OR d.reference_code IS NULL)
  AND d.seed_flag = 0;

-- Create a view for paginated blood inventory queries with donor info
CREATE OR REPLACE VIEW blood_inventory_paginated AS
SELECT 
    bi.id,
    bi.unit_id,
    bi.blood_type,
    bi.collection_date,
    bi.expiry_date,
    bi.status,
    bi.collection_site,
    bi.storage_location,
    bi.volume_ml,
    bi.screening_status,
    bi.notes,
    bi.created_at,
    bi.updated_at,
    bi.seed_flag,
    d.id as donor_id,
    d.first_name as donor_first_name,
    d.last_name as donor_last_name,
    d.reference_code as donor_reference,
    d.email as donor_email,
    d.phone as donor_phone,
    d.blood_type as donor_blood_type,
    CONCAT(d.first_name, ' ', d.last_name) as donor_full_name,
    CASE 
        WHEN bi.seed_flag = 1 THEN CONCAT(bi.unit_id, ' (TEST)')
        ELSE bi.unit_id
    END as display_unit_id,
    DATEDIFF(bi.expiry_date, CURDATE()) as days_to_expiry,
    CASE 
        WHEN bi.expiry_date < CURDATE() THEN 'expired'
        WHEN DATEDIFF(bi.expiry_date, CURDATE()) <= 5 THEN 'expiring_soon'
        ELSE 'good'
    END as urgency_status
FROM blood_inventory bi
LEFT JOIN donors_new d ON bi.donor_id = d.id
WHERE bi.seed_flag = 0;

-- Create stored procedure for paginated donor queries
DELIMITER //

CREATE PROCEDURE GetDonorsPaginated(
    IN p_page INT,
    IN p_per_page INT,
    IN p_status VARCHAR(50),
    IN p_search VARCHAR(255),
    IN p_blood_type VARCHAR(10),
    IN p_sort_by VARCHAR(50),
    IN p_sort_order VARCHAR(10)
)
BEGIN
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_where_clause TEXT DEFAULT '';
    DECLARE v_order_clause TEXT DEFAULT '';
    DECLARE v_query TEXT;
    DECLARE v_count_query TEXT;
    
    -- Calculate offset
    SET v_offset = (p_page - 1) * p_per_page;
    
    -- Build WHERE clause
    SET v_where_clause = 'WHERE d.seed_flag = 0';
    
    IF p_status IS NOT NULL AND p_status != '' THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND d.status = ''', p_status, '''');
    END IF;
    
    IF p_blood_type IS NOT NULL AND p_blood_type != '' THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND d.blood_type = ''', p_blood_type, '''');
    END IF;
    
    IF p_search IS NOT NULL AND p_search != '' THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND (d.first_name LIKE ''%', p_search, '%'' OR d.last_name LIKE ''%', p_search, '%'' OR d.email LIKE ''%', p_search, '%'' OR d.reference_code LIKE ''%', p_search, '%'')');
    END IF;
    
    -- Build ORDER clause
    IF p_sort_by IS NULL OR p_sort_by = '' THEN
        SET p_sort_by = 'created_at';
    END IF;
    
    IF p_sort_order IS NULL OR p_sort_order = '' THEN
        SET p_sort_order = 'DESC';
    END IF;
    
    SET v_order_clause = CONCAT('ORDER BY d.', p_sort_by, ' ', p_sort_order);
    
    -- Build main query
    SET v_query = CONCAT('
        SELECT 
            d.id,
            d.first_name,
            d.last_name,
            d.email,
            d.phone,
            d.blood_type,
            d.status,
            d.reference_code,
            d.created_at,
            d.updated_at,
            CONCAT(d.first_name, '' '', d.last_name) as full_name
        FROM donors_new d
        ', v_where_clause, '
        ', v_order_clause, '
        LIMIT ', p_per_page, ' OFFSET ', v_offset
    );
    
    -- Execute main query
    SET @sql = v_query;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    -- Get total count
    SET v_count_query = CONCAT('
        SELECT COUNT(*) as total
        FROM donors_new d
        ', v_where_clause
    );
    
    SET @sql = v_count_query;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
END //

-- Create stored procedure for paginated blood inventory queries
CREATE PROCEDURE GetBloodInventoryPaginated(
    IN p_page INT,
    IN p_per_page INT,
    IN p_status VARCHAR(50),
    IN p_blood_type VARCHAR(10),
    IN p_search VARCHAR(255),
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_sort_by VARCHAR(50),
    IN p_sort_order VARCHAR(10)
)
BEGIN
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_where_clause TEXT DEFAULT '';
    DECLARE v_order_clause TEXT DEFAULT '';
    DECLARE v_query TEXT;
    DECLARE v_count_query TEXT;
    
    -- Calculate offset
    SET v_offset = (p_page - 1) * p_per_page;
    
    -- Build WHERE clause
    SET v_where_clause = 'WHERE bi.seed_flag = 0';
    
    IF p_status IS NOT NULL AND p_status != '' THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND bi.status = ''', p_status, '''');
    END IF;
    
    IF p_blood_type IS NOT NULL AND p_blood_type != '' THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND bi.blood_type = ''', p_blood_type, '''');
    END IF;
    
    IF p_search IS NOT NULL AND p_search != '' THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND (bi.unit_id LIKE ''%', p_search, '%'' OR d.first_name LIKE ''%', p_search, '%'' OR d.last_name LIKE ''%', p_search, '%'' OR d.reference_code LIKE ''%', p_search, '%'')');
    END IF;
    
    IF p_date_from IS NOT NULL THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND bi.collection_date >= ''', p_date_from, '''');
    END IF;
    
    IF p_date_to IS NOT NULL THEN
        SET v_where_clause = CONCAT(v_where_clause, ' AND bi.collection_date <= ''', p_date_to, '''');
    END IF;
    
    -- Build ORDER clause
    IF p_sort_by IS NULL OR p_sort_by = '' THEN
        SET p_sort_by = 'created_at';
    END IF;
    
    IF p_sort_order IS NULL OR p_sort_order = '' THEN
        SET p_sort_order = 'DESC';
    END IF;
    
    SET v_order_clause = CONCAT('ORDER BY bi.', p_sort_by, ' ', p_sort_order);
    
    -- Build main query
    SET v_query = CONCAT('
        SELECT 
            bi.id,
            bi.unit_id,
            bi.blood_type,
            bi.collection_date,
            bi.expiry_date,
            bi.status,
            bi.collection_site,
            bi.storage_location,
            bi.volume_ml,
            bi.screening_status,
            bi.notes,
            bi.created_at,
            bi.updated_at,
            d.id as donor_id,
            d.first_name as donor_first_name,
            d.last_name as donor_last_name,
            d.reference_code as donor_reference,
            d.email as donor_email,
            d.phone as donor_phone,
            d.blood_type as donor_blood_type,
            CONCAT(d.first_name, '' '', d.last_name) as donor_full_name,
            DATEDIFF(bi.expiry_date, CURDATE()) as days_to_expiry,
            CASE 
                WHEN bi.expiry_date < CURDATE() THEN ''expired''
                WHEN DATEDIFF(bi.expiry_date, CURDATE()) <= 5 THEN ''expiring_soon''
                ELSE ''good''
            END as urgency_status
        FROM blood_inventory bi
        LEFT JOIN donors_new d ON bi.donor_id = d.id
        ', v_where_clause, '
        ', v_order_clause, '
        LIMIT ', p_per_page, ' OFFSET ', v_offset
    );
    
    -- Execute main query
    SET @sql = v_query;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    -- Get total count
    SET v_count_query = CONCAT('
        SELECT COUNT(*) as total
        FROM blood_inventory bi
        LEFT JOIN donors_new d ON bi.donor_id = d.id
        ', v_where_clause
    );
    
    SET @sql = v_count_query;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
END //

DELIMITER ;

-- Create function to generate pagination info
DELIMITER //

CREATE FUNCTION GetPaginationInfo(
    p_total_records INT,
    p_current_page INT,
    p_per_page INT
)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_total_pages INT;
    DECLARE v_start_record INT;
    DECLARE v_end_record INT;
    DECLARE v_result JSON;
    
    SET v_total_pages = CEIL(p_total_records / p_per_page);
    SET v_start_record = ((p_current_page - 1) * p_per_page) + 1;
    SET v_end_record = LEAST(p_current_page * p_per_page, p_total_records);
    
    SET v_result = JSON_OBJECT(
        'current_page', p_current_page,
        'per_page', p_per_page,
        'total_records', p_total_records,
        'total_pages', v_total_pages,
        'start_record', v_start_record,
        'end_record', v_end_record,
        'has_previous', p_current_page > 1,
        'has_next', p_current_page < v_total_pages
    );
    
    RETURN v_result;
END //

DELIMITER ;

-- Add comments to tables
ALTER TABLE donors_new COMMENT = 'Donors table with seed_flag for test data identification';
ALTER TABLE blood_inventory COMMENT = 'Blood inventory table with donor linking and seed_flag for test data identification';
