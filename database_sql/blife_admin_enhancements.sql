-- Safe migration for BLife PH admin and moderation features.
-- Run this once against the existing blood_donate database.
-- It adds account status tracking, richer blood request lifecycle data,
-- reusable event moderation fields, and an audit trail for admin actions.

ALTER TABLE `uinfo`
  ADD COLUMN `account_status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active' AFTER `role`,
  ADD COLUMN `suspended_at` TIMESTAMP NULL DEFAULT NULL AFTER `account_status`,
  ADD COLUMN `suspended_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `suspended_at`;

ALTER TABLE `blood_requests`
  MODIFY `status` ENUM('open', 'fulfilled', 'cancelled', 'expired') NOT NULL DEFAULT 'open',
  ADD COLUMN `admin_note` VARCHAR(500) NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN `reviewed_by` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `admin_note`,
  ADD COLUMN `reviewed_at` TIMESTAMP NULL DEFAULT NULL AFTER `reviewed_by`,
  ADD COLUMN `status_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `reviewed_at`;

ALTER TABLE `community_posts`
  ADD COLUMN `title` VARCHAR(160) NULL DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `status` ENUM('published', 'draft', 'cancelled', 'completed') NOT NULL DEFAULT 'published' AFTER `event_date`;

CREATE TABLE IF NOT EXISTS `admin_activity_log` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT(10) UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `affected_table` VARCHAR(100) NOT NULL,
  `affected_record_id` INT(10) UNSIGNED NOT NULL,
  `action_details` TEXT DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_activity_log_admin_id` (`admin_id`),
  KEY `admin_activity_log_created_at` (`created_at`),
  CONSTRAINT `admin_activity_log_admin_fk`
    FOREIGN KEY (`admin_id`) REFERENCES `uinfo` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional: normalise previously created records to active if they do not yet have a status.
UPDATE `uinfo`
SET `account_status` = 'active'
WHERE `account_status` IS NULL;

-- Optional: ensure old requests have a valid status and do not create invalid data.
UPDATE `blood_requests`
SET `status` = 'open'
WHERE `status` NOT IN ('open', 'fulfilled', 'cancelled', 'expired');
