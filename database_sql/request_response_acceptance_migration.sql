-- Require requesters to accept an offer before private messaging is available.
-- Run after user_panel_migration.sql and request_response_messages_migration.sql.
-- Existing responses stay accepted so their existing conversations remain available.

ALTER TABLE `request_responses`
  ADD COLUMN `status` enum('pending','accepted') NOT NULL DEFAULT 'accepted' AFTER `message`;
