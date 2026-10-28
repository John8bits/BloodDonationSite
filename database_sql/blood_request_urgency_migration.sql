-- Add urgency levels to existing blood requests.
-- Run once after user_panel_migration.sql and dashboard_features_migration.sql.

ALTER TABLE `blood_requests`
  ADD COLUMN IF NOT EXISTS `urgency` ENUM('routine','urgent','emergency') NOT NULL DEFAULT 'routine' AFTER `needed_by`;
