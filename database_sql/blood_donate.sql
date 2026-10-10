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
-- NOTE: a `role` column has been added so the app can tell
-- regular donors apart from administrators. Every existing
-- and newly registered account defaults to `user`; promote
-- an account to `admin` manually (see the UPDATE statement
-- near the bottom of this file).
--

CREATE TABLE `uinfo` (
  `id` int(10) UNSIGNED NOT NULL,
  `fname` varchar(100) DEFAULT '',
  `lname` varchar(100) DEFAULT '',
  `email` varchar(255) NOT NULL,
  `btype` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `phone` varchar(20) DEFAULT '',
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
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

-- ---------------------------------------------------------------
-- If you already have this database installed and just want to
-- add roles without re-importing everything, run ONLY this part
-- against your existing `blood_donate` database:
-- ---------------------------------------------------------------

-- ALTER TABLE `uinfo`
--   MODIFY `fname` VARCHAR(100) NULL DEFAULT '',
--   MODIFY `lname` VARCHAR(100) NULL DEFAULT '',
--   MODIFY `btype` ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL DEFAULT NULL,
--   MODIFY `phone` VARCHAR(20) NULL DEFAULT '';
--
-- -- Promote whichever account should be an administrator, e.g.:
-- UPDATE `uinfo` SET `role` = 'admin' WHERE `email` = 'jbits@gmail.com';
