-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2025 at 12:41 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stadium_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `gender` varchar(10) DEFAULT 'Other'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `first_name`, `last_name`, `email`, `password_hash`, `phone`, `created_at`, `updated_at`, `gender`) VALUES
(1, 'Admin', 'System', 'admin@example.com', '$2y$10$Di7KEhabyH9tiWRPK1kXceU/BuJTr7z6yGEKAcTp/2dHvWzFMiaK.', '0812345678', '2025-10-07 07:17:39', '2025-10-07 07:36:05', 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` bigint(20) NOT NULL,
  `member_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'PENDING_PAYMENT',
  `total_price` int(11) NOT NULL,
  `method` enum('QR_CODE') NOT NULL DEFAULT 'QR_CODE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `booking_date` date NOT NULL COMMENT 'วันที่ผู้ใช้จองสนาม'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `member_id`, `status`, `total_price`, `method`, `created_at`, `updated_at`, `booking_date`) VALUES
(61, 9, 'COMPLETED', 50, 'QR_CODE', '2025-10-29 08:20:15', '2025-10-29 08:22:24', '2025-10-29'),
(62, 9, 'PAID_CONFIRMED', 50, 'QR_CODE', '2025-10-29 09:00:02', '2025-10-29 09:00:20', '2025-10-29'),
(63, 9, 'PAID_PENDING_REVIEW', 70, 'QR_CODE', '2025-10-29 09:00:55', '2025-10-29 09:01:20', '2025-10-29'),
(64, 9, 'PAID_CONFIRMED', 50, 'QR_CODE', '2025-10-29 09:05:45', '2025-10-29 09:09:58', '2025-10-29'),
(65, 9, 'CANCELLED_TIMEOUT', 240, 'QR_CODE', '2025-10-29 09:10:35', '2025-10-29 09:15:39', '2025-10-31'),
(66, 9, 'CANCELLED_BY_MEMBER', 70, 'QR_CODE', '2025-10-29 09:12:08', '2025-10-29 09:12:19', '2025-10-29'),
(67, 9, 'CANCELLED_TIMEOUT', 100, 'QR_CODE', '2025-11-06 10:39:15', '2025-11-06 11:05:22', '2025-11-06'),
(68, 9, 'PENDING_PAYMENT', 120, 'QR_CODE', '2025-11-06 11:05:30', '2025-11-06 11:05:30', '2025-11-06');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `item_id` bigint(20) NOT NULL,
  `booking_id` bigint(20) NOT NULL,
  `field_code` varchar(50) NOT NULL,
  `sport_type` enum('football','basketball') NOT NULL,
  `use_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `price` int(11) NOT NULL,
  `start_dt` datetime GENERATED ALWAYS AS (timestamp(`use_date`,`start_time`)) STORED,
  `end_dt` datetime GENERATED ALWAYS AS (timestamp(`use_date`,`end_time`)) STORED
) ;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`item_id`, `booking_id`, `field_code`, `sport_type`, `use_date`, `start_time`, `end_time`, `price`) VALUES
(89, 61, 'F001', 'football', '2025-10-29', '13:00:00', '14:00:00', 50),
(90, 62, 'F002', 'basketball', '2025-10-29', '10:00:00', '11:00:00', 50),
(91, 63, 'F004', 'basketball', '2025-10-29', '13:00:00', '14:00:00', 70),
(92, 64, 'F002', 'basketball', '2025-10-29', '12:00:00', '13:00:00', 50),
(93, 65, 'F003', 'football', '2025-10-31', '09:00:00', '10:00:00', 120),
(94, 65, 'F003', 'football', '2025-10-31', '10:00:00', '11:00:00', 120),
(95, 66, 'F004', 'basketball', '2025-10-29', '20:00:00', '21:00:00', 70),
(96, 67, 'F002', 'basketball', '2025-11-06', '12:00:00', '13:00:00', 50),
(97, 67, 'F002', 'basketball', '2025-11-06', '13:00:00', '14:00:00', 50),
(98, 68, 'F003', 'football', '2025-11-06', '13:00:00', '14:00:00', 120);

-- --------------------------------------------------------

--
-- Table structure for table `field_blackouts`
--

CREATE TABLE `field_blackouts` (
  `blackout_id` bigint(20) NOT NULL,
  `field_code` varchar(50) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `username`, `first_name`, `last_name`, `gender`, `email`, `password_hash`, `phone`, `created_at`, `updated_at`) VALUES
(5, 'kittiphop', 'kittiphop', 'hasyourapong', 'male', 'kittiphop@gmail.com', '$2y$10$E905oO.YIu1Fe9wJA90j9uUCKvvDYLjz1QqaCjYBx0DK6GGO7785a', '0932309958', '2025-09-14 07:55:17', '2025-09-14 07:55:17'),
(6, 'b6540205520', 'kittiphop1', 'hasyourapong', 'male', 'kittiphoph@gmail.com', '$2y$10$HH6qedHvVNcPzXOgRXb7he8P4PSJTc/JBEZvl7FQ/5M73N4AncuQS', '0563486787ก', '2025-09-14 09:11:03', '2025-09-14 09:13:07'),
(8, 'ิb646878a', 'kittiphop2', 'hasyourapong', 'female', 'kittiphob@gmail.com', '$2y$10$.nnYiRqtdoiEtlMXMCMGIe6s3I9lWgCgUgntUZ5U/Yjlll9cNI1VG', '0804483476', '2025-09-22 12:27:22', '2025-09-22 12:27:22'),
(9, 'kittiphop2', 'kittiphop3', 'hasyourapong', 'male', 'kittiphobh@gmail.com', '$2y$10$qSbstluhvL.XKApE0830YOJJxXnqC62.2SdQKq5sndPcZRF1ps/S.', '0981563270', '2025-10-02 03:22:05', '2025-10-15 04:28:17'),
(10, 'user4', 'kittipop', 'hasyourapong5', 'male', 'khasyourapong@gmail.com', '$2y$10$OhdIjq4jY8IPca8lkHJ62uEG246GFfCVfYkWSTZ7eGfVgts2yC06W', '0932309958', '2025-10-15 04:16:01', '2025-10-15 04:16:01');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` bigint(20) NOT NULL,
  `member_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` varchar(50) NOT NULL,
  `booking_id` bigint(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_time` time NOT NULL,
  `slip_path` varchar(255) NOT NULL,
  `transfer_name` varchar(100) NOT NULL,
  `transfer_bank` varchar(100) DEFAULT NULL,
  `status` enum('PENDING_REVIEW','REVIEWED','REJECTED') NOT NULL DEFAULT 'PENDING_REVIEW',
  `reviewed_by` int(11) DEFAULT NULL,
  `slip_url` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `amount`, `payment_date`, `payment_time`, `slip_path`, `transfer_name`, `transfer_bank`, `status`, `reviewed_by`, `slip_url`, `paid_at`, `created_at`, `updated_at`) VALUES
('PAY_6901ceab1f0995.03529078', 61, 50.00, '2025-10-29', '09:21:00', 'slip_61_1761726123.jpg', 'กิตติภพ หาญยูรพงศ์', 'KBank', 'PENDING_REVIEW', NULL, NULL, NULL, '2025-10-29 08:22:03', '2025-10-29 08:22:03'),
('PAY_6901d79ac302f9.09308989', 62, 50.00, '2025-10-29', '10:00:00', 'slip_62_1761728410.jpg', 'กิตติภพ หาญยูรพงศ์', 'SCB', 'PENDING_REVIEW', NULL, NULL, NULL, '2025-10-29 09:00:10', '2025-10-29 09:00:10'),
('PAY_6901d7e0b12848.26595444', 63, 70.00, '2025-10-29', '10:01:00', 'slip_63_1761728480.jpeg', 'กิตติภพ หาญยูรพงศ์', 'KBank', 'PENDING_REVIEW', NULL, NULL, NULL, '2025-10-29 09:01:20', '2025-10-29 09:01:20'),
('PAY_6901d9cc931864.77565674', 64, 50.00, '2025-10-29', '10:09:00', 'slip_64_1761728972.jpeg', 'กิตติภพ หาญยูรพงศ์', 'Krungthai', 'PENDING_REVIEW', NULL, NULL, NULL, '2025-10-29 09:09:32', '2025-10-29 09:09:32');

-- --------------------------------------------------------

--
-- Table structure for table `sports_fields`
--

CREATE TABLE `sports_fields` (
  `field_id` varchar(50) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `sport_type` enum('football','basketball') NOT NULL,
  `open_time` time NOT NULL,
  `close_time` time NOT NULL,
  `price_per_hour` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `sports_fields`
--

INSERT INTO `sports_fields` (`field_id`, `field_name`, `image_path`, `sport_type`, `open_time`, `close_time`, `price_per_hour`, `is_active`, `image_url`, `created_at`, `updated_at`) VALUES
('F001', 'สนามกีฬา 1', 'field_1761478501.jpg', 'football', '09:00:00', '21:00:00', 50, 1, NULL, '2025-10-26 11:35:01', '2025-10-26 11:35:01'),
('F002', 'สนามกีฬา 2', 'field_1761478725.jpeg', 'basketball', '09:00:00', '21:00:00', 50, 1, NULL, '2025-10-26 11:38:45', '2025-10-26 11:38:45'),
('F003', 'สนามกีฬา 3', 'field_1761478800.jpg', 'football', '09:00:00', '21:00:00', 120, 1, NULL, '2025-10-26 11:40:00', '2025-10-29 08:23:46'),
('F004', 'สนามกีฬา 4', 'field_1761726282.jpg', 'basketball', '09:00:00', '21:00:00', 70, 1, NULL, '2025-10-29 08:24:42', '2025-10-29 08:24:42'),
('F005', 'สนามกีฬา 5', 'field_1761729175.jpg', 'basketball', '09:00:00', '21:00:00', 100, 1, NULL, '2025-10-29 09:12:55', '2025-10-29 09:12:55');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_booking_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_booking_summary` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_field_schedule`
-- (See below for the actual view)
--
CREATE TABLE `v_field_schedule` (
);

-- --------------------------------------------------------

--
-- Structure for view `v_booking_summary`
--
DROP TABLE IF EXISTS `v_booking_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Project`@`localhost` SQL SECURITY DEFINER VIEW `v_booking_summary`  AS SELECT `b`.`booking_id` AS `booking_id`, `b`.`member_id` AS `member_id`, `m`.`first_name` AS `first_name`, `m`.`last_name` AS `last_name`, `m`.`email` AS `email`, `b`.`status` AS `booking_status`, `bi`.`field_code` AS `field_code`, `f`.`field_name` AS `field_name`, `bi`.`sport_type` AS `sport_type`, `bi`.`use_date` AS `use_date`, `bi`.`start_time` AS `start_time`, `bi`.`end_time` AS `end_time`, `b`.`total_price` AS `total_price`, `p`.`paid_status` AS `paid_status`, `p`.`paid_at` AS `paid_at` FROM ((((`bookings` `b` join `members` `m` on(`m`.`member_id` = `b`.`member_id`)) join `booking_items` `bi` on(`bi`.`booking_id` = `b`.`booking_id`)) join `sports_fields` `f` on(`f`.`field_code` = `bi`.`field_code`)) left join `payments` `p` on(`p`.`booking_id` = `b`.`booking_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_field_schedule`
--
DROP TABLE IF EXISTS `v_field_schedule`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Project`@`localhost` SQL SECURITY DEFINER VIEW `v_field_schedule`  AS SELECT `f`.`field_code` AS `field_code`, `f`.`field_name` AS `field_name`, `f`.`sport_type` AS `sport_type`, `f`.`open_time` AS `open_time`, `f`.`close_time` AS `close_time`, `bi`.`use_date` AS `use_date`, `bi`.`start_time` AS `start_time`, `bi`.`end_time` AS `end_time`, `b`.`status` AS `status` FROM ((`sports_fields` `f` left join `booking_items` `bi` on(`bi`.`field_code` = `f`.`field_code`)) left join `bookings` `b` on(`b`.`booking_id` = `bi`.`booking_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `fk_booking_member` (`member_id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `uq_field_slot_exact` (`field_code`,`use_date`,`start_time`),
  ADD KEY `fk_item_booking` (`booking_id`);

--
-- Indexes for table `field_blackouts`
--
ALTER TABLE `field_blackouts`
  ADD PRIMARY KEY (`blackout_id`),
  ADD KEY `fk_blackout_field` (`field_code`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `fk_reset_member` (`member_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`);

--
-- Indexes for table `sports_fields`
--
ALTER TABLE `sports_fields`
  ADD PRIMARY KEY (`field_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `item_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `field_blackouts`
--
ALTER TABLE `field_blackouts`
  MODIFY `blackout_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE;

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `fk_item_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_item_field` FOREIGN KEY (`field_code`) REFERENCES `sports_fields` (`field_id`) ON UPDATE CASCADE;

--
-- Constraints for table `field_blackouts`
--
ALTER TABLE `field_blackouts`
  ADD CONSTRAINT `fk_blackout_field` FOREIGN KEY (`field_code`) REFERENCES `sports_fields` (`field_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_reset_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`Project`@`localhost` EVENT `ev_auto_cancel_unpaid` ON SCHEDULE EVERY 1 MINUTE STARTS '2025-08-23 11:25:14' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
  UPDATE bookings b
  LEFT JOIN payments p ON p.booking_id = b.booking_id
  SET b.status = 'CANCELLED_TIMEOUT'
  WHERE b.status = 'PENDING_PAYMENT'
    AND TIMESTAMPDIFF(MINUTE, b.created_at, NOW()) > 15;
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
