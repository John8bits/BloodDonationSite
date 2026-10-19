-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 27, 2026
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `blood_donate`
--

-- --------------------------------------------------------

--
-- Table structure for table `uinfo`
--
-- NOTE: `role` distinguishes member accounts from administrators.
-- Members can request blood or opt in to donor search; every existing
-- and newly registered member defaults to `user`.
--

CREATE TABLE `uinfo` (
  `id` int(10) UNSIGNED NOT NULL,
  `fname` varchar(100) DEFAULT '',
  `lname` varchar(100) DEFAULT '',
  `email` varchar(255) NOT NULL,
  `btype` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `phone` varchar(20) DEFAULT '',
  `city` varchar(120) DEFAULT '',
  `donor_available` tinyint(1) NOT NULL DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `notify_request_responses` tinyint(1) NOT NULL DEFAULT 1,
  `notify_post_comments` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uinfo`
--

INSERT INTO `uinfo` (`id`, `fname`, `lname`, `email`, `btype`, `phone`, `password`, `role`, `created_at`) VALUES
(1, 'Johnrelle', 'Bito', 'jbits@gmail.com', 'O+', '09765422468', '$2y$10$leFPbEt0GuqLDcr6mVQc8etfXGPSY254cvZp5hrlESq/wK6b9gaqC', 'admin', '2026-08-21 03:51:19'),
(2, 'John', 'Doe', 'johndoe@gmail.com', 'B-', '09863437483', '$2y$10$vjGBM6WayKZEfQUsipXqEOX.8cfVPnZCcUdD/43nIFBOJ32rdTS7e', 'user', '2026-08-22 13:49:52'),
(3, 'Johnrelle', 'Bito', 'johnrellebito@gmail.com', 'O+', '09882738292', '$2y$10$Q/VoGXWsHkOqmRMLyDgKS.hV5e4Wfn3Jl3sSmBL.p1D0.7jFUjwhC', 'user', '2026-09-15 14:47:34');

--
-- Indexes for dumped tables
--  

--
-- Indexes for table `uinfo`
--
ALTER TABLE `uinfo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `uinfo`
--
ALTER TABLE `uinfo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

CREATE TABLE `community_posts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `post_type` enum('community','donation','event','success_story') NOT NULL DEFAULT 'community',
  `content` varchar(1000) NOT NULL,
  `event_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `community_posts_created_at` (`created_at`),
  KEY `community_posts_user_id` (`user_id`),
  CONSTRAINT `community_posts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `uinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `blood_requests` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_id` int(10) UNSIGNED NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `hospital` varchar(160) NOT NULL,
  `location` varchar(160) NOT NULL,
  `needed_by` date NOT NULL,
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

-- ---------------------------------------------------------------
-- If you already have this database installed and just want to
-- add roles without re-importing everything, run ONLY this part
-- against your existing `blood_donate` database:
-- The member dashboard also requires the columns and tables in
-- database_sql/user_panel_migration.sql; run that file once as well.
-- ---------------------------------------------------------------

-- ALTER TABLE `uinfo`
--   MODIFY `fname` VARCHAR(100) NULL DEFAULT '',
--   MODIFY `lname` VARCHAR(100) NULL DEFAULT '',
--   MODIFY `btype` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL DEFAULT NULL,
--   MODIFY `phone` VARCHAR(20) NULL DEFAULT '';
--
-- -- Promote whichever account should be an administrator, e.g.:
-- UPDATE `uinfo` SET `role` = 'admin' WHERE `email` = 'jbits@gmail.com';
