-- Adds municipality and barangay selections to member profiles.
-- The existing uinfo.city column continues to store the member's province
-- for compatibility with donor discovery and older account records.
ALTER TABLE uinfo
  ADD COLUMN IF NOT EXISTS municipality VARCHAR(120) NOT NULL DEFAULT '' AFTER city,
  ADD COLUMN IF NOT EXISTS barangay VARCHAR(120) NOT NULL DEFAULT '' AFTER municipality;

