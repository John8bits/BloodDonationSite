-- Adds private structured location and map coordinates for blood requests and donation offers.
-- Coordinates are nullable so existing posts remain readable and editable after migration.

ALTER TABLE blood_requests
    ADD COLUMN IF NOT EXISTS city VARCHAR(120) NOT NULL DEFAULT '' AFTER location,
    ADD COLUMN IF NOT EXISTS barangay VARCHAR(120) NULL DEFAULT NULL AFTER city,
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL DEFAULT NULL AFTER barangay,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL DEFAULT NULL AFTER latitude;

ALTER TABLE community_posts
    ADD COLUMN IF NOT EXISTS donation_province VARCHAR(120) NULL DEFAULT NULL AFTER donation_location,
    ADD COLUMN IF NOT EXISTS donation_city VARCHAR(120) NULL DEFAULT NULL AFTER donation_province,
    ADD COLUMN IF NOT EXISTS donation_barangay VARCHAR(120) NULL DEFAULT NULL AFTER donation_city,
    ADD COLUMN IF NOT EXISTS donation_latitude DECIMAL(10,7) NULL DEFAULT NULL AFTER donation_barangay,
    ADD COLUMN IF NOT EXISTS donation_longitude DECIMAL(10,7) NULL DEFAULT NULL AFTER donation_latitude;
