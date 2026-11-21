-- Track that clinic staff confirmed permission to represent another person.
-- No represented person's name or contact information is stored by this change.
-- Run once after the clinic account, blood request, and donation offer migrations.

ALTER TABLE `blood_requests`
  ADD COLUMN IF NOT EXISTS `representation_authorized_at` DATETIME DEFAULT NULL;

ALTER TABLE `community_posts`
  ADD COLUMN IF NOT EXISTS `representation_authorized_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `donation_completed_at` DATETIME DEFAULT NULL;
