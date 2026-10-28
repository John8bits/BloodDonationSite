-- Run this once against an existing blood_donate database before using
-- the member dashboard. Fresh installs should use blood_donate.sql.

ALTER TABLE `uinfo`
  ADD COLUMN `city` varchar(120) DEFAULT '' AFTER `phone`,
  ADD COLUMN `donor_available` tinyint(1) NOT NULL DEFAULT 0 AFTER `city`;

CREATE TABLE `community_posts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `post_type` enum('community','donation','event') NOT NULL DEFAULT 'community',
  `content` varchar(1000) NOT NULL,
  `donation_blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `donation_location` varchar(120) DEFAULT NULL,
  `donation_available_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `community_posts_created_at` (`created_at`),
  KEY `community_posts_user_id` (`user_id`),
  CONSTRAINT `community_posts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `blood_requests` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_id` int(10) UNSIGNED NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `hospital` varchar(160) NOT NULL,
  `location` varchar(160) NOT NULL,
  `needed_by` date NOT NULL,
  `urgency` enum('routine','urgent','emergency') NOT NULL DEFAULT 'routine',
  `details` varchar(1000) NOT NULL DEFAULT '',
  `status` enum('open','fulfilled','cancelled') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blood_requests_status_needed_by` (`status`, `needed_by`),
  KEY `blood_requests_requester_id` (`requester_id`),
  CONSTRAINT `blood_requests_requester_fk` FOREIGN KEY (`requester_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `request_responses` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_responses_request_user` (`request_id`, `user_id`),
  KEY `request_responses_user_id` (`user_id`),
  CONSTRAINT `request_responses_request_fk` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_responses_user_fk` FOREIGN KEY (`user_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_comments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_comments_post_created_at` (`post_id`, `created_at`),
  KEY `post_comments_user_id` (`user_id`),
  CONSTRAINT `post_comments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
