-- Supabase Migration Script for Blood Donation PWA
-- This script creates the database schema in Supabase

-- Admin Users Table
CREATE TABLE IF NOT EXISTS admin_users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Donors Table
CREATE TABLE IF NOT EXISTS donors (
    id BIGSERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    blood_type VARCHAR(10),
    date_of_birth DATE,
    gender VARCHAR(10),
    address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    postal_code VARCHAR(20),
    weight NUMERIC(5,2),
    height NUMERIC(5,2),
    reference_code VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    served_date TIMESTAMP NULL,
    rejection_reason TEXT,
    unserved_reason TEXT,
    last_donation_date DATE,
    last_reminder_sent DATE,
    seed_flag BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Blood Units Table
CREATE TABLE IF NOT EXISTS blood_units (
    id BIGSERIAL PRIMARY KEY,
    donor_id BIGINT REFERENCES donors(id) ON DELETE CASCADE,
    blood_type VARCHAR(10) NOT NULL,
    donation_date DATE NOT NULL,
    expiry_date DATE,
    status VARCHAR(20) DEFAULT 'available',
    volume_ml INTEGER DEFAULT 450,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enable Row Level Security (RLS) on all tables
ALTER TABLE admin_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE donors ENABLE ROW LEVEL SECURITY;
ALTER TABLE blood_units ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;

-- Indexes for better performance
CREATE INDEX IF NOT EXISTS idx_donors_status ON donors(status);
CREATE INDEX IF NOT EXISTS idx_donors_blood_type ON donors(blood_type);
CREATE INDEX IF NOT EXISTS idx_donors_created_at ON donors(created_at);
CREATE UNIQUE INDEX IF NOT EXISTS idx_donors_reference_code ON donors(reference_code);
CREATE INDEX IF NOT EXISTS idx_donors_status_created ON donors(status, created_at);
CREATE INDEX IF NOT EXISTS idx_donors_seed_flag ON donors(seed_flag);
CREATE INDEX IF NOT EXISTS idx_blood_units_donor_id ON blood_units(donor_id);
CREATE INDEX IF NOT EXISTS idx_blood_units_status ON blood_units(status);
CREATE INDEX IF NOT EXISTS idx_blood_units_donation_date ON blood_units(donation_date);

-- Row Level Security Policies
-- Allow read access for all users (anon and authenticated)
GRANT SELECT ON admin_users TO anon, authenticated;
GRANT SELECT ON donors TO anon, authenticated;
GRANT SELECT ON blood_units TO anon, authenticated;
GRANT SELECT ON notifications TO anon, authenticated;

-- Allow insert/update/delete for authenticated users
GRANT INSERT, UPDATE, DELETE ON admin_users TO authenticated;
GRANT INSERT, UPDATE, DELETE ON donors TO authenticated;
GRANT INSERT, UPDATE, DELETE ON blood_units TO authenticated;
GRANT INSERT, UPDATE, DELETE ON notifications TO authenticated;

-- Basic RLS Policies (you can customize these based on your needs)
-- For now, allow all authenticated users to access all data
CREATE POLICY "Authenticated users can view all admin_users" ON admin_users FOR SELECT TO authenticated USING (true);
CREATE POLICY "Authenticated users can modify admin_users" ON admin_users FOR ALL TO authenticated USING (true);

CREATE POLICY "Authenticated users can view all donors" ON donors FOR SELECT TO authenticated USING (true);
CREATE POLICY "Authenticated users can modify donors" ON donors FOR ALL TO authenticated USING (true);

CREATE POLICY "Authenticated users can view all blood_units" ON blood_units FOR SELECT TO authenticated USING (true);
CREATE POLICY "Authenticated users can modify blood_units" ON blood_units FOR ALL TO authenticated USING (true);

CREATE POLICY "Authenticated users can view all notifications" ON notifications FOR SELECT TO authenticated USING (true);
CREATE POLICY "Authenticated users can modify notifications" ON notifications FOR ALL TO authenticated USING (true);

-- Allow anonymous users to read (for public access)
CREATE POLICY "Anonymous users can view donors" ON donors FOR SELECT TO anon USING (true);
CREATE POLICY "Anonymous users can view blood_units" ON blood_units FOR SELECT TO anon USING (true);