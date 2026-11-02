-- Add inbox read state, message reporting, donor verification, and optional offer times.
-- Run after donation_offer_messages_migration.sql and the member panel migrations.

ALTER TABLE `uinfo`
  ADD COLUMN IF NOT EXISTS `donor_verified` tinyint(1) NOT NULL DEFAULT 0;

ALTER TABLE `community_posts`
  ADD COLUMN IF NOT EXISTS `donation_available_time` time DEFAULT NULL;

ALTER TABLE `request_response_messages`
  ADD COLUMN IF NOT EXISTS `read_at` timestamp NULL DEFAULT NULL;

UPDATE `request_response_messages`
SET `read_at` = `created_at`
WHERE `read_at` IS NULL;

ALTER TABLE `donation_offer_messages`
  ADD COLUMN IF NOT EXISTS `read_at` timestamp NULL DEFAULT NULL;

UPDATE `donation_offer_messages`
SET `read_at` = `created_at`
WHERE `read_at` IS NULL;

CREATE TABLE IF NOT EXISTS `member_message_reports` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` int(10) UNSIGNED NOT NULL,
  `message_type` enum('donation_offer','request_response') NOT NULL,
  `message_id` int(10) UNSIGNED NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('pending','reviewed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_message_reports_once` (`reporter_id`,`message_type`,`message_id`),
  KEY `member_message_reports_status` (`status`,`created_at`),
  CONSTRAINT `member_message_reports_reporter_fk`
    FOREIGN KEY (`reporter_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_message_reports_admin_fk`
    FOREIGN KEY (`reviewed_by`) REFERENCES `uinfo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
