-- Run after the existing user panel and admin enhancement migrations.
-- Extends the existing account table; existing member/admin rows are preserved.

ALTER TABLE `uinfo`
  MODIFY `role` ENUM('user','admin','clinic') NOT NULL DEFAULT 'user',
  ADD COLUMN `profile_completed_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`,
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `profile_completed_at`;

CREATE TABLE IF NOT EXISTS `clinic_profiles` (
  `user_id` INT(10) UNSIGNED NOT NULL,
  `facility_name` VARCHAR(180) NOT NULL,
  `facility_type` ENUM('clinic','blood_bank') NOT NULL,
  `facility_address` VARCHAR(255) NOT NULL,
  `city` VARCHAR(120) NOT NULL,
  `province` VARCHAR(120) NOT NULL,
  `contact_number` VARCHAR(30) NOT NULL,
  `official_email` VARCHAR(255) NOT NULL,
  `authorized_representative` VARCHAR(160) NOT NULL,
  `description` VARCHAR(1000) NOT NULL DEFAULT '',
  `created_by` INT(10) UNSIGNED NOT NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `clinic_profiles_creator` (`created_by`),
  KEY `clinic_profiles_location` (`province`,`city`),
  CONSTRAINT `clinic_profiles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinic_profiles_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `uinfo` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE `uinfo`
SET `profile_completed_at` = COALESCE(`profile_completed_at`, CURRENT_TIMESTAMP)
WHERE `role` = 'user'
  AND TRIM(COALESCE(`fname`,'')) <> ''
  AND TRIM(COALESCE(`lname`,'')) <> ''
  AND TRIM(COALESCE(`phone`,'')) <> ''
  AND `btype` IS NOT NULL
  AND TRIM(COALESCE(`city`,'')) <> '';
