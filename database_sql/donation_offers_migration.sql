-- Add structured member-to-member donation offers to an existing BLife PH database.
-- Run once after user_panel_migration.sql and dashboard_features_migration.sql.
-- Existing donation announcements remain valid; their new offer details are NULL.

ALTER TABLE `community_posts`
  ADD COLUMN IF NOT EXISTS `donation_blood_type` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `donation_location` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `donation_available_date` DATE DEFAULT NULL;

-- Donation offers use the existing community_posts table and post_type='donation'.
-- Members remain one account role and can continue to create blood requests.
