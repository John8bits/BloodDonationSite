-- Add member-managed availability for donation offers.
-- Run once after donation_offers_migration.sql.
-- Existing offers are treated as available.

ALTER TABLE `community_posts`
  ADD COLUMN IF NOT EXISTS `donation_status` ENUM('available','unavailable','completed') NOT NULL DEFAULT 'available' AFTER `donation_available_date`;
