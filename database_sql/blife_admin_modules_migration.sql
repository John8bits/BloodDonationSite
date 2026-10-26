-- Run once after blife_admin_enhancements.sql and the member-panel migrations.
-- Adds fields needed by event publishing and persistent admin settings.

ALTER TABLE `community_posts`
  ADD COLUMN IF NOT EXISTS `event_title` VARCHAR(160) NULL DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `event_time` TIME DEFAULT NULL AFTER `event_date`,
  ADD COLUMN IF NOT EXISTS `organizer` VARCHAR(160) NOT NULL DEFAULT '' AFTER `event_time`,
  ADD COLUMN IF NOT EXISTS `venue` VARCHAR(180) NOT NULL DEFAULT '' AFTER `organizer`,
  ADD COLUMN IF NOT EXISTS `event_city` VARCHAR(120) NOT NULL DEFAULT '' AFTER `venue`,
  ADD COLUMN IF NOT EXISTS `public_contact` VARCHAR(180) NOT NULL DEFAULT '' AFTER `event_city`,
  ADD COLUMN IF NOT EXISTS `event_status` ENUM('published', 'draft', 'cancelled', 'completed') NOT NULL DEFAULT 'published' AFTER `public_contact`;

ALTER TABLE `post_interactions`
  ADD COLUMN IF NOT EXISTS `report_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `interaction`;

CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `updated_by` INT(10) UNSIGNED NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  KEY `platform_settings_updated_by` (`updated_by`),
  CONSTRAINT `platform_settings_admin_fk`
    FOREIGN KEY (`updated_by`) REFERENCES `uinfo` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
