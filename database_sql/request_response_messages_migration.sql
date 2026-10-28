-- Enable private replies between a blood requester and each person who responds.
-- Run after user_panel_migration.sql and dashboard_features_migration.sql.

CREATE TABLE IF NOT EXISTS `request_response_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `response_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `message` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_response_messages_thread` (`response_id`,`created_at`),
  KEY `request_response_messages_sender` (`sender_id`),
  CONSTRAINT `request_response_messages_response_fk` FOREIGN KEY (`response_id`) REFERENCES `request_responses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_response_messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `member_notifications`
  MODIFY `notification_type` ENUM('request_response','request_message','post_comment') NOT NULL;
