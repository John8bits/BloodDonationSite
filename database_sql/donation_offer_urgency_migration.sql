-- Add self-reported urgency to new donation offer conversations.
-- Run after donation_offer_messages_migration.sql on existing installations.

ALTER TABLE `donation_offer_messages`
  ADD COLUMN IF NOT EXISTS `urgency` enum('routine','urgent','emergency') DEFAULT NULL AFTER `message`;
