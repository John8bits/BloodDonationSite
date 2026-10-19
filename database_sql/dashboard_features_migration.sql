-- Run once after user_panel_migration.sql against an existing database.
-- This adds feed interactions, event dates, preferences, and notifications.

ALTER TABLE `community_posts`
  MODIFY `post_type` enum('community','donation','event','success_story') NOT NULL DEFAULT 'community',
  ADD COLUMN `event_date` date DEFAULT NULL AFTER `content`;

ALTER TABLE `uinfo`
  ADD COLUMN `notify_request_responses` tinyint(1) NOT NULL DEFAULT 1,
  ADD COLUMN `notify_post_comments` tinyint(1) NOT NULL DEFAULT 1;

CREATE TABLE `post_interactions` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `interaction` enum('like','save','report') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`post_id`,`user_id`,`interaction`),
  KEY `post_interactions_user_id` (`user_id`,`interaction`),
  CONSTRAINT `post_interactions_post_fk` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_interactions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `member_notifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `notification_type` enum('request_response','post_comment') NOT NULL,
  `target_id` int(10) UNSIGNED NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_notifications_user_read` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `member_notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_notifications_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `uinfo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
