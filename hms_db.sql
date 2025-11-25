-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 07:12 PM
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
-- Database: `hms_db`
--
CREATE DATABASE IF NOT EXISTS `hms_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hms_db`;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--
-- Creation: Nov 25, 2025 at 03:11 PM
-- Last update: Nov 25, 2025 at 05:43 PM
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent') DEFAULT 'present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `attendance`:
--   `student_id`
--       `students` -> `id`
--

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `date`, `status`) VALUES
(1, 1, '2025-11-16', 'present'),
(2, 1, '2025-11-18', 'present'),
(6, 1, '2025-11-22', 'present'),
(7, 1, '2025-11-23', 'present'),
(8, 1, '2025-11-24', 'present'),
(9, 1, '2025-11-25', 'present'),
(10, 1, '2025-11-26', 'absent'),
(11, 1, '2025-11-27', 'absent'),
(12, 1, '2025-11-28', 'absent'),
(13, 1, '2025-11-29', 'absent'),
(14, 1, '2025-11-30', 'absent'),
(15, 1, '2025-12-01', 'absent'),
(18, 6, '2025-11-16', 'present'),
(30, 6, '2025-11-18', 'absent'),
(36, 6, '2025-11-22', 'present'),
(38, 6, '2025-11-23', 'present'),
(40, 6, '2025-11-24', 'present'),
(42, 6, '2025-11-25', 'present'),
(45, 7, '2025-11-16', 'absent'),
(52, 1, '2025-11-20', 'present'),
(53, 6, '2025-11-20', 'present'),
(54, 7, '2025-11-20', 'present'),
(58, 6, '2025-11-19', 'present'),
(73, 1, '2025-11-17', 'present'),
(74, 6, '2025-11-17', 'present'),
(75, 7, '2025-11-17', 'present'),
(87, 1, '2025-11-19', 'present'),
(90, 7, '2025-11-19', 'present'),
(127, 2, '2025-11-19', 'present'),
(129, 2, '2025-11-20', 'present'),
(130, 2, '2025-11-22', 'absent'),
(131, 2, '2025-11-23', 'absent'),
(132, 2, '2025-11-24', 'absent'),
(133, 2, '2025-11-25', 'absent'),
(145, 7, '2025-11-18', 'absent'),
(147, 1, '2025-11-21', 'absent'),
(148, 6, '2025-11-21', 'absent'),
(149, 7, '2025-11-21', 'absent');

-- --------------------------------------------------------

--
-- Table structure for table `cleaning_requests`
--
-- Creation: Nov 25, 2025 at 03:11 PM
--

DROP TABLE IF EXISTS `cleaning_requests`;
CREATE TABLE `cleaning_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `room_no` varchar(10) NOT NULL,
  `issue` text NOT NULL,
  `preferred_date` date DEFAULT NULL,
  `applied_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `action_by` int(11) DEFAULT NULL,
  `action_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `cleaning_requests`:
--   `student_id`
--       `students` -> `id`
--   `action_by`
--       `users` -> `id`
--

--
-- Dumping data for table `cleaning_requests`
--

INSERT INTO `cleaning_requests` (`id`, `student_id`, `room_no`, `issue`, `preferred_date`, `applied_at`, `status`, `requested_at`, `action_by`, `action_at`) VALUES
(1, 1, '101', 'Floor not Cleaned...', '2025-11-17', '2025-11-16 23:11:45', 'approved', '2025-11-16 18:12:37', 3, '2025-11-16 18:12:53'),
(2, 6, '101', 'floor cleaning', '2025-11-17', '2025-11-16 23:11:45', 'approved', '2025-11-16 22:50:13', 3, '2025-11-16 22:53:30'),
(3, 1, '101', 'bvxz', '2025-11-06', '2025-11-20 01:25:13', 'rejected', '2025-11-20 01:25:13', 3, '2025-11-20 16:38:41'),
(4, 1, '101', 'heloo', '2025-11-21', '2025-11-20 01:25:41', 'approved', '2025-11-20 01:25:41', 3, '2025-11-20 10:44:36'),
(5, 1, '101', 'sadad', '2025-11-21', '2025-11-20 01:25:53', 'approved', '2025-11-20 01:25:53', 3, '2025-11-20 10:44:18'),
(6, 1, '101', 'floor', '2025-11-07', '2025-11-20 10:51:07', 'pending', '2025-11-20 10:51:07', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--
-- Creation: Nov 25, 2025 at 03:11 PM
--

DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','resolved') DEFAULT 'open',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `complaints`:
--   `student_id`
--       `students` -> `id`
--   `resolved_by`
--       `users` -> `id`
--

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `student_id`, `subject`, `message`, `status`, `submitted_at`, `resolved_by`, `resolved_at`) VALUES
(1, 1, 'functionality problem', 'Tap not working', 'resolved', '2025-11-16 21:05:14', 3, '2025-11-16 21:33:02'),
(2, 1, 'functionality problem', 'Tap not working', 'resolved', '2025-11-16 21:08:24', 3, '2025-11-16 21:33:01'),
(3, 1, 'functionality problem', 'Tap not working', 'resolved', '2025-11-16 21:08:41', 3, '2025-11-16 21:33:01'),
(4, 1, 'functionality problem', 'Tap not working', 'resolved', '2025-11-16 21:12:00', 3, '2025-11-16 21:32:59'),
(5, 1, 'functionality problem', 'Tap not working', 'resolved', '2025-11-16 21:14:17', 3, '2025-11-16 21:32:58'),
(6, 1, 'tap', 'not working', 'resolved', '2025-11-16 21:52:56', 3, '2025-11-16 21:54:33'),
(7, 6, 'functionality problem', 'tap not working', 'resolved', '2025-11-16 22:51:15', 3, '2025-11-17 09:21:35'),
(9, 1, 'com', 'asca', 'resolved', '2025-11-20 01:27:34', 3, '2025-11-20 16:38:55');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--
-- Creation: Nov 25, 2025 at 03:11 PM
--

DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `action_by` int(11) DEFAULT NULL,
  `action_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `leave_requests`:
--   `student_id`
--       `students` -> `id`
--   `action_by`
--       `users` -> `id`
--

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `student_id`, `from_date`, `to_date`, `reason`, `status`, `applied_at`, `action_by`, `action_at`) VALUES
(1, 1, '2025-11-18', '2025-11-18', 'family function', 'approved', '2025-11-16 04:34:56', 3, '2025-11-16 04:39:48'),
(2, 2, '2025-11-22', '2025-11-25', 'personal reasons', 'approved', '2025-11-16 04:41:44', 44, '2025-11-20 11:11:26'),
(3, 1, '2025-11-22', '2025-12-01', 'personal reasons', 'approved', '2025-11-16 04:47:00', 3, '2025-11-16 04:47:14'),
(4, 6, '2025-11-19', '2025-11-19', 'Health issues', 'rejected', '2025-11-16 04:58:58', 3, '2025-11-16 05:01:21'),
(5, 6, '2025-11-19', '2025-11-20', 'family function', 'approved', '2025-11-16 17:19:27', 3, '2025-11-16 17:23:26'),
(7, 1, '2025-11-22', '2025-11-23', 'personal', 'pending', '2025-11-19 19:57:00', NULL, NULL);

--
-- Triggers `leave_requests`
--
DROP TRIGGER IF EXISTS `auto_mark_absent_on_leave`;
DELIMITER $$
CREATE TRIGGER `auto_mark_absent_on_leave` AFTER UPDATE ON `leave_requests` FOR EACH ROW BEGIN
    IF NEW.status = 'approved' AND OLD.status = 'pending' THEN
        SET @current_date = NEW.from_date;
        SET @end_date = NEW.to_date;

        WHILE @current_date <= @end_date DO
            INSERT INTO attendance (student_id, date, status)
            VALUES (NEW.student_id, @current_date, 'absent')
            ON DUPLICATE KEY UPDATE status = 'absent';

            SET @current_date = DATE_ADD(@current_date, INTERVAL 1 DAY);
        END WHILE;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `mess_bills`
--
-- Creation: Nov 25, 2025 at 03:11 PM
-- Last update: Nov 25, 2025 at 06:08 PM
--

DROP TABLE IF EXISTS `mess_bills`;
CREATE TABLE `mess_bills` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `month` varchar(7) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `mess_bills`:
--   `student_id`
--       `students` -> `id`
--   `uploaded_by`
--       `users` -> `id`
--

--
-- Triggers `mess_bills`
--
DROP TRIGGER IF EXISTS `deduct_mess_balance`;
DELIMITER $$
CREATE TRIGGER `deduct_mess_balance` AFTER INSERT ON `mess_bills` FOR EACH ROW BEGIN
    UPDATE students 
    SET mess_balance = mess_balance - NEW.amount 
    WHERE id = NEW.student_id 
    AND mess_balance >= NEW.amount;
    
    -- Optional: Prevent negative balance
    UPDATE students 
    SET mess_balance = 0 
    WHERE id = NEW.student_id 
    AND mess_balance < 0;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--
-- Creation: Nov 25, 2025 at 03:11 PM
--

DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `posted_by` int(11) NOT NULL,
  `block` varchar(10) DEFAULT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `visible_to` enum('all','students','wardens') DEFAULT 'all',
  `target_audience` varchar(20) DEFAULT 'both'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `notices`:
--   `posted_by`
--       `users` -> `id`
--

--
-- Dumping data for table `notices`
--

INSERT INTO `notices` (`id`, `title`, `content`, `posted_by`, `block`, `posted_at`, `visible_to`, `target_audience`) VALUES
(6, 'Good morning', 'Today is Monday', 1, NULL, '2025-11-17 02:00:39', 'all', 'both'),
(7, 'Meeting', 'Today at 9', 3, 'A', '2025-11-17 02:11:45', 'all', 'both'),
(8, 'Warning', 'everyone has to be in hostel before 7\r\n', 1, NULL, '2025-11-17 03:47:58', 'all', 'both'),
(13, 'dear students', 'mess will close early today', 3, 'A', '2025-11-20 18:10:23', 'all', 'students'),
(15, 'hello', 'everyone', 3, 'A', '2025-11-20 18:19:23', 'all', 'both');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--
-- Creation: Nov 25, 2025 at 03:44 PM
-- Last update: Nov 25, 2025 at 05:43 PM
--

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_no` varchar(10) NOT NULL,
  `block` varchar(10) NOT NULL,
  `capacity` int(11) DEFAULT 4,
  `occupied` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `rooms`:
--

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_no`, `block`, `capacity`, `occupied`) VALUES
(1, '101', 'A', 4, 2),
(2, '102', 'A', 4, 0),
(3, '103', 'A', 4, 0),
(4, '201', 'A', 4, 0),
(5, '202', 'A', 4, 0),
(26, '101', 'B', 4, 1),
(27, '102', 'B', 4, 0),
(28, '103', 'B', 4, 0),
(29, '104', 'B', 4, 0),
(30, '201', 'B', 4, 0),
(31, '202', 'B', 4, 0),
(32, '203', 'B', 4, 0),
(33, '204', 'B', 4, 0),
(34, '301', 'B', 4, 0),
(35, '302', 'B', 4, 0),
(36, '101', 'C', 4, 0),
(37, '102', 'C', 4, 0),
(38, '103', 'C', 4, 0),
(39, '104', 'C', 4, 0),
(40, '201', 'C', 4, 0),
(41, '202', 'C', 4, 0),
(42, '203', 'C', 4, 0),
(43, '204', 'C', 4, 0),
(44, '301', 'C', 4, 0),
(45, '302', 'C', 4, 0),
(46, '104', 'A', 4, 1),
(47, '203', 'A', 4, 0),
(48, '204', 'A', 4, 0),
(49, '301', 'A', 4, 0),
(50, '302', 'A', 4, 0);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--
-- Creation: Nov 25, 2025 at 05:25 PM
-- Last update: Nov 25, 2025 at 06:08 PM
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `register_no` varchar(20) NOT NULL,
  `room_no` varchar(10) DEFAULT NULL,
  `block` varchar(10) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `branch` varchar(50) DEFAULT NULL,
  `mess_balance` decimal(10,2) DEFAULT 25000.00,
  `fee_status` enum('paid','not_paid') DEFAULT 'not_paid',
  `fee_paid_by` int(11) DEFAULT NULL,
  `fee_paid_at` datetime DEFAULT NULL,
  `semester_mess_balance` decimal(10,2) DEFAULT 25000.00,
  `semester_rent` decimal(10,2) DEFAULT 50000.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `students`:
--   `block`
--       `rooms` -> `block`
--   `room_no`
--       `rooms` -> `room_no`
--   `user_id`
--       `users` -> `id`
--   `user_id`
--       `users` -> `id`
--   `fee_paid_by`
--       `users` -> `id`
--

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `name`, `phone`, `email`, `dob`, `address`, `register_no`, `room_no`, `block`, `year`, `branch`, `mess_balance`, `fee_status`, `fee_paid_by`, `fee_paid_at`, `semester_mess_balance`, `semester_rent`) VALUES
(1, 2, 'Greeshma H Bhandary', '9481508834', 'greeshmahbhandary2005@gmail.com', '2005-04-05', 'Mangalore', 'J023', '101', 'A', '3rd Year', 'AIDS', 21000.00, 'paid', 1, '2025-11-16 11:36:22', 25000.00, 50000.00),
(2, 4, 'Manvi Anchan', '9995218803', 'manvianchan@gmail.com', '2005-03-20', 'Kasargod', 'J028', '101', 'B', '2nd Year', 'CSE', 20500.00, 'paid', 1, '2025-11-16 11:36:25', 25000.00, 50000.00),
(6, 8, 'Nishmitha', '8105800534', 'nishmitha@gmail.com', '2005-04-27', 'Kapu', 'J032', '104', 'A', '4th Year', 'ECE', 25000.00, 'paid', 1, '2025-11-16 22:56:00', 25000.00, 50000.00),
(7, 11, 'Thrisha Santhosh', '7338474522', 'thrishasanthosh@gmail.com', '2005-11-07', 'Mangalore', 'J066', '101', 'A', '1st Year', 'IT', 25000.00, 'paid', 1, '2025-11-20 16:36:13', 25000.00, 50000.00),
(19, 43, 'Diya', '7834431359', 'megha@gmail.com', '2004-06-20', 'Karkala', 'J096', NULL, 'C', '4th Year', 'MECH', 22000.00, 'paid', 1, '2025-11-21 14:50:00', 25000.00, 50000.00),
(31, 59, 'Shreya', '7654324598', 'shreya4@gmail.com', '2006-09-03', 'Manipal', 'J006', NULL, 'A', '2nd Year', 'MECH', 25000.00, 'not_paid', NULL, NULL, 25000.00, 50000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--
-- Creation: Nov 25, 2025 at 03:11 PM
-- Last update: Nov 25, 2025 at 06:08 PM
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','warden','office') NOT NULL,
  `first_login` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `users`:
--

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `first_login`, `created_at`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'office', 0, '2025-11-16 03:37:19'),
(2, 'J023', '1958b4ad94aaf4e3589e7606fef99277', 'student', 0, '2025-11-16 04:01:39'),
(3, 'wardenA', 'a6e4b408c8d87fa54412db5204fc4f7e', 'warden', 0, '2025-11-16 04:07:00'),
(4, 'J028', '0de359c5e02fba87cd9843c1816e1930', 'student', 0, '2025-11-16 04:18:30'),
(8, 'J032', '7508b31588a144b52569855e196caf12', 'student', 0, '2025-11-16 04:54:45'),
(11, 'J066', 'fcaeccf97a7c0a4be23ce8bb0e623674', 'student', 1, '2025-11-16 13:16:49'),
(43, 'J096', '4b2867ac9da89211a9ac281ac8b6af84', 'student', 0, '2025-11-20 11:02:40'),
(44, 'wardenB', '93f484e38aaf2330a0dcea04a22f20b4', 'warden', 0, '2025-11-20 11:03:40'),
(59, 'J006', 'c77dd8b9ff9d1d67f926eb7de0940b3a', 'student', 1, '2025-11-25 18:08:38');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_requests`
--
-- Creation: Nov 25, 2025 at 03:11 PM
--

DROP TABLE IF EXISTS `visitor_requests`;
CREATE TABLE `visitor_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `visitor_name` varchar(100) NOT NULL,
  `relation` varchar(50) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time NOT NULL,
  `applied_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `action_by` int(11) DEFAULT NULL,
  `action_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `visitor_requests`:
--   `student_id`
--       `students` -> `id`
--   `action_by`
--       `users` -> `id`
--

--
-- Dumping data for table `visitor_requests`
--

INSERT INTO `visitor_requests` (`id`, `student_id`, `visitor_name`, `relation`, `visit_date`, `visit_time`, `applied_at`, `status`, `requested_at`, `action_by`, `action_at`) VALUES
(1, 1, 'Nisha', 'Mother', '2025-11-18', '19:08:00', '2025-11-16 23:11:45', 'rejected', '2025-11-16 19:08:37', 3, '2025-11-16 20:07:59'),
(2, 1, 'Nisha', 'Mother', '2025-11-18', '19:08:00', '2025-11-16 23:11:45', 'rejected', '2025-11-16 19:09:22', 3, '2025-11-16 20:08:01'),
(3, 1, 'Nisha', 'Mother', '2025-11-18', '19:08:00', '2025-11-16 23:11:45', 'rejected', '2025-11-16 19:10:15', 3, '2025-11-16 20:08:05'),
(4, 1, 'Nisha', 'Mother', '2025-11-18', '19:08:00', '2025-11-16 23:11:45', 'rejected', '2025-11-16 19:15:04', 3, '2025-11-16 20:08:10'),
(5, 1, 'Nisha', 'Mother', '2025-11-18', '19:08:00', '2025-11-16 23:11:45', 'approved', '2025-11-16 19:17:28', 3, '2025-11-16 20:08:07'),
(6, 1, 'Nisha', 'Mother', '2025-11-08', '00:00:00', '2025-11-16 23:11:45', 'rejected', '2025-11-16 21:52:40', 3, '2025-11-16 21:54:46'),
(7, 6, 'manvi', 'friend', '2025-11-17', '00:00:08', '2025-11-16 23:11:45', 'rejected', '2025-11-16 22:51:02', 3, '2025-11-16 22:53:28'),
(9, 1, 'james', 'uncle', '2025-11-22', '01:30:00', '2025-11-20 01:27:24', 'pending', '2025-11-20 01:27:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wardens`
--
-- Creation: Nov 25, 2025 at 03:11 PM
--

DROP TABLE IF EXISTS `wardens`;
CREATE TABLE `wardens` (
  `id` varchar(10) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `block` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `wardens`:
--   `user_id`
--       `users` -> `id`
--

--
-- Dumping data for table `wardens`
--

INSERT INTO `wardens` (`id`, `user_id`, `name`, `phone`, `email`, `dob`, `address`, `block`) VALUES
('W001', 3, 'Harshitha', '9495218803', 'harsitha@gmail.com', '1990-10-12', 'Udupi', 'A'),
('W002', 44, 'Smitha Shetty', '7865034563', 'smitha@gmail.com', '1995-01-17', 'Puttur', 'B');

--
-- Triggers `wardens`
--
DROP TRIGGER IF EXISTS `warden_auto_id`;
DELIMITER $$
CREATE TRIGGER `warden_auto_id` BEFORE INSERT ON `wardens` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    DECLARE new_id VARCHAR(10);
    
    -- Find the highest number after 'W' and add 1
    SELECT COALESCE(MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)), 0) + 1 
    INTO next_num 
    FROM wardens 
    WHERE id REGEXP '^W[0-9]+$';
    
    -- If no Wxxx found yet, start from 1
    IF next_num IS NULL THEN
        SET next_num = 1;
    END IF;
    
    -- Format as W001, W002, etc.
    SET new_id = CONCAT('W', LPAD(next_num, 3, '0'));
    
    -- Assign the beautiful ID
    SET NEW.id = new_id;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`date`);

--
-- Indexes for table `cleaning_requests`
--
ALTER TABLE `cleaning_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `mess_bills`
--
ALTER TABLE `mess_bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bill` (`student_id`,`month`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room` (`block`,`room_no`),
  ADD UNIQUE KEY `uq_block_room` (`block`,`room_no`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `register_no_active` (`register_no`,`id`),
  ADD KEY `fee_paid_by` (`fee_paid_by`),
  ADD KEY `fk_student_to_room` (`block`,`room_no`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `visitor_requests`
--
ALTER TABLE `visitor_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `wardens`
--
ALTER TABLE `wardens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `unique_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `cleaning_requests`
--
ALTER TABLE `cleaning_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `mess_bills`
--
ALTER TABLE `mess_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `visitor_requests`
--
ALTER TABLE `visitor_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cleaning_requests`
--
ALTER TABLE `cleaning_requests`
  ADD CONSTRAINT `cleaning_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cleaning_requests_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `mess_bills`
--
ALTER TABLE `mess_bills`
  ADD CONSTRAINT `mess_bills_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mess_bills_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_to_room` FOREIGN KEY (`block`,`room_no`) REFERENCES `rooms` (`block`, `room_no`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`fee_paid_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `visitor_requests`
--
ALTER TABLE `visitor_requests`
  ADD CONSTRAINT `visitor_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visitor_requests_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `wardens`
--
ALTER TABLE `wardens`
  ADD CONSTRAINT `wardens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
