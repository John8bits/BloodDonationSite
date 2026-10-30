-- Enable private conversations between members and donation offer authors.
-- Run after donation_offers_migration.sql and post_activity_notifications_migration.sql.

CREATE TABLE IF NOT EXISTS `donation_offer_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int(10) UNSIGNED NOT NULL,
  `seeker_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `message` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `donation_offer_messages_thread` (`post_id`,`seeker_id`,`created_at`),
  KEY `donation_offer_messages_seeker` (`seeker_id`),
  KEY `donation_offer_messages_sender` (`sender_id`),
  CONSTRAINT `donation_offer_messages_post_fk` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `donation_offer_messages_seeker_fk` FOREIGN KEY (`seeker_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `donation_offer_messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `member_notifications`
  MODIFY `notification_type` ENUM('request_response','request_message','post_like','post_comment','post_share','post_save','donation_offer_message') NOT NULL;
