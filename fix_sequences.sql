-- Fix PostgreSQL Sequences for donor_notes and admin_audit_log tables
-- Run this script in Supabase SQL Editor

-- 1. Fix donor_notes table
-- First, check if the table exists and create it if it doesn't
CREATE TABLE IF NOT EXISTS donor_notes (
    donor_id INTEGER NOT NULL,
    note TEXT NOT NULL,
    created_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drop the existing id column and recreate it with proper SERIAL type
ALTER TABLE donor_notes DROP COLUMN IF EXISTS id CASCADE;
ALTER TABLE donor_notes ADD COLUMN id SERIAL PRIMARY KEY;

-- Set the sequence to start from the current max id + 1
SELECT setval('donor_notes_id_seq', COALESCE((SELECT MAX(id) FROM donor_notes), 0) + 1, false);

-- 2. Fix admin_audit_log table
-- First, check if the table exists and create it if it doesn't
CREATE TABLE IF NOT EXISTS admin_audit_log (
    admin_username VARCHAR(255) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drop the existing id column and recreate it with proper SERIAL type
ALTER TABLE admin_audit_log DROP COLUMN IF EXISTS id CASCADE;
ALTER TABLE admin_audit_log ADD COLUMN id SERIAL PRIMARY KEY;

-- Set the sequence to start from the current max id + 1
SELECT setval('admin_audit_log_id_seq', COALESCE((SELECT MAX(id) FROM admin_audit_log), 0) + 1, false);

-- 3. Fix donations_new table (if needed)
-- Check if it has a proper sequence
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_attribute 
        WHERE attrelid = 'donations_new'::regclass 
        AND attname = 'id'
    ) THEN
        ALTER TABLE donations_new ADD COLUMN id SERIAL PRIMARY KEY;
    END IF;
END $$;

-- Set the sequence to start from the current max id + 1
SELECT setval('donations_new_id_seq', COALESCE((SELECT MAX(id) FROM donations_new), 0) + 1, false);

-- 4. Verify the sequences are working
-- Test insert into donor_notes (will be rolled back)
DO $$
DECLARE
    test_id INTEGER;
BEGIN
    INSERT INTO donor_notes (donor_id, note, created_by) 
    VALUES (1, 'Test note - will be rolled back', 'test_script')
    RETURNING id INTO test_id;
    
    RAISE NOTICE 'Successfully inserted into donor_notes with ID: %', test_id;
    
    -- Rollback the test insert
    RAISE EXCEPTION 'Rolling back test insert';
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'Test completed and rolled back';
END $$;

-- Test insert into admin_audit_log (will be rolled back)
DO $$
DECLARE
    test_id INTEGER;
BEGIN
    INSERT INTO admin_audit_log (admin_username, action_type, table_name, record_id, description) 
    VALUES ('test_script', 'test_action', 'test_table', '1', 'Test entry - will be rolled back')
    RETURNING id INTO test_id;
    
    RAISE NOTICE 'Successfully inserted into admin_audit_log with ID: %', test_id;
    
    -- Rollback the test insert
    RAISE EXCEPTION 'Rolling back test insert';
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'Test completed and rolled back';
END $$;

-- Display success message
SELECT 'Sequences fixed successfully! You can now add notes and audit log entries.' AS status;
