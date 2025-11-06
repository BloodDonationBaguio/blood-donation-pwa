-- 2025-11-06-align-schema.sql
-- Align schema for PostgreSQL: essential columns first, then views

BEGIN;

-- Essential columns
ALTER TABLE IF EXISTS admin_users
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NULL;
UPDATE admin_users SET updated_at = NOW() WHERE updated_at IS NULL;

ALTER TABLE IF EXISTS donors
  ADD COLUMN IF NOT EXISTS reference_number TEXT;
UPDATE donors
SET reference_number = COALESCE(reference_code, 'DN-' || LPAD(id::text, 6, '0'))
WHERE reference_number IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS donors_reference_number_unique
  ON donors(reference_number) WHERE reference_number IS NOT NULL;

ALTER TABLE IF EXISTS donor_medical_screening_simple
  ADD COLUMN IF NOT EXISTS hemoglobin_level NUMERIC(4,1),
  ADD COLUMN IF NOT EXISTS blood_pressure TEXT,
  ADD COLUMN IF NOT EXISTS medical_condition TEXT;

ALTER TABLE IF EXISTS blood_units
  ADD COLUMN IF NOT EXISTS rh_factor TEXT,
  ADD COLUMN IF NOT EXISTS collection_date DATE;

ALTER TABLE IF EXISTS blood_inventory
  ADD COLUMN IF NOT EXISTS rh_factor TEXT,
  ADD COLUMN IF NOT EXISTS collection_date DATE;

ALTER TABLE IF EXISTS donations_new
  ADD COLUMN IF NOT EXISTS unit_id INTEGER,
  ADD COLUMN IF NOT EXISTS donated_at TIMESTAMPTZ;

-- Views: create after column additions
CREATE OR REPLACE VIEW blood_inventory_summary AS
SELECT
  blood_type,
  COALESCE(rh_factor,'-') AS rh_factor,
  COUNT(*) FILTER (WHERE deleted_at IS NULL) AS total_units,
  COUNT(*) FILTER (WHERE deleted_at IS NULL AND expiry_date > NOW()) AS available_units,
  COUNT(*) FILTER (WHERE deleted_at IS NULL AND expiry_date <= NOW() + INTERVAL '7 days') AS expiring_7d
FROM blood_inventory
GROUP BY blood_type, COALESCE(rh_factor,'-');

CREATE OR REPLACE VIEW expiring_blood_units AS
SELECT id, blood_type, rh_factor, expiry_date, storage_location
FROM blood_inventory
WHERE deleted_at IS NULL AND expiry_date <= NOW() + INTERVAL '7 days'
ORDER BY expiry_date ASC;

-- Compatibility view for audit logs
CREATE OR REPLACE VIEW admin_audit_log_compat AS
SELECT id,
       NULL::INTEGER AS admin_user_id,
       action_type   AS action,
       description   AS details,
       created_at
FROM admin_audit_log;

COMMIT;