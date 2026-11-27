-- Run after clinic_accounts_migration.sql.
-- Adds an optional PSGC-selected barangay for institutional facility locations.
ALTER TABLE clinic_profiles
  ADD COLUMN barangay VARCHAR(120) NULL DEFAULT NULL AFTER city;
