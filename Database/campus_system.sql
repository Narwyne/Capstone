-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2026 at 01:43 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `campus_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `emergency_services`
--

CREATE TABLE `emergency_services` (
  `id` int(10) UNSIGNED NOT NULL,
  `category` enum('fire','medical','police','campus','other') NOT NULL,
  `name` varchar(150) NOT NULL,
  `number` varchar(50) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emergency_services`
--

INSERT INTO `emergency_services` (`id`, `category`, `name`, `number`, `address`, `description`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'fire', 'Bureau of Fire Protection (National)', '160', NULL, 'National fire emergency hotline', 1, 1, '2026-04-26 14:24:05'),
(2, 'fire', 'BFP Quezon City District 1', '8925-4826', 'Batasan Hills, Quezon City', 'Covers northern QC area', 1, 2, '2026-04-26 14:24:05'),
(3, 'medical', 'National Emergency Hotline', '911', NULL, 'All-in-one emergency dispatch', 1, 1, '2026-04-26 14:24:05'),
(4, 'medical', 'Red Cross Philippines', '143', 'Manila', 'Ambulance and disaster response', 1, 2, '2026-04-26 14:24:05'),
(5, 'medical', 'PGH Emergency Room', '8554-8400', 'Taft Ave, Manila', 'Philippine General Hospital ER', 1, 3, '2026-04-26 14:24:05'),
(6, 'police', 'PNP Emergency Hotline', '117', NULL, 'Philippine National Police', 1, 1, '2026-04-26 14:24:05'),
(7, 'police', 'PNP QC District (QCPD)', '8722-0650', 'Camp Karingal, Sikatuna Village, QC', 'Quezon City Police District HQ', 1, 2, '2026-04-26 14:24:05'),
(8, 'police', 'NBI Hotline', '8523-8231', 'NBI Bldg, Taft Ave, Manila', 'National Bureau of Investigation', 1, 3, '2026-04-26 14:24:05'),
(9, 'campus', 'ACLC Campus Security', '0917-000-0001', 'ACLC Campus, Main Gate', 'On-duty 24/7', 1, 1, '2026-04-26 14:24:05'),
(10, 'campus', 'ACLC Clinic / School Nurse', '0917-000-0002', 'ACLC Campus, Admin Building', 'Medical assistance on campus', 1, 2, '2026-04-26 14:24:05'),
(11, 'campus', 'ACLC Admin Office', '0917-000-0003', 'ACLC Campus, Admin Building', 'For administrative emergencies', 1, 3, '2026-04-26 14:24:05'),
(12, 'other', 'Cabrigas, John Narwyne P.', '09152154072', 'Calbiga', 'Dev test contact', 1, 0, '2026-05-03 12:19:41');

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL,
  `location` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `reported_by` varchar(100) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `incident_type`, `severity`, `location`, `description`, `reported_by`, `photo_path`, `status`, `reported_at`, `updated_at`) VALUES
(1, 'fire', 'high', 'laboratory', 'Slab 2, one of the computer just when ablaze', 'Anonymous', 'uploads/incidents/inc_69ee195e1196c4.75006270.png', 'resolved', '2026-04-26 21:55:42', '2026-04-26 22:06:25'),
(2, 'medical', 'medium', 'library', 'asdasdasdasdasdas', 'yotsugi', NULL, 'resolved', '2026-04-27 17:10:40', '2026-04-27 22:27:33'),
(3, 'accident', 'critical', 'entrance', 'Na dismayo la tigda adi na student', 'narwyne ', NULL, 'open', '2026-05-01 15:58:49', NULL),
(4, 'suspicious', 'low', 'laboratory', 'this guy thats not our classmate is looking at us weird', 'narwyne', NULL, 'open', '2026-05-05 19:02:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `first_name` varchar(80) DEFAULT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('user','admin') DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded avatar image',
  `phone` varchar(30) DEFAULT NULL COMMENT 'Contact number',
  `department` varchar(100) DEFAULT NULL COMMENT 'Department / course',
  `student_id` varchar(50) DEFAULT NULL COMMENT 'Student or employee ID',
  `bio` text DEFAULT NULL COMMENT 'Short bio',
  `notif_email` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Email notifications on/off',
  `notif_sms` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'SMS notifications on/off',
  `theme` enum('light','dark') NOT NULL DEFAULT 'light',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `created_at`, `role`, `avatar`, `phone`, `department`, `student_id`, `bio`, `notif_email`, `notif_sms`, `theme`, `updated_at`) VALUES
(1, 'narwyne', 'narwyne', NULL, NULL, 'admin@gmail.com', '$2y$10$FDlQI2QgQEy9Qp7e0LmCV..XnU0pmqGY9ybM.cY5xIFHHJjsq6R8S', '2026-04-19 08:05:43', 'admin', 'uploads/avatars/user_1.jpg', '', 'BSIT', '', 'Hello student narwyne tesing the profile bio', 1, 0, 'light', '2026-05-03 12:34:45'),
(2, 'yotsugi', 'yotsugi', NULL, NULL, 'yotsugiononoki9029@gmail.com', '$2y$10$L.7cqD.zpkSX11VeUsx5IenCktTvpWzj5KhiyzcYbL6KEvErWlXCS', '2026-04-19 08:20:20', 'user', NULL, NULL, NULL, NULL, NULL, 1, 0, 'light', '2026-05-03 12:34:45'),
(6, 'john', 'john', NULL, NULL, 'johnnarwynecabrigas@gmail.com', '$2y$10$zTTJzr7jdDyW0F1WkKDBiuB/xmbP1WK5fYp9KTwxU7grOU6rJdrFO', '2026-04-19 08:47:39', 'user', NULL, NULL, NULL, NULL, NULL, 1, 0, 'light', '2026-05-03 12:34:45'),
(8, 'user', 'user', NULL, NULL, 'user@gmail.com', '$2y$10$BTOJFpuF7HN9Ja28/Ug/Vu1OxJ2Y1BiNOv2neQo3gug6FEU/kXomO', '2026-04-20 13:07:43', 'user', NULL, NULL, NULL, NULL, NULL, 1, 0, 'light', '2026-05-03 12:34:45'),
(12, 'luigi S. bardillion', 'luigi', 'sili', 'bardillion', 'luigi@gmail.com', '$2y$10$vNvyW82b1VipQ9bPPZ7x/en9rcJYp.P7St8gt7p9ydSKrNCGMtVTS', '2026-05-05 11:27:06', 'user', NULL, '', '', '', '', 1, 0, 'light', '2026-05-05 11:30:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `emergency_services`
--
ALTER TABLE `emergency_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_reported_at` (`reported_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `emergency_services`
--
ALTER TABLE `emergency_services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
