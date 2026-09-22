-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2026 at 12:50 PM
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
(1, 'fire', 'Tacloban Fire Station', '09951350245', 'Tacloban', 'Tacloban fire station', 1, 1, '2026-04-26 06:24:05'),
(2, 'fire', 'BFP Quezon City District 1', '8925-4826', 'Batasan Hills, Quezon City', 'Covers northern QC area', 0, 2, '2026-04-26 06:24:05'),
(3, 'medical', 'Tacloban City Hospital', '09318359253', 'Tacloban', '', 1, 1, '2026-04-26 06:24:05'),
(4, 'medical', 'Red Cross Philippines', '143', 'Manila', 'Ambulance and disaster response', 0, 2, '2026-04-26 06:24:05'),
(5, 'medical', 'PGH Emergency Room', '8554-8400', 'Taft Ave, Manila', 'Philippine General Hospital ER', 0, 3, '2026-04-26 06:24:05'),
(6, 'police', 'Tacloban Police Office', '09176317752', 'Tacloban', 'tacloban police', 1, 1, '2026-04-26 06:24:05'),
(7, 'police', 'PNP QC District (QCPD)', '8722-0650', 'Camp Karingal, Sikatuna Village, QC', 'Quezon City Police District HQ', 0, 2, '2026-04-26 06:24:05'),
(8, 'police', 'NBI Hotline', '8523-8231', 'NBI Bldg, Taft Ave, Manila', 'National Bureau of Investigation', 0, 3, '2026-04-26 06:24:05'),
(9, 'campus', 'ACLC Campus Security', '0917-000-0001', 'ACLC Campus, Main Gate', 'On-duty 24/7', 0, 1, '2026-04-26 06:24:05'),
(10, 'campus', 'ACLC Clinic / School Nurse', '0917-000-0002', 'ACLC Campus, Admin Building', 'Medical assistance on campus', 0, 2, '2026-04-26 06:24:05'),
(11, 'campus', 'ACLC Admin Office', '0917-000-0003', 'ACLC Campus, Admin Building', 'For administrative emergencies', 0, 3, '2026-04-26 06:24:05'),
(12, 'other', 'Cabrigas, John Narwyne P.', '09152154072', 'Calbiga', 'Dev test contact', 1, 0, '2026-05-03 04:19:41');

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
(4, 'suspicious', 'low', 'laboratory', 'this guy thats not our classmate is looking at us weird', 'narwyne', NULL, 'open', '2026-05-05 19:02:10', NULL),
(5, 'fire', 'high', 'Slab 2', 'Computer just went on flames', 'narwyne', NULL, 'open', '2026-09-13 20:20:58', NULL),
(6, 'fire', 'high', 'Library – Book Shelf 3', 'Book on shelf caught fire', 'narwyne', NULL, 'open', '2026-09-13 21:25:32', NULL),
(7, 'suspicious', 'high', 'Slab 2', 'Active student shooter outside, hiding in Slab 2', 'narwyne', NULL, 'open', '2026-09-13 21:29:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'engineering', 1, 1, '2026-09-16 10:45:33'),
(2, 'admin', 1, 2, '2026-09-16 10:45:33'),
(3, 'library', 1, 3, '2026-09-16 10:45:33'),
(4, 'cafeteria', 1, 4, '2026-09-16 10:45:33'),
(5, 'gymnasium', 1, 5, '2026-09-16 10:45:33'),
(6, 'parking', 1, 6, '2026-09-16 10:45:33'),
(7, 'entrance', 1, 7, '2026-09-16 10:45:33'),
(8, 'laboratory', 1, 8, '2026-09-16 10:45:33'),
(9, 'clinic', 1, 9, '2026-09-16 10:45:33'),
(10, 'comfort_room', 1, 10, '2026-09-16 10:45:33'),
(11, 'grounds', 1, 11, '2026-09-16 10:45:33'),
(12, 'other', 1, 99, '2026-09-16 10:45:33');

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
  `avatar` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `notif_email` tinyint(1) NOT NULL DEFAULT 1,
  `notif_sms` tinyint(1) NOT NULL DEFAULT 0,
  `theme` enum('light','dark') NOT NULL DEFAULT 'light',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `created_at`, `role`, `avatar`, `phone`, `department`, `student_id`, `bio`, `notif_email`, `notif_sms`, `theme`, `updated_at`) VALUES
(1, 'narwyne', 'narwyne', NULL, NULL, 'admin@gmail.com', '$2y$10$FDlQI2QgQEy9Qp7e0LmCV..XnU0pmqGY9ybM.cY5xIFHHJjsq6R8S', '2026-04-19 00:05:43', 'admin', 'uploads/avatars/user_1.jpg', '', 'BSIT', '', 'Hello student narwyne tesing the profile bio', 1, 0, 'light', '2026-05-03 04:34:45'),
(2, 'yotsugi', 'yotsugi', NULL, NULL, 'yotsugiononoki9029@gmail.com', '$2y$10$L.7cqD.zpkSX11VeUsx5IenCktTvpWzj5KhiyzcYbL6KEvErWlXCS', '2026-04-19 00:20:20', 'user', NULL, NULL, NULL, NULL, NULL, 1, 0, 'light', '2026-05-03 04:34:45'),
(6, 'john', 'john', NULL, NULL, 'johnnarwynecabrigas@gmail.com', '$2y$10$zTTJzr7jdDyW0F1WkKDBiuB/xmbP1WK5fYp9KTwxU7grOU6rJdrFO', '2026-04-19 00:47:39', 'user', NULL, NULL, NULL, NULL, NULL, 1, 0, 'light', '2026-05-03 04:34:45'),
(8, 'user', 'user', NULL, NULL, 'user@gmail.com', '$2y$10$BTOJFpuF7HN9Ja28/Ug/Vu1OxJ2Y1BiNOv2neQo3gug6FEU/kXomO', '2026-04-20 05:07:43', 'user', NULL, NULL, NULL, NULL, NULL, 1, 0, 'light', '2026-05-03 04:34:45'),
(12, 'luigi S. bardillion', 'luigi', 'sili', 'bardillion', 'luigi@gmail.com', '$2y$10$vNvyW82b1VipQ9bPPZ7x/en9rcJYp.P7St8gt7p9ydSKrNCGMtVTS', '2026-05-05 03:27:06', 'user', NULL, '', '', '', '', 1, 0, 'light', '2026-05-05 03:30:26');

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
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
