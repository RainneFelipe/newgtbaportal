-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 07, 2025 at 04:20 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `newgtbaportal`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AssignTeacherToSection` (IN `p_section_id` INT, IN `p_teacher_id` INT, IN `p_is_primary` BOOLEAN, IN `p_created_by` INT)   BEGIN
    DECLARE teacher_count INT;
    DECLARE primary_count INT;
    
    -- Check if teacher is already assigned to this section
    SELECT COUNT(*) INTO teacher_count 
    FROM section_teachers 
    WHERE section_id = p_section_id AND teacher_id = p_teacher_id AND is_active = 1;
    
    IF teacher_count = 0 THEN
        -- If setting as primary, remove primary status from other teachers in the section
        IF p_is_primary THEN
            UPDATE section_teachers 
            SET is_primary = FALSE 
            WHERE section_id = p_section_id AND is_active = 1;
        END IF;
        
        -- Insert the new assignment
        INSERT INTO section_teachers (section_id, teacher_id, is_primary, created_by)
        VALUES (p_section_id, p_teacher_id, p_is_primary, p_created_by);
        
        SELECT 'Teacher assigned successfully' as message;
    ELSE
        SELECT 'Teacher is already assigned to this section' as message;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RemoveTeacherFromSection` (IN `p_section_id` INT, IN `p_teacher_id` INT)   BEGIN
    UPDATE section_teachers 
    SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP
    WHERE section_id = p_section_id AND teacher_id = p_teacher_id AND is_active = 1;
    
    SELECT 'Teacher removed from section successfully' as message;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `announcement_type` enum('General','Academic','Event','Emergency','Holiday','Maintenance') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'General',
  `target_audience` enum('All','Students','Teachers','Staff','Specific Grade') DEFAULT 'All',
  `target_grade_level_id` int DEFAULT NULL,
  `priority` enum('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `is_published` tinyint(1) DEFAULT '0',
  `is_pinned` tinyint(1) DEFAULT '0',
  `publish_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `has_attachments` tinyint(1) DEFAULT '0',
  `attachment_count` int DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `announcement_type`, `target_audience`, `target_grade_level_id`, `priority`, `is_published`, `is_pinned`, `publish_date`, `expiry_date`, `has_attachments`, `attachment_count`, `created_by`, `created_at`, `updated_at`) VALUES
(9, 'Class Suspension Due to Typhoon Warning', 'Due to the recent typhoon warning issued by PAGASA, all classes from July 30 to August 1 are suspended. Students are advised to stay safe and monitor official channels for further updates.', 'Academic', 'Students', NULL, 'High', 1, 0, '2025-07-30', '2025-08-14', 1, 1, 4, '2025-07-30 09:21:17', '2025-07-30 09:36:16'),
(11, 'Scheduled System Maintenance', 'The student portal will undergo scheduled maintenance on August 1, from 12:00 AM to 6:00 AM. Please avoid accessing the system during this period. We apologize for any inconvenience.', 'Maintenance', 'All', NULL, 'Normal', 1, 0, '2025-07-30', '2025-08-07', 0, 0, 4, '2025-07-30 09:29:33', '2025-07-30 09:29:33'),
(12, 'Orientation Schedule for Freshmen', 'All incoming freshmen are invited to attend the orientation program on August 8, 2025, at the Main Auditorium, 9:00 AM. Attendance is required. Please wear your school ID.', 'Event', 'Students', NULL, 'Normal', 1, 0, '2025-07-30', NULL, 0, 0, 4, '2025-07-30 09:32:30', '2025-07-30 09:32:30'),
(13, 'Application for Academic Scholarships Now Open', 'Students may now apply for academic scholarships for AY 2025–2026. Kindly visit the Scholarship Office page on the student portal for the application form and requirements.', 'Academic', 'Students', NULL, 'Normal', 1, 0, '2025-07-30', NULL, 0, 0, 4, '2025-07-30 09:33:00', '2025-07-30 09:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_attachments`
--

CREATE TABLE `announcement_attachments` (
  `id` int NOT NULL,
  `announcement_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `announcement_attachments`
--

INSERT INTO `announcement_attachments` (`id`, `announcement_id`, `filename`, `original_filename`, `file_path`, `file_size`, `mime_type`, `created_at`) VALUES
(6, 9, 'announcement_9_1753867277_0.jpeg', 'walang-pasok-20september2022.jpeg', '../uploads/announcements/announcement_9_1753867277_0.jpeg', 104671, 'image/jpeg', '2025-07-30 09:21:17');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(183, 4, 'UPDATE_PROFILE', 'Profile picture updated', 'users', 4, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-07-30 11:47:38'),
(184, 3, 'Student Registration', 'Created new student account for LOL LOL', 'students', 0, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-08-13 12:43:56'),
(185, 1, 'School Year Created: 2023-2024', NULL, 'school_years', 4, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0', '2025-09-05 07:16:25'),
(186, 1, 'School Year Created: 2022-2023', NULL, 'school_years', 5, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0', '2025-09-26 08:15:22'),
(187, 4, 'CREATE', 'Created teacher schedule for subject on Monday 11:00-12:00', 'teacher_schedules', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0', '2025-09-26 09:24:25'),
(188, 4, 'CREATE', 'Created teacher schedule for subject on Monday 12:00-13:00', 'teacher_schedules', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0', '2025-09-26 10:14:28'),
(189, 3, 'Student Registration', 'Created new student account for Alix Felipe', 'students', 0, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-23 09:31:28'),
(190, 3, 'Student Status Change', 'Changed enrollment status to: Suspended', 'students', 366, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-24 07:43:43'),
(191, 3, 'Student Status Change', 'Changed enrollment status to: Dropped', 'students', 463, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-24 07:43:48'),
(192, 3, 'Student Status Change', 'Changed enrollment status to: Transferred', 'students', 464, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-24 07:43:52'),
(193, 3, 'Student Status Change', 'Changed enrollment status to: Graduated', 'students', 465, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-11-24 07:44:00'),
(194, 3, 'Student Status Change', 'Changed enrollment status to: Enrolled', 'students', 472, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-05 11:46:15'),
(195, 3, 'prevent_auto_promotion', '{\"prevented_count\":1,\"students\":[\"Alix Felipe\"]}', 'students', NULL, NULL, NULL, '127.0.0.1', NULL, '2025-12-05 11:54:37'),
(196, 3, 'Student Status Change', 'Changed enrollment status to: Enrolled', 'students', 472, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-05 12:05:33'),
(197, 3, 'prevent_auto_promotion', '{\"prevented_count\":1,\"students\":[\"Alix Felipe\"]}', 'students', NULL, NULL, NULL, '127.0.0.1', NULL, '2025-12-05 12:06:08'),
(198, 1, 'User Created: test (teacher)', NULL, 'users', 543, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-05 13:55:18'),
(199, 1, 'User Archived: test', NULL, 'users', 543, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-05 13:55:24'),
(200, 1, 'User Restored: test', NULL, 'users', 543, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-05 13:59:09'),
(201, 1, 'User Archived: 2025025', NULL, 'users', 444, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-05 13:59:22'),
(202, 1, 'User Restored: 2025025', NULL, 'users', 444, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-05 13:59:39'),
(203, 3, 'Student Status Change', 'Changed enrollment status to: Pending Payment', 'students', 464, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-07 03:37:42'),
(204, 3, 'Student Status Change', 'Changed enrollment status to: Transferred | Transferred to: Colegie de San Juan de Letran | Transfer Date: 2025-12-07', 'students', 464, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-07 03:39:40'),
(205, 3, 'Student Status Change', 'Changed enrollment status to: Pending Payment', 'students', 464, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-07 03:52:05'),
(206, 3, 'Student Status Change', 'Changed enrollment status to: Transferred | Transferred to: Colegie de San Juan de Letran | Transfer Date: 2025-12-07', 'students', 464, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-07 03:52:27'),
(207, 3, 'Student Status Change', 'Changed enrollment status to: Dropped', 'students', 472, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-07 03:58:21'),
(208, 3, 'Student Status Change', 'Changed enrollment status to: Enrolled', 'students', 472, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', '2025-12-07 03:59:05');

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `id` int NOT NULL,
  `section_id` int NOT NULL,
  `subject_id` int DEFAULT NULL,
  `activity_name` varchar(200) DEFAULT NULL,
  `school_year_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `teacher_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `section_id`, `subject_id`, `activity_name`, `school_year_id`, `day_of_week`, `start_time`, `end_time`, `room`, `is_active`, `created_by`, `created_at`, `updated_at`, `teacher_id`) VALUES
(18, 6, NULL, 'Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 4, '2025-07-30 12:31:23', '2025-07-30 12:31:23', NULL),
(19, 6, NULL, 'Morning Assembly', 2, 'Monday', '08:00:00', '08:30:00', 'Playground', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(20, 6, NULL, 'Morning Snack', 2, 'Monday', '10:00:00', '10:30:00', 'Classroom', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(21, 6, NULL, 'Afternoon Snack', 2, 'Monday', '14:30:00', '15:00:00', 'Classroom', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(22, 6, NULL, 'Dismissal', 2, 'Monday', '15:00:00', '15:30:00', 'Gate Area', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(23, 6, 1, NULL, 2, 'Monday', '08:30:00', '09:30:00', 'Room 103', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(24, 6, 2, NULL, 2, 'Monday', '09:30:00', '10:00:00', 'Room 103', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(25, 6, 5, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 103', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(26, 6, 3, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 103', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(27, 6, 4, NULL, 2, 'Monday', '14:00:00', '14:30:00', 'Room 103', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(28, 7, NULL, 'Morning Assembly', 2, 'Monday', '08:00:00', '08:30:00', 'Playground', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(29, 7, NULL, 'Morning Snack', 2, 'Monday', '10:00:00', '10:30:00', 'Room 104', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(30, 7, NULL, 'Lunch Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(31, 7, NULL, 'Afternoon Snack', 2, 'Monday', '14:30:00', '15:00:00', 'Room 104', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(32, 7, NULL, 'Dismissal', 2, 'Monday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(33, 7, 6, NULL, 2, 'Monday', '08:30:00', '09:30:00', 'Room 104', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(34, 7, 7, NULL, 2, 'Monday', '09:30:00', '10:00:00', 'Room 104', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(35, 7, 8, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 104', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(36, 7, 9, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 104', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(37, 7, 10, NULL, 2, 'Monday', '14:00:00', '14:30:00', 'Room 104', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(38, 8, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(39, 8, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(40, 8, NULL, 'Lunch Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(41, 8, NULL, 'Dismissal', 2, 'Monday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(42, 8, 11, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 204', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(43, 8, 12, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 204', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(44, 8, 13, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 204', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(45, 8, 14, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 204', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(46, 8, 15, NULL, 2, 'Monday', '14:00:00', '15:00:00', 'Room 204', 1, 1, '2025-09-05 05:48:31', '2025-09-05 05:48:31', NULL),
(47, 9, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(48, 9, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(49, 9, NULL, 'Lunch Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(50, 9, NULL, 'Dismissal', 2, 'Monday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(51, 9, 16, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 205', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(52, 9, 17, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 205', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(53, 9, 18, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 205', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(54, 9, 19, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 205', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(55, 9, 20, NULL, 2, 'Monday', '14:00:00', '15:00:00', 'Room 205', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(56, 10, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(57, 10, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(58, 10, NULL, 'Lunch Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(59, 10, NULL, 'Dismissal', 2, 'Monday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(60, 10, 21, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 303', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(61, 10, 22, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 303', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(62, 10, 23, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 303', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(63, 10, 24, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 303', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(64, 10, 25, NULL, 2, 'Monday', '14:00:00', '15:00:00', 'Room 303', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(65, 11, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(66, 11, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(67, 11, NULL, 'Lunch Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(68, 11, NULL, 'Dismissal', 2, 'Monday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(69, 11, 26, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 304', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(70, 11, 27, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 304', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(71, 11, 28, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 304', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(72, 11, 29, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 304', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(73, 11, 30, NULL, 2, 'Monday', '14:00:00', '15:00:00', 'Room 304', 1, 1, '2025-09-05 05:48:57', '2025-09-05 05:48:57', NULL),
(74, 12, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(75, 12, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(76, 12, NULL, 'Lunch Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(77, 12, NULL, 'Dismissal', 2, 'Monday', '16:30:00', '17:00:00', 'Gate Area', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(78, 12, 31, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 403', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(79, 12, 32, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 403', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(80, 12, 33, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 403', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(81, 12, 34, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 403', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(82, 12, 35, NULL, 2, 'Monday', '14:00:00', '15:00:00', 'Room 403', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(83, 13, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(84, 13, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(85, 13, NULL, 'Lunch Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(86, 13, NULL, 'Dismissal', 2, 'Monday', '16:30:00', '17:00:00', 'Gate Area', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(87, 13, 36, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 404', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(88, 13, 37, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 404', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(89, 13, 38, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 404', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(90, 13, 39, NULL, 2, 'Monday', '13:00:00', '14:00:00', 'Room 404', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(91, 13, 40, NULL, 2, 'Monday', '14:00:00', '15:00:00', 'Room 404', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(92, 14, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(93, 14, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(94, 14, NULL, 'Lunch Break', 2, 'Monday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(95, 14, NULL, 'Dismissal', 2, 'Monday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(96, 14, 41, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 505', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(97, 14, 42, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 505', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(98, 14, 43, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 505', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(99, 14, 44, NULL, 2, 'Monday', '11:30:00', '12:30:00', 'Room 505', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(100, 14, 45, NULL, 2, 'Monday', '13:30:00', '14:30:00', 'Room 505', 1, 1, '2025-09-05 05:49:19', '2025-09-05 05:49:19', NULL),
(101, 15, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(102, 15, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(103, 15, NULL, 'Lunch Break', 2, 'Monday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(104, 15, NULL, 'Dismissal', 2, 'Monday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(105, 15, 46, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 506', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(106, 15, 47, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 506', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(107, 15, 48, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 506', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(108, 15, 49, NULL, 2, 'Monday', '11:30:00', '12:30:00', 'Room 506', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(109, 15, 50, NULL, 2, 'Monday', '13:30:00', '14:30:00', 'Room 506', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(110, 16, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(111, 16, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(112, 16, NULL, 'Lunch Break', 2, 'Monday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(113, 16, NULL, 'Dismissal', 2, 'Monday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(114, 16, 51, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 507', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(115, 16, 52, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 507', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(116, 16, 53, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 507', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(117, 16, 54, NULL, 2, 'Monday', '11:30:00', '12:30:00', 'Room 507', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(118, 16, 55, NULL, 2, 'Monday', '13:30:00', '14:30:00', 'Room 507', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(119, 17, NULL, 'Flag Ceremony', 2, 'Monday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(120, 17, NULL, 'Recess', 2, 'Monday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(121, 17, NULL, 'Lunch Break', 2, 'Monday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(122, 17, NULL, 'Dismissal', 2, 'Monday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(123, 17, 56, NULL, 2, 'Monday', '08:00:00', '09:00:00', 'Room 508', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(124, 17, 57, NULL, 2, 'Monday', '09:00:00', '10:00:00', 'Room 508', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(125, 17, 58, NULL, 2, 'Monday', '10:30:00', '11:30:00', 'Room 508', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(126, 17, 59, NULL, 2, 'Monday', '11:30:00', '12:30:00', 'Room 508', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(127, 17, 60, NULL, 2, 'Monday', '13:30:00', '14:30:00', 'Room 508', 1, 1, '2025-09-05 05:50:05', '2025-09-05 05:50:05', NULL),
(128, 6, NULL, 'Morning Assembly', 2, 'Tuesday', '08:00:00', '08:30:00', 'Playground', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(129, 6, NULL, 'Morning Snack', 2, 'Tuesday', '10:00:00', '10:30:00', 'Classroom', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(130, 6, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(131, 6, NULL, 'Afternoon Snack', 2, 'Tuesday', '14:30:00', '15:00:00', 'Classroom', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(132, 6, NULL, 'Dismissal', 2, 'Tuesday', '15:00:00', '15:30:00', 'Gate Area', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(133, 6, 2, NULL, 2, 'Tuesday', '08:30:00', '09:30:00', 'Room 103', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(134, 6, 3, NULL, 2, 'Tuesday', '09:30:00', '10:00:00', 'Room 103', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(135, 6, 4, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 103', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(136, 6, 1, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 103', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(137, 6, 5, NULL, 2, 'Tuesday', '14:00:00', '14:30:00', 'Room 103', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(138, 7, NULL, 'Morning Assembly', 2, 'Tuesday', '08:00:00', '08:30:00', 'Playground', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(139, 7, NULL, 'Morning Snack', 2, 'Tuesday', '10:00:00', '10:30:00', 'Room 104', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(140, 7, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(141, 7, NULL, 'Afternoon Snack', 2, 'Tuesday', '14:30:00', '15:00:00', 'Room 104', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(142, 7, NULL, 'Dismissal', 2, 'Tuesday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(143, 7, 7, NULL, 2, 'Tuesday', '08:30:00', '09:30:00', 'Room 104', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(144, 7, 8, NULL, 2, 'Tuesday', '09:30:00', '10:00:00', 'Room 104', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(145, 7, 10, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 104', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(146, 7, 6, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 104', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(147, 7, 9, NULL, 2, 'Tuesday', '14:00:00', '14:30:00', 'Room 104', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(148, 8, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(149, 8, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(150, 8, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(151, 8, NULL, 'Dismissal', 2, 'Tuesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(152, 8, 12, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 204', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(153, 8, 13, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 204', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(154, 8, 15, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 204', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(155, 8, 11, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 204', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(156, 8, 14, NULL, 2, 'Tuesday', '14:00:00', '15:00:00', 'Room 204', 1, 1, '2025-09-05 05:50:34', '2025-09-05 05:50:34', NULL),
(157, 9, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(158, 9, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(159, 9, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(160, 9, NULL, 'Dismissal', 2, 'Tuesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(161, 9, 17, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 205', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(162, 9, 16, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 205', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(163, 9, 20, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 205', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(164, 9, 19, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 205', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(165, 9, 18, NULL, 2, 'Tuesday', '14:00:00', '15:00:00', 'Room 205', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(166, 10, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(167, 10, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(168, 10, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(169, 10, NULL, 'Dismissal', 2, 'Tuesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(170, 10, 22, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 206', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(171, 10, 21, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 206', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(172, 10, 25, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 206', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(173, 10, 24, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 206', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(174, 10, 23, NULL, 2, 'Tuesday', '14:00:00', '15:00:00', 'Room 206', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(175, 11, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(176, 11, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(177, 11, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(178, 11, NULL, 'Dismissal', 2, 'Tuesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(179, 11, 27, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 207', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(180, 11, 26, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 207', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(181, 11, 30, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 207', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(182, 11, 29, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 207', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(183, 11, 28, NULL, 2, 'Tuesday', '14:00:00', '15:00:00', 'Room 207', 1, 1, '2025-09-05 05:52:00', '2025-09-05 05:52:00', NULL),
(184, 12, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(185, 12, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(186, 12, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(187, 12, NULL, 'Dismissal', 2, 'Tuesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(188, 12, 32, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 208', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(189, 12, 31, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 208', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(190, 12, 35, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 208', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(191, 12, 34, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 208', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(192, 12, 33, NULL, 2, 'Tuesday', '14:00:00', '15:00:00', 'Room 208', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(193, 13, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(194, 13, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(195, 13, NULL, 'Lunch Break', 2, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(196, 13, NULL, 'Dismissal', 2, 'Tuesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(197, 13, 37, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 209', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(198, 13, 36, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 209', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(199, 13, 40, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 209', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(200, 13, 39, NULL, 2, 'Tuesday', '13:00:00', '14:00:00', 'Room 209', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(201, 13, 38, NULL, 2, 'Tuesday', '14:00:00', '15:00:00', 'Room 209', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(202, 14, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(203, 14, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(204, 14, NULL, 'Lunch Break', 2, 'Tuesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(205, 14, NULL, 'Dismissal', 2, 'Tuesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(206, 14, 42, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 308', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(207, 14, 41, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 308', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(208, 14, 45, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 308', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(209, 14, 44, NULL, 2, 'Tuesday', '11:30:00', '12:30:00', 'Room 308', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(210, 14, 43, NULL, 2, 'Tuesday', '13:30:00', '14:30:00', 'Room 308', 1, 1, '2025-09-05 05:52:20', '2025-09-05 05:52:20', NULL),
(211, 15, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(212, 15, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(213, 15, NULL, 'Lunch Break', 2, 'Tuesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(214, 15, NULL, 'Dismissal', 2, 'Tuesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(215, 15, 47, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 409', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(216, 15, 46, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 409', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(217, 15, 50, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 409', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(218, 15, 49, NULL, 2, 'Tuesday', '11:30:00', '12:30:00', 'Room 409', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(219, 15, 48, NULL, 2, 'Tuesday', '13:30:00', '14:30:00', 'Room 409', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(220, 16, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(221, 16, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(222, 16, NULL, 'Lunch Break', 2, 'Tuesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(223, 16, NULL, 'Dismissal', 2, 'Tuesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(224, 16, 52, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 508', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(225, 16, 51, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 508', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(226, 16, 55, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 508', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(227, 16, 54, NULL, 2, 'Tuesday', '11:30:00', '12:30:00', 'Room 508', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(228, 16, 53, NULL, 2, 'Tuesday', '13:30:00', '14:30:00', 'Room 508', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(229, 17, NULL, 'Morning Assembly', 2, 'Tuesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(230, 17, NULL, 'Recess', 2, 'Tuesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(231, 17, NULL, 'Lunch Break', 2, 'Tuesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(232, 17, NULL, 'Dismissal', 2, 'Tuesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(233, 17, 57, NULL, 2, 'Tuesday', '08:00:00', '09:00:00', 'Room 507', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(234, 17, 56, NULL, 2, 'Tuesday', '09:00:00', '10:00:00', 'Room 507', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(235, 17, 60, NULL, 2, 'Tuesday', '10:30:00', '11:30:00', 'Room 507', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(236, 17, 59, NULL, 2, 'Tuesday', '11:30:00', '12:30:00', 'Room 507', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(237, 17, 58, NULL, 2, 'Tuesday', '13:30:00', '14:30:00', 'Room 507', 1, 1, '2025-09-05 05:52:37', '2025-09-05 05:52:37', NULL),
(238, 6, NULL, 'Morning Circle', 2, 'Wednesday', '08:00:00', '08:30:00', 'Room 103', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(239, 6, NULL, 'Morning Snack', 2, 'Wednesday', '10:00:00', '10:30:00', 'Classroom', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(240, 6, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(241, 6, NULL, 'Rest Time', 2, 'Wednesday', '14:00:00', '14:30:00', 'Room 103', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(242, 6, NULL, 'Dismissal', 2, 'Wednesday', '15:00:00', '15:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(243, 6, 3, NULL, 2, 'Wednesday', '08:30:00', '09:30:00', 'Room 103', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(244, 6, 5, NULL, 2, 'Wednesday', '09:30:00', '10:00:00', 'Room 103', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(245, 6, 1, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 103', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(246, 6, 4, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 103', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(247, 6, 2, NULL, 2, 'Wednesday', '14:30:00', '15:00:00', 'Room 103', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(248, 7, NULL, 'Morning Circle', 2, 'Wednesday', '08:00:00', '08:30:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(249, 7, NULL, 'Morning Snack', 2, 'Wednesday', '10:00:00', '10:30:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(250, 7, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(251, 7, NULL, 'Rest Time', 2, 'Wednesday', '14:30:00', '15:00:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(252, 7, NULL, 'Dismissal', 2, 'Wednesday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(253, 7, 8, NULL, 2, 'Wednesday', '08:30:00', '09:30:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(254, 7, 9, NULL, 2, 'Wednesday', '09:30:00', '10:00:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(255, 7, 6, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(256, 7, 10, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(257, 7, 7, NULL, 2, 'Wednesday', '14:00:00', '14:30:00', 'Room 104', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(258, 8, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(259, 8, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(260, 8, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(261, 8, NULL, 'Dismissal', 2, 'Wednesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(262, 8, 14, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 204', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(263, 8, 15, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 204', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(264, 8, 12, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 204', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(265, 8, 13, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 204', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(266, 8, 11, NULL, 2, 'Wednesday', '14:00:00', '15:00:00', 'Room 204', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(267, 9, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(268, 9, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(269, 9, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(270, 9, NULL, 'Dismissal', 2, 'Wednesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(271, 9, 19, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 205', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(272, 9, 20, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 205', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(273, 9, 17, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 205', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(274, 9, 18, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 205', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(275, 9, 16, NULL, 2, 'Wednesday', '14:00:00', '15:00:00', 'Room 205', 1, 1, '2025-09-05 05:53:01', '2025-09-05 05:53:01', NULL),
(276, 10, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(277, 10, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(278, 10, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(279, 10, NULL, 'Dismissal', 2, 'Wednesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(280, 10, 24, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 206', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(281, 10, 25, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 206', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(282, 10, 22, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 206', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(283, 10, 23, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 206', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(284, 10, 21, NULL, 2, 'Wednesday', '14:00:00', '15:00:00', 'Room 206', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(285, 11, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(286, 11, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(287, 11, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(288, 11, NULL, 'Dismissal', 2, 'Wednesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(289, 11, 29, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 207', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(290, 11, 30, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 207', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(291, 11, 27, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 207', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(292, 11, 28, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 207', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(293, 11, 26, NULL, 2, 'Wednesday', '14:00:00', '15:00:00', 'Room 207', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(294, 12, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(295, 12, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(296, 12, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(297, 12, NULL, 'Dismissal', 2, 'Wednesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(298, 12, 34, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 208', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(299, 12, 35, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 208', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(300, 12, 32, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 208', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(301, 12, 33, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 208', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(302, 12, 31, NULL, 2, 'Wednesday', '14:00:00', '15:00:00', 'Room 208', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(303, 13, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(304, 13, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(305, 13, NULL, 'Lunch Break', 2, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(306, 13, NULL, 'Dismissal', 2, 'Wednesday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(307, 13, 39, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 209', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(308, 13, 40, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 209', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(309, 13, 37, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 209', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(310, 13, 38, NULL, 2, 'Wednesday', '13:00:00', '14:00:00', 'Room 209', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(311, 13, 36, NULL, 2, 'Wednesday', '14:00:00', '15:00:00', 'Room 209', 1, 1, '2025-09-05 05:53:25', '2025-09-05 05:53:25', NULL),
(312, 14, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(313, 14, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(314, 14, NULL, 'Lunch Break', 2, 'Wednesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(315, 14, NULL, 'Dismissal', 2, 'Wednesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(316, 14, 44, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 308', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(317, 14, 45, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 308', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(318, 14, 42, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 308', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(319, 14, 43, NULL, 2, 'Wednesday', '11:30:00', '12:30:00', 'Room 308', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(320, 14, 41, NULL, 2, 'Wednesday', '13:30:00', '14:30:00', 'Room 308', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(321, 15, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(322, 15, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(323, 15, NULL, 'Lunch Break', 2, 'Wednesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(324, 15, NULL, 'Dismissal', 2, 'Wednesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(325, 15, 49, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 409', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(326, 15, 50, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 409', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(327, 15, 47, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 409', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(328, 15, 48, NULL, 2, 'Wednesday', '11:30:00', '12:30:00', 'Room 409', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(329, 15, 46, NULL, 2, 'Wednesday', '13:30:00', '14:30:00', 'Room 409', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(330, 16, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(331, 16, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(332, 16, NULL, 'Lunch Break', 2, 'Wednesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(333, 16, NULL, 'Dismissal', 2, 'Wednesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(334, 16, 54, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 508', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(335, 16, 55, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 508', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(336, 16, 52, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 508', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(337, 16, 53, NULL, 2, 'Wednesday', '11:30:00', '12:30:00', 'Room 508', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(338, 16, 51, NULL, 2, 'Wednesday', '13:30:00', '14:30:00', 'Room 508', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(339, 17, NULL, 'Morning Assembly', 2, 'Wednesday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(340, 17, NULL, 'Recess', 2, 'Wednesday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(341, 17, NULL, 'Lunch Break', 2, 'Wednesday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(342, 17, NULL, 'Dismissal', 2, 'Wednesday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(343, 17, 59, NULL, 2, 'Wednesday', '08:00:00', '09:00:00', 'Room 507', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(344, 17, 60, NULL, 2, 'Wednesday', '09:00:00', '10:00:00', 'Room 507', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(345, 17, 57, NULL, 2, 'Wednesday', '10:30:00', '11:30:00', 'Room 507', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(346, 17, 58, NULL, 2, 'Wednesday', '11:30:00', '12:30:00', 'Room 507', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(347, 17, 56, NULL, 2, 'Wednesday', '13:30:00', '14:30:00', 'Room 507', 1, 1, '2025-09-05 05:53:51', '2025-09-05 05:53:51', NULL),
(348, 6, NULL, 'Story Time', 2, 'Thursday', '08:00:00', '08:30:00', 'Library Corner', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(349, 6, NULL, 'Morning Snack', 2, 'Thursday', '10:00:00', '10:30:00', 'Classroom', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(350, 6, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(351, 6, NULL, 'Quiet Time', 2, 'Thursday', '14:30:00', '15:00:00', 'Room 103', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(352, 6, NULL, 'Dismissal', 2, 'Thursday', '15:00:00', '15:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(353, 6, 4, NULL, 2, 'Thursday', '08:30:00', '09:30:00', 'Room 103', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(354, 6, 2, NULL, 2, 'Thursday', '09:30:00', '10:00:00', 'Room 103', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(355, 6, 5, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 103', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(356, 6, 3, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 103', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(357, 6, 1, NULL, 2, 'Thursday', '14:00:00', '14:30:00', 'Room 103', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(358, 7, NULL, 'Story Time', 2, 'Thursday', '08:00:00', '08:30:00', 'Library Corner', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(359, 7, NULL, 'Morning Snack', 2, 'Thursday', '10:00:00', '10:30:00', 'Room 104', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(360, 7, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(361, 7, NULL, 'Quiet Time', 2, 'Thursday', '14:30:00', '15:00:00', 'Room 104', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(362, 7, NULL, 'Dismissal', 2, 'Thursday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(363, 7, 9, NULL, 2, 'Thursday', '08:30:00', '09:30:00', 'Room 104', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(364, 7, 7, NULL, 2, 'Thursday', '09:30:00', '10:00:00', 'Room 104', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(365, 7, 10, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 104', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(366, 7, 8, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 104', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(367, 7, 6, NULL, 2, 'Thursday', '14:00:00', '14:30:00', 'Room 104', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(368, 8, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(369, 8, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(370, 8, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(371, 8, NULL, 'Dismissal', 2, 'Thursday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(372, 8, 11, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 204', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(373, 8, 12, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 204', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(374, 8, 14, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 204', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(375, 8, 15, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 204', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(376, 8, 13, NULL, 2, 'Thursday', '14:00:00', '15:00:00', 'Room 204', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(377, 9, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(378, 9, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(379, 9, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(380, 9, NULL, 'Dismissal', 2, 'Thursday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(381, 9, 16, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 205', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(382, 9, 17, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 205', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(383, 9, 19, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 205', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(384, 9, 20, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 205', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(385, 9, 18, NULL, 2, 'Thursday', '14:00:00', '15:00:00', 'Room 205', 1, 1, '2025-09-05 05:54:14', '2025-09-05 05:54:14', NULL),
(386, 10, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(387, 10, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(388, 10, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(389, 10, NULL, 'Dismissal', 2, 'Thursday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(390, 10, 21, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 206', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(391, 10, 22, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 206', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(392, 10, 24, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 206', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(393, 10, 25, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 206', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL);
INSERT INTO `class_schedules` (`id`, `section_id`, `subject_id`, `activity_name`, `school_year_id`, `day_of_week`, `start_time`, `end_time`, `room`, `is_active`, `created_by`, `created_at`, `updated_at`, `teacher_id`) VALUES
(394, 10, 23, NULL, 2, 'Thursday', '14:00:00', '15:00:00', 'Room 206', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(395, 11, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(396, 11, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(397, 11, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(398, 11, NULL, 'Dismissal', 2, 'Thursday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(399, 11, 26, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 207', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(400, 11, 27, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 207', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(401, 11, 29, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 207', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(402, 11, 30, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 207', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(403, 11, 28, NULL, 2, 'Thursday', '14:00:00', '15:00:00', 'Room 207', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(404, 12, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(405, 12, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(406, 12, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(407, 12, NULL, 'Dismissal', 2, 'Thursday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(408, 12, 31, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 208', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(409, 12, 32, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 208', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(410, 12, 34, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 208', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(411, 12, 35, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 208', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(412, 12, 33, NULL, 2, 'Thursday', '14:00:00', '15:00:00', 'Room 208', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(413, 13, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(414, 13, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(415, 13, NULL, 'Lunch Break', 2, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(416, 13, NULL, 'Dismissal', 2, 'Thursday', '16:00:00', '16:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(417, 13, 36, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 209', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(418, 13, 37, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 209', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(419, 13, 39, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 209', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(420, 13, 40, NULL, 2, 'Thursday', '13:00:00', '14:00:00', 'Room 209', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(421, 13, 38, NULL, 2, 'Thursday', '14:00:00', '15:00:00', 'Room 209', 1, 1, '2025-09-05 05:54:36', '2025-09-05 05:54:36', NULL),
(422, 14, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(423, 14, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(424, 14, NULL, 'Lunch Break', 2, 'Thursday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(425, 14, NULL, 'Dismissal', 2, 'Thursday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(426, 14, 41, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 308', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(427, 14, 42, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 308', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(428, 14, 44, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 308', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(429, 14, 45, NULL, 2, 'Thursday', '11:30:00', '12:30:00', 'Room 308', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(430, 14, 43, NULL, 2, 'Thursday', '13:30:00', '14:30:00', 'Room 308', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(431, 15, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(432, 15, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(433, 15, NULL, 'Lunch Break', 2, 'Thursday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(434, 15, NULL, 'Dismissal', 2, 'Thursday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(435, 15, 46, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 409', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(436, 15, 47, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 409', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(437, 15, 49, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 409', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(438, 15, 50, NULL, 2, 'Thursday', '11:30:00', '12:30:00', 'Room 409', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(439, 15, 48, NULL, 2, 'Thursday', '13:30:00', '14:30:00', 'Room 409', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(440, 16, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(441, 16, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(442, 16, NULL, 'Lunch Break', 2, 'Thursday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(443, 16, NULL, 'Dismissal', 2, 'Thursday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(444, 16, 51, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 508', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(445, 16, 52, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 508', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(446, 16, 54, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 508', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(447, 16, 55, NULL, 2, 'Thursday', '11:30:00', '12:30:00', 'Room 508', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(448, 16, 53, NULL, 2, 'Thursday', '13:30:00', '14:30:00', 'Room 508', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(449, 17, NULL, 'Morning Assembly', 2, 'Thursday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(450, 17, NULL, 'Recess', 2, 'Thursday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(451, 17, NULL, 'Lunch Break', 2, 'Thursday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(452, 17, NULL, 'Dismissal', 2, 'Thursday', '17:00:00', '17:30:00', 'Gate Area', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(453, 17, 56, NULL, 2, 'Thursday', '08:00:00', '09:00:00', 'Room 507', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(454, 17, 57, NULL, 2, 'Thursday', '09:00:00', '10:00:00', 'Room 507', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(455, 17, 59, NULL, 2, 'Thursday', '10:30:00', '11:30:00', 'Room 507', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(456, 17, 60, NULL, 2, 'Thursday', '11:30:00', '12:30:00', 'Room 507', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(457, 17, 58, NULL, 2, 'Thursday', '13:30:00', '14:30:00', 'Room 507', 1, 1, '2025-09-05 05:54:58', '2025-09-05 05:54:58', NULL),
(458, 6, NULL, 'Show and Tell', 2, 'Friday', '08:00:00', '08:30:00', 'Room 103', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(459, 6, NULL, 'Morning Snack', 2, 'Friday', '10:00:00', '10:30:00', 'Classroom', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(460, 6, NULL, 'Special Lunch', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(461, 6, NULL, 'Free Play', 2, 'Friday', '14:00:00', '14:30:00', 'Playground', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(462, 6, NULL, 'Early Dismissal', 2, 'Friday', '15:00:00', '15:30:00', 'Gate Area', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(463, 6, 1, NULL, 2, 'Friday', '08:30:00', '09:30:00', 'Room 103', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(464, 6, 4, NULL, 2, 'Friday', '09:30:00', '10:00:00', 'Room 103', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(465, 6, 2, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 103', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(466, 6, 5, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 103', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(467, 6, 3, NULL, 2, 'Friday', '14:30:00', '15:00:00', 'Room 103', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(468, 7, NULL, 'Show and Tell', 2, 'Friday', '08:00:00', '08:30:00', 'Room 104', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(469, 7, NULL, 'Morning Snack', 2, 'Friday', '10:00:00', '10:30:00', 'Room 104', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(470, 7, NULL, 'Special Lunch', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(471, 7, NULL, 'Free Play', 2, 'Friday', '14:30:00', '15:00:00', 'Playground', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(472, 7, NULL, 'Early Dismissal', 2, 'Friday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(473, 7, 6, NULL, 2, 'Friday', '08:30:00', '09:30:00', 'Room 104', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(474, 7, 9, NULL, 2, 'Friday', '09:30:00', '10:00:00', 'Room 104', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(475, 7, 7, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 104', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(476, 7, 10, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 104', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(477, 7, 8, NULL, 2, 'Friday', '14:00:00', '14:30:00', 'Room 104', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(478, 8, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(479, 8, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(480, 8, NULL, 'Lunch Break', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(481, 8, NULL, 'Early Dismissal', 2, 'Friday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(482, 8, 13, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 204', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(483, 8, 11, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 204', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(484, 8, 12, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 204', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(485, 8, 14, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 204', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(486, 8, 15, NULL, 2, 'Friday', '14:00:00', '15:00:00', 'Room 204', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(487, 9, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(488, 9, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(489, 9, NULL, 'Lunch Break', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(490, 9, NULL, 'Early Dismissal', 2, 'Friday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(491, 9, 18, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 205', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(492, 9, 16, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 205', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(493, 9, 17, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 205', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(494, 9, 19, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 205', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(495, 9, 20, NULL, 2, 'Friday', '14:00:00', '15:00:00', 'Room 205', 1, 1, '2025-09-05 05:55:46', '2025-09-05 05:55:46', NULL),
(496, 10, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(497, 10, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(498, 10, NULL, 'Lunch Break', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(499, 10, NULL, 'Early Dismissal', 2, 'Friday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(500, 10, 23, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 206', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(501, 10, 21, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 206', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(502, 10, 22, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 206', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(503, 10, 24, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 206', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(504, 10, 25, NULL, 2, 'Friday', '14:00:00', '15:00:00', 'Room 206', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(505, 11, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(506, 11, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(507, 11, NULL, 'Lunch Break', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(508, 11, NULL, 'Early Dismissal', 2, 'Friday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(509, 11, 28, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 207', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(510, 11, 26, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 207', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(511, 11, 27, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 207', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(512, 11, 29, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 207', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(513, 11, 30, NULL, 2, 'Friday', '14:00:00', '15:00:00', 'Room 207', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(514, 12, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(515, 12, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(516, 12, NULL, 'Lunch Break', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(517, 12, NULL, 'Early Dismissal', 2, 'Friday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(518, 12, 33, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 208', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(519, 12, 31, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 208', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(520, 12, 32, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 208', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(521, 12, 34, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 208', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(522, 12, 35, NULL, 2, 'Friday', '14:00:00', '15:00:00', 'Room 208', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(523, 13, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(524, 13, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(525, 13, NULL, 'Lunch Break', 2, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(526, 13, NULL, 'Early Dismissal', 2, 'Friday', '15:30:00', '16:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(527, 13, 38, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 209', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(528, 13, 36, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 209', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(529, 13, 37, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 209', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(530, 13, 39, NULL, 2, 'Friday', '13:00:00', '14:00:00', 'Room 209', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(531, 13, 40, NULL, 2, 'Friday', '14:00:00', '15:00:00', 'Room 209', 1, 1, '2025-09-05 05:56:12', '2025-09-05 05:56:12', NULL),
(532, 14, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(533, 14, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(534, 14, NULL, 'Lunch Break', 2, 'Friday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(535, 14, NULL, 'Early Dismissal', 2, 'Friday', '16:30:00', '17:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(536, 14, 43, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 308', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(537, 14, 41, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 308', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(538, 14, 42, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 308', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(539, 14, 44, NULL, 2, 'Friday', '11:30:00', '12:30:00', 'Room 308', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(540, 14, 45, NULL, 2, 'Friday', '13:30:00', '14:30:00', 'Room 308', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(541, 15, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(542, 15, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(543, 15, NULL, 'Lunch Break', 2, 'Friday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(544, 15, NULL, 'Early Dismissal', 2, 'Friday', '16:30:00', '17:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(545, 15, 48, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 409', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(546, 15, 46, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 409', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(547, 15, 47, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 409', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(548, 15, 49, NULL, 2, 'Friday', '11:30:00', '12:30:00', 'Room 409', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(549, 15, 50, NULL, 2, 'Friday', '13:30:00', '14:30:00', 'Room 409', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(550, 16, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(551, 16, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(552, 16, NULL, 'Lunch Break', 2, 'Friday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(553, 16, NULL, 'Early Dismissal', 2, 'Friday', '16:30:00', '17:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(554, 16, 53, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 508', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(555, 16, 51, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 508', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(556, 16, 52, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 508', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(557, 16, 54, NULL, 2, 'Friday', '11:30:00', '12:30:00', 'Room 508', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(558, 16, 55, NULL, 2, 'Friday', '13:30:00', '14:30:00', 'Room 508', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(559, 17, NULL, 'Morning Assembly', 2, 'Friday', '07:30:00', '08:00:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(560, 17, NULL, 'Recess', 2, 'Friday', '10:00:00', '10:30:00', 'Playground', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(561, 17, NULL, 'Lunch Break', 2, 'Friday', '12:30:00', '13:30:00', 'Cafeteria', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(562, 17, NULL, 'Early Dismissal', 2, 'Friday', '16:30:00', '17:00:00', 'Gate Area', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(563, 17, 58, NULL, 2, 'Friday', '08:00:00', '09:00:00', 'Room 507', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(564, 17, 56, NULL, 2, 'Friday', '09:00:00', '10:00:00', 'Room 507', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(565, 17, 57, NULL, 2, 'Friday', '10:30:00', '11:30:00', 'Room 507', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(566, 17, 59, NULL, 2, 'Friday', '11:30:00', '12:30:00', 'Room 507', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(567, 17, 60, NULL, 2, 'Friday', '13:30:00', '14:30:00', 'Room 507', 1, 1, '2025-09-05 05:56:35', '2025-09-05 05:56:35', NULL),
(568, 19, 11, NULL, 1, 'Monday', '08:30:00', '09:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(569, 19, 12, NULL, 1, 'Monday', '09:30:00', '10:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(570, 19, NULL, 'Morning Snack', 1, 'Monday', '10:30:00', '11:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(571, 19, 13, NULL, 1, 'Monday', '11:00:00', '12:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(572, 19, NULL, 'Lunch Break', 1, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(573, 19, 14, NULL, 1, 'Monday', '13:00:00', '14:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(574, 19, 15, NULL, 1, 'Monday', '14:00:00', '15:00:00', 'Music Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(575, 19, 12, NULL, 1, 'Tuesday', '08:30:00', '09:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(576, 19, 11, NULL, 1, 'Tuesday', '09:30:00', '10:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(577, 19, NULL, 'Morning Snack', 1, 'Tuesday', '10:30:00', '11:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(578, 19, 14, NULL, 1, 'Tuesday', '11:00:00', '12:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(579, 19, NULL, 'Lunch Break', 1, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(580, 19, 13, NULL, 1, 'Tuesday', '13:00:00', '14:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(581, 19, 15, NULL, 1, 'Tuesday', '14:00:00', '15:00:00', 'Gymnasium', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(582, 19, 11, NULL, 1, 'Wednesday', '08:30:00', '09:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(583, 19, 13, NULL, 1, 'Wednesday', '09:30:00', '10:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(584, 19, NULL, 'Morning Snack', 1, 'Wednesday', '10:30:00', '11:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(585, 19, 12, NULL, 1, 'Wednesday', '11:00:00', '12:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(586, 19, NULL, 'Lunch Break', 1, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(587, 19, 14, NULL, 1, 'Wednesday', '13:00:00', '14:00:00', 'Science Lab', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(588, 19, NULL, 'Library Time', 1, 'Wednesday', '14:00:00', '15:00:00', 'Library', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(589, 20, 1, NULL, 4, 'Monday', '08:30:00', '09:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(590, 20, 2, NULL, 4, 'Monday', '09:15:00', '10:00:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(591, 20, NULL, 'Morning Snack', 4, 'Monday', '10:00:00', '10:30:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(592, 20, 3, NULL, 4, 'Monday', '10:30:00', '11:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(593, 20, 5, NULL, 4, 'Monday', '11:15:00', '12:00:00', 'Playground', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(594, 20, NULL, 'Lunch Break', 4, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(595, 20, 4, NULL, 4, 'Monday', '13:00:00', '13:45:00', 'Art Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(596, 20, NULL, 'Story Time', 4, 'Monday', '13:45:00', '14:15:00', 'Reading Corner', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(597, 20, 2, NULL, 4, 'Tuesday', '08:30:00', '09:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(598, 20, 1, NULL, 4, 'Tuesday', '09:15:00', '10:00:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(599, 20, NULL, 'Morning Snack', 4, 'Tuesday', '10:00:00', '10:30:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(600, 20, 4, NULL, 4, 'Tuesday', '10:30:00', '11:15:00', 'Art Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(601, 20, 5, NULL, 4, 'Tuesday', '11:15:00', '12:00:00', 'Playground', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(602, 20, NULL, 'Lunch Break', 4, 'Tuesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(603, 20, 3, NULL, 4, 'Tuesday', '13:00:00', '13:45:00', 'Science Corner', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(604, 20, NULL, 'Music and Movement', 4, 'Tuesday', '13:45:00', '14:15:00', 'Music Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(605, 20, 1, NULL, 4, 'Wednesday', '08:30:00', '09:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(606, 20, 3, NULL, 4, 'Wednesday', '09:15:00', '10:00:00', 'Science Corner', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(607, 20, NULL, 'Morning Snack', 4, 'Wednesday', '10:00:00', '10:30:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(608, 20, 2, NULL, 4, 'Wednesday', '10:30:00', '11:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(609, 20, 5, NULL, 4, 'Wednesday', '11:15:00', '12:00:00', 'Playground', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(610, 20, NULL, 'Lunch Break', 4, 'Wednesday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(611, 20, 4, NULL, 4, 'Wednesday', '13:00:00', '13:45:00', 'Art Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(612, 20, NULL, 'Free Play', 4, 'Wednesday', '13:45:00', '14:15:00', 'Playground', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(613, 19, 14, NULL, 1, 'Thursday', '08:30:00', '09:30:00', 'Science Lab', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(614, 19, 11, NULL, 1, 'Thursday', '09:30:00', '10:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(615, 19, NULL, 'Morning Snack', 1, 'Thursday', '10:30:00', '11:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(616, 19, 12, NULL, 1, 'Thursday', '11:00:00', '12:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(617, 19, NULL, 'Lunch Break', 1, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(618, 19, 13, NULL, 1, 'Thursday', '13:00:00', '14:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(619, 19, 15, NULL, 1, 'Thursday', '14:00:00', '15:00:00', 'Gymnasium', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(620, 20, 2, NULL, 4, 'Thursday', '08:30:00', '09:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(621, 20, 4, NULL, 4, 'Thursday', '09:15:00', '10:00:00', 'Art Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(622, 20, NULL, 'Morning Snack', 4, 'Thursday', '10:00:00', '10:30:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(623, 20, 1, NULL, 4, 'Thursday', '10:30:00', '11:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(624, 20, 3, NULL, 4, 'Thursday', '11:15:00', '12:00:00', 'Science Corner', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 14),
(625, 20, NULL, 'Lunch Break', 4, 'Thursday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(626, 20, 5, NULL, 4, 'Thursday', '13:00:00', '13:45:00', 'Playground', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(627, 20, NULL, 'Nap Time', 4, 'Thursday', '13:45:00', '14:15:00', 'Rest Area', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(628, 19, 15, NULL, 1, 'Friday', '08:30:00', '09:30:00', 'Music Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(629, 19, 13, NULL, 1, 'Friday', '09:30:00', '10:30:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(630, 19, NULL, 'Morning Snack', 1, 'Friday', '10:30:00', '11:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(631, 19, 11, NULL, 1, 'Friday', '11:00:00', '12:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(632, 19, NULL, 'Lunch Break', 1, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(633, 19, NULL, 'Show and Tell', 1, 'Friday', '13:00:00', '14:00:00', 'Room 101', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(634, 19, NULL, 'Free Reading', 1, 'Friday', '14:00:00', '15:00:00', 'Library', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(635, 20, 5, NULL, 4, 'Friday', '08:30:00', '09:15:00', 'Playground', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 13),
(636, 20, 1, NULL, 4, 'Friday', '09:15:00', '10:00:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(637, 20, NULL, 'Morning Snack', 4, 'Friday', '10:00:00', '10:30:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(638, 20, 4, NULL, 4, 'Friday', '10:30:00', '11:15:00', 'Art Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 12),
(639, 20, 2, NULL, 4, 'Friday', '11:15:00', '12:00:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', 11),
(640, 20, NULL, 'Lunch Break', 4, 'Friday', '12:00:00', '13:00:00', 'Cafeteria', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(641, 20, NULL, 'Fun Friday Activities', 4, 'Friday', '13:00:00', '13:45:00', 'Activity Center', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(642, 20, NULL, 'Clean Up Time', 4, 'Friday', '13:45:00', '14:15:00', 'Nursery Room', 1, 1, '2025-09-26 08:06:09', '2025-09-26 08:06:09', NULL),
(643, 21, NULL, 'Lunch Break', 5, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 4, '2025-09-26 08:18:55', '2025-09-26 08:18:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `curriculum`
--

CREATE TABLE `curriculum` (
  `id` int NOT NULL,
  `grade_level_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `school_year_id` int NOT NULL,
  `is_required` tinyint(1) DEFAULT '1',
  `order_sequence` int DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `curriculum`
--

INSERT INTO `curriculum` (`id`, `grade_level_id`, `subject_id`, `school_year_id`, `is_required`, `order_sequence`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(2, 1, 2, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(3, 1, 3, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(4, 1, 4, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(5, 1, 5, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(8, 2, 6, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(9, 2, 7, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(10, 2, 8, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(11, 2, 9, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(12, 2, 10, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(15, 3, 11, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(16, 3, 12, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(17, 3, 13, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(18, 3, 14, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(19, 3, 15, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(22, 4, 16, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(23, 4, 17, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(24, 4, 18, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(25, 4, 19, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(26, 4, 20, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(29, 5, 21, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(30, 5, 22, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(31, 5, 23, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(32, 5, 24, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(33, 5, 25, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(36, 6, 26, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(37, 6, 27, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(38, 6, 28, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(39, 6, 29, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(40, 6, 30, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(43, 7, 31, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(44, 7, 32, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(45, 7, 33, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(46, 7, 34, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(47, 7, 35, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(50, 8, 36, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(51, 8, 37, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(52, 8, 38, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(53, 8, 39, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(54, 8, 40, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(57, 9, 41, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(58, 9, 42, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(59, 9, 43, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(60, 9, 44, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(61, 9, 45, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(64, 10, 46, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(65, 10, 47, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(66, 10, 48, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(67, 10, 49, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(68, 10, 50, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(71, 11, 51, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(72, 11, 52, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(73, 11, 53, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(74, 11, 54, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(75, 11, 55, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(78, 12, 56, 2, 1, 1, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(79, 12, 57, 2, 1, 2, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(80, 12, 58, 2, 1, 3, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(81, 12, 59, 2, 1, 4, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(82, 12, 60, 2, 1, 5, 1, '2025-09-05 05:41:02', '2025-09-05 05:41:02'),
(85, 3, 11, 1, 1, 1, 1, '2025-09-05 06:39:57', '2025-09-05 06:39:57'),
(86, 3, 12, 1, 1, 2, 1, '2025-09-05 06:39:57', '2025-09-05 06:39:57'),
(87, 3, 13, 1, 1, 3, 1, '2025-09-05 06:39:57', '2025-09-05 06:39:57'),
(88, 3, 14, 1, 1, 4, 1, '2025-09-05 06:39:57', '2025-09-05 06:39:57'),
(89, 3, 15, 1, 1, 5, 1, '2025-09-05 06:39:57', '2025-09-05 06:39:57'),
(90, 1, 3, 4, 1, 1, 4, '2025-09-05 07:17:39', '2025-09-05 07:17:39'),
(91, 12, 59, 5, 1, 1, 4, '2025-09-26 08:15:53', '2025-09-26 08:15:53'),
(92, 12, 58, 5, 1, 1, 4, '2025-09-26 08:16:27', '2025-09-26 08:16:27');

-- --------------------------------------------------------

--
-- Table structure for table `grade_levels`
--

CREATE TABLE `grade_levels` (
  `id` int NOT NULL,
  `grade_name` varchar(50) NOT NULL,
  `grade_code` varchar(10) NOT NULL,
  `level_type` enum('Nursery','Kindergarten','Elementary','Junior High School') NOT NULL,
  `grade_order` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `grade_levels`
--

INSERT INTO `grade_levels` (`id`, `grade_name`, `grade_code`, `level_type`, `grade_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Nursery', 'N', 'Nursery', 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(2, 'Kindergarten', 'K', 'Kindergarten', 2, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(3, 'Grade 1', 'G1', 'Elementary', 3, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(4, 'Grade 2', 'G2', 'Elementary', 4, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(5, 'Grade 3', 'G3', 'Elementary', 5, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(6, 'Grade 4', 'G4', 'Elementary', 6, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(7, 'Grade 5', 'G5', 'Elementary', 7, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(8, 'Grade 6', 'G6', 'Elementary', 8, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(9, 'Grade 7', 'G7', 'Junior High School', 9, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(10, 'Grade 8', 'G8', 'Junior High School', 10, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(11, 'Grade 9', 'G9', 'Junior High School', 11, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(12, 'Grade 10', 'G10', 'Junior High School', 12, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `method_type` enum('Bank','E-Wallet','Cash') NOT NULL,
  `account_name` varchar(200) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `qr_code_image` varchar(255) DEFAULT NULL,
  `display_name` varchar(100) NOT NULL,
  `instructions` text,
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_name`, `method_type`, `account_name`, `account_number`, `bank_name`, `qr_code_image`, `display_name`, `instructions`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'cash', 'Cash', NULL, NULL, NULL, NULL, 'Cash Payment', 'Pay directly at the school finance office during office hours.', 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(2, 'bank_transfer', 'Bank', 'GTBA School Inc.', '1234567890', 'BDO Unibank', NULL, 'Bank Transfer', 'Transfer to our BDO account. Please send proof of payment to finance office.', 1, 2, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(3, 'gcash', 'E-Wallet', 'GTBA School', '09171234567', NULL, NULL, 'GCash', 'Send payment via GCash. Screenshot the receipt and submit to finance office.', 1, 3, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(4, 'maya', 'E-Wallet', 'GTBA School', '09171234567', NULL, NULL, 'Maya (PayMaya)', 'Send payment via Maya. Screenshot the receipt and submit to finance office.', 1, 4, '2025-07-19 07:49:43', '2025-07-19 07:49:43');

-- --------------------------------------------------------

--
-- Table structure for table `payment_terms`
--

CREATE TABLE `payment_terms` (
  `id` int NOT NULL,
  `term_name` varchar(100) NOT NULL,
  `term_type` enum('installment','full_payment') NOT NULL,
  `school_year_id` int NOT NULL,
  `grade_level_id` int DEFAULT NULL,
  `full_payment_amount` decimal(10,2) DEFAULT NULL,
  `full_payment_due_date` date DEFAULT NULL,
  `full_payment_discount_percentage` decimal(5,2) DEFAULT '0.00',
  `down_payment_amount` decimal(10,2) DEFAULT NULL,
  `down_payment_due_date` date DEFAULT NULL,
  `monthly_fee_amount` decimal(10,2) DEFAULT NULL,
  `installment_start_month` int DEFAULT NULL,
  `installment_start_year` year DEFAULT NULL,
  `installment_end_month` int DEFAULT NULL,
  `installment_end_year` year DEFAULT NULL,
  `number_of_installments` int DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_terms`
--

INSERT INTO `payment_terms` (`id`, `term_name`, `term_type`, `school_year_id`, `grade_level_id`, `full_payment_amount`, `full_payment_due_date`, `full_payment_discount_percentage`, `down_payment_amount`, `down_payment_due_date`, `monthly_fee_amount`, `installment_start_month`, `installment_start_year`, `installment_end_month`, `installment_end_year`, `number_of_installments`, `description`, `is_active`, `is_default`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 'Full Payment - Nursery', 'full_payment', 3, 1, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱25,000.00\nDiscounted Amount: ₱23,750.00\nSavings: ₱1,250.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(7, 'Standard Installment - Nursery', 'installment', 3, 1, NULL, NULL, 0.00, 6250.00, '2025-06-15', 1875.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱6,250.00 (due upon enrollment)\n• Monthly Payment: ₱1,875.00 for 10 months\n• Total Amount: ₱25,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(8, 'Full Payment - Kindergarten', 'full_payment', 3, 2, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱28,000.00\nDiscounted Amount: ₱26,600.00\nSavings: ₱1,400.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(9, 'Standard Installment - Kindergarten', 'installment', 3, 2, NULL, NULL, 0.00, 7000.00, '2025-06-15', 2100.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱7,000.00 (due upon enrollment)\n• Monthly Payment: ₱2,100.00 for 10 months\n• Total Amount: ₱28,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(10, 'Full Payment - Grade 1', 'full_payment', 3, 3, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱30,000.00\nDiscounted Amount: ₱28,500.00\nSavings: ₱1,500.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(11, 'Standard Installment - Grade 1', 'installment', 3, 3, NULL, NULL, 0.00, 7500.00, '2025-06-15', 2250.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱7,500.00 (due upon enrollment)\n• Monthly Payment: ₱2,250.00 for 10 months\n• Total Amount: ₱30,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(12, 'Full Payment - Grade 2', 'full_payment', 3, 4, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱30,000.00\nDiscounted Amount: ₱28,500.00\nSavings: ₱1,500.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(13, 'Standard Installment - Grade 2', 'installment', 3, 4, NULL, NULL, 0.00, 7500.00, '2025-06-15', 2250.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱7,500.00 (due upon enrollment)\n• Monthly Payment: ₱2,250.00 for 10 months\n• Total Amount: ₱30,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(14, 'Full Payment - Grade 3', 'full_payment', 3, 5, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱32,000.00\nDiscounted Amount: ₱30,400.00\nSavings: ₱1,600.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(15, 'Standard Installment - Grade 3', 'installment', 3, 5, NULL, NULL, 0.00, 8000.00, '2025-06-15', 2400.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱8,000.00 (due upon enrollment)\n• Monthly Payment: ₱2,400.00 for 10 months\n• Total Amount: ₱32,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(16, 'Full Payment - Grade 4', 'full_payment', 3, 6, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱32,000.00\nDiscounted Amount: ₱30,400.00\nSavings: ₱1,600.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(17, 'Standard Installment - Grade 4', 'installment', 3, 6, NULL, NULL, 0.00, 8000.00, '2025-06-15', 2400.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱8,000.00 (due upon enrollment)\n• Monthly Payment: ₱2,400.00 for 10 months\n• Total Amount: ₱32,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(18, 'Full Payment - Grade 5', 'full_payment', 3, 7, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱34,000.00\nDiscounted Amount: ₱32,300.00\nSavings: ₱1,700.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(19, 'Standard Installment - Grade 5', 'installment', 3, 7, NULL, NULL, 0.00, 8500.00, '2025-06-15', 2550.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱8,500.00 (due upon enrollment)\n• Monthly Payment: ₱2,550.00 for 10 months\n• Total Amount: ₱34,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(20, 'Full Payment - Grade 6', 'full_payment', 3, 8, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱34,000.00\nDiscounted Amount: ₱32,300.00\nSavings: ₱1,700.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(21, 'Standard Installment - Grade 6', 'installment', 3, 8, NULL, NULL, 0.00, 8500.00, '2025-06-15', 2550.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱8,500.00 (due upon enrollment)\n• Monthly Payment: ₱2,550.00 for 10 months\n• Total Amount: ₱34,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(22, 'Full Payment - Grade 7', 'full_payment', 3, 9, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱36,000.00\nDiscounted Amount: ₱34,200.00\nSavings: ₱1,800.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(23, 'Standard Installment - Grade 7', 'installment', 3, 9, NULL, NULL, 0.00, 9000.00, '2025-06-15', 2700.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱9,000.00 (due upon enrollment)\n• Monthly Payment: ₱2,700.00 for 10 months\n• Total Amount: ₱36,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(24, 'Full Payment - Grade 8', 'full_payment', 3, 10, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱36,000.00\nDiscounted Amount: ₱34,200.00\nSavings: ₱1,800.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(25, 'Standard Installment - Grade 8', 'installment', 3, 10, NULL, NULL, 0.00, 9000.00, '2025-06-15', 2700.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱9,000.00 (due upon enrollment)\n• Monthly Payment: ₱2,700.00 for 10 months\n• Total Amount: ₱36,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(26, 'Full Payment - Grade 9', 'full_payment', 3, 11, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱38,000.00\nDiscounted Amount: ₱36,100.00\nSavings: ₱1,900.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(27, 'Standard Installment - Grade 9', 'installment', 3, 11, NULL, NULL, 0.00, 9500.00, '2025-06-15', 2850.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱9,500.00 (due upon enrollment)\n• Monthly Payment: ₱2,850.00 for 10 months\n• Total Amount: ₱38,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(28, 'Full Payment - Grade 10', 'full_payment', 3, 12, NULL, '2025-06-30', 5.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pay the full tuition amount before 2025-06-30 and get 5% discount.\nOriginal Amount: ₱38,000.00\nDiscounted Amount: ₱36,100.00\nSavings: ₱1,900.00', 1, 0, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(29, 'Standard Installment - Grade 10', 'installment', 3, 12, NULL, NULL, 0.00, 9500.00, '2025-06-15', 2850.00, 7, '2025', 4, '2026', 10, 'Standard 10-month installment plan:\n• Down Payment: ₱9,500.00 (due upon enrollment)\n• Monthly Payment: ₱2,850.00 for 10 months\n• Total Amount: ₱38,000.00\n• Payment Period: July 2025 - April 2026', 1, 1, 1, '2025-08-15 12:57:11', '2025-08-15 12:57:11'),
(31, 'Nursery - Standard Installment Plan', 'installment', 2, 1, NULL, NULL, 0.00, 7260.00, '2025-06-15', 2904.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Nursery. Down payment: ₱7,260.00 upon enrollment, then ₱2,904.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(33, 'Kindergarten - Standard Installment Plan', 'installment', 2, 2, NULL, NULL, 0.00, 8074.00, '2025-06-15', 3229.60, 7, '2025', 4, '2026', 10, 'Standard installment plan for Kindergarten. Down payment: ₱8,074.00 upon enrollment, then ₱3,229.60 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(35, 'Grade 1 - Standard Installment Plan', 'installment', 2, 3, NULL, NULL, 0.00, 8690.00, '2025-06-15', 3476.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 1. Down payment: ₱8,690.00 upon enrollment, then ₱3,476.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(37, 'Grade 2 - Standard Installment Plan', 'installment', 2, 4, NULL, NULL, 0.00, 8690.00, '2025-06-15', 3476.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 2. Down payment: ₱8,690.00 upon enrollment, then ₱3,476.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(39, 'Grade 3 - Standard Installment Plan', 'installment', 2, 5, NULL, NULL, 0.00, 9306.00, '2025-06-15', 3722.40, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 3. Down payment: ₱9,306.00 upon enrollment, then ₱3,722.40 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(41, 'Grade 4 - Standard Installment Plan', 'installment', 2, 6, NULL, NULL, 0.00, 9306.00, '2025-06-15', 3722.40, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 4. Down payment: ₱9,306.00 upon enrollment, then ₱3,722.40 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(43, 'Grade 5 - Standard Installment Plan', 'installment', 2, 7, NULL, NULL, 0.00, 9900.00, '2025-06-15', 3960.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 5. Down payment: ₱9,900.00 upon enrollment, then ₱3,960.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(45, 'Grade 6 - Standard Installment Plan', 'installment', 2, 8, NULL, NULL, 0.00, 9900.00, '2025-06-15', 3960.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 6. Down payment: ₱9,900.00 upon enrollment, then ₱3,960.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(47, 'Grade 7 - Standard Installment Plan', 'installment', 2, 9, NULL, NULL, 0.00, 10000.00, '2025-06-15', 4280.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 7. Down payment: ₱10,000.00 upon enrollment, then ₱4,280.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(49, 'Grade 8 - Standard Installment Plan', 'installment', 2, 10, NULL, NULL, 0.00, 10000.00, '2025-06-15', 4280.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 8. Down payment: ₱10,000.00 upon enrollment, then ₱4,280.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(51, 'Grade 9 - Standard Installment Plan', 'installment', 2, 11, NULL, NULL, 0.00, 10000.00, '2025-06-15', 4610.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 9. Down payment: ₱10,000.00 upon enrollment, then ₱4,610.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(53, 'Grade 10 - Standard Installment Plan', 'installment', 2, 12, NULL, NULL, 0.00, 10000.00, '2025-06-15', 4610.00, 7, '2025', 4, '2026', 10, 'Standard installment plan for Grade 10. Down payment: ₱10,000.00 upon enrollment, then ₱4,610.00 monthly for 10 months (July 2025 - April 2026).', 1, 1, 1, '2025-08-15 13:13:30', '2025-08-15 13:13:30'),
(54, 'Grade 2 - Full Payment', 'full_payment', 2, 4, 43450.00, '2026-01-01', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Full payment for Grade 2 Students.', 1, 0, 2, '2025-11-23 06:24:26', '2025-11-23 06:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `payment_uploads`
--

CREATE TABLE `payment_uploads` (
  `id` int NOT NULL,
  `payment_id` int NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text,
  `permissions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Administrator', 'Full system access', '{\"manage_all\": true, \"manage_users\": true, \"view_audit_logs\": true, \"manage_school_years\": true}', '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(2, 'principal', 'Principal', 'School management access', '{\"manage_sections\": true, \"manage_schedules\": true, \"manage_curriculum\": true, \"upload_announcements\": true}', '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(3, 'finance', 'Finance Officer', 'Financial management access', '{\"manage_tuition_fees\": true, \"manage_payment_methods\": true}', '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(4, 'registrar', 'Registrar', 'Student registration access', '{\"view_enrollments\": true, \"manage_student_info\": true, \"create_student_accounts\": true}', '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(5, 'teacher', 'Teacher', 'Teaching access', '{\"manage_grades\": true, \"view_students\": true, \"view_assigned_sections\": true}', '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(6, 'student', 'Student', 'Student portal access', '{\"view_grades\": true, \"view_section\": true, \"view_tuition\": true, \"view_schedule\": true}', '2025-07-19 07:49:43', '2025-07-19 07:49:43');

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int NOT NULL,
  `year_name` varchar(20) DEFAULT NULL,
  `year_label` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT '0',
  `is_current` tinyint(1) DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `year_name`, `year_label`, `start_date`, `end_date`, `is_active`, `is_current`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2024-2025', '2024-2025', '2024-08-15', '2025-05-30', 0, 0, 1, '2025-07-19 07:49:43', '2025-07-30 09:59:56'),
(2, '2025-2026', '2025-2026', '2025-08-15', '2026-05-30', 1, 1, 1, '2025-07-19 07:49:43', '2025-07-30 09:59:51'),
(3, '2026-2027', '2026-2027', '2026-11-11', '2027-11-11', 0, 0, 1, '2025-07-22 08:57:39', '2025-07-30 02:13:16'),
(4, '2023-2024', '2023-2024', '2023-01-11', '2024-11-11', 0, 0, 1, '2025-09-05 07:16:25', '2025-09-05 07:16:25'),
(5, '2022-2023', '2022-2023', '2022-01-01', '2023-01-01', 0, 0, 1, '2025-09-26 08:15:22', '2025-09-26 08:15:22');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `grade_level_id` int NOT NULL,
  `school_year_id` int NOT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `description` text,
  `current_enrollment` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `grade_level_id`, `school_year_id`, `room_number`, `description`, `current_enrollment`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 'Nursery - Daisy', 1, 2, 'Room 103', 'Nursery section for 3-4 year olds', 10, 1, 3, '2025-07-22 10:22:54', '2025-07-30 15:52:36'),
(7, 'Kindergarten - Lily', 2, 2, 'Room 104', 'Kindergarten section for 5-6 year olds', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:07:50'),
(8, 'Grade 1 - Bamboo', 3, 2, 'Room 204', 'Grade 1 section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:07:54'),
(9, 'Grade 2 - Orchid', 4, 2, 'Room 205', 'Grade 2 section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:07:58'),
(10, 'Grade 3 - Hibiscus', 5, 2, 'Room 303', 'Grade 3 section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:04'),
(11, 'Grade 4 - Acacia', 6, 2, 'Room 304', 'Grade 4 section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:07'),
(12, 'Grade 5 - Mahogany', 7, 2, 'Room 403', 'Grade 5 section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:11'),
(13, 'Grade 6 - Narra', 8, 2, 'Room 404', 'Grade 6 section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:14'),
(14, 'Grade 7 - Pearl', 9, 2, 'Room 505', 'Grade 7 junior high section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:19'),
(15, 'Grade 8 - Gold', 10, 2, 'Room 506', 'Grade 8 junior high section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:23'),
(16, 'Grade 9 - Silver', 11, 2, 'Room 507', 'Grade 9 junior high section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:28'),
(17, 'Grade 10 - Platinum', 12, 2, 'Room 508', 'Grade 10 junior high section', 10, 1, 3, '2025-07-22 10:22:54', '2025-08-13 12:46:22'),
(19, 'Grade 1 - Bamboo', 3, 1, 'Room 204', 'Grade 1 Bamboo section for 2024-2025', 0, 1, 3, '2025-09-05 06:43:02', '2025-09-05 06:43:02'),
(20, 'Nursery - Daisy', 1, 4, 'Room 202', '', 0, 1, 4, '2025-09-05 07:18:48', '2025-09-05 07:18:48'),
(21, 'Hope', 12, 5, 'Room 101', 'Testing for history', 0, 1, 4, '2025-09-26 08:17:18', '2025-09-26 08:17:18'),
(22, 'Destiny', 3, 2, 'Room 101', '', 1, 1, 4, '2025-11-23 09:12:38', '2025-11-23 09:33:38');

-- --------------------------------------------------------

--
-- Stand-in structure for view `section_assignments`
-- (See below for the actual view)
--
CREATE TABLE `section_assignments` (
`section_id` int
,`section_name` varchar(100)
,`grade_level_id` int
,`school_year_id` int
,`current_enrollment` int
,`section_active` tinyint(1)
,`grade_name` varchar(50)
,`grade_code` varchar(10)
,`year_label` varchar(20)
,`teacher_id` int
,`teacher_name` varchar(302)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`is_primary` tinyint(1)
,`assigned_date` date
,`assignment_active` tinyint(1)
);

-- --------------------------------------------------------

--
-- Table structure for table `section_teachers`
--

CREATE TABLE `section_teachers` (
  `id` int NOT NULL,
  `section_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `assigned_date` date NOT NULL DEFAULT (curdate()),
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `section_teachers`
--

INSERT INTO `section_teachers` (`id`, `section_id`, `teacher_id`, `is_primary`, `assigned_date`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(10, 6, 12, 0, '2025-07-30', 1, 4, '2025-07-30 10:56:18', '2025-07-30 10:56:18'),
(11, 17, 11, 0, '2025-08-13', 1, 4, '2025-08-13 12:46:30', '2025-08-13 12:46:30'),
(12, 6, 11, 0, '2025-09-03', 1, 4, '2025-09-03 06:54:50', '2025-09-03 06:54:50'),
(13, 20, 11, 1, '2025-09-05', 1, 4, '2025-09-05 07:18:48', '2025-09-05 07:18:48'),
(14, 22, 12, 1, '2025-11-23', 1, 4, '2025-11-23 09:12:38', '2025-11-23 09:12:38'),
(15, 22, 13, 1, '2025-11-23', 0, 4, '2025-11-23 09:12:38', '2025-11-23 09:21:26'),
(16, 22, 11, 0, '2025-11-23', 1, 4, '2025-11-23 09:39:11', '2025-11-23 09:39:11');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `lrn` varchar(12) DEFAULT NULL,
  `student_type` enum('Continuing','Transfer','New') NOT NULL,
  `enrollment_status` enum('Pending Payment','Enrolled','Dropped','Suspended','Transferred','Graduated') DEFAULT 'Pending Payment',
  `transferred_to_school` varchar(255) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `transfer_reason` text,
  `transfer_approved_by` int DEFAULT NULL,
  `transfer_approved_at` timestamp NULL DEFAULT NULL,
  `prevent_auto_promotion` tinyint(1) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `place_of_birth` varchar(200) NOT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `present_address` text NOT NULL,
  `permanent_address` text NOT NULL,
  `father_id` int DEFAULT NULL,
  `mother_id` int DEFAULT NULL,
  `legal_guardian_id` int DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `current_grade_level_id` int DEFAULT NULL,
  `current_section_id` int DEFAULT NULL,
  `current_school_year_id` int DEFAULT NULL,
  `medical_conditions` text,
  `allergies` text,
  `special_needs` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_id`, `lrn`, `student_type`, `enrollment_status`, `transferred_to_school`, `transfer_date`, `transfer_reason`, `transfer_approved_by`, `transfer_approved_at`, `prevent_auto_promotion`, `first_name`, `last_name`, `middle_name`, `suffix`, `gender`, `date_of_birth`, `place_of_birth`, `religion`, `present_address`, `permanent_address`, `father_id`, `mother_id`, `legal_guardian_id`, `emergency_contact_name`, `emergency_contact_number`, `emergency_contact_relationship`, `current_grade_level_id`, `current_section_id`, `current_school_year_id`, `medical_conditions`, `allergies`, `special_needs`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(241, 310, '2025001', '202500000001', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Angelo', 'Cruz', 'Santos', NULL, 'Male', '2021-03-15', 'Manila', 'Catholic', '123 Main Street, Manila', '123 Main Street, Manila', 226, 238, 538, 'Juan Cruz', '09171234567', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(242, 311, '2025002', '202500000002', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Sofia', 'Reyes', 'Garcia', NULL, 'Female', '2021-07-22', 'Quezon City', 'Catholic', '456 Oak Avenue, Quezon City', '456 Oak Avenue, Quezon City', 227, 239, 539, 'Jose Reyes', '09182345678', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(243, 312, '2025003', '202500000003', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Gabriel', 'Santos', 'Lopez', NULL, 'Male', '2021-11-08', 'Pasig City', 'Catholic', '789 Pine Street, Pasig City', '789 Pine Street, Pasig City', 228, 240, 540, 'Antonio Santos', '09193456789', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(244, 313, '2025004', '202500000004', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Isabella', 'Gonzales', 'Martinez', NULL, 'Female', '2021-05-30', 'Makati City', 'Catholic', '321 Elm Road, Makati City', '321 Elm Road, Makati City', 229, 241, 541, 'Pedro Gonzales', '09204567890', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(245, 314, '2025005', '202500000005', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Miguel', 'Hernandez', 'Rivera', NULL, 'Male', '2021-09-12', 'Taguig City', 'Catholic', '654 Birch Lane, Taguig City', '654 Birch Lane, Taguig City', 230, 242, 542, 'Miguel Hernandez', '09215678901', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(246, 315, '2025006', '202500000006', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Sophia', 'Lopez', 'Torres', NULL, 'Female', '2021-01-25', 'Paranaque City', 'Catholic', '987 Cedar Drive, Paranaque City', '987 Cedar Drive, Paranaque City', 231, 243, 543, 'Carlos Lopez', '09226789012', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(247, 316, '2025007', '202500000007', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Lorenzo', 'Martinez', 'Flores', NULL, 'Male', '2021-04-18', 'Las Pinas City', 'Catholic', '147 Maple Court, Las Pinas City', '147 Maple Court, Las Pinas City', 232, 244, 544, 'Roberto Martinez', '09237890123', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(248, 317, '2025008', '202500000008', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Camila', 'Torres', 'Ramos', NULL, 'Female', '2021-12-03', 'Muntinlupa City', 'Catholic', '258 Walnut Street, Muntinlupa City', '258 Walnut Street, Muntinlupa City', 233, 245, 545, 'Fernando Torres', '09248901234', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(249, 318, '2025009', '202500000009', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Sebastian', 'Flores', 'Morales', NULL, 'Male', '2021-08-27', 'Marikina City', 'Catholic', '369 Aspen Avenue, Marikina City', '369 Aspen Avenue, Marikina City', 234, 246, 546, 'Manuel Flores', '09259012345', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(250, 319, '2025010', '202500000010', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Valentina', 'Ramos', 'Castro', NULL, 'Female', '2021-06-14', 'Pasay City', 'Catholic', '741 Spruce Road, Pasay City', '741 Spruce Road, Pasay City', 235, 247, 547, 'Ricardo Ramos', '09260123456', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-11-24 07:33:42'),
(361, 421, '2025011', '202500000011', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Mateo', 'Morales', 'Mendoza', NULL, 'Male', '2020-03-10', 'Caloocan City', 'Catholic', '852 Hickory Lane, Caloocan City', '852 Hickory Lane, Caloocan City', 236, 248, 548, 'Eduardo Morales', '09271234567', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(362, 422, '2025012', '202500000012', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Emma', 'Castro', 'Jimenez', NULL, 'Female', '2020-07-15', 'Malabon City', 'Catholic', '963 Poplar Street, Malabon City', '963 Poplar Street, Malabon City', 237, 249, 549, 'Alejandro Castro', '09282345678', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(363, 423, '2025013', '202500000013', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Diego', 'Cruz', 'Santos', NULL, 'Male', '2020-11-20', 'Navotas City', 'Catholic', '159 Willow Road, Navotas City', '159 Willow Road, Navotas City', 322, 358, 550, 'Daniel Dela Rosa', '09301234567', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(364, 424, '2025014', '202500000014', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Mia', 'Reyes', 'Garcia', NULL, 'Female', '2020-05-25', 'Valenzuela City', 'Catholic', '753 Chestnut Ave, Valenzuela City', '753 Chestnut Ave, Valenzuela City', 323, 359, 551, 'Gabriel Bautista', '09312345678', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(365, 425, '2025015', '202500000015', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Lucas', 'Santos', 'Lopez', NULL, 'Male', '2020-09-30', 'San Juan City', 'Catholic', '486 Sycamore Dr, San Juan City', '486 Sycamore Dr, San Juan City', 324, 360, 552, 'Rafael Navarro', '09323456789', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(366, 426, '2025016', '202500000016', 'New', 'Suspended', NULL, NULL, NULL, NULL, NULL, NULL, 'Zoe', 'Gonzales', 'Martinez', NULL, 'Female', '2020-01-12', 'Mandaluyong City', 'Catholic', '357 Magnolia St, Mandaluyong City', '357 Magnolia St, Mandaluyong City', 325, 361, 553, 'Samuel Valdez', '09334567890', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:43:43'),
(367, 427, '2025017', '202500000017', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Ethan', 'Hernandez', 'Rivera', NULL, 'Male', '2020-04-08', 'Pateros', 'Catholic', '624 Dogwood Lane, Pateros', '624 Dogwood Lane, Pateros', 326, 362, 554, 'Benjamin Aguilar', '09345678901', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(368, 428, '2025018', '202500000018', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Aria', 'Lopez', 'Torres', NULL, 'Female', '2020-12-18', 'Marikina City', 'Catholic', '791 Redwood Road, Marikina City', '791 Redwood Road, Marikina City', 327, 363, 555, 'Nicolas Velasco', '09356789012', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(369, 429, '2025019', '202500000019', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Noah', 'Martinez', 'Flores', NULL, 'Male', '2020-08-22', 'Antipolo City', 'Catholic', '135 Sequoia Circle, Antipolo City', '135 Sequoia Circle, Antipolo City', 328, 364, 556, 'Adrian Castillo', '09367890123', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(370, 430, '2025020', '202500000020', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Luna', 'Torres', 'Ramos', NULL, 'Female', '2020-06-05', 'Cainta, Rizal', 'Catholic', '802 Bamboo Street, Cainta, Rizal', '802 Bamboo Street, Cainta, Rizal', 329, 365, 557, 'Victor Medina', '09378901234', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(371, 431, '2025021', '202500000021', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Alexander', 'Dela Rosa', 'Villanueva', NULL, 'Male', '2019-02-15', 'Manila', 'Catholic', '123 Narra Street, Manila', '123 Narra Street, Manila', 330, 366, 558, 'Francisco Campos', '09390123456', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(372, 432, '2025022', '202500000022', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Victoria', 'Bautista', 'Salazar', NULL, 'Female', '2019-06-20', 'Quezon City', 'Catholic', '456 Molave Avenue, Quezon City', '456 Molave Avenue, Quezon City', 331, 367, 559, 'Diego Ortega', '09401234567', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(373, 433, '2025023', '202500000023', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Leonardo', 'Navarro', 'Cordero', NULL, 'Male', '2019-10-25', 'Pasig City', 'Catholic', '789 Mahogany Road, Pasig City', '789 Mahogany Road, Pasig City', 332, 368, 560, 'Jorge Lozano', '09412345678', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(374, 434, '2025024', '202500000024', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Angelica', 'Valdez', 'Gutierrez', NULL, 'Female', '2019-04-30', 'Makati City', 'Catholic', '321 Banyan Street, Makati City', '321 Banyan Street, Makati City', 333, 369, 561, 'Raul Espinoza', '09423456789', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(375, 435, '2025025', '202500000025', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Nicolas', 'Aguilar', 'Herrera', NULL, 'Male', '2019-08-14', 'Taguig City', 'Catholic', '654 Ipil Lane, Taguig City', '654 Ipil Lane, Taguig City', 334, 370, 562, 'Sergio Molina', '09434567890', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(376, 436, '2025026', '202500000026', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Catalina', 'Velasco', 'Romero', NULL, 'Female', '2019-12-18', 'Paranaque City', 'Catholic', '987 Acacia Drive, Paranaque City', '987 Acacia Drive, Paranaque City', 335, 371, 563, 'Arturo Pacheco', '09445678901', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(377, 437, '2025027', '202500000027', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Maximiliano', 'Castillo', 'Vargas', NULL, 'Male', '2019-03-22', 'Las Pinas City', 'Catholic', '147 Bamboo Court, Las Pinas City', '147 Bamboo Court, Las Pinas City', 336, 372, 564, 'Ignacio Figueroa', '09456789012', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(378, 438, '2025028', '202500000028', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Francesca', 'Medina', 'Peña', NULL, 'Female', '2019-07-26', 'Muntinlupa City', 'Catholic', '258 Talisay Street, Muntinlupa City', '258 Talisay Street, Muntinlupa City', 337, 373, 565, 'Emilio Contreras', '09467890123', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(379, 439, '2025029', '202500000029', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Santiago', 'Guerrero', 'Dominguez', NULL, 'Male', '2019-11-30', 'Marikina City', 'Catholic', '369 Bougainvillea Avenue, Marikina City', '369 Bougainvillea Avenue, Marikina City', 338, 374, 566, 'Rodrigo Maldonado', '09478901234', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(380, 440, '2025030', '202500000030', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Esperanza', 'Campos', 'Silva', NULL, 'Female', '2019-05-14', 'Pasay City', 'Catholic', '741 Sampaguita Road, Pasay City', '741 Sampaguita Road, Pasay City', 339, 375, 567, 'Armando Acosta', '09489012345', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(381, 441, '2025031', '1123', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Emmanuel', 'Ortega', 'Ponce', NULL, 'Male', '2018-01-20', 'Manila', 'Catholic', '456 Rose Street, Manila', '456 Rose Street, Manila', 340, 376, 568, 'Diego Ortega', '09401234567', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(382, 442, '2025032', '202500000032', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Gabriella', 'Lozano', 'Cervantes', NULL, 'Female', '2018-05-25', 'Quezon City', 'Catholic', '789 Lily Avenue, Quezon City', '789 Lily Avenue, Quezon City', 341, 377, 569, 'Jorge Lozano', '09412345678', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(383, 443, '2025033', '202500000033', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Rafael', 'Espinoza', 'Delgado', NULL, 'Male', '2018-09-30', 'Pasig City', 'Catholic', '321 Tulip Road, Pasig City', '321 Tulip Road, Pasig City', 342, 378, 570, 'Raul Espinoza', '09423456789', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(384, 444, '2025034', '202500000034', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Valeria', 'Molina', 'Fuentes', NULL, 'Female', '2018-03-14', 'Makati City', 'Catholic', '654 Sunflower Lane, Makati City', '654 Sunflower Lane, Makati City', 343, 379, 571, 'Sergio Molina', '09434567890', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(385, 445, '2025035', '202500000035', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Rodrigo', 'Pacheco', 'Sandoval', NULL, 'Male', '2018-07-18', 'Taguig City', 'Catholic', '987 Orchid Drive, Taguig City', '987 Orchid Drive, Taguig City', 344, 380, 572, 'Arturo Pacheco', '09445678901', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(386, 446, '2025036', '202500000036', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Ximena', 'Figueroa', 'Escobar', NULL, 'Female', '2018-11-22', 'Paranaque City', 'Catholic', '147 Jasmine Court, Paranaque City', '147 Jasmine Court, Paranaque City', 345, 381, 573, 'Ignacio Figueroa', '09456789012', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(387, 447, '2025037', '202500000037', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Thiago', 'Contreras', 'Galvan', NULL, 'Male', '2018-04-26', 'Las Pinas City', 'Catholic', '258 Dahlia Street, Las Pinas City', '258 Dahlia Street, Las Pinas City', 346, 382, 574, 'Emilio Contreras', '09467890123', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(388, 448, '2025038', '202500000038', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Amelia', 'Maldonado', 'Barrera', NULL, 'Female', '2018-08-30', 'Muntinlupa City', 'Catholic', '369 Hibiscus Avenue, Muntinlupa City', '369 Hibiscus Avenue, Muntinlupa City', 347, 383, 575, 'Rodrigo Maldonado', '09478901234', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(389, 449, '2025039', '202500000039', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Fernando', 'Acosta', 'Cabrera', NULL, 'Male', '2018-12-14', 'Marikina City', 'Catholic', '741 Carnation Road, Marikina City', '741 Carnation Road, Marikina City', 348, 384, 576, 'Armando Acosta', '09489012345', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(390, 450, '2025040', '202500000040', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Dulce', 'Vega', 'Cortez', NULL, 'Female', '2018-06-18', 'Pasay City', 'Catholic', '852 Petunia Lane, Pasay City', '852 Petunia Lane, Pasay City', 349, 385, 577, 'Esteban Vega', '09490123456', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(391, 451, '2025041', '202500000041', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Joaquin', 'Rojas', 'Moreno', NULL, 'Male', '2017-02-10', 'Manila', 'Catholic', '123 Violet Street, Manila', '123 Violet Street, Manila', 350, 386, 578, 'Cesar Rojas', '09501234567', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(392, 452, '2025042', '202500000042', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Renata', 'Perez', 'Soto', NULL, 'Female', '2017-06-15', 'Quezon City', 'Catholic', '456 Marigold Avenue, Quezon City', '456 Marigold Avenue, Quezon City', 351, 387, 579, 'Ruben Perez', '09512345678', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(393, 453, '2025043', '202500000043', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Adriano', 'Carrasco', 'Restrepo', NULL, 'Male', '2017-10-20', 'Pasig City', 'Catholic', '789 Chrysanthemum Road, Pasig City', '789 Chrysanthemum Road, Pasig City', 352, 388, 580, 'Andres Carrasco', '09523456789', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(394, 454, '2025044', '202500000044', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Esperanza', 'Zuniga', 'Osorio', NULL, 'Female', '2017-04-25', 'Makati City', 'Catholic', '321 Lavender Lane, Makati City', '321 Lavender Lane, Makati City', 353, 389, 581, 'Mauricio Zuniga', '09534567890', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(395, 455, '2025045', '202500000045', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Maximos', 'Varela', 'Calderon', NULL, 'Male', '2017-08-30', 'Taguig City', 'Catholic', '654 Daffodil Drive, Taguig City', '654 Daffodil Drive, Taguig City', 354, 390, 582, 'Hector Varela', '09545678901', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(396, 456, '2025046', '202500000046', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Soledad', 'Hidalgo', 'Espejo', NULL, 'Female', '2017-12-14', 'Paranaque City', 'Catholic', '987 Freesia Court, Paranaque City', '987 Freesia Court, Paranaque City', 355, 391, 583, 'Oscar Hidalgo', '09556789012', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(397, 457, '2025047', '202500000047', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Patricio', 'Pantoja', 'Uribe', NULL, 'Male', '2017-03-18', 'Las Pinas City', 'Catholic', '147 Poppy Street, Las Pinas City', '147 Poppy Street, Las Pinas City', 356, 392, 584, 'Marco Pantoja', '09567890123', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(398, 458, '2025048', '202500000048', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Milagros', 'Quintero', 'Benitez', NULL, 'Female', '2017-07-22', 'Muntinlupa City', 'Catholic', '258 Zinnia Avenue, Muntinlupa City', '258 Zinnia Avenue, Muntinlupa City', 357, 393, 585, 'Luis Quintero', '09578901234', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(399, 459, '2025049', '202500000049', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Cristobal', 'Camacho', 'Palacios', NULL, 'Male', '2017-11-26', 'Marikina City', 'Catholic', '369 Iris Road, Marikina City', '369 Iris Road, Marikina City', 394, 466, 586, 'Pablo Camacho', '09589012345', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(400, 460, '2025050', '202500000050', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Remedios', 'Cardenas', 'Montoya', NULL, 'Female', '2017-05-30', 'Pasay City', 'Catholic', '741 Begonia Lane, Pasay City', '741 Begonia Lane, Pasay City', 395, 467, 587, 'Enrique Cardenas', '09590123456', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(401, 461, '2025051', '202500000051', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Agustin', 'Solis', 'Villalobos', NULL, 'Male', '2016-01-15', 'Manila', 'Catholic', '852 Camellia Street, Manila', '852 Camellia Street, Manila', 396, 468, 588, 'Javier Solis', '09601234567', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(402, 462, '2025052', '202500000052', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Concepcion', 'Ibarra', 'Aranda', NULL, 'Female', '2016-05-20', 'Quezon City', 'Catholic', '963 Azalea Avenue, Quezon City', '963 Azalea Avenue, Quezon City', 397, 469, 589, 'Mario Ibarra', '09612345678', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(403, 463, '2025053', '202500000053', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Teodoro', 'Meza', 'Coronado', NULL, 'Male', '2016-09-25', 'Pasig City', 'Catholic', '159 Geranium Road, Pasig City', '159 Geranium Road, Pasig City', 398, 470, 590, 'Felipe Meza', '09623456789', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(404, 464, '2025054', '202500000054', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Pilar', 'Cano', 'Avalos', NULL, 'Female', '2016-03-30', 'Makati City', 'Catholic', '753 Snapdragon Lane, Makati City', '753 Snapdragon Lane, Makati City', 399, 471, 591, 'Alvaro Cano', '09634567890', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(405, 465, '2025055', '202500000055', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Leopoldo', 'Ochoa', 'Bermudez', NULL, 'Male', '2016-07-14', 'Taguig City', 'Catholic', '486 Peony Drive, Taguig City', '486 Peony Drive, Taguig City', 400, 472, 592, 'Guillermo Ochoa', '09645678901', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(406, 466, '2025056', '202500000056', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Clementina', 'Paredes', 'Casillas', NULL, 'Female', '2016-11-18', 'Paranaque City', 'Catholic', '357 Pansy Court, Paranaque City', '357 Pansy Court, Paranaque City', 401, 473, 593, 'Jaime Paredes', '09656789012', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(407, 467, '2025057', '202500000057', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Evaristo', 'Cruz', 'Santos', NULL, 'Male', '2016-04-22', 'Las Pinas City', 'Catholic', '624 Cosmos Street, Las Pinas City', '624 Cosmos Street, Las Pinas City', 402, 474, 594, 'Juan Cruz', '09171234567', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(408, 468, '2025058', '202500000058', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Rosario', 'Reyes', 'Garcia', NULL, 'Female', '2016-08-26', 'Muntinlupa City', 'Catholic', '791 Gladiolus Avenue, Muntinlupa City', '791 Gladiolus Avenue, Muntinlupa City', 403, 475, 595, 'Jose Reyes', '09182345678', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(409, 469, '2025059', '202500000059', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Florencio', 'Santos', 'Lopez', NULL, 'Male', '2016-12-30', 'Marikina City', 'Catholic', '135 Aster Road, Marikina City', '135 Aster Road, Marikina City', 404, 476, 596, 'Antonio Santos', '09193456789', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(410, 470, '2025060', '202500000060', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Amparo', 'Gonzales', 'Martinez', NULL, 'Female', '2016-06-14', 'Pasay City', 'Catholic', '802 Carnation Lane, Pasay City', '802 Carnation Lane, Pasay City', 405, 477, 597, 'Pedro Gonzales', '09204567890', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(411, 471, '2025061', '202500000061', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Anastacio', 'Hernandez', 'Rivera', NULL, 'Male', '2015-02-20', 'Manila', 'Catholic', '147 Lotus Street, Manila', '147 Lotus Street, Manila', 406, 478, 598, 'Miguel Hernandez', '09215678901', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(412, 472, '2025062', '202500000062', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Trinidad', 'Lopez', 'Torres', NULL, 'Female', '2015-06-25', 'Quezon City', 'Catholic', '258 Tulip Avenue, Quezon City', '258 Tulip Avenue, Quezon City', 407, 479, 599, 'Carlos Lopez', '09226789012', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(413, 473, '2025063', '202500000063', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Ireneo', 'Martinez', 'Flores', NULL, 'Male', '2015-10-30', 'Pasig City', 'Catholic', '369 Jasmine Road, Pasig City', '369 Jasmine Road, Pasig City', 408, 480, 600, 'Roberto Martinez', '09237890123', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(414, 474, '2025064', '202500000064', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Asuncion', 'Torres', 'Ramos', NULL, 'Female', '2015-04-14', 'Makati City', 'Catholic', '741 Magnolia Lane, Makati City', '741 Magnolia Lane, Makati City', 409, 481, 601, 'Fernando Torres', '09248901234', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(415, 475, '2025065', '202500000065', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Crisanto', 'Flores', 'Morales', NULL, 'Male', '2015-08-18', 'Taguig City', 'Catholic', '852 Gardenia Drive, Taguig City', '852 Gardenia Drive, Taguig City', 410, 482, 602, 'Manuel Flores', '09259012345', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(416, 476, '2025066', '202500000066', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Encarnacion', 'Ramos', 'Castro', NULL, 'Female', '2015-12-22', 'Paranaque City', 'Catholic', '963 Sunflower Court, Paranaque City', '963 Sunflower Court, Paranaque City', 411, 483, 603, 'Ricardo Ramos', '09260123456', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(417, 477, '2025067', '202500000067', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Placido', 'Morales', 'Mendoza', NULL, 'Male', '2015-03-26', 'Las Pinas City', 'Catholic', '159 Lily Street, Las Pinas City', '159 Lily Street, Las Pinas City', 412, 484, 604, 'Eduardo Morales', '09271234567', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(418, 478, '2025068', '202500000068', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Natividad', 'Castro', 'Jimenez', NULL, 'Female', '2015-07-30', 'Muntinlupa City', 'Catholic', '753 Rose Avenue, Muntinlupa City', '753 Rose Avenue, Muntinlupa City', 413, 485, 605, 'Alejandro Castro', '09282345678', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(419, 479, '2025069', '202500000069', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Basilio', 'Dela Rosa', 'Villanueva', NULL, 'Male', '2015-11-14', 'Marikina City', 'Catholic', '486 Daisy Road, Marikina City', '486 Daisy Road, Marikina City', 414, 486, 606, 'Daniel Dela Rosa', '09301234567', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(420, 480, '2025070', '202500000070', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Purificacion', 'Bautista', 'Salazar', NULL, 'Female', '2015-05-18', 'Pasay City', 'Catholic', '357 Orchid Lane, Pasay City', '357 Orchid Lane, Pasay City', 415, 487, 607, 'Gabriel Bautista', '09312345678', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(421, 481, '2025071', '202500000071', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Fortunato', 'Navarro', 'Cordero', NULL, 'Male', '2014-01-10', 'Manila', 'Catholic', '624 Hibiscus Street, Manila', '624 Hibiscus Street, Manila', 416, 488, 608, 'Rafael Navarro', '09323456789', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(422, 482, '2025072', '202500000072', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Guadalupe', 'Valdez', 'Gutierrez', NULL, 'Female', '2014-05-15', 'Quezon City', 'Catholic', '791 Dahlia Avenue, Quezon City', '791 Dahlia Avenue, Quezon City', 417, 489, 609, 'Samuel Valdez', '09334567890', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(423, 483, '2025073', '202500000073', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Leoncio', 'Aguilar', 'Herrera', NULL, 'Male', '2014-09-20', 'Pasig City', 'Catholic', '135 Petunia Road, Pasig City', '135 Petunia Road, Pasig City', 418, 490, 610, 'Benjamin Aguilar', '09345678901', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(424, 484, '2025074', '202500000074', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Inmaculada', 'Velasco', 'Romero', NULL, 'Female', '2014-03-25', 'Makati City', 'Catholic', '802 Violet Lane, Makati City', '802 Violet Lane, Makati City', 419, 491, 611, 'Nicolas Velasco', '09356789012', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(425, 485, '2025075', '202500000075', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Teodulo', 'Castillo', 'Vargas', NULL, 'Male', '2014-07-30', 'Taguig City', 'Catholic', '147 Marigold Drive, Taguig City', '147 Marigold Drive, Taguig City', 420, 492, 612, 'Adrian Castillo', '09367890123', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(426, 486, '2025076', '202500000076', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Presentacion', 'Medina', 'Peña', NULL, 'Female', '2014-11-14', 'Paranaque City', 'Catholic', '258 Poppy Court, Paranaque City', '258 Poppy Court, Paranaque City', 421, 493, 613, 'Victor Medina', '09378901234', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(427, 487, '2025077', '202500000077', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Ambrosio', 'Guerrero', 'Dominguez', NULL, 'Male', '2014-04-18', 'Las Pinas City', 'Catholic', '369 Azalea Street, Las Pinas City', '369 Azalea Street, Las Pinas City', 422, 494, 614, 'Alejandro Guerrero', '09389012345', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(428, 488, '2025078', '202500000078', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Visitacion', 'Campos', 'Silva', NULL, 'Female', '2014-08-22', 'Muntinlupa City', 'Catholic', '741 Chrysanthemum Avenue, Muntinlupa City', '741 Chrysanthemum Avenue, Muntinlupa City', 423, 495, 615, 'Francisco Campos', '09390123456', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(429, 489, '2025079', '202500000079', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Primitivo', 'Ortega', 'Ponce', NULL, 'Male', '2014-12-26', 'Marikina City', 'Catholic', '852 Freesia Road, Marikina City', '852 Freesia Road, Marikina City', 424, 496, 616, 'Diego Ortega', '09401234567', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(430, 490, '2025080', '202500000080', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Salvacion', 'Lozano', 'Cervantes', NULL, 'Female', '2014-06-30', 'Pasay City', 'Catholic', '963 Cosmos Lane, Pasay City', '963 Cosmos Lane, Pasay City', 425, 497, 617, 'Jorge Lozano', '09412345678', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(431, 491, '2025081', '202500000081', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Domingo', 'Espinoza', 'Delgado', NULL, 'Male', '2013-02-14', 'Manila', 'Catholic', '159 Begonia Street, Manila', '159 Begonia Street, Manila', 426, 498, 618, 'Raul Espinoza', '09423456789', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(432, 492, '2025082', '202500000082', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Socorro', 'Molina', 'Fuentes', NULL, 'Female', '2013-06-18', 'Quezon City', 'Catholic', '753 Camellia Avenue, Quezon City', '753 Camellia Avenue, Quezon City', 427, 499, 619, 'Sergio Molina', '09434567890', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(433, 493, '2025083', '202500000083', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Eusebio', 'Pacheco', 'Sandoval', NULL, 'Male', '2013-10-22', 'Pasig City', 'Catholic', '486 Gladiolus Road, Pasig City', '486 Gladiolus Road, Pasig City', 428, 500, 620, 'Arturo Pacheco', '09445678901', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(434, 494, '2025084', '202500000084', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Milagros', 'Figueroa', 'Escobar', NULL, 'Female', '2013-04-26', 'Makati City', 'Catholic', '357 Snapdragon Lane, Makati City', '357 Snapdragon Lane, Makati City', 429, 501, 621, 'Ignacio Figueroa', '09456789012', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(435, 495, '2025085', '202500000085', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Prudencio', 'Contreras', 'Galvan', NULL, 'Male', '2013-08-30', 'Taguig City', 'Catholic', '624 Carnation Drive, Taguig City', '624 Carnation Drive, Taguig City', 430, 502, 622, 'Emilio Contreras', '09467890123', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(436, 496, '2025086', '202500000086', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Conception', 'Maldonado', 'Barrera', NULL, 'Female', '2013-12-14', 'Paranaque City', 'Catholic', '791 Peony Court, Paranaque City', '791 Peony Court, Paranaque City', 431, 503, 623, 'Rodrigo Maldonado', '09478901234', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(437, 497, '2025087', '202500000087', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Eufemio', 'Acosta', 'Cabrera', NULL, 'Male', '2013-03-18', 'Las Pinas City', 'Catholic', '135 Geranium Street, Las Pinas City', '135 Geranium Street, Las Pinas City', 432, 504, 624, 'Armando Acosta', '09489012345', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(438, 498, '2025088', '202500000088', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Remedios', 'Vega', 'Cortez', NULL, 'Female', '2013-07-22', 'Muntinlupa City', 'Catholic', '802 Aster Avenue, Muntinlupa City', '802 Aster Avenue, Muntinlupa City', 433, 505, 625, 'Esteban Vega', '09490123456', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(439, 499, '2025089', '202500000089', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Clementino', 'Rojas', 'Moreno', NULL, 'Male', '2013-11-26', 'Marikina City', 'Catholic', '147 Pansy Road, Marikina City', '147 Pansy Road, Marikina City', 434, 506, 626, 'Cesar Rojas', '09501234567', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(440, 500, '2025090', '202500000090', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Soledad', 'Perez', 'Soto', NULL, 'Female', '2013-05-30', 'Pasay City', 'Catholic', '258 Daffodil Lane, Pasay City', '258 Daffodil Lane, Pasay City', 435, 507, 627, 'Ruben Perez', '09512345678', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(441, 501, '2025091', '202500000091', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Genaro', 'Carrasco', 'Restrepo', NULL, 'Male', '2012-01-12', 'Manila', 'Catholic', '369 Tulip Street, Manila', '369 Tulip Street, Manila', 436, 508, 628, 'Andres Carrasco', '09523456789', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(442, 502, '2025092', '202500000092', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Filomena', 'Zuniga', 'Osorio', NULL, 'Female', '2012-05-16', 'Quezon City', 'Catholic', '741 Iris Avenue, Quezon City', '741 Iris Avenue, Quezon City', 437, 509, 629, 'Mauricio Zuniga', '09534567890', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(443, 503, '2025093', '202500000093', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Honorio', 'Varela', 'Calderon', NULL, 'Male', '2012-09-20', 'Pasig City', 'Catholic', '852 Lavender Road, Pasig City', '852 Lavender Road, Pasig City', 438, 510, 630, 'Hector Varela', '09545678901', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(444, 504, '2025094', '202500000094', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Perpetua', 'Hidalgo', 'Espejo', NULL, 'Female', '2012-03-24', 'Makati City', 'Catholic', '963 Jasmine Lane, Makati City', '963 Jasmine Lane, Makati City', 439, 511, 631, 'Oscar Hidalgo', '09556789012', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(445, 505, '2025095', '202500000095', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Isidoro', 'Pantoja', 'Uribe', NULL, 'Male', '2012-07-28', 'Taguig City', 'Catholic', '159 Zinnia Drive, Taguig City', '159 Zinnia Drive, Taguig City', 440, 512, 632, 'Marco Pantoja', '09567890123', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(446, 506, '2025096', '202500000096', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Consolacion', 'Quintero', 'Benitez', NULL, 'Female', '2012-11-12', 'Paranaque City', 'Catholic', '753 Cosmos Court, Paranaque City', '753 Cosmos Court, Paranaque City', 441, 513, 633, 'Luis Quintero', '09578901234', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(447, 507, '2025097', '202500000097', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Macario', 'Camacho', 'Palacios', NULL, 'Male', '2012-04-16', 'Las Pinas City', 'Catholic', '486 Lily Street, Las Pinas City', '486 Lily Street, Las Pinas City', 442, 514, 634, 'Pablo Camacho', '09589012345', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(448, 508, '2025098', '202500000098', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Caridad', 'Cardenas', 'Montoya', NULL, 'Female', '2012-08-20', 'Muntinlupa City', 'Catholic', '357 Sunflower Avenue, Muntinlupa City', '357 Sunflower Avenue, Muntinlupa City', 443, 515, 635, 'Enrique Cardenas', '09590123456', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(449, 509, '2025099', '202500000099', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Nemesio', 'Solis', 'Villalobos', NULL, 'Male', '2012-12-24', 'Marikina City', 'Catholic', '624 Magnolia Road, Marikina City', '624 Magnolia Road, Marikina City', 444, 516, 636, 'Javier Solis', '09601234567', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(450, 510, '2025100', '202500000100', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Epifania', 'Ibarra', 'Aranda', NULL, 'Female', '2012-06-28', 'Pasay City', 'Catholic', '791 Orchid Lane, Pasay City', '791 Orchid Lane, Pasay City', 445, 517, 637, 'Mario Ibarra', '09612345678', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(451, 511, '2025101', '202500000101', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Evaristo', 'Meza', 'Coronado', NULL, 'Male', '2011-02-08', 'Manila', 'Catholic', '135 Dahlia Street, Manila', '135 Dahlia Street, Manila', 446, 518, 638, 'Felipe Meza', '09623456789', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(452, 512, '2025102', '202500000102', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Felicitas', 'Cano', 'Avalos', NULL, 'Female', '2011-06-12', 'Quezon City', 'Catholic', '802 Petunia Avenue, Quezon City', '802 Petunia Avenue, Quezon City', 447, 519, 639, 'Alvaro Cano', '09634567890', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(453, 513, '2025103', '202500000103', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Melquiades', 'Ochoa', 'Bermudez', NULL, 'Male', '2011-10-16', 'Pasig City', 'Catholic', '147 Hibiscus Road, Pasig City', '147 Hibiscus Road, Pasig City', 448, 520, 640, 'Guillermo Ochoa', '09645678901', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(454, 514, '2025104', '202500000104', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Candelaria', 'Paredes', 'Casillas', NULL, 'Female', '2011-04-20', 'Makati City', 'Catholic', '258 Freesia Lane, Makati City', '258 Freesia Lane, Makati City', 449, 521, 641, 'Jaime Paredes', '09656789012', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(455, 515, '2025105', '202500000105', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Apolinario', 'Cruz', 'Santos', NULL, 'Male', '2011-08-24', 'Taguig City', 'Catholic', '369 Marigold Drive, Taguig City', '369 Marigold Drive, Taguig City', 450, 522, 642, 'Juan Cruz', '09171234567', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(456, 516, '2025106', '202500000106', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Encarnacion', 'Reyes', 'Garcia', NULL, 'Female', '2011-12-28', 'Paranaque City', 'Catholic', '741 Gladiolus Court, Paranaque City', '741 Gladiolus Court, Paranaque City', 451, 523, 643, 'Jose Reyes', '09182345678', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(457, 517, '2025107', '202500000107', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Bartolome', 'Santos', 'Lopez', NULL, 'Male', '2011-05-12', 'Las Pinas City', 'Catholic', '852 Chrysanthemum Street, Las Pinas City', '852 Chrysanthemum Street, Las Pinas City', 452, 524, 644, 'Antonio Santos', '09193456789', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(458, 518, '2025108', '202500000108', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Esperanza', 'Gonzales', 'Martinez', NULL, 'Female', '2011-09-16', 'Muntinlupa City', 'Catholic', '963 Azalea Avenue, Muntinlupa City', '963 Azalea Avenue, Muntinlupa City', 453, 525, 645, 'Pedro Gonzales', '09204567890', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(459, 519, '2025109', '202500000109', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Sinforoso', 'Hernandez', 'Rivera', NULL, 'Male', '2011-01-20', 'Marikina City', 'Catholic', '159 Begonia Road, Marikina City', '159 Begonia Road, Marikina City', 454, 526, 646, 'Miguel Hernandez', '09215678901', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(460, 520, '2025110', '202500000110', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Remedios', 'Lopez', 'Torres', NULL, 'Female', '2011-07-24', 'Pasay City', 'Catholic', '486 Camellia Lane, Pasay City', '486 Camellia Lane, Pasay City', 455, 527, 647, 'Carlos Lopez', '09226789012', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(461, 521, '2025111', '202500000111', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Floriano', 'Martinez', 'Flores', NULL, 'Male', '2010-01-05', 'Manila', 'Catholic', '753 Violet Street, Manila', '753 Violet Street, Manila', 456, 528, 648, 'Roberto Martinez', '09237890123', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(462, 522, '2025112', '202500000112', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Catalina', 'Torres', 'Ramos', NULL, 'Female', '2010-05-10', 'Quezon City', 'Catholic', '624 Daffodil Avenue, Quezon City', '624 Daffodil Avenue, Quezon City', 457, 529, 649, 'Fernando Torres', '09248901234', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(463, 523, '2025113', '202500000113', 'New', 'Dropped', NULL, NULL, NULL, NULL, NULL, NULL, 'Nicanor', 'Flores', 'Morales', NULL, 'Male', '2010-09-14', 'Pasig City', 'Catholic', '791 Lavender Road, Pasig City', '791 Lavender Road, Pasig City', 458, 530, 650, 'Manuel Flores', '09259012345', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:43:48'),
(464, 524, '2025114', '202500000114', 'New', 'Transferred', 'Colegie de San Juan de Letran', '2025-12-07', 'Personal Reasons', 3, '2025-12-07 03:52:27', NULL, 'Dionisia', 'Ramos', 'Castro', NULL, 'Female', '2010-03-18', 'Makati City', 'Catholic', '135 Jasmine Lane, Makati City', '135 Jasmine Lane, Makati City', 459, 531, 651, 'Ricardo Ramos', '09260123456', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-12-07 03:52:27'),
(465, 525, '2025115', '202500000115', 'New', 'Graduated', NULL, NULL, NULL, NULL, NULL, NULL, 'Policarpo', 'Morales', 'Mendoza', NULL, 'Male', '2010-07-22', 'Taguig City', 'Catholic', '802 Poppy Drive, Taguig City', '802 Poppy Drive, Taguig City', 460, 532, 652, 'Eduardo Morales', '09271234567', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:44:00'),
(466, 526, '2025116', '202500000116', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Consolacion', 'Castro', 'Jimenez', NULL, 'Female', '2010-11-26', 'Paranaque City', 'Catholic', '147 Geranium Court, Paranaque City', '147 Geranium Court, Paranaque City', 461, 533, 653, 'Alejandro Castro', '09282345678', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(467, 527, '2025117', '202500000117', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Anastasio', 'Dela Rosa', 'Villanueva', NULL, 'Male', '2010-04-30', 'Las Pinas City', 'Catholic', '258 Snapdragon Street, Las Pinas City', '258 Snapdragon Street, Las Pinas City', 462, 534, 654, 'Daniel Dela Rosa', '09301234567', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(468, 528, '2025118', '202500000118', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Feliciana', 'Bautista', 'Salazar', NULL, 'Female', '2010-08-14', 'Muntinlupa City', 'Catholic', '369 Carnation Avenue, Muntinlupa City', '369 Carnation Avenue, Muntinlupa City', 463, 535, 655, 'Gabriel Bautista', '09312345678', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(469, 529, '2025119', '202500000119', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Hermenegildo', 'Navarro', 'Cordero', NULL, 'Male', '2010-12-18', 'Marikina City', 'Catholic', '741 Peony Road, Marikina City', '741 Peony Road, Marikina City', 464, 536, 656, 'Rafael Navarro', '09323456789', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(470, 530, '2025120', '202500000120', 'New', 'Pending Payment', NULL, NULL, NULL, NULL, NULL, NULL, 'Esperanza', 'Valdez', 'Gutierrez', NULL, 'Female', '2010-06-22', 'Pasay City', 'Catholic', '852 Cosmos Lane, Pasay City', '852 Cosmos Lane, Pasay City', 465, 537, 657, 'Samuel Valdez', '09334567890', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-11-24 07:33:42'),
(472, 542, '123123', NULL, 'New', 'Enrolled', NULL, NULL, NULL, NULL, NULL, NULL, 'Alix', 'Felipe', NULL, NULL, 'Male', '2025-11-13', 'NCR', 'Catholic', 'street', 'street', 663, 664, NULL, '123', '123', '123', 4, NULL, 2, NULL, NULL, NULL, 1, 3, '2025-11-23 09:31:28', '2025-12-07 03:59:05');

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `school_year_id` int NOT NULL,
  `grade_level_id` int NOT NULL,
  `section_id` int DEFAULT NULL,
  `enrollment_date` date NOT NULL,
  `enrollment_status` enum('Pending Payment','Enrolled','Dropped','Suspended','Transferred','Graduated') DEFAULT 'Pending Payment',
  `drop_date` date DEFAULT NULL,
  `drop_reason` text,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `remarks` text,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_enrollments`
--

INSERT INTO `student_enrollments` (`id`, `student_id`, `school_year_id`, `grade_level_id`, `section_id`, `enrollment_date`, `enrollment_status`, `drop_date`, `drop_reason`, `final_grade`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(23, 241, 2, 1, 6, '2025-07-30', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 15:52:36'),
(24, 242, 2, 1, 6, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 11:05:45'),
(25, 243, 2, 1, 6, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 11:05:47'),
(26, 244, 2, 1, 6, '2025-07-30', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 10:56:15'),
(27, 245, 2, 1, 6, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 11:05:51'),
(28, 246, 2, 1, 6, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 11:05:53'),
(29, 247, 2, 1, 6, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 11:05:55'),
(30, 248, 2, 1, 6, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 11:05:58'),
(31, 249, 2, 1, 6, '2025-07-30', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 10:54:38'),
(32, 250, 2, 1, 6, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:40:18', '2025-07-30 11:38:48'),
(34, 361, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(35, 362, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(36, 363, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(37, 364, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(38, 365, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(39, 366, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(40, 367, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(41, 368, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(42, 369, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(43, 370, 2, 2, 7, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(49, 371, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(50, 372, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(51, 373, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(52, 374, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(53, 375, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(54, 376, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(55, 377, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(56, 378, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(57, 379, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(58, 380, 2, 3, 8, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(64, 381, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(65, 382, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(66, 383, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(67, 384, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(68, 385, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(69, 386, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(70, 387, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(71, 388, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(72, 389, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(73, 390, 2, 4, 9, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(79, 391, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(80, 392, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(81, 393, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(82, 394, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(83, 395, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(84, 396, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(85, 397, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(86, 398, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(87, 399, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(88, 400, 2, 5, 10, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(94, 401, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(95, 402, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(96, 403, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(97, 404, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(98, 405, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(99, 406, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(100, 407, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(101, 408, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(102, 409, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(103, 410, 2, 6, 11, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(109, 411, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(110, 412, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(111, 413, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(112, 414, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(113, 415, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(114, 416, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(115, 417, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(116, 418, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(117, 419, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(118, 420, 2, 7, 12, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(124, 421, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(125, 422, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(126, 423, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(127, 424, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(128, 425, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(129, 426, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(130, 427, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(131, 428, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(132, 429, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(133, 430, 2, 8, 13, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(139, 431, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(140, 432, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(141, 433, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(142, 434, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(143, 435, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(144, 436, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(145, 437, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(146, 438, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(147, 439, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(148, 440, 2, 9, 14, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(154, 441, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(155, 442, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(156, 443, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(157, 444, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(158, 445, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(159, 446, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(160, 447, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(161, 448, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(162, 449, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(163, 450, 2, 10, 15, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(169, 451, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(170, 452, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(171, 453, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(172, 454, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(173, 455, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(174, 456, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(175, 457, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(176, 458, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(177, 459, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(178, 460, 2, 11, 16, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(184, 461, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(185, 462, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(186, 463, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(187, 464, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(188, 465, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(189, 466, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(190, 467, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(191, 468, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(192, 469, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(193, 470, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48'),
(204, 472, 2, 3, 22, '2025-11-23', 'Enrolled', NULL, NULL, NULL, NULL, 4, '2025-11-23 09:33:38', '2025-11-23 09:33:38');

-- --------------------------------------------------------

--
-- Table structure for table `student_grades`
--

CREATE TABLE `student_grades` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `school_year_id` int NOT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `remarks` enum('Passed','Failed','Incomplete','Dropped') DEFAULT NULL,
  `teacher_comments` text,
  `date_recorded` date NOT NULL,
  `recorded_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `teacher_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_grades`
--

INSERT INTO `student_grades` (`id`, `student_id`, `subject_id`, `school_year_id`, `final_grade`, `remarks`, `teacher_comments`, `date_recorded`, `recorded_by`, `created_at`, `updated_at`, `teacher_id`) VALUES
(639, 241, 1, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(640, 241, 2, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(641, 241, 3, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(642, 241, 4, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(643, 241, 5, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(644, 242, 1, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(645, 242, 2, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(646, 242, 3, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(647, 242, 4, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(648, 242, 5, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(649, 243, 1, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(650, 243, 2, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(651, 243, 3, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(652, 243, 4, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(653, 243, 5, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(654, 244, 1, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(655, 244, 2, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(656, 244, 3, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(657, 244, 4, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(658, 244, 5, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(659, 245, 1, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(660, 245, 2, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(661, 245, 3, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(662, 245, 4, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(663, 245, 5, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(664, 246, 1, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(665, 246, 2, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(666, 246, 3, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(667, 246, 4, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(668, 246, 5, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(669, 247, 1, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(670, 247, 2, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(671, 247, 3, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(672, 247, 4, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(673, 247, 5, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(674, 248, 1, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(675, 248, 2, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(676, 248, 3, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(677, 248, 4, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(678, 248, 5, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(679, 249, 1, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(680, 249, 2, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(681, 249, 3, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(682, 249, 4, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(683, 249, 5, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(684, 250, 1, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-05 06:18:46', 1),
(685, 250, 2, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(686, 250, 3, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(687, 250, 4, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(688, 250, 5, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(689, 361, 6, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(690, 361, 7, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(691, 361, 8, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(692, 361, 9, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(693, 361, 10, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(694, 362, 6, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(695, 362, 7, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(696, 362, 8, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(697, 362, 9, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(698, 362, 10, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(699, 363, 6, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(700, 363, 7, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(701, 363, 8, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(702, 363, 9, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(703, 363, 10, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(704, 364, 6, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(705, 364, 7, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(706, 364, 8, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(707, 364, 9, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(708, 364, 10, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(709, 365, 6, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(710, 365, 7, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(711, 365, 8, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(712, 365, 9, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(713, 365, 10, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(714, 366, 6, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(715, 366, 7, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(716, 366, 8, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(717, 366, 9, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(718, 366, 10, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(719, 367, 6, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(720, 367, 7, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(721, 367, 8, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(722, 367, 9, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(723, 367, 10, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(724, 368, 6, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(725, 368, 7, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(726, 368, 8, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(727, 368, 9, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(728, 368, 10, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(729, 369, 6, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(730, 369, 7, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(731, 369, 8, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(732, 369, 9, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(733, 369, 10, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(734, 370, 6, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(735, 370, 7, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(736, 370, 8, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(737, 370, 9, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(738, 370, 10, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(739, 371, 11, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(740, 371, 12, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(741, 371, 13, 2, 84.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(742, 371, 14, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(743, 371, 15, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(744, 372, 11, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(745, 372, 12, 2, 84.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(746, 372, 13, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(747, 372, 14, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(748, 372, 15, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(749, 373, 11, 2, 84.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(750, 373, 12, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(751, 373, 13, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(752, 373, 14, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(753, 373, 15, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(754, 374, 11, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(755, 374, 12, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(756, 374, 13, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(757, 374, 14, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(758, 374, 15, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(759, 375, 11, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(760, 375, 12, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(761, 375, 13, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(762, 375, 14, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(763, 375, 15, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(764, 376, 11, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(765, 376, 12, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(766, 376, 13, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(767, 376, 14, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(768, 376, 15, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(769, 377, 11, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(770, 377, 12, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(771, 377, 13, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(772, 377, 14, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(773, 377, 15, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(774, 378, 11, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(775, 378, 12, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(776, 378, 13, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(777, 378, 14, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(778, 378, 15, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(779, 379, 11, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(780, 379, 12, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(781, 379, 13, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(782, 379, 14, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(783, 379, 15, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(784, 380, 11, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(785, 380, 12, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(786, 380, 13, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(787, 380, 14, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(788, 380, 15, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(789, 381, 16, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(790, 381, 17, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(791, 381, 18, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(792, 381, 19, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(793, 381, 20, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(794, 382, 16, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(795, 382, 17, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(796, 382, 18, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(797, 382, 19, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(798, 382, 20, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(799, 383, 16, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(800, 383, 17, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(801, 383, 18, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(802, 383, 19, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(803, 383, 20, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(804, 384, 16, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(805, 384, 17, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(806, 384, 18, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(807, 384, 19, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(808, 384, 20, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(809, 385, 16, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(810, 385, 17, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(811, 385, 18, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(812, 385, 19, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(813, 385, 20, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(814, 386, 16, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(815, 386, 17, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(816, 386, 18, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(817, 386, 19, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(818, 386, 20, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(819, 387, 16, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(820, 387, 17, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(821, 387, 18, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(822, 387, 19, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(823, 387, 20, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(824, 388, 16, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(825, 388, 17, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(826, 388, 18, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(827, 388, 19, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(828, 388, 20, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(829, 389, 16, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(830, 389, 17, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(831, 389, 18, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(832, 389, 19, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(833, 389, 20, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(834, 390, 16, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(835, 390, 17, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(836, 390, 18, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(837, 390, 19, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(838, 390, 20, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(839, 391, 21, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(840, 391, 22, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(841, 391, 23, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(842, 391, 24, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(843, 391, 25, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(844, 392, 21, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(845, 392, 22, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(846, 392, 23, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(847, 392, 24, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(848, 392, 25, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(849, 393, 21, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(850, 393, 22, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(851, 393, 23, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(852, 393, 24, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(853, 393, 25, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(854, 394, 21, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(855, 394, 22, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(856, 394, 23, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(857, 394, 24, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(858, 394, 25, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(859, 395, 21, 2, 86.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(860, 395, 22, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(861, 395, 23, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(862, 395, 24, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(863, 395, 25, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(864, 396, 21, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(865, 396, 22, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(866, 396, 23, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(867, 396, 24, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(868, 396, 25, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(869, 397, 21, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(870, 397, 22, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(871, 397, 23, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(872, 397, 24, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(873, 397, 25, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(874, 398, 21, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(875, 398, 22, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(876, 398, 23, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(877, 398, 24, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(878, 398, 25, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(879, 399, 21, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(880, 399, 22, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(881, 399, 23, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(882, 399, 24, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(883, 399, 25, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(884, 400, 21, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(885, 400, 22, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(886, 400, 23, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(887, 400, 24, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(888, 400, 25, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(889, 401, 26, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(890, 401, 27, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(891, 401, 28, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(892, 401, 29, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(893, 401, 30, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(894, 402, 26, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(895, 402, 27, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(896, 402, 28, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(897, 402, 29, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(898, 402, 30, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(899, 403, 26, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(900, 403, 27, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(901, 403, 28, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(902, 403, 29, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(903, 403, 30, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(904, 404, 26, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(905, 404, 27, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(906, 404, 28, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(907, 404, 29, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(908, 404, 30, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(909, 405, 26, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(910, 405, 27, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(911, 405, 28, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(912, 405, 29, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(913, 405, 30, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(914, 406, 26, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(915, 406, 27, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(916, 406, 28, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(917, 406, 29, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(918, 406, 30, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(919, 407, 26, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(920, 407, 27, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(921, 407, 28, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(922, 407, 29, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(923, 407, 30, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(924, 408, 26, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(925, 408, 27, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(926, 408, 28, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(927, 408, 29, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(928, 408, 30, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(929, 409, 26, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(930, 409, 27, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(931, 409, 28, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(932, 409, 29, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(933, 409, 30, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1);
INSERT INTO `student_grades` (`id`, `student_id`, `subject_id`, `school_year_id`, `final_grade`, `remarks`, `teacher_comments`, `date_recorded`, `recorded_by`, `created_at`, `updated_at`, `teacher_id`) VALUES
(934, 410, 26, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(935, 410, 27, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(936, 410, 28, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(937, 410, 29, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(938, 410, 30, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(939, 411, 31, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(940, 411, 32, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(941, 411, 33, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(942, 411, 34, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(943, 411, 35, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(944, 412, 31, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(945, 412, 32, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(946, 412, 33, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(947, 412, 34, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(948, 412, 35, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(949, 413, 31, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(950, 413, 32, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(951, 413, 33, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(952, 413, 34, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(953, 413, 35, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(954, 414, 31, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(955, 414, 32, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(956, 414, 33, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(957, 414, 34, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(958, 414, 35, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(959, 415, 31, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(960, 415, 32, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(961, 415, 33, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(962, 415, 34, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(963, 415, 35, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(964, 416, 31, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(965, 416, 32, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(966, 416, 33, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(967, 416, 34, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(968, 416, 35, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(969, 417, 31, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(970, 417, 32, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(971, 417, 33, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(972, 417, 34, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(973, 417, 35, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(974, 418, 31, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(975, 418, 32, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(976, 418, 33, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(977, 418, 34, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(978, 418, 35, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(979, 419, 31, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(980, 419, 32, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(981, 419, 33, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(982, 419, 34, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(983, 419, 35, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(984, 420, 31, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(985, 420, 32, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(986, 420, 33, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(987, 420, 34, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(988, 420, 35, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(989, 421, 36, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(990, 421, 37, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(991, 421, 38, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(992, 421, 39, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(993, 421, 40, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(994, 422, 36, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(995, 422, 37, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(996, 422, 38, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(997, 422, 39, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(998, 422, 40, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(999, 423, 36, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1000, 423, 37, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1001, 423, 38, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1002, 423, 39, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1003, 423, 40, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1004, 424, 36, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1005, 424, 37, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1006, 424, 38, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1007, 424, 39, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1008, 424, 40, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1009, 425, 36, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1010, 425, 37, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1011, 425, 38, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1012, 425, 39, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1013, 425, 40, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1014, 426, 36, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1015, 426, 37, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1016, 426, 38, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1017, 426, 39, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1018, 426, 40, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1019, 427, 36, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1020, 427, 37, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1021, 427, 38, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1022, 427, 39, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1023, 427, 40, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1024, 428, 36, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1025, 428, 37, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1026, 428, 38, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1027, 428, 39, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1028, 428, 40, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1029, 429, 36, 2, 85.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1030, 429, 37, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1031, 429, 38, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1032, 429, 39, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1033, 429, 40, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1034, 430, 36, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1035, 430, 37, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1036, 430, 38, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1037, 430, 39, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1038, 430, 40, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1039, 431, 41, 2, 82.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1040, 431, 42, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1041, 431, 43, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1042, 431, 44, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1043, 431, 45, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1044, 432, 41, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1045, 432, 42, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1046, 432, 43, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1047, 432, 44, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1048, 432, 45, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1049, 433, 41, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1050, 433, 42, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1051, 433, 43, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1052, 433, 44, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1053, 433, 45, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1054, 434, 41, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1055, 434, 42, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1056, 434, 43, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1057, 434, 44, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1058, 434, 45, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1059, 435, 41, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1060, 435, 42, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1061, 435, 43, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1062, 435, 44, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1063, 435, 45, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1064, 436, 41, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1065, 436, 42, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1066, 436, 43, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1067, 436, 44, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1068, 436, 45, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1069, 437, 41, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1070, 437, 42, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1071, 437, 43, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1072, 437, 44, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1073, 437, 45, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1074, 438, 41, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1075, 438, 42, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1076, 438, 43, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1077, 438, 44, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1078, 438, 45, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1079, 439, 41, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1080, 439, 42, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1081, 439, 43, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1082, 439, 44, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1083, 439, 45, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1084, 440, 41, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1085, 440, 42, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1086, 440, 43, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1087, 440, 44, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1088, 440, 45, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1089, 441, 46, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1090, 441, 47, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1091, 441, 48, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1092, 441, 49, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1093, 441, 50, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1094, 442, 46, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1095, 442, 47, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1096, 442, 48, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1097, 442, 49, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1098, 442, 50, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1099, 443, 46, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1100, 443, 47, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1101, 443, 48, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1102, 443, 49, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1103, 443, 50, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1104, 444, 46, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1105, 444, 47, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1106, 444, 48, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1107, 444, 49, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1108, 444, 50, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1109, 445, 46, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1110, 445, 47, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1111, 445, 48, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1112, 445, 49, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1113, 445, 50, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1114, 446, 46, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1115, 446, 47, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1116, 446, 48, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1117, 446, 49, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1118, 446, 50, 2, 81.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1119, 447, 46, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1120, 447, 47, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1121, 447, 48, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1122, 447, 49, 2, 81.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1123, 447, 50, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1124, 448, 46, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1125, 448, 47, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1126, 448, 48, 2, 81.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1127, 448, 49, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1128, 448, 50, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1129, 449, 46, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1130, 449, 47, 2, 81.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1131, 449, 48, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1132, 449, 49, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1133, 449, 50, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1134, 450, 46, 2, 81.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1135, 450, 47, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1136, 450, 48, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1137, 450, 49, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1138, 450, 50, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1139, 451, 51, 2, 92.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1140, 451, 52, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1141, 451, 53, 2, 84.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1142, 451, 54, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1143, 451, 55, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1144, 452, 51, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1145, 452, 52, 2, 84.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1146, 452, 53, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1147, 452, 54, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1148, 452, 55, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1149, 453, 51, 2, 84.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1150, 453, 52, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1151, 453, 53, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1152, 453, 54, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1153, 453, 55, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1154, 454, 51, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1155, 454, 52, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1156, 454, 53, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1157, 454, 54, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1158, 454, 55, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1159, 455, 51, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1160, 455, 52, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1161, 455, 53, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1162, 455, 54, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1163, 455, 55, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1164, 456, 51, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1165, 456, 52, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1166, 456, 53, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1167, 456, 54, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1168, 456, 55, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1169, 457, 51, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1170, 457, 52, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1171, 457, 53, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1172, 457, 54, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1173, 457, 55, 2, 82.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1174, 458, 51, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1175, 458, 52, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1176, 458, 53, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1177, 458, 54, 2, 82.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1178, 458, 55, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1179, 459, 51, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1180, 459, 52, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1181, 459, 53, 2, 82.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1182, 459, 54, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1183, 459, 55, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1184, 460, 51, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1185, 460, 52, 2, 82.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1186, 460, 53, 2, 88.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1187, 460, 54, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1188, 460, 55, 2, 95.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1189, 461, 56, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1190, 461, 57, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1191, 461, 58, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1192, 461, 59, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1193, 461, 60, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1194, 462, 56, 2, 93.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1195, 462, 57, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1196, 462, 58, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1197, 462, 59, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1198, 462, 60, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1199, 463, 56, 2, 89.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1200, 463, 57, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1201, 463, 58, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1202, 463, 59, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1203, 463, 60, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1204, 464, 56, 2, 80.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1205, 464, 57, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1206, 464, 58, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1207, 464, 59, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1208, 464, 60, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1209, 465, 56, 2, 96.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1210, 465, 57, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1211, 465, 58, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1212, 465, 59, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1213, 465, 60, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1214, 466, 56, 2, 87.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1215, 466, 57, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1216, 466, 58, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1217, 466, 59, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1218, 466, 60, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1219, 467, 56, 2, 98.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1220, 467, 57, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1221, 467, 58, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1222, 467, 59, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1223, 467, 60, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1224, 468, 56, 2, 94.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1225, 468, 57, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1226, 468, 58, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1);
INSERT INTO `student_grades` (`id`, `student_id`, `subject_id`, `school_year_id`, `final_grade`, `remarks`, `teacher_comments`, `date_recorded`, `recorded_by`, `created_at`, `updated_at`, `teacher_id`) VALUES
(1227, 468, 59, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1228, 468, 60, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1229, 469, 56, 2, 75.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1230, 469, 57, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1231, 469, 58, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1232, 469, 59, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1233, 469, 60, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1234, 470, 56, 2, 91.00, 'Passed', 'Shows good progress and effort. Continue practicing key concepts.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1235, 470, 57, 2, 97.00, 'Passed', 'Demonstrates solid understanding. Well done on consistent performance.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1236, 470, 58, 2, 83.00, 'Passed', 'Good improvement throughout the term. Keep working hard!', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1237, 470, 59, 2, 99.00, 'Passed', 'Outstanding achievement! Shows mastery of all learning objectives.', '2025-09-05', 1, '2025-09-05 06:01:02', '2025-09-05 06:01:02', 1),
(1238, 470, 60, 2, 90.00, 'Passed', 'Excellent participation and understanding. Keep up the great work!', '2025-09-05', 11, '2025-09-05 06:01:02', '2025-09-10 07:20:12', 1),
(1662, 381, 11, 1, 87.50, 'Passed', 'Good understanding of basic math concepts', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1663, 381, 12, 1, 91.25, 'Passed', 'Excellent reading comprehension and writing skills', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1664, 381, 13, 1, 87.25, 'Passed', 'Good progress in Filipino language skills', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1665, 381, 14, 1, 88.75, 'Passed', 'Shows curiosity in science topics', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1666, 381, 15, 1, 94.50, 'Passed', 'Very active in music and physical activities', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1667, 382, 11, 1, 92.00, 'Passed', 'Excellent mathematical reasoning skills', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1668, 382, 12, 1, 89.50, 'Passed', 'Strong vocabulary and communication skills', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1669, 382, 13, 1, 90.75, 'Passed', 'Excellent Filipino language proficiency', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1670, 382, 14, 1, 91.25, 'Passed', 'Very observant in science experiments', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1671, 382, 15, 1, 88.00, 'Passed', 'Good artistic expression and physical coordination', '2025-05-15', 1, '2025-09-05 06:44:34', '2025-09-05 06:44:34', 1),
(1672, 383, 11, 1, 85.00, 'Passed', 'Needs more practice with number operations', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1673, 383, 12, 1, 88.25, 'Passed', 'Good reading skills, improving writing', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1674, 383, 13, 1, 86.50, 'Passed', 'Steady progress in Filipino comprehension', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1675, 383, 14, 1, 87.00, 'Passed', 'Shows interest in nature and animals', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1676, 383, 15, 1, 92.75, 'Passed', 'Outstanding in sports and music activities', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1677, 384, 11, 1, 90.50, 'Passed', 'Very good at solving math problems', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1678, 384, 12, 1, 93.00, 'Passed', 'Exceptional reading and storytelling skills', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1679, 384, 13, 1, 89.25, 'Passed', 'Strong Filipino language foundation', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1680, 384, 14, 1, 91.75, 'Passed', 'Excellent observation and questioning skills', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1681, 384, 15, 1, 87.50, 'Passed', 'Creative in arts, good teamwork in PE', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1682, 385, 11, 1, 84.25, 'Passed', 'Improving steadily in mathematical concepts', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1683, 385, 12, 1, 86.75, 'Passed', 'Good effort in reading and writing exercises', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1684, 385, 13, 1, 88.00, 'Passed', 'Active participation in Filipino discussions', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1685, 385, 14, 1, 85.50, 'Passed', 'Curious about how things work', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1686, 385, 15, 1, 90.25, 'Passed', 'Enjoys physical activities and music', '2025-05-15', 1, '2025-09-05 06:45:35', '2025-09-05 06:45:35', 1),
(1687, 386, 11, 1, 89.75, 'Passed', 'Shows strong analytical thinking in math', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1688, 386, 12, 1, 91.50, 'Passed', 'Excellent comprehension and expression skills', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1689, 386, 13, 1, 87.75, 'Passed', 'Good grasp of Filipino grammar and vocabulary', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1690, 386, 14, 1, 90.00, 'Passed', 'Very engaged in science experiments', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1691, 386, 15, 1, 93.25, 'Passed', 'Talented in arts and shows leadership in PE', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1692, 387, 11, 1, 86.50, 'Passed', 'Good understanding of basic number concepts', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1693, 387, 12, 1, 87.25, 'Passed', 'Improving reading fluency and writing skills', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1694, 387, 13, 1, 85.75, 'Passed', 'Steady progress in Filipino language skills', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1695, 387, 14, 1, 88.50, 'Passed', 'Shows interest in plants and environment', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1696, 387, 15, 1, 91.00, 'Passed', 'Very energetic in physical activities', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1697, 388, 11, 1, 93.25, 'Passed', 'Outstanding mathematical problem-solving skills', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1698, 388, 12, 1, 90.75, 'Passed', 'Excellent reading comprehension and creativity', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1699, 388, 13, 1, 92.00, 'Passed', 'Very strong Filipino language proficiency', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1700, 388, 14, 1, 89.50, 'Passed', 'Demonstrates scientific thinking skills', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1701, 388, 15, 1, 88.75, 'Passed', 'Well-rounded in all MAPEH areas', '2025-05-15', 1, '2025-09-05 06:46:04', '2025-09-05 06:46:04', 1),
(1702, 389, 11, 1, 88.00, 'Passed', 'Good mathematical reasoning and calculation skills', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1703, 389, 12, 1, 85.50, 'Passed', 'Needs encouragement in reading confidence', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1704, 389, 13, 1, 87.25, 'Passed', 'Active in Filipino storytelling activities', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1705, 389, 14, 1, 90.75, 'Passed', 'Very curious about natural phenomena', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1706, 389, 15, 1, 89.25, 'Passed', 'Enjoys music and shows good sportsmanship', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1707, 390, 11, 1, 91.50, 'Passed', 'Excellent attention to detail in math work', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1708, 390, 12, 1, 94.00, 'Passed', 'Outstanding reading and writing abilities', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1709, 390, 13, 1, 90.50, 'Passed', 'Excellent Filipino language skills and expression', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1710, 390, 14, 1, 92.25, 'Passed', 'Shows excellent scientific observation skills', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1711, 390, 15, 1, 95.00, 'Passed', 'Exceptional talent in arts and music', '2025-05-15', 1, '2025-09-05 06:46:39', '2025-09-05 06:46:39', 1),
(1712, 390, 3, 4, 85.00, 'Passed', 'Excellent participation in science exploration activities. Shows great curiosity about the natural world.', '2024-06-15', 1, '2025-09-05 07:24:56', '2025-09-05 07:24:56', 1),
(1713, 381, 3, 4, 85.00, 'Passed', 'Shows good understanding of basic science concepts.', '2024-05-15', 1, '2025-09-10 07:34:25', '2025-09-10 07:39:39', 1),
(1714, 472, 15, 2, 99.00, 'Passed', NULL, '2025-11-23', 11, '2025-11-23 09:41:42', '2025-11-23 09:41:42', 11),
(1715, 472, 12, 2, 99.00, 'Passed', NULL, '2025-11-23', 11, '2025-11-23 09:42:01', '2025-11-23 09:42:04', 11),
(1716, 472, 13, 2, 88.00, 'Passed', NULL, '2025-11-23', 11, '2025-11-23 09:42:12', '2025-11-23 09:42:12', 11),
(1717, 472, 11, 2, 99.00, 'Passed', NULL, '2025-11-23', 11, '2025-11-23 09:42:34', '2025-11-23 09:42:34', 11),
(1718, 472, 14, 2, 99.00, 'Passed', NULL, '2025-11-24', 11, '2025-11-24 07:59:55', '2025-11-24 07:59:55', 11);

--
-- Triggers `student_grades`
--
DELIMITER $$
CREATE TRIGGER `auto_promote_student_on_grade_save` AFTER INSERT ON `student_grades` FOR EACH ROW auto_promote_label:BEGIN
    DECLARE student_grade_level_id INT;
    DECLARE next_grade_level_id INT;
    DECLARE student_enrollment_status VARCHAR(50);
    DECLARE student_prevent_promotion TINYINT;
    DECLARE total_subjects INT;
    DECLARE passing_subjects INT;
    DECLARE is_last_grade TINYINT;
    
    
    SELECT current_grade_level_id, enrollment_status, prevent_auto_promotion
    INTO student_grade_level_id, student_enrollment_status, student_prevent_promotion
    FROM students 
    WHERE id = NEW.student_id;
    
    
    IF student_prevent_promotion = 1 THEN
        LEAVE auto_promote_label;
    END IF;
    
    
    IF student_enrollment_status = 'Enrolled' THEN
        
        SELECT COUNT(*), SUM(CASE WHEN final_grade >= 75 THEN 1 ELSE 0 END)
        INTO total_subjects, passing_subjects
        FROM student_grades
        WHERE student_id = NEW.student_id 
        AND school_year_id = NEW.school_year_id;
        
        
        IF total_subjects > 0 AND total_subjects = passing_subjects THEN
            
            SELECT gl2.id, gl2.grade_order
            INTO next_grade_level_id, is_last_grade
            FROM grade_levels gl1
            LEFT JOIN grade_levels gl2 ON gl2.grade_order = gl1.grade_order + 1 AND gl2.is_active = 1
            WHERE gl1.id = student_grade_level_id
            LIMIT 1;
            
            IF next_grade_level_id IS NOT NULL THEN
                
                UPDATE students 
                SET current_grade_level_id = next_grade_level_id,
                    current_section_id = NULL,
                    enrollment_status = 'Pending Payment',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            ELSE
                
                UPDATE students 
                SET enrollment_status = 'Graduated',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            END IF;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `auto_promote_student_on_grade_update` AFTER UPDATE ON `student_grades` FOR EACH ROW auto_promote_update_label:BEGIN
    DECLARE student_grade_level_id INT;
    DECLARE next_grade_level_id INT;
    DECLARE student_enrollment_status VARCHAR(50);
    DECLARE student_prevent_promotion TINYINT;
    DECLARE total_subjects INT;
    DECLARE passing_subjects INT;
    DECLARE is_last_grade TINYINT;
    
    
    SELECT current_grade_level_id, enrollment_status, prevent_auto_promotion
    INTO student_grade_level_id, student_enrollment_status, student_prevent_promotion
    FROM students 
    WHERE id = NEW.student_id;
    
    
    IF student_prevent_promotion = 1 THEN
        LEAVE auto_promote_update_label;
    END IF;
    
    
    IF student_enrollment_status = 'Enrolled' THEN
        
        SELECT COUNT(*), SUM(CASE WHEN final_grade >= 75 THEN 1 ELSE 0 END)
        INTO total_subjects, passing_subjects
        FROM student_grades
        WHERE student_id = NEW.student_id 
        AND school_year_id = NEW.school_year_id;
        
        
        IF total_subjects > 0 AND total_subjects = passing_subjects THEN
            
            SELECT gl2.id, gl2.grade_order
            INTO next_grade_level_id, is_last_grade
            FROM grade_levels gl1
            LEFT JOIN grade_levels gl2 ON gl2.grade_order = gl1.grade_order + 1 AND gl2.is_active = 1
            WHERE gl1.id = student_grade_level_id
            LIMIT 1;
            
            IF next_grade_level_id IS NOT NULL THEN
                
                UPDATE students 
                SET current_grade_level_id = next_grade_level_id,
                    current_section_id = NULL,
                    enrollment_status = 'Pending Payment',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            ELSE
                
                UPDATE students 
                SET enrollment_status = 'Graduated',
                    prevent_auto_promotion = NULL,
                    updated_at = NOW()
                WHERE id = NEW.student_id;
            END IF;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_guardians`
--

CREATE TABLE `student_guardians` (
  `id` int NOT NULL,
  `guardian_type` enum('Father','Mother','Legal Guardian') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `complete_name` varchar(255) GENERATED ALWAYS AS (concat(`first_name`,_utf8mb4' ',ifnull(concat(`middle_name`,_utf8mb4' '),_utf8mb4''),`last_name`)) STORED,
  `date_of_birth` date DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email_address` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_guardians`
--

INSERT INTO `student_guardians` (`id`, `guardian_type`, `first_name`, `last_name`, `middle_name`, `date_of_birth`, `occupation`, `religion`, `contact_number`, `email_address`, `created_at`, `updated_at`) VALUES
(226, 'Father', 'Juan', 'Cruz', 'Santos', '1985-03-15', 'Engineer', 'Catholic', '09171234567', 'juan.cruz@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(227, 'Father', 'Jose', 'Reyes', 'Garcia', '1983-07-22', 'Teacher', 'Catholic', '09182345678', 'jose.reyes@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(228, 'Father', 'Antonio', 'Santos', 'Lopez', '1987-11-08', 'Businessman', 'Catholic', '09193456789', 'antonio.santos@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(229, 'Father', 'Pedro', 'Gonzales', 'Martinez', '1984-05-30', 'Driver', 'Catholic', '09204567890', 'pedro.gonzales@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(230, 'Father', 'Miguel', 'Hernandez', 'Rivera', '1986-09-12', 'Mechanic', 'Catholic', '09215678901', 'miguel.hernandez@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(231, 'Father', 'Carlos', 'Lopez', 'Torres', '1982-01-25', 'Police Officer', 'Catholic', '09226789012', 'carlos.lopez@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(232, 'Father', 'Roberto', 'Martinez', 'Flores', '1988-04-18', 'Farmer', 'Catholic', '09237890123', 'roberto.martinez@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(233, 'Father', 'Fernando', 'Torres', 'Ramos', '1985-12-03', 'Electrician', 'Catholic', '09248901234', 'fernando.torres@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(234, 'Father', 'Manuel', 'Flores', 'Morales', '1983-08-27', 'Security Guard', 'Catholic', '09259012345', 'manuel.flores@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(235, 'Father', 'Ricardo', 'Ramos', 'Castro', '1987-06-14', 'Construction Worker', 'Catholic', '09260123456', 'ricardo.ramos@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(236, 'Father', 'Eduardo', 'Morales', 'Mendoza', '1984-10-09', 'Salesman', 'Catholic', '09271234567', 'eduardo.morales@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(237, 'Father', 'Alejandro', 'Castro', 'Jimenez', '1986-02-21', 'Office Worker', 'Catholic', '09282345678', 'alejandro.castro@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(238, 'Mother', 'Maria', 'Cruz', 'dela Cruz', '1987-05-20', 'Housewife', 'Catholic', '09171234568', 'maria.cruz@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(239, 'Mother', 'Ana', 'Reyes', 'Santos', '1985-09-15', 'Nurse', 'Catholic', '09182345679', 'ana.reyes@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(240, 'Mother', 'Carmen', 'Santos', 'Garcia', '1989-12-10', 'Teacher', 'Catholic', '09193456790', 'carmen.santos@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(241, 'Mother', 'Rosa', 'Gonzales', 'Lopez', '1986-03-28', 'Vendor', 'Catholic', '09204567891', 'rosa.gonzales@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(242, 'Mother', 'Elena', 'Hernandez', 'Martinez', '1988-07-05', 'Seamstress', 'Catholic', '09215678902', 'elena.hernandez@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(243, 'Mother', 'Isabel', 'Lopez', 'Rivera', '1984-11-17', 'Cashier', 'Catholic', '09226789013', 'isabel.lopez@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(244, 'Mother', 'Teresa', 'Martinez', 'Torres', '1990-01-12', 'Cook', 'Catholic', '09237890124', 'teresa.martinez@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(245, 'Mother', 'Patricia', 'Torres', 'Flores', '1987-04-25', 'Cleaner', 'Catholic', '09248901235', 'patricia.torres@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(246, 'Mother', 'Gloria', 'Flores', 'Ramos', '1985-08-08', 'Laundry Worker', 'Catholic', '09259012346', 'gloria.flores@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(247, 'Mother', 'Esperanza', 'Ramos', 'Morales', '1989-06-22', 'Store Owner', 'Catholic', '09260123457', 'esperanza.ramos@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(248, 'Mother', 'Luz', 'Morales', 'Castro', '1986-10-30', 'Babysitter', 'Catholic', '09271234568', 'luz.morales@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(249, 'Mother', 'Cristina', 'Castro', 'Mendoza', '1988-12-05', 'Factory Worker', 'Catholic', '09282345679', 'cristina.castro@email.com', '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(322, 'Father', 'Daniel', 'Dela Rosa', 'Villanueva', '1985-01-15', 'IT Specialist', 'Catholic', '09301234567', 'daniel.delarosa@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(323, 'Father', 'Gabriel', 'Bautista', 'Salazar', '1983-03-20', 'Bank Manager', 'Catholic', '09312345678', 'gabriel.bautista@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(324, 'Father', 'Rafael', 'Navarro', 'Cordero', '1987-05-25', 'Architect', 'Catholic', '09323456789', 'rafael.navarro@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(325, 'Father', 'Samuel', 'Valdez', 'Gutierrez', '1984-07-30', 'Pharmacist', 'Catholic', '09334567890', 'samuel.valdez@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(326, 'Father', 'Benjamin', 'Aguilar', 'Herrera', '1986-09-14', 'Chef', 'Catholic', '09345678901', 'benjamin.aguilar@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(327, 'Father', 'Nicolas', 'Velasco', 'Romero', '1982-11-18', 'Pilot', 'Catholic', '09356789012', 'nicolas.velasco@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(328, 'Father', 'Adrian', 'Castillo', 'Vargas', '1988-01-22', 'Lawyer', 'Catholic', '09367890123', 'adrian.castillo@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(329, 'Father', 'Victor', 'Medina', 'Peña', '1985-03-26', 'Doctor', 'Catholic', '09378901234', 'victor.medina@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(330, 'Father', 'Alejandro', 'Guerrero', 'Dominguez', '1983-05-30', 'Engineer', 'Catholic', '09389012345', 'alejandro.guerrero@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(331, 'Father', 'Francisco', 'Campos', 'Silva', '1987-07-14', 'Businessman', 'Catholic', '09390123456', 'francisco.campos@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(332, 'Father', 'Diego', 'Ortega', 'Ponce', '1984-09-18', 'Accountant', 'Catholic', '09401234567', 'diego.ortega@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(333, 'Father', 'Jorge', 'Lozano', 'Cervantes', '1986-11-22', 'Teacher', 'Catholic', '09412345678', 'jorge.lozano@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(334, 'Father', 'Raul', 'Espinoza', 'Delgado', '1982-01-26', 'Mechanic', 'Catholic', '09423456789', 'raul.espinoza@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(335, 'Father', 'Sergio', 'Molina', 'Fuentes', '1988-03-30', 'Engineer', 'Catholic', '09434567890', 'sergio.molina@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(336, 'Father', 'Arturo', 'Pacheco', 'Sandoval', '1985-05-14', 'Manager', 'Catholic', '09445678901', 'arturo.pacheco@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(337, 'Father', 'Ignacio', 'Figueroa', 'Escobar', '1983-07-18', 'Technician', 'Catholic', '09456789012', 'ignacio.figueroa@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(338, 'Father', 'Emilio', 'Contreras', 'Galvan', '1987-09-22', 'Supervisor', 'Catholic', '09467890123', 'emilio.contreras@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(339, 'Father', 'Rodrigo', 'Maldonado', 'Barrera', '1984-11-26', 'Sales Manager', 'Catholic', '09478901234', 'rodrigo.maldonado@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(340, 'Father', 'Armando', 'Acosta', 'Cabrera', '1986-01-30', 'Electrician', 'Catholic', '09489012345', 'armando.acosta@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(341, 'Father', 'Esteban', 'Vega', 'Cortez', '1982-03-14', 'Driver', 'Catholic', '09490123456', 'esteban.vega@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(342, 'Father', 'Cesar', 'Rojas', 'Moreno', '1988-05-18', 'Security Guard', 'Catholic', '09501234567', 'cesar.rojas@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(343, 'Father', 'Ruben', 'Perez', 'Soto', '1985-07-22', 'Plumber', 'Catholic', '09512345678', 'ruben.perez@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(344, 'Father', 'Andres', 'Carrasco', 'Restrepo', '1983-09-26', 'Carpenter', 'Catholic', '09523456789', 'andres.carrasco@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(345, 'Father', 'Mauricio', 'Zuniga', 'Osorio', '1987-11-30', 'Welder', 'Catholic', '09534567890', 'mauricio.zuniga@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(346, 'Father', 'Hector', 'Varela', 'Calderon', '1984-01-14', 'Farmer', 'Catholic', '09545678901', 'hector.varela@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(347, 'Father', 'Oscar', 'Hidalgo', 'Espejo', '1986-03-18', 'Cook', 'Catholic', '09556789012', 'oscar.hidalgo@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(348, 'Father', 'Marco', 'Pantoja', 'Uribe', '1982-05-22', 'Janitor', 'Catholic', '09567890123', 'marco.pantoja@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(349, 'Father', 'Luis', 'Quintero', 'Benitez', '1988-07-26', 'Delivery Driver', 'Catholic', '09578901234', 'luis.quintero@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(350, 'Father', 'Pablo', 'Camacho', 'Palacios', '1985-09-30', 'Maintenance', 'Catholic', '09589012345', 'pablo.camacho@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(351, 'Father', 'Enrique', 'Cardenas', 'Montoya', '1983-11-14', 'Factory Worker', 'Catholic', '09590123456', 'enrique.cardenas@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(352, 'Father', 'Javier', 'Solis', 'Villalobos', '1987-01-18', 'Store Clerk', 'Catholic', '09601234567', 'javier.solis@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(353, 'Father', 'Mario', 'Ibarra', 'Aranda', '1984-03-22', 'Taxi Driver', 'Catholic', '09612345678', 'mario.ibarra@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(354, 'Father', 'Felipe', 'Meza', 'Coronado', '1986-05-26', 'Construction', 'Catholic', '09623456789', 'felipe.meza@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(355, 'Father', 'Alvaro', 'Cano', 'Avalos', '1982-07-30', 'Painter', 'Catholic', '09634567890', 'alvaro.cano@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(356, 'Father', 'Guillermo', 'Ochoa', 'Bermudez', '1988-09-14', 'Electrician', 'Catholic', '09645678901', 'guillermo.ochoa@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(357, 'Father', 'Jaime', 'Paredes', 'Casillas', '1985-11-18', 'Mechanic', 'Catholic', '09656789012', 'jaime.paredes@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(358, 'Mother', 'Beatriz', 'Dela Rosa', 'Santos', '1987-02-20', 'Teacher', 'Catholic', '09301234568', 'beatriz.delarosa@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(359, 'Mother', 'Claudia', 'Bautista', 'Cruz', '1985-04-25', 'Nurse', 'Catholic', '09312345679', 'claudia.bautista@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(360, 'Mother', 'Diana', 'Navarro', 'Garcia', '1989-06-30', 'Secretary', 'Catholic', '09323456790', 'diana.navarro@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(361, 'Mother', 'Estela', 'Valdez', 'Lopez', '1986-08-14', 'Accountant', 'Catholic', '09334567891', 'estela.valdez@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(362, 'Mother', 'Fernanda', 'Aguilar', 'Martinez', '1988-10-18', 'Cashier', 'Catholic', '09345678902', 'fernanda.aguilar@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(363, 'Mother', 'Gabriela', 'Velasco', 'Rivera', '1984-12-22', 'Sales Lady', 'Catholic', '09356789013', 'gabriela.velasco@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(364, 'Mother', 'Helena', 'Castillo', 'Torres', '1990-02-26', 'Housewife', 'Catholic', '09367890124', 'helena.castillo@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(365, 'Mother', 'Irene', 'Medina', 'Flores', '1987-04-30', 'Cook', 'Catholic', '09378901235', 'irene.medina@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(366, 'Mother', 'Julia', 'Guerrero', 'Ramos', '1985-06-14', 'Seamstress', 'Catholic', '09389012346', 'julia.guerrero@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(367, 'Mother', 'Karina', 'Campos', 'Morales', '1989-08-18', 'Vendor', 'Catholic', '09390123457', 'karina.campos@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(368, 'Mother', 'Leticia', 'Ortega', 'Castro', '1986-10-22', 'Laundry Worker', 'Catholic', '09401234568', 'leticia.ortega@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(369, 'Mother', 'Monica', 'Lozano', 'Mendoza', '1988-12-26', 'Babysitter', 'Catholic', '09412345679', 'monica.lozano@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(370, 'Mother', 'Natalia', 'Espinoza', 'Jimenez', '1984-02-28', 'Store Owner', 'Catholic', '09423456790', 'natalia.espinoza@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(371, 'Mother', 'Olga', 'Molina', 'Villanueva', '1990-04-14', 'Cleaner', 'Catholic', '09434567891', 'olga.molina@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(372, 'Mother', 'Paloma', 'Pacheco', 'Salazar', '1987-06-18', 'Factory Worker', 'Catholic', '09445678902', 'paloma.pacheco@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(373, 'Mother', 'Raquel', 'Figueroa', 'Cordero', '1985-08-22', 'Office Worker', 'Catholic', '09456789013', 'raquel.figueroa@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(374, 'Mother', 'Sandra', 'Contreras', 'Gutierrez', '1989-10-26', 'Cashier', 'Catholic', '09467890124', 'sandra.contreras@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(375, 'Mother', 'Tania', 'Maldonado', 'Herrera', '1986-12-30', 'Teacher', 'Catholic', '09478901235', 'tania.maldonado@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(376, 'Mother', 'Ursula', 'Acosta', 'Romero', '1988-02-14', 'Nurse', 'Catholic', '09489012346', 'ursula.acosta@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(377, 'Mother', 'Veronica', 'Vega', 'Vargas', '1984-04-18', 'Secretary', 'Catholic', '09490123457', 'veronica.vega@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(378, 'Mother', 'Wendy', 'Rojas', 'Peña', '1990-06-22', 'Sales Lady', 'Catholic', '09501234568', 'wendy.rojas@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(379, 'Mother', 'Ximena', 'Perez', 'Dominguez', '1987-08-26', 'Housewife', 'Catholic', '09512345679', 'ximena.perez@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(380, 'Mother', 'Yolanda', 'Carrasco', 'Silva', '1985-10-30', 'Cook', 'Catholic', '09523456790', 'yolanda.carrasco@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(381, 'Mother', 'Zulema', 'Zuniga', 'Ponce', '1989-12-14', 'Seamstress', 'Catholic', '09534567891', 'zulema.zuniga@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(382, 'Mother', 'Adriana', 'Varela', 'Cervantes', '1986-02-18', 'Vendor', 'Catholic', '09545678902', 'adriana.varela@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(383, 'Mother', 'Blanca', 'Hidalgo', 'Delgado', '1988-04-22', 'Laundry Worker', 'Catholic', '09556789013', 'blanca.hidalgo@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(384, 'Mother', 'Cecilia', 'Pantoja', 'Fuentes', '1984-06-26', 'Babysitter', 'Catholic', '09567890124', 'cecilia.pantoja@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(385, 'Mother', 'Dolores', 'Quintero', 'Sandoval', '1990-08-30', 'Store Owner', 'Catholic', '09578901235', 'dolores.quintero@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(386, 'Mother', 'Esperanza', 'Camacho', 'Escobar', '1987-10-14', 'Cleaner', 'Catholic', '09589012346', 'esperanza.camacho@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(387, 'Mother', 'Fatima', 'Cardenas', 'Galvan', '1985-12-18', 'Factory Worker', 'Catholic', '09590123457', 'fatima.cardenas@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(388, 'Mother', 'Graciela', 'Solis', 'Barrera', '1989-02-22', 'Office Worker', 'Catholic', '09601234568', 'graciela.solis@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(389, 'Mother', 'Hilda', 'Ibarra', 'Cabrera', '1986-04-26', 'Cashier', 'Catholic', '09612345679', 'hilda.ibarra@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(390, 'Mother', 'Ines', 'Meza', 'Cortez', '1988-06-30', 'Teacher', 'Catholic', '09623456790', 'ines.meza@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(391, 'Mother', 'Josefina', 'Cano', 'Moreno', '1984-08-14', 'Nurse', 'Catholic', '09634567891', 'josefina.cano@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(392, 'Mother', 'Karen', 'Ochoa', 'Soto', '1990-10-18', 'Secretary', 'Catholic', '09645678902', 'karen.ochoa@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(393, 'Mother', 'Lidia', 'Paredes', 'Restrepo', '1987-12-22', 'Sales Lady', 'Catholic', '09656789013', 'lidia.paredes@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(394, 'Father', 'Jorge', 'Pacheco', 'Navarro', '1983-08-13', 'Accountant', 'Catholic', '09236386322', 'jorge.pacheco@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(395, 'Father', 'Jorge', 'Aguilar', 'Flores', '1972-08-13', 'Businessman', 'Catholic', '09205725055', 'jorge.aguilar@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(396, 'Father', 'Daniel', 'Espinoza', 'Perez', '1989-08-13', 'Cook', 'Catholic', '09266198040', 'daniel.espinoza@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(397, 'Father', 'Ramiro', 'Paredes', 'Garcia', '1987-08-13', 'Cleaner', 'Catholic', '09183054185', 'ramiro.paredes@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(398, 'Father', 'Alvaro', 'Vega', 'Flores', '1987-08-13', 'Laundry Worker', 'Catholic', '09199780569', 'alvaro.vega@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(399, 'Father', 'Fernando', 'Reyes', 'Torres', '1979-08-13', 'Doctor', 'Catholic', '09267352551', 'fernando.reyes@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(400, 'Father', 'Jorge', 'Romero', 'Cordero', '1989-08-13', 'Businessman', 'Catholic', '09209406275', 'jorge.romero@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(401, 'Father', 'Fernando', 'Solis', 'Aquino', '1974-08-13', 'Plumber', 'Catholic', '09241141256', 'fernando.solis@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(402, 'Father', 'Jaime', 'Mendoza', 'Peña', '1981-08-13', 'Engineer', 'Catholic', '09176492968', 'jaime.mendoza@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(403, 'Father', 'Alvaro', 'Solis', 'Aguilar', '1987-08-13', 'Police Officer', 'Catholic', '09223778646', 'alvaro.solis@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(404, 'Father', 'Fernando', 'Romero', 'Ochoa', '1990-08-13', 'IT Specialist', 'Catholic', '09269221567', 'fernando.romero@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(405, 'Father', 'Ernesto', 'Navarro', 'Zuniga', '1987-08-13', 'Farmer', 'Catholic', '09172320902', 'ernesto.navarro@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(406, 'Father', 'Pablo', 'Varela', 'Ramos', '1998-08-13', 'Doctor', 'Catholic', '09202966292', 'pablo.varela@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(407, 'Father', 'Nicolas', 'Paredes', 'Hidalgo', '1984-08-13', 'Cook', 'Catholic', '09249764934', 'nicolas.paredes@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(408, 'Father', 'Alberto', 'Gonzales', 'Lopez', '1978-08-13', 'Farmer', 'Catholic', '09247557952', 'alberto.gonzales@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(409, 'Father', 'Roberto', 'Espinoza', 'Ramos', '1980-08-13', 'Supervisor', 'Catholic', '09182995893', 'roberto.espinoza@email.com', '2025-08-13 12:31:45', '2025-08-13 12:31:45'),
(410, 'Father', 'Nicolas', 'Gonzales', 'Campos', '1998-08-13', 'Store Owner', 'Catholic', '09222035064', 'nicolas.gonzales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(411, 'Father', 'Adrian', 'Varela', 'Castro', '1987-08-13', 'Driver', 'Catholic', '09257696934', 'adrian.varela@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(412, 'Father', 'Marco', 'Villanueva', 'Espinoza', '1981-08-13', 'Store Owner', 'Catholic', '09261764662', 'marco.villanueva@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(413, 'Father', 'Jorge', 'Salazar', 'Torres', '1979-08-13', 'Babysitter', 'Catholic', '09172899056', 'jorge.salazar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(414, 'Father', 'Alberto', 'Meza', 'Cordero', '1973-08-13', 'Laundry Worker', 'Catholic', '09241508127', 'alberto.meza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(415, 'Father', 'Mario', 'Morales', 'Salazar', '1972-08-13', 'Construction Worker', 'Catholic', '09231942406', 'mario.morales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(416, 'Father', 'Eduardo', 'Meza', 'Acosta', '1986-08-13', 'Sales Manager', 'Catholic', '09187185992', 'eduardo.meza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(417, 'Father', 'Adrian', 'Ortega', 'Cordero', '1998-08-13', 'IT Specialist', 'Catholic', '09209243938', 'adrian.ortega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(418, 'Father', 'Alejandro', 'Carrasco', 'Ortega', '1985-08-13', 'Architect', 'Catholic', '09246835028', 'alejandro.carrasco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(419, 'Father', 'Felipe', 'Hernandez', 'Lozano', '1979-08-13', 'Lawyer', 'Catholic', '09208444410', 'felipe.hernandez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(420, 'Father', 'Pablo', 'Santos', 'Paredes', '1978-08-13', 'Businessman', 'Catholic', '09268067955', 'pablo.santos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(421, 'Father', 'Emilio', 'Zuniga', 'Ramos', '1983-08-13', 'Cook', 'Catholic', '09221281193', 'emilio.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(422, 'Father', 'Andres', 'Ochoa', 'Torres', '1992-08-13', 'Nurse', 'Catholic', '09206645828', 'andres.ochoa@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(423, 'Father', 'Carlos', 'Molina', 'Pacheco', '1999-08-13', 'Businessman', 'Catholic', '09266129546', 'carlos.molina@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(424, 'Father', 'Carlos', 'Vega', 'Varela', '1971-08-13', 'Technician', 'Catholic', '09182889863', 'carlos.vega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(425, 'Father', 'Adrian', 'Camacho', 'Peña', '1973-08-13', 'Pharmacist', 'Catholic', '09217642160', 'adrian.camacho@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(426, 'Father', 'Antonio', 'Gutierrez', 'Flores', '1998-08-13', 'Pharmacist', 'Catholic', '09193713768', 'antonio.gutierrez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(427, 'Father', 'Francisco', 'Ibarra', 'Paredes', '1992-08-13', 'Chef', 'Catholic', '09205587376', 'francisco.ibarra@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(428, 'Father', 'Javier', 'Garcia', 'Pacheco', '1977-08-13', 'Pilot', 'Catholic', '09197581863', 'javier.garcia@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(429, 'Father', 'Andres', 'Cardenas', 'Meza', '1975-08-13', 'Cleaner', 'Catholic', '09202071812', 'andres.cardenas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(430, 'Father', 'Arturo', 'Vargas', 'Mendoza', '2000-08-13', 'Engineer', 'Catholic', '09209349278', 'arturo.vargas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(431, 'Father', 'Arturo', 'Camacho', 'Salazar', '1991-08-13', 'Carpenter', 'Catholic', '09253485043', 'arturo.camacho@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(432, 'Father', 'Arturo', 'Salazar', 'Quintero', '1996-08-13', 'Cook', 'Catholic', '09178580083', 'arturo.salazar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(433, 'Father', 'Miguel', 'Perez', 'Pantoja', '1975-08-13', 'Pharmacist', 'Catholic', '09218652788', 'miguel.perez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(434, 'Father', 'Eduardo', 'Ortega', 'Aguilar', '1975-08-13', 'Babysitter', 'Catholic', '09264462361', 'eduardo.ortega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(435, 'Father', 'Roberto', 'Guerrero', 'Pacheco', '1991-08-13', 'Engineer', 'Catholic', '09254815550', 'roberto.guerrero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(436, 'Father', 'Jose', 'Acosta', 'Cordero', '1982-08-13', 'Accountant', 'Catholic', '09204397449', 'jose.acosta@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(437, 'Father', 'Rodrigo', 'Torres', 'Navarro', '1993-08-13', 'Driver', 'Catholic', '09244208412', 'rodrigo.torres@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(438, 'Father', 'Luis', 'Lopez', 'Lozano', '1995-08-13', 'Vendor', 'Catholic', '09197300390', 'luis.lopez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(439, 'Father', 'Jaime', 'Paredes', 'Perez', '1971-08-13', 'Farmer', 'Catholic', '09262825788', 'jaime.paredes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(440, 'Father', 'Mario', 'Hernandez', 'Martinez', '1984-08-13', 'Construction Worker', 'Catholic', '09225331031', 'mario.hernandez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(441, 'Father', 'Jorge', 'Pantoja', 'Mendoza', '1993-08-13', 'Sales Manager', 'Catholic', '09254371264', 'jorge.pantoja@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(442, 'Father', 'Ruben', 'Contreras', 'Varela', '1973-08-13', 'Accountant', 'Catholic', '09246382401', 'ruben.contreras@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(443, 'Father', 'Enrique', 'Reyes', 'Pacheco', '2000-08-13', 'Police Officer', 'Catholic', '09174320264', 'enrique.reyes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(444, 'Father', 'Eduardo', 'Guerrero', 'Navarro', '1983-08-13', 'Welder', 'Catholic', '09218768150', 'eduardo.guerrero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(445, 'Father', 'Enrique', 'Cruz', 'Peña', '1971-08-13', 'Factory Worker', 'Catholic', '09202929382', 'enrique.cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(446, 'Father', 'Diego', 'Molina', 'Cano', '1992-08-13', 'Office Worker', 'Catholic', '09256696671', 'diego.molina@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(447, 'Father', 'Guillermo', 'Varela', 'Acosta', '1983-08-13', 'Cashier', 'Catholic', '09198761120', 'guillermo.varela@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(448, 'Father', 'Marco', 'Hidalgo', 'Campos', '1976-08-13', 'Pilot', 'Catholic', '09245052825', 'marco.hidalgo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(449, 'Father', 'Enrique', 'Acosta', 'Mendoza', '1973-08-13', 'Electrician', 'Catholic', '09183537661', 'enrique.acosta@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(450, 'Father', 'Raul', 'Lozano', 'Cruz', '1980-08-13', 'Store Owner', 'Catholic', '09183931854', 'raul.lozano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(451, 'Father', 'Fernando', 'Flores', 'Figueroa', '1984-08-13', 'Technician', 'Catholic', '09231269576', 'fernando.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(452, 'Father', 'Esteban', 'Navarro', 'Espinoza', '1984-08-13', 'Factory Worker', 'Catholic', '09206835669', 'esteban.navarro@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(453, 'Father', 'Jose', 'Ocampo', 'Gutierrez', '1973-08-13', 'Cleaner', 'Catholic', '09225801974', 'jose.ocampo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(454, 'Father', 'Fernando', 'Flores', 'Hernandez', '1998-08-13', 'Accountant', 'Catholic', '09228188777', 'fernando.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(455, 'Father', 'Jose', 'Valdez', 'Lozano', '1984-08-13', 'Technician', 'Catholic', '09216014192', 'jose.valdez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(456, 'Father', 'Cesar', 'Bautista', 'Flores', '1996-08-13', 'Store Owner', 'Catholic', '09235112764', 'cesar.bautista@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(457, 'Father', 'Eduardo', 'Zuniga', 'Gutierrez', '1989-08-13', 'Sales Manager', 'Catholic', '09193957045', 'eduardo.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(458, 'Father', 'Nicolas', 'Acosta', 'Aquino', '1996-08-13', 'Lawyer', 'Catholic', '09222261379', 'nicolas.acosta@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(459, 'Father', 'Ernesto', 'Pantoja', 'Hidalgo', '1973-08-13', 'Cook', 'Catholic', '09215833968', 'ernesto.pantoja@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(460, 'Father', 'Gabriel', 'Romero', 'Ortega', '1987-08-13', 'Manager', 'Catholic', '09235203730', 'gabriel.romero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(461, 'Father', 'Roberto', 'Gonzales', 'Bautista', '1989-08-13', 'Vendor', 'Catholic', '09192974733', 'roberto.gonzales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(462, 'Father', 'Jose', 'Gutierrez', 'Herrera', '1970-08-13', 'Cleaner', 'Catholic', '09268805011', 'jose.gutierrez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(463, 'Father', 'Ramiro', 'Velasco', 'Cardenas', '1985-08-13', 'Police Officer', 'Catholic', '09247607516', 'ramiro.velasco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(464, 'Father', 'Hector', 'Herrera', 'Gonzales', '1984-08-13', 'Cook', 'Catholic', '09221512781', 'hector.herrera@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(465, 'Father', 'Alberto', 'Ortega', 'Bautista', '1992-08-13', 'Laundry Worker', 'Catholic', '09216309650', 'alberto.ortega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(466, 'Mother', 'Rosario', 'Santos', 'Rojas', '1970-08-13', 'Architect', 'Catholic', '09179282881', 'rosario.santos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(467, 'Mother', 'Corazon', 'Gutierrez', 'Solis', '1998-08-13', 'IT Specialist', 'Catholic', '09248299642', 'corazon.gutierrez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(468, 'Mother', 'Lourdes', 'Ramos', 'Perez', '1975-08-13', 'Businessman', 'Catholic', '09229143050', 'lourdes.ramos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(469, 'Mother', 'Dolores', 'Santos', 'Camacho', '1975-08-13', 'Laundry Worker', 'Catholic', '09213396888', 'dolores.santos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(470, 'Mother', 'Alma', 'Perez', 'Quintero', '1996-08-13', 'Electrician', 'Catholic', '09171667754', 'alma.perez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(471, 'Mother', 'Angelica', 'Zuniga', 'Rojas', '1994-08-13', 'Supervisor', 'Catholic', '09243284201', 'angelica.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(472, 'Mother', 'Dulce', 'Perez', 'Mendoza', '1984-08-13', 'Farmer', 'Catholic', '09219629745', 'dulce.perez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(473, 'Mother', 'Resurreccion', 'Cruz', 'Pantoja', '1993-08-13', 'Security Guard', 'Catholic', '09258875703', 'resurreccion.cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(474, 'Mother', 'Esperanza', 'Mendoza', 'Maldonado', '1973-08-13', 'Carpenter', 'Catholic', '09235645142', 'esperanza.mendoza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(475, 'Mother', 'Patricia', 'Torres', 'Meza', '1971-08-13', 'Driver', 'Catholic', '09185043279', 'patricia.torres@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(476, 'Mother', 'Consolacion', 'Castillo', 'Solis', '1970-08-13', 'Architect', 'Catholic', '09172958176', 'consolacion.castillo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(477, 'Mother', 'Sofia', 'Velasco', 'Flores', '1993-08-13', 'Doctor', 'Catholic', '09183876706', 'sofia.velasco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(478, 'Mother', 'Dulce', 'Cano', 'Molina', '1985-08-13', 'Security Guard', 'Catholic', '09263297499', 'dulce.cano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(479, 'Mother', 'Asuncion', 'Lozano', 'Salazar', '1984-08-13', 'Accountant', 'Catholic', '09216983446', 'asuncion.lozano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(480, 'Mother', 'Trinidad', 'Cardenas', 'Medina', '1985-08-13', 'Salesman', 'Catholic', '09231908576', 'trinidad.cardenas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(481, 'Mother', 'Guadalupe', 'Pacheco', 'Gutierrez', '1993-08-13', 'Construction Worker', 'Catholic', '09241625967', 'guadalupe.pacheco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(482, 'Mother', 'Dulce', 'Rojas', 'Aquino', '1990-08-13', 'Pilot', 'Catholic', '09226848108', 'dulce.rojas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(483, 'Mother', 'Presentacion', 'Garcia', 'Dela Cruz', '1983-08-13', 'Store Owner', 'Catholic', '09182071497', 'presentacion.garcia@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(484, 'Mother', 'Catalina', 'Torres', 'Zuniga', '1980-08-13', 'Cashier', 'Catholic', '09212961236', 'catalina.torres@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(485, 'Mother', 'Dolores', 'Ibarra', 'Cordero', '1991-08-13', 'Electrician', 'Catholic', '09208723212', 'dolores.ibarra@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(486, 'Mother', 'Catalina', 'Perez', 'Espinoza', '1976-08-13', 'Laundry Worker', 'Catholic', '09232876121', 'catalina.perez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(487, 'Mother', 'Encarnacion', 'Navarro', 'Cruz', '1997-08-13', 'Cleaner', 'Catholic', '09212651475', 'encarnacion.navarro@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(488, 'Mother', 'Resurreccion', 'Herrera', 'Reyes', '1976-08-13', 'Supervisor', 'Catholic', '09251780961', 'resurreccion.herrera@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(489, 'Mother', 'Dulce', 'Vargas', 'Pacheco', '1983-08-13', 'Laundry Worker', 'Catholic', '09194751073', 'dulce.vargas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(490, 'Mother', 'Lourdes', 'Reyes', 'Aguilar', '1994-08-13', 'Architect', 'Catholic', '09193949619', 'lourdes.reyes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(491, 'Mother', 'Dolores', 'Valdez', 'Varela', '1987-08-13', 'Cook', 'Catholic', '09179025764', 'dolores.valdez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(492, 'Mother', 'Elena', 'Herrera', 'Lopez', '1996-08-13', 'Factory Worker', 'Catholic', '09243323552', 'elena.herrera@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(493, 'Mother', 'Guadalupe', 'Cardenas', 'Ocampo', '1985-08-13', 'Security Guard', 'Catholic', '09194743026', 'guadalupe.cardenas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(494, 'Mother', 'Isabel', 'Ocampo', 'Carrasco', '1983-08-13', 'Accountant', 'Catholic', '09239879067', 'isabel.ocampo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(495, 'Mother', 'Natividad', 'Medina', 'Medina', '1981-08-13', 'Plumber', 'Catholic', '09178677129', 'natividad.medina@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(496, 'Mother', 'Luz', 'Molina', 'Cordero', '1996-08-13', 'Driver', 'Catholic', '09218902915', 'luz.molina@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(497, 'Mother', 'Luz', 'Reyes', 'Valdez', '1986-08-13', 'Cleaner', 'Catholic', '09248267797', 'luz.reyes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(498, 'Mother', 'Benita', 'Molina', 'Pacheco', '1994-08-13', 'Babysitter', 'Catholic', '09237076214', 'benita.molina@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(499, 'Mother', 'Encarnacion', 'Camacho', 'Pantoja', '1991-08-13', 'Chef', 'Catholic', '09218420688', 'encarnacion.camacho@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(500, 'Mother', 'Alma', 'Reyes', 'Paredes', '1989-08-13', 'Lawyer', 'Catholic', '09243187146', 'alma.reyes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(501, 'Mother', 'Trinidad', 'Dela Cruz', 'Salazar', '1973-08-13', 'Driver', 'Catholic', '09203986909', 'trinidad.dela cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(502, 'Mother', 'Trinidad', 'Castillo', 'Hidalgo', '1970-08-13', 'Store Owner', 'Catholic', '09262798440', 'trinidad.castillo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(503, 'Mother', 'Rosa', 'Meza', 'Vega', '1999-08-13', 'Mechanic', 'Catholic', '09244434818', 'rosa.meza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(504, 'Mother', 'Esperanza', 'Vargas', 'Vega', '1986-08-13', 'Architect', 'Catholic', '09173250685', 'esperanza.vargas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(505, 'Mother', 'Francesca', 'Morales', 'Aguilar', '1980-08-13', 'Lawyer', 'Catholic', '09174052103', 'francesca.morales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(506, 'Mother', 'Asuncion', 'Torres', 'Gutierrez', '1973-08-13', 'Nurse', 'Catholic', '09247239213', 'asuncion.torres@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(507, 'Mother', 'Benita', 'Campos', 'Peña', '1972-08-13', 'Bank Manager', 'Catholic', '09217233558', 'benita.campos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(508, 'Mother', 'Catalina', 'Peña', 'Flores', '1970-08-13', 'Cashier', 'Catholic', '09208766447', 'catalina.peña@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(509, 'Mother', 'Esperanza', 'Maldonado', 'Valdez', '1971-08-13', 'IT Specialist', 'Catholic', '09174395624', 'esperanza.maldonado@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(510, 'Mother', 'Corazon', 'Zuniga', 'Camacho', '1993-08-13', 'Babysitter', 'Catholic', '09233216401', 'corazon.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(511, 'Mother', 'Pilar', 'Santos', 'Rojas', '1978-08-13', 'Vendor', 'Catholic', '09222353436', 'pilar.santos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(512, 'Mother', 'Patricia', 'Garcia', 'Lozano', '1992-08-13', 'Businessman', 'Catholic', '09221284848', 'patricia.garcia@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(513, 'Mother', 'Encarnacion', 'Ocampo', 'Perez', '1997-08-13', 'Supervisor', 'Catholic', '09217878520', 'encarnacion.ocampo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(514, 'Mother', 'Clementina', 'Vega', 'Valdez', '1999-08-13', 'Businessman', 'Catholic', '09197635372', 'clementina.vega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(515, 'Mother', 'Esperanza', 'Ramos', 'Dela Cruz', '1985-08-13', 'Construction Worker', 'Catholic', '09234726929', 'esperanza.ramos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(516, 'Mother', 'Esperanza', 'Maldonado', 'Herrera', '1970-08-13', 'Supervisor', 'Catholic', '09218591456', 'esperanza.maldonado@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(517, 'Mother', 'Dolores', 'Santos', 'Contreras', '1998-08-13', 'Welder', 'Catholic', '09236025794', 'dolores.santos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(518, 'Mother', 'Esperanza', 'Peña', 'Guerrero', '1998-08-13', 'Salesman', 'Catholic', '09198413417', 'esperanza.peña@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(519, 'Mother', 'Esperanza', 'Figueroa', 'Ibarra', '1970-08-13', 'IT Specialist', 'Catholic', '09179125355', 'esperanza.figueroa@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(520, 'Mother', 'Esperanza', 'Valdez', 'Hidalgo', '1990-08-13', 'Electrician', 'Catholic', '09262426722', 'esperanza.valdez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(521, 'Mother', 'Esperanza', 'Cruz', 'Navarro', '1974-08-13', 'Architect', 'Catholic', '09229127445', 'esperanza.cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(522, 'Mother', 'Consolacion', 'Flores', 'Solis', '1986-08-13', 'Police Officer', 'Catholic', '09189618781', 'consolacion.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(523, 'Mother', 'Gloria', 'Morales', 'Mendoza', '1992-08-13', 'Manager', 'Catholic', '09194623488', 'gloria.morales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(524, 'Mother', 'Ana', 'Salazar', 'Rojas', '1992-08-13', 'Office Worker', 'Catholic', '09238191126', 'ana.salazar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(525, 'Mother', 'Concepcion', 'Reyes', 'Velasco', '1989-08-13', 'Police Officer', 'Catholic', '09194445652', 'concepcion.reyes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(526, 'Mother', 'Cristina', 'Valdez', 'Salazar', '1989-08-13', 'Supervisor', 'Catholic', '09222958901', 'cristina.valdez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(527, 'Mother', 'Consolacion', 'Perez', 'Carrasco', '1974-08-13', 'Police Officer', 'Catholic', '09203756263', 'consolacion.perez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(528, 'Mother', 'Consolacion', 'Vega', 'Maldonado', '1998-08-13', 'Sales Manager', 'Catholic', '09245262742', 'consolacion.vega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(529, 'Mother', 'Teresa', 'Meza', 'Ochoa', '1985-08-13', 'Engineer', 'Catholic', '09237869054', 'teresa.meza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(530, 'Mother', 'Victoria', 'Cruz', 'Dela Cruz', '1984-08-13', 'Doctor', 'Catholic', '09256626333', 'victoria.cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(531, 'Mother', 'Esperanza', 'Guerrero', 'Hidalgo', '1981-08-13', 'Farmer', 'Catholic', '09219908789', 'esperanza.guerrero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(532, 'Mother', 'Corazon', 'Santos', 'Gonzales', '1991-08-13', 'Cook', 'Catholic', '09269514825', 'corazon.santos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(533, 'Mother', 'Remedios', 'Paredes', 'Gonzales', '1992-08-13', 'Office Worker', 'Catholic', '09264130070', 'remedios.paredes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(534, 'Mother', 'Luz', 'Flores', 'Lozano', '1987-08-13', 'Carpenter', 'Catholic', '09178574661', 'luz.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(535, 'Mother', 'Amelia', 'Maldonado', 'Morales', '1971-08-13', 'Nurse', 'Catholic', '09174875595', 'amelia.maldonado@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(536, 'Mother', 'Purificacion', 'Pacheco', 'Figueroa', '1999-08-13', 'Factory Worker', 'Catholic', '09202374155', 'purificacion.pacheco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(537, 'Mother', 'Asuncion', 'Camacho', 'Romero', '2000-08-13', 'Accountant', 'Catholic', '09236319251', 'asuncion.camacho@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(538, 'Legal Guardian', 'Purificacion', 'Martinez', 'Martinez', '1964-08-13', 'Technician', 'Catholic', '09241897899', 'purificacion.martinez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(539, 'Legal Guardian', 'Guadalupe', 'Peña', 'Aquino', '1986-08-13', 'Electrician', 'Catholic', '09228888035', 'guadalupe.peña@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(540, 'Legal Guardian', 'Gracia', 'Carrasco', 'Zuniga', '1984-08-13', 'Teacher', 'Catholic', '09211168828', 'gracia.carrasco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(541, 'Legal Guardian', 'Roberto', 'Lopez', 'Velasco', '1980-08-13', 'Plumber', 'Catholic', '09236387314', 'roberto.lopez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(542, 'Legal Guardian', 'Esperanza', 'Aguilar', 'Lozano', '1987-08-13', 'Pharmacist', 'Catholic', '09246864588', 'esperanza.aguilar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(543, 'Legal Guardian', 'Amelia', 'Pacheco', 'Santos', '1992-08-13', 'Store Owner', 'Catholic', '09217104210', 'amelia.pacheco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(544, 'Legal Guardian', 'Daniel', 'Castro', 'Cardenas', '1972-08-13', 'Cashier', 'Catholic', '09184017829', 'daniel.castro@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(545, 'Legal Guardian', 'Presentacion', 'Bautista', 'Maldonado', '1991-08-13', 'Sales Manager', 'Catholic', '09188518934', 'presentacion.bautista@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(546, 'Legal Guardian', 'Roberto', 'Morales', 'Zuniga', '1972-08-13', 'Supervisor', 'Catholic', '09259130504', 'roberto.morales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(547, 'Legal Guardian', 'Mauricio', 'Paredes', 'Molina', '1987-08-13', 'Cook', 'Catholic', '09265985464', 'mauricio.paredes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(548, 'Legal Guardian', 'Gabriel', 'Valdez', 'Aquino', '1982-08-13', 'Accountant', 'Catholic', '09235551713', 'gabriel.valdez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(549, 'Legal Guardian', 'Rosa', 'Peña', 'Navarro', '1966-08-13', 'Salesman', 'Catholic', '09202913088', 'rosa.peña@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(550, 'Legal Guardian', 'Guadalupe', 'Cardenas', 'Aquino', '1959-08-13', 'Seamstress', 'Catholic', '09266136705', 'guadalupe.cardenas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(551, 'Legal Guardian', 'Alejandro', 'Castro', 'Romero', '1966-08-13', 'Electrician', 'Catholic', '09179190577', 'alejandro.castro@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(552, 'Legal Guardian', 'Antonio', 'Vargas', 'Ramos', '1969-08-13', 'Teacher', 'Catholic', '09171009724', 'antonio.vargas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(553, 'Legal Guardian', 'Emilio', 'Garcia', 'Ocampo', '1960-08-13', 'Chef', 'Catholic', '09181525098', 'emilio.garcia@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(554, 'Legal Guardian', 'Jose', 'Ochoa', 'Solis', '1965-08-13', 'Technician', 'Catholic', '09215888418', 'jose.ochoa@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(555, 'Legal Guardian', 'Concepcion', 'Herrera', 'Ochoa', '1974-08-13', 'Plumber', 'Catholic', '09262982235', 'concepcion.herrera@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(556, 'Legal Guardian', 'Esperanza', 'Vega', 'Villanueva', '1971-08-13', 'Security Guard', 'Catholic', '09223755817', 'esperanza.vega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(557, 'Legal Guardian', 'Dulce', 'Cardenas', 'Cordero', '1993-08-13', 'Police Officer', 'Catholic', '09216453895', 'dulce.cardenas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(558, 'Legal Guardian', 'Esperanza', 'Lozano', 'Castillo', '1958-08-13', 'Mechanic', 'Catholic', '09264729028', 'esperanza.lozano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(559, 'Legal Guardian', 'Sofia', 'Dela Cruz', 'Pacheco', '1974-08-13', 'Supervisor', 'Catholic', '09225584349', 'sofia.dela cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(560, 'Legal Guardian', 'Remedios', 'Ocampo', 'Garcia', '1956-08-13', 'Manager', 'Catholic', '09248846064', 'remedios.ocampo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(561, 'Legal Guardian', 'Antonio', 'Ramos', 'Hernandez', '1961-08-13', 'Plumber', 'Catholic', '09212117296', 'antonio.ramos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(562, 'Legal Guardian', 'Dulce', 'Peña', 'Campos', '1955-08-13', 'IT Specialist', 'Catholic', '09244592101', 'dulce.peña@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(563, 'Legal Guardian', 'Pilar', 'Dela Cruz', 'Contreras', '1970-08-13', 'Supervisor', 'Catholic', '09238290167', 'pilar.dela cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(564, 'Legal Guardian', 'Daniel', 'Castro', 'Pantoja', '1985-08-13', 'Factory Worker', 'Catholic', '09175828951', 'daniel.castro@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(565, 'Legal Guardian', 'Carlos', 'Valdez', 'Medina', '1979-08-13', 'Factory Worker', 'Catholic', '09232182884', 'carlos.valdez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(566, 'Legal Guardian', 'Trinidad', 'Cano', 'Ramos', '1964-08-13', 'IT Specialist', 'Catholic', '09253291614', 'trinidad.cano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(567, 'Legal Guardian', 'Angelica', 'Guerrero', 'Meza', '1985-08-13', 'Factory Worker', 'Catholic', '09225040141', 'angelica.guerrero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(568, 'Legal Guardian', 'Carmen', 'Castro', 'Rojas', '1960-08-13', 'Babysitter', 'Catholic', '09269387954', 'carmen.castro@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(569, 'Legal Guardian', 'Ricardo', 'Meza', 'Torres', '1993-08-13', 'Cook', 'Catholic', '09223586338', 'ricardo.meza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(570, 'Legal Guardian', 'Alvaro', 'Salazar', 'Dela Cruz', '1967-08-13', 'Pilot', 'Catholic', '09179385657', 'alvaro.salazar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(571, 'Legal Guardian', 'Maria', 'Guerrero', 'Lopez', '1979-08-13', 'Construction Worker', 'Catholic', '09178890891', 'maria.guerrero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(572, 'Legal Guardian', 'Resurreccion', 'Santos', 'Gonzales', '1978-08-13', 'Security Guard', 'Catholic', '09256673534', 'resurreccion.santos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(573, 'Legal Guardian', 'Pablo', 'Zuniga', 'Morales', '1977-08-13', 'Police Officer', 'Catholic', '09261775698', 'pablo.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(574, 'Legal Guardian', 'Corazon', 'Varela', 'Zuniga', '1988-08-13', 'Factory Worker', 'Catholic', '09207435077', 'corazon.varela@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(575, 'Legal Guardian', 'Patricia', 'Vega', 'Cardenas', '1971-08-13', 'Vendor', 'Catholic', '09184178877', 'patricia.vega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(576, 'Legal Guardian', 'Diego', 'Cruz', 'Aguilar', '1960-08-13', 'Store Owner', 'Catholic', '09187391072', 'diego.cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(577, 'Legal Guardian', 'Antonio', 'Campos', 'Ocampo', '1965-08-13', 'Factory Worker', 'Catholic', '09202207059', 'antonio.campos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(578, 'Legal Guardian', 'Angelica', 'Aquino', 'Herrera', '1989-08-13', 'Sales Manager', 'Catholic', '09229315527', 'angelica.aquino@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(579, 'Legal Guardian', 'Trinidad', 'Hernandez', 'Pantoja', '1988-08-13', 'Mechanic', 'Catholic', '09242810119', 'trinidad.hernandez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46');
INSERT INTO `student_guardians` (`id`, `guardian_type`, `first_name`, `last_name`, `middle_name`, `date_of_birth`, `occupation`, `religion`, `contact_number`, `email_address`, `created_at`, `updated_at`) VALUES
(580, 'Legal Guardian', 'Pilar', 'Martinez', 'Guerrero', '1983-08-13', 'Salesman', 'Catholic', '09233894151', 'pilar.martinez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(581, 'Legal Guardian', 'Elena', 'Cruz', 'Guerrero', '1973-08-13', 'Driver', 'Catholic', '09188334999', 'elena.cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(582, 'Legal Guardian', 'Trinidad', 'Flores', 'Castillo', '1976-08-13', 'Sales Manager', 'Catholic', '09248654823', 'trinidad.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(583, 'Legal Guardian', 'Caridad', 'Flores', 'Castillo', '1992-08-13', 'Carpenter', 'Catholic', '09248133629', 'caridad.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(584, 'Legal Guardian', 'Patricia', 'Perez', 'Navarro', '1971-08-13', 'Teacher', 'Catholic', '09172949752', 'patricia.perez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(585, 'Legal Guardian', 'Felipe', 'Dela Cruz', 'Herrera', '1955-08-13', 'Store Owner', 'Catholic', '09269331419', 'felipe.dela cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(586, 'Legal Guardian', 'Jose', 'Vargas', 'Camacho', '1969-08-13', 'Businessman', 'Catholic', '09174315765', 'jose.vargas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(587, 'Legal Guardian', 'Hector', 'Meza', 'Torres', '1990-08-13', 'Farmer', 'Catholic', '09253330147', 'hector.meza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(588, 'Legal Guardian', 'Benita', 'Villanueva', 'Ramos', '1961-08-13', 'Carpenter', 'Catholic', '09192293507', 'benita.villanueva@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(589, 'Legal Guardian', 'Catalina', 'Castillo', 'Gonzales', '1991-08-13', 'Lawyer', 'Catholic', '09184267623', 'catalina.castillo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(590, 'Legal Guardian', 'Roberto', 'Salazar', 'Lopez', '1958-08-13', 'Factory Worker', 'Catholic', '09254586447', 'roberto.salazar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(591, 'Legal Guardian', 'Concepcion', 'Dela Cruz', 'Gutierrez', '1992-08-13', 'Businessman', 'Catholic', '09268177272', 'concepcion.dela cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(592, 'Legal Guardian', 'Juan', 'Maldonado', 'Perez', '1983-08-13', 'Construction Worker', 'Catholic', '09219820478', 'juan.maldonado@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(593, 'Legal Guardian', 'Eduardo', 'Solis', 'Zuniga', '1991-08-13', 'Vendor', 'Catholic', '09244081925', 'eduardo.solis@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(594, 'Legal Guardian', 'Eduardo', 'Figueroa', 'Romero', '1955-08-13', 'Manager', 'Catholic', '09204901144', 'eduardo.figueroa@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(595, 'Legal Guardian', 'Luis', 'Flores', 'Herrera', '1957-08-13', 'Doctor', 'Catholic', '09205632821', 'luis.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(596, 'Legal Guardian', 'Javier', 'Ramos', 'Lopez', '1975-08-13', 'Accountant', 'Catholic', '09252919520', 'javier.ramos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(597, 'Legal Guardian', 'Andres', 'Cordero', 'Ramos', '1977-08-13', 'Architect', 'Catholic', '09268940907', 'andres.cordero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(598, 'Legal Guardian', 'Victoria', 'Cardenas', 'Cordero', '1992-08-13', 'Salesman', 'Catholic', '09239741403', 'victoria.cardenas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(599, 'Legal Guardian', 'Jose', 'Aquino', 'Bautista', '1986-08-13', 'Vendor', 'Catholic', '09206575706', 'jose.aquino@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(600, 'Legal Guardian', 'Natividad', 'Garcia', 'Herrera', '1978-08-13', 'Salesman', 'Catholic', '09186551638', 'natividad.garcia@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(601, 'Legal Guardian', 'Oscar', 'Ibarra', 'Peña', '1992-08-13', 'Cook', 'Catholic', '09258851448', 'oscar.ibarra@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(602, 'Legal Guardian', 'Pilar', 'Vega', 'Aguilar', '1977-08-13', 'Seamstress', 'Catholic', '09234753862', 'pilar.vega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(603, 'Legal Guardian', 'Alegria', 'Contreras', 'Valdez', '1965-08-13', 'Bank Manager', 'Catholic', '09173063317', 'alegria.contreras@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(604, 'Legal Guardian', 'Felicidad', 'Meza', 'Reyes', '1994-08-13', 'Cleaner', 'Catholic', '09232440230', 'felicidad.meza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(605, 'Legal Guardian', 'Ramiro', 'Morales', 'Velasco', '1989-08-13', 'Bank Manager', 'Catholic', '09178095091', 'ramiro.morales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(606, 'Legal Guardian', 'Francisco', 'Ramos', 'Velasco', '1992-08-13', 'IT Specialist', 'Catholic', '09207967261', 'francisco.ramos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(607, 'Legal Guardian', 'Paz', 'Aguilar', 'Navarro', '1986-08-13', 'Engineer', 'Catholic', '09234847247', 'paz.aguilar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(608, 'Legal Guardian', 'Esperanza', 'Mendoza', 'Mendoza', '1968-08-13', 'Businessman', 'Catholic', '09256194574', 'esperanza.mendoza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(609, 'Legal Guardian', 'Guillermo', 'Campos', 'Romero', '1966-08-13', 'Technician', 'Catholic', '09211788849', 'guillermo.campos@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(610, 'Legal Guardian', 'Alberto', 'Mendoza', 'Contreras', '1967-08-13', 'Accountant', 'Catholic', '09193812554', 'alberto.mendoza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(611, 'Legal Guardian', 'Fernando', 'Gutierrez', 'Medina', '1955-08-13', 'Mechanic', 'Catholic', '09237512388', 'fernando.gutierrez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(612, 'Legal Guardian', 'Amparo', 'Mendoza', 'Rojas', '1993-08-13', 'Salesman', 'Catholic', '09249022268', 'amparo.mendoza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(613, 'Legal Guardian', 'Dulce', 'Aguilar', 'Castro', '1966-08-13', 'IT Specialist', 'Catholic', '09241406597', 'dulce.aguilar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(614, 'Legal Guardian', 'Francisco', 'Contreras', 'Lozano', '1979-08-13', 'Plumber', 'Catholic', '09255129573', 'francisco.contreras@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(615, 'Legal Guardian', 'Fe', 'Solis', 'Flores', '1981-08-13', 'Pilot', 'Catholic', '09208307030', 'fe.solis@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(616, 'Legal Guardian', 'Gloria', 'Vargas', 'Castillo', '1990-08-13', 'Salesman', 'Catholic', '09188325726', 'gloria.vargas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(617, 'Legal Guardian', 'Carlos', 'Villanueva', 'Lozano', '1986-08-13', 'Plumber', 'Catholic', '09194875180', 'carlos.villanueva@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(618, 'Legal Guardian', 'Paz', 'Hidalgo', 'Aguilar', '1967-08-13', 'Farmer', 'Catholic', '09191762285', 'paz.hidalgo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(619, 'Legal Guardian', 'Trinidad', 'Zuniga', 'Cruz', '1959-08-13', 'Nurse', 'Catholic', '09182455687', 'trinidad.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(620, 'Legal Guardian', 'Fe', 'Aquino', 'Guerrero', '1975-08-13', 'Factory Worker', 'Catholic', '09177304598', 'fe.aquino@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(621, 'Legal Guardian', 'Luz', 'Dela Cruz', 'Velasco', '1978-08-13', 'Babysitter', 'Catholic', '09239523070', 'luz.dela cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(622, 'Legal Guardian', 'Isabel', 'Herrera', 'Castillo', '1956-08-13', 'Engineer', 'Catholic', '09229005176', 'isabel.herrera@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(623, 'Legal Guardian', 'Alvaro', 'Velasco', 'Salazar', '1971-08-13', 'Businessman', 'Catholic', '09266359419', 'alvaro.velasco@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(624, 'Legal Guardian', 'Soledad', 'Contreras', 'Hidalgo', '1978-08-13', 'Laundry Worker', 'Catholic', '09198654361', 'soledad.contreras@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(625, 'Legal Guardian', 'Emilio', 'Quintero', 'Contreras', '1974-08-13', 'Pilot', 'Catholic', '09183844812', 'emilio.quintero@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(626, 'Legal Guardian', 'Clementina', 'Martinez', 'Bautista', '1994-08-13', 'Carpenter', 'Catholic', '09174466003', 'clementina.martinez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(627, 'Legal Guardian', 'Luis', 'Gonzales', 'Aquino', '1965-08-13', 'Farmer', 'Catholic', '09227345215', 'luis.gonzales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(628, 'Legal Guardian', 'Rafael', 'Ortega', 'Vega', '1978-08-13', 'Electrician', 'Catholic', '09225637660', 'rafael.ortega@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(629, 'Legal Guardian', 'Victor', 'Morales', 'Valdez', '1955-08-13', 'Driver', 'Catholic', '09194294892', 'victor.morales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(630, 'Legal Guardian', 'Mario', 'Figueroa', 'Ortega', '1956-08-13', 'IT Specialist', 'Catholic', '09217988094', 'mario.figueroa@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(631, 'Legal Guardian', 'Clementina', 'Cruz', 'Solis', '1956-08-13', 'Security Guard', 'Catholic', '09247822597', 'clementina.cruz@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(632, 'Legal Guardian', 'Angelica', 'Flores', 'Dela Cruz', '1977-08-13', 'Engineer', 'Catholic', '09218907756', 'angelica.flores@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(633, 'Legal Guardian', 'Clementina', 'Hidalgo', 'Garcia', '1981-08-13', 'Architect', 'Catholic', '09186504297', 'clementina.hidalgo@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(634, 'Legal Guardian', 'Francisco', 'Salazar', 'Meza', '1977-08-13', 'Construction Worker', 'Catholic', '09203847635', 'francisco.salazar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(635, 'Legal Guardian', 'Gabriel', 'Cano', 'Ramos', '1976-08-13', 'Architect', 'Catholic', '09208515998', 'gabriel.cano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(636, 'Legal Guardian', 'Guillermo', 'Perez', 'Figueroa', '1979-08-13', 'Electrician', 'Catholic', '09253383525', 'guillermo.perez@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(637, 'Legal Guardian', 'Esteban', 'Herrera', 'Pantoja', '1962-08-13', 'Carpenter', 'Catholic', '09257408680', 'esteban.herrera@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(638, 'Legal Guardian', 'Pedro', 'Salazar', 'Reyes', '1962-08-13', 'Chef', 'Catholic', '09254871743', 'pedro.salazar@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(639, 'Legal Guardian', 'Gabriel', 'Espinoza', 'Villanueva', '1986-08-13', 'Cashier', 'Catholic', '09175724923', 'gabriel.espinoza@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(640, 'Legal Guardian', 'Natividad', 'Cano', 'Pantoja', '1981-08-13', 'Plumber', 'Catholic', '09245676198', 'natividad.cano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(641, 'Legal Guardian', 'Pablo', 'Zuniga', 'Ibarra', '1975-08-13', 'Manager', 'Catholic', '09241634686', 'pablo.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(642, 'Legal Guardian', 'Angelica', 'Castro', 'Santos', '1981-08-13', 'Technician', 'Catholic', '09219375460', 'angelica.castro@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(643, 'Legal Guardian', 'Eduardo', 'Cardenas', 'Quintero', '1981-08-13', 'Technician', 'Catholic', '09191378710', 'eduardo.cardenas@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(644, 'Legal Guardian', 'Alberto', 'Ibarra', 'Espinoza', '1980-08-13', 'Salesman', 'Catholic', '09178895527', 'alberto.ibarra@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(645, 'Legal Guardian', 'Cristina', 'Varela', 'Castro', '1989-08-13', 'Engineer', 'Catholic', '09181685112', 'cristina.varela@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(646, 'Legal Guardian', 'Carlos', 'Ochoa', 'Castillo', '1969-08-13', 'Bank Manager', 'Catholic', '09266090280', 'carlos.ochoa@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(647, 'Legal Guardian', 'Teresa', 'Solis', 'Hernandez', '1971-08-13', 'Laundry Worker', 'Catholic', '09212924417', 'teresa.solis@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(648, 'Legal Guardian', 'Carmen', 'Lozano', 'Valdez', '1980-08-13', 'IT Specialist', 'Catholic', '09264687528', 'carmen.lozano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(649, 'Legal Guardian', 'Catalina', 'Ochoa', 'Peña', '1979-08-13', 'Sales Manager', 'Catholic', '09182957274', 'catalina.ochoa@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(650, 'Legal Guardian', 'Patricia', 'Paredes', 'Lozano', '1962-08-13', 'Manager', 'Catholic', '09204333603', 'patricia.paredes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(651, 'Legal Guardian', 'Resurreccion', 'Maldonado', 'Meza', '1978-08-13', 'Laundry Worker', 'Catholic', '09201785341', 'resurreccion.maldonado@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(652, 'Legal Guardian', 'Manuel', 'Zuniga', 'Vargas', '1983-08-13', 'Security Guard', 'Catholic', '09189114169', 'manuel.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(653, 'Legal Guardian', 'Benjamin', 'Gonzales', 'Guerrero', '1968-08-13', 'Laundry Worker', 'Catholic', '09204977027', 'benjamin.gonzales@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(654, 'Legal Guardian', 'Jorge', 'Reyes', 'Ochoa', '1990-08-13', 'Pharmacist', 'Catholic', '09192929851', 'jorge.reyes@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(655, 'Legal Guardian', 'Ramiro', 'Lozano', 'Cordero', '1957-08-13', 'Chef', 'Catholic', '09246837137', 'ramiro.lozano@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(656, 'Legal Guardian', 'Francesca', 'Zuniga', 'Perez', '1994-08-13', 'Police Officer', 'Catholic', '09255865578', 'francesca.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(657, 'Legal Guardian', 'Adrian', 'Zuniga', 'Pantoja', '1990-08-13', 'Seamstress', 'Catholic', '09231252039', 'adrian.zuniga@email.com', '2025-08-13 12:31:46', '2025-08-13 12:31:46'),
(663, 'Father', 'street', 'aas', NULL, '2025-11-03', 'street', 'street', '123123123', 'a@g.c', '2025-11-23 09:31:27', '2025-11-23 09:31:27'),
(664, 'Mother', 'street', 'aaaas', NULL, '2025-11-05', 'street', 'Catholic', '123123123', 'a@g.c', '2025-11-23 09:31:27', '2025-11-23 09:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `student_payments`
--

CREATE TABLE `student_payments` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `payment_term_id` int NOT NULL,
  `payment_type` enum('down_payment','monthly_installment','full_payment') NOT NULL,
  `installment_number` int DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('bank_transfer','cash','check','gcash','paymaya','other') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `proof_notes` text,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `verification_notes` text,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_payments`
--

INSERT INTO `student_payments` (`id`, `student_id`, `payment_term_id`, `payment_type`, `installment_number`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `proof_image`, `proof_notes`, `verification_status`, `verified_by`, `verification_date`, `verification_notes`, `submitted_at`, `updated_at`) VALUES
(8, 241, 31, 'down_payment', NULL, 7260.00, '2025-08-17', 'gcash', '33233333', 'uploads/payment_proofs/payment_241_1755521836_68a3232c46590.jpeg', '', 'verified', 2, '2025-08-18 20:57:57', '', '2025-08-18 12:57:16', '2025-08-18 12:57:57'),
(10, 390, 37, 'down_payment', NULL, 8690.00, '2025-11-23', 'gcash', '123', 'uploads/payment_proofs/payment_390_1763881155_6922b0c398594.png', 'Here is the down payment', 'verified', 2, '2025-11-23 15:07:45', '', '2025-11-23 06:59:15', '2025-11-23 07:07:45'),
(11, 472, 35, 'down_payment', NULL, 8690.00, '2025-11-24', 'gcash', '1231231', 'uploads/payment_proofs/payment_472_1763969895_69240b6734f8c.png', '', 'verified', 2, '2025-11-24 15:40:09', '', '2025-11-24 07:38:15', '2025-11-24 07:40:09'),
(12, 472, 35, 'monthly_installment', 1, 34760.00, '2025-11-24', 'gcash', '1231231234', 'uploads/payment_proofs/payment_472_1763969975_69240bb75243b.png', '', 'verified', 2, '2025-11-24 15:40:06', '', '2025-11-24 07:39:35', '2025-11-24 07:40:06');

--
-- Triggers `student_payments`
--
DELIMITER $$
CREATE TRIGGER `update_student_status_on_payment` AFTER UPDATE ON `student_payments` FOR EACH ROW BEGIN
    DECLARE total_required DECIMAL(10,2);
    DECLARE total_paid DECIMAL(10,2);
    DECLARE all_verified INT;
    DECLARE student_term_id INT;
    
    
    IF NEW.verification_status = 'verified' AND OLD.verification_status != 'verified' THEN
        
        SELECT payment_term_id INTO student_term_id
        FROM student_payments
        WHERE id = NEW.id;
        
        
        SELECT 
            CASE 
                WHEN pt.term_type = 'full_payment' THEN 
                    pt.full_payment_amount - (pt.full_payment_amount * pt.full_payment_discount_percentage / 100)
                WHEN pt.term_type = 'installment' THEN 
                    pt.down_payment_amount + (pt.monthly_fee_amount * pt.number_of_installments)
                ELSE 0
            END INTO total_required
        FROM payment_terms pt
        WHERE pt.id = student_term_id;
        
        
        SELECT 
            COALESCE(SUM(sp.amount_paid), 0),
            COUNT(*) = SUM(CASE WHEN sp.verification_status = 'verified' THEN 1 ELSE 0 END)
        INTO total_paid, all_verified
        FROM student_payments sp
        WHERE sp.student_id = NEW.student_id
        AND sp.payment_term_id = student_term_id;
        
        
        IF total_paid >= total_required AND all_verified = 1 THEN
            UPDATE students 
            SET enrollment_status = 'Enrolled'
            WHERE id = NEW.student_id 
            AND enrollment_status = 'Pending Payment';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_payment_preferences`
--

CREATE TABLE `student_payment_preferences` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `payment_term_id` int NOT NULL,
  `selected_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_payment_preferences`
--

INSERT INTO `student_payment_preferences` (`id`, `student_id`, `payment_term_id`, `selected_at`, `updated_at`) VALUES
(1, 241, 31, '2025-08-18 12:42:45', '2025-08-18 12:42:45'),
(6, 390, 37, '2025-11-23 07:08:43', '2025-11-23 07:08:43'),
(7, 472, 35, '2025-11-24 07:37:39', '2025-11-24 07:37:39');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(200) NOT NULL,
  `grade_level_id` int DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `grade_level_id`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'N-LANG', 'Nursery Language Development', 1, 'Basic language and communication skills for nursery', 1, '2025-09-05 05:40:24', '2025-12-05 11:01:42'),
(2, 'N-MATH', 'Nursery Mathematics', 1, 'Basic number concepts and counting for nursery', 1, '2025-09-05 05:40:24', '2025-12-05 11:01:42'),
(3, 'N-SCI', 'Nursery Science Exploration', 1, 'Basic science discovery for nursery children', 1, '2025-09-05 05:40:24', '2025-12-05 11:01:42'),
(4, 'N-ART', 'Nursery Arts and Crafts', 1, 'Creative arts and fine motor development for nursery', 1, '2025-09-05 05:40:24', '2025-12-05 11:01:42'),
(5, 'N-PLAY', 'Nursery Play-based Learning', 1, 'Play-based learning activities for nursery development', 1, '2025-09-05 05:40:24', '2025-12-05 11:01:42'),
(6, 'K-LANG', 'Kindergarten Language Arts', 2, 'Reading readiness and literacy for kindergarten', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(7, 'K-MATH', 'Kindergarten Mathematics', 2, 'Foundation mathematics for kindergarten', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(8, 'K-SCI', 'Kindergarten Science', 2, 'Basic science exploration for kindergarten', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(9, 'K-ART', 'Kindergarten Arts', 2, 'Creative expression and fine arts for kindergarten', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(10, 'K-SOCIAL', 'Kindergarten Social Skills', 2, 'Social interaction and community awareness', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(11, 'G1-MATH', 'Grade 1 Mathematics', 3, 'Basic arithmetic and number concepts for Grade 1', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(12, 'G1-ENG', 'Grade 1 English', 3, 'English language arts for Grade 1', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(13, 'G1-FIL', 'Grade 1 Filipino', 3, 'Filipino language for Grade 1', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(14, 'G1-SCI', 'Grade 1 Science', 3, 'Elementary science for Grade 1', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(15, 'G1-MAPEH', 'Grade 1 MAPEH', 3, 'Music, Arts, PE, and Health for Grade 1', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(16, 'G2-MATH', 'Grade 2 Mathematics', 4, 'Elementary mathematics for Grade 2', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(17, 'G2-ENG', 'Grade 2 English', 4, 'English language arts for Grade 2', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(18, 'G2-FIL', 'Grade 2 Filipino', 4, 'Filipino language for Grade 2', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(19, 'G2-SCI', 'Grade 2 Science', 4, 'Elementary science for Grade 2', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(20, 'G2-AP', 'Grade 2 Araling Panlipunan', 4, 'Social studies for Grade 2', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(21, 'G3-MATH', 'Grade 3 Mathematics', 5, 'Elementary mathematics for Grade 3', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(22, 'G3-ENG', 'Grade 3 English', 5, 'English language arts for Grade 3', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(23, 'G3-FIL', 'Grade 3 Filipino', 5, 'Filipino language for Grade 3', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(24, 'G3-SCI', 'Grade 3 Science', 5, 'Elementary science for Grade 3', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(25, 'G3-ESP', 'Grade 3 ESP', 5, 'Values education for Grade 3', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(26, 'G4-MATH', 'Grade 4 Mathematics', 6, 'Elementary mathematics for Grade 4', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(27, 'G4-ENG', 'Grade 4 English', 6, 'English language arts for Grade 4', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(28, 'G4-FIL', 'Grade 4 Filipino', 6, 'Filipino language for Grade 4', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(29, 'G4-SCI', 'Grade 4 Science', 6, 'Elementary science for Grade 4', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(30, 'G4-MAPEH', 'Grade 4 MAPEH', 6, 'Music, Arts, PE, and Health for Grade 4', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(31, 'G5-MATH', 'Grade 5 Mathematics', 7, 'Elementary mathematics for Grade 5', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(32, 'G5-ENG', 'Grade 5 English', 7, 'English language arts for Grade 5', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(33, 'G5-FIL', 'Grade 5 Filipino', 7, 'Filipino language for Grade 5', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(34, 'G5-SCI', 'Grade 5 Science', 7, 'Elementary science for Grade 5', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(35, 'G5-AP', 'Grade 5 Araling Panlipunan', 7, 'Social studies for Grade 5', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(36, 'G6-MATH', 'Grade 6 Mathematics', 8, 'Elementary mathematics for Grade 6', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(37, 'G6-ENG', 'Grade 6 English', 8, 'English language arts for Grade 6', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(38, 'G6-FIL', 'Grade 6 Filipino', 8, 'Filipino language for Grade 6', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(39, 'G6-SCI', 'Grade 6 Science', 8, 'Elementary science for Grade 6', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(40, 'G6-ESP', 'Grade 6 ESP', 8, 'Values education for Grade 6', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(41, 'G7-MATH', 'Grade 7 Mathematics', 9, 'Secondary mathematics for Grade 7', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(42, 'G7-ENG', 'Grade 7 English', 9, 'English language and literature for Grade 7', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(43, 'G7-FIL', 'Grade 7 Filipino', 9, 'Filipino language and literature for Grade 7', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(44, 'G7-SCI', 'Grade 7 Science', 9, 'Integrated science for Grade 7', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(45, 'G7-AP', 'Grade 7 Araling Panlipunan', 9, 'Social studies for Grade 7', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(46, 'G8-MATH', 'Grade 8 Mathematics', 10, 'Secondary mathematics for Grade 8', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(47, 'G8-ENG', 'Grade 8 English', 10, 'English language and literature for Grade 8', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(48, 'G8-FIL', 'Grade 8 Filipino', 10, 'Filipino language and literature for Grade 8', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(49, 'G8-SCI', 'Grade 8 Science', 10, 'Integrated science for Grade 8', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(50, 'G8-TLE', 'Grade 8 TLE', 10, 'Technology and Livelihood Education for Grade 8', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(51, 'G9-MATH', 'Grade 9 Mathematics', 11, 'Secondary mathematics for Grade 9', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(52, 'G9-ENG', 'Grade 9 English', 11, 'English language and literature for Grade 9', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(53, 'G9-FIL', 'Grade 9 Filipino', 11, 'Filipino language and literature for Grade 9', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(54, 'G9-SCI', 'Grade 9 Science', 11, 'Integrated science for Grade 9', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(55, 'G9-ESP', 'Grade 9 ESP', 11, 'Values education for Grade 9', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(56, 'G10-MATH', 'Grade 10 Mathematics', 12, 'Secondary mathematics for Grade 10', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(57, 'G10-ENG', 'Grade 10 English', 12, 'English language and literature for Grade 10', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(58, 'G10-FIL', 'Grade 10 Filipino', 12, 'Filipino language and literature for Grade 10', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(59, 'G10-SCI', 'Grade 10 Science', 12, 'Integrated science for Grade 10', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56'),
(60, 'G10-MAPEH', 'Grade 10 MAPEH', 12, 'Music, Arts, PE, and Health for Grade 10', 1, '2025-09-05 05:40:24', '2025-12-05 10:59:56');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email_address` varchar(100) DEFAULT NULL,
  `address` text,
  `specialization` varchar(200) DEFAULT NULL,
  `employment_status` enum('Regular','Contractual','Part-time') DEFAULT 'Regular',
  `date_hired` date NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `employee_id`, `first_name`, `last_name`, `middle_name`, `gender`, `date_of_birth`, `contact_number`, `email_address`, `address`, `specialization`, `employment_status`, `date_hired`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 11, 'TCH001', 'John', 'Doe', NULL, 'Male', '1985-01-01', '09171234567', NULL, NULL, 'Mathematics', 'Regular', '2024-08-01', 1, 1, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(2, 12, 'TCH002', 'Jane', 'Smith', NULL, 'Male', '1985-01-01', '09171234567', NULL, NULL, 'English', 'Regular', '2024-08-01', 1, 1, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(3, 13, 'TCH003', 'Mike', 'Johnson', NULL, 'Male', '1985-01-01', '09171234567', NULL, NULL, 'Science', 'Regular', '2024-08-01', 1, 1, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(4, 14, 'TCH004', 'Sarah', 'Wilson', NULL, 'Male', '1985-01-01', '09171234567', NULL, NULL, 'Filipino', 'Regular', '2024-08-01', 1, 1, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(5, 23, 'EMP007', 'Maria', 'Santos', 'Cruz', 'Female', '1985-03-15', '09171234567', 'maria.santos@gtba.edu.ph', '123 Rizal Street, Manila', 'Elementary Education', 'Regular', '2020-06-01', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(6, 24, 'EMP008', 'Juan', 'Dela Cruz', 'Reyes', 'Male', '1982-07-20', '09181234567', 'juan.delacru@gtba.edu.ph', '456 Bonifacio Avenue, Quezon City', 'Mathematics', 'Regular', '2019-08-15', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(7, 25, 'EMP009', 'Ana', 'Reyes', 'Garcia', 'Female', '1988-11-10', '09191234567', 'ana.reyes@gtba.edu.ph', '789 Mabini Street, Makati', 'Science', 'Regular', '2021-01-10', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(8, 26, 'EMP010', 'Pedro', 'Garcia', 'Lopez', 'Male', '1980-05-25', '09201234567', 'pedro.garcia@gtba.edu.ph', '321 Luna Street, Pasig', 'English', 'Regular', '2018-03-20', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(9, 27, 'EMP011', 'Linda', 'Martinez', 'Torres', 'Female', '1987-09-18', '09211234567', 'linda.martinez@gtba.edu.ph', '654 Del Pilar Street, San Juan', 'Filipino', 'Contractual', '2022-06-01', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(10, 28, 'EMP012', 'Carlos', 'Lopez', 'Morales', 'Male', '1983-12-08', '09221234567', 'carlos.lopez@gtba.edu.ph', '987 Aguinaldo Street, Taguig', 'Physical Education', 'Regular', '2020-08-15', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(11, 29, 'EMP013', 'Rosa', 'Fernandez', 'Rivera', 'Female', '1984-02-14', '09231234567', 'rosa.fernandez@gtba.edu.ph', '147 Jose Rizal Avenue, Mandaluyong', 'Music and Arts', 'Regular', '2021-09-01', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(12, 30, 'EMP014', 'Jose', 'Mendoza', 'Cruz', 'Male', '1981-06-30', '09241234567', 'jose.mendoza@gtba.edu.ph', '258 Andres Bonifacio Street, Pasay', 'Araling Panlipunan', 'Regular', '2019-06-15', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(13, 31, 'EMP015', 'Carmen', 'Ramos', 'Dela Cruz', 'Female', '1986-10-12', '09251234567', 'carmen.ramos@gtba.edu.ph', '369 Lapu-Lapu Street, Caloocan', 'Technology and Livelihood Education', 'Contractual', '2022-08-01', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54'),
(14, 32, 'EMP016', 'Miguel', 'Torres', 'Santos', 'Male', '1983-04-08', '09261234567', 'miguel.torres@gtba.edu.ph', '741 Emilio Jacinto Avenue, Marikina', 'Computer Education', 'Regular', '2020-10-01', 1, 1, '2025-07-22 10:22:54', '2025-07-22 10:22:54');

-- --------------------------------------------------------

--
-- Stand-in structure for view `teacher_info`
-- (See below for the actual view)
--
CREATE TABLE `teacher_info` (
`user_id` int
,`username` varchar(50)
,`email` varchar(100)
,`user_active` tinyint(1)
,`teacher_table_id` int
,`employee_id` varchar(20)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`middle_name` varchar(100)
,`full_name` varchar(302)
,`gender` enum('Male','Female')
,`date_of_birth` date
,`contact_number` varchar(20)
,`email_address` varchar(100)
,`specialization` varchar(200)
,`employment_status` enum('Regular','Contractual','Part-time')
,`date_hired` date
,`teacher_active` tinyint(1)
);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_schedules`
--

CREATE TABLE `teacher_schedules` (
  `id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `subject_id` int DEFAULT NULL,
  `activity_name` varchar(200) DEFAULT NULL,
  `section_id` int DEFAULT NULL,
  `school_year_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `teacher_schedules`
--

INSERT INTO `teacher_schedules` (`id`, `teacher_id`, `subject_id`, `activity_name`, `section_id`, `school_year_id`, `day_of_week`, `start_time`, `end_time`, `room`, `notes`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 11, 60, NULL, 17, 2, 'Monday', '11:00:00', '12:00:00', 'room 202', '', 1, 4, '2025-09-26 09:24:25', '2025-09-26 09:24:25'),
(2, 11, 59, NULL, 17, 2, 'Monday', '12:00:00', '13:00:00', 'Room 101', '', 1, 4, '2025-09-26 10:14:28', '2025-09-26 10:14:28');

-- --------------------------------------------------------

--
-- Table structure for table `tuition_fees`
--

CREATE TABLE `tuition_fees` (
  `id` int NOT NULL,
  `grade_level_id` int NOT NULL,
  `school_year_id` int NOT NULL,
  `gtba_tuition_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gtba_other_fees` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gtba_miscellaneous_fees` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gtba_total_amount` decimal(10,2) GENERATED ALWAYS AS (((`gtba_tuition_fee` + `gtba_other_fees`) + `gtba_miscellaneous_fees`)) STORED,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tuition_fees`
--

INSERT INTO `tuition_fees` (`id`, `grade_level_id`, `school_year_id`, `gtba_tuition_fee`, `gtba_other_fees`, `gtba_miscellaneous_fees`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 25000.00, 5000.00, 3000.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(2, 2, 1, 28000.00, 5500.00, 3200.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(3, 3, 1, 30000.00, 6000.00, 3500.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(4, 4, 1, 30000.00, 6000.00, 3500.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(5, 5, 1, 32000.00, 6500.00, 3800.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(6, 6, 1, 32000.00, 6500.00, 3800.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(7, 7, 1, 34000.00, 7000.00, 4000.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(8, 8, 1, 34000.00, 7000.00, 4000.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(9, 9, 1, 36000.00, 7500.00, 4500.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(10, 10, 1, 36000.00, 7500.00, 4500.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(11, 11, 1, 38000.00, 8000.00, 5000.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(12, 12, 1, 38000.00, 8000.00, 5000.00, 1, 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(25, 1, 2, 27500.00, 5500.00, 3300.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:42'),
(26, 2, 2, 30800.00, 6050.00, 3520.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(27, 3, 2, 33000.00, 6600.00, 3850.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(28, 4, 2, 33000.00, 6600.00, 3850.00, 1, 2, '2025-07-30 02:24:22', '2025-11-23 06:25:29'),
(29, 5, 2, 35200.00, 7150.00, 4180.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(30, 6, 2, 35200.00, 7150.00, 4180.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(31, 7, 2, 37400.00, 7700.00, 4400.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(32, 8, 2, 37400.00, 7700.00, 4400.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(33, 9, 2, 39600.00, 8250.00, 4950.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(34, 10, 2, 39600.00, 8250.00, 4950.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(35, 11, 2, 41800.00, 8800.00, 5500.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43'),
(36, 12, 2, 41800.00, 8800.00, 5500.00, 1, 2, '2025-07-30 02:24:22', '2025-08-15 13:11:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `archived_at` timestamp NULL DEFAULT NULL,
  `privacy_policy_accepted` tinyint(1) DEFAULT '0',
  `privacy_policy_accepted_at` timestamp NULL DEFAULT NULL,
  `privacy_policy_ip_address` varchar(45) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `profile_picture`, `password`, `role_id`, `is_active`, `archived_at`, `privacy_policy_accepted`, `privacy_policy_accepted_at`, `privacy_policy_ip_address`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(2, 'finance', 'finance@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(3, 'registrar', 'registrar@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(4, 'principal', 'principal@gtba.edu.ph', 'uploads/profiles/profile_4_1753876058.png', '$2y$10$QlKucTMa9Z2toEb7T6oHTeM2CrVUOy4xK6mdJmANZvGRkVIx.v0tO', 2, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-19 07:49:43', '2025-07-30 11:47:38'),
(5, 'student', 'student@gtba.edu.ph', NULL, '$2y$10$1h4T2556r/2pf9J3X2CozeSAUAeaXYLE5yZKcKUPO0u74Cykxr8Hi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-19 11:21:02', '2025-07-19 11:21:52'),
(6, 'teacher', 'teacher@gtba.edu.ph', NULL, '$2y$10$I18cCqCUc9o7hwSwhBEyqOk5lhqR1anTCl89tdP4trekNUu3Cc/I.', 5, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-19 11:24:50', '2025-07-19 11:24:50'),
(11, 'john.doe', 'john.doe@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-20 09:19:33', '2025-07-22 07:50:18'),
(12, 'jane.smith', 'jane.smith@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(13, 'mike.johnson', 'mike.johnson@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(14, 'sarah.wilson', 'sarah.wilson@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(310, '2025001', '2025001@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(311, '2025002', '2025002@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(312, '2025003', '2025003@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(313, '2025004', '2025004@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(314, '2025005', '2025005@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(315, '2025006', '2025006@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(316, '2025007', '2025007@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(317, '2025008', '2025008@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(318, '2025009', '2025009@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(319, '2025010', '2025010@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(430, '2025011', '2025011@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(431, '2025012', '2025012@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(432, '2025013', '2025013@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(433, '2025014', '2025014@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(434, '2025015', '2025015@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(435, '2025016', '2025016@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(436, '2025017', '2025017@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(437, '2025018', '2025018@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(438, '2025019', '2025019@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(439, '2025020', '2025020@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(440, '2025021', '2025021@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(441, '2025022', '2025022@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(442, '2025023', '2025023@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(443, '2025024', '2025024@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(444, '2025025', '2025025@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-12-05 13:59:39'),
(445, '2025026', '2025026@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(446, '2025027', '2025027@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(447, '2025028', '2025028@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(448, '2025029', '2025029@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(449, '2025030', '2025030@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(450, '2025031', '2025031@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 1, '2025-12-07 04:15:28', '127.0.0.1', NULL, NULL, '2025-07-30 10:50:35', '2025-12-07 04:15:28'),
(451, '2025032', '2025032@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(452, '2025033', '2025033@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(453, '2025034', '2025034@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(454, '2025035', '2025035@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(455, '2025036', '2025036@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(456, '2025037', '2025037@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(457, '2025038', '2025038@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(458, '2025039', '2025039@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(459, '2025040', '2025040@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(460, '2025041', '2025041@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(461, '2025042', '2025042@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(462, '2025043', '2025043@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(463, '2025044', '2025044@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(464, '2025045', '2025045@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(465, '2025046', '2025046@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(466, '2025047', '2025047@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(467, '2025048', '2025048@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(468, '2025049', '2025049@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(469, '2025050', '2025050@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(470, '2025051', '2025051@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(471, '2025052', '2025052@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(472, '2025053', '2025053@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(473, '2025054', '2025054@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(474, '2025055', '2025055@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(475, '2025056', '2025056@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(476, '2025057', '2025057@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(477, '2025058', '2025058@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(478, '2025059', '2025059@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(479, '2025060', '2025060@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(480, '2025061', '2025061@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(481, '2025062', '2025062@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(482, '2025063', '2025063@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(483, '2025064', '2025064@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(484, '2025065', '2025065@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(485, '2025066', '2025066@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(486, '2025067', '2025067@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(487, '2025068', '2025068@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(488, '2025069', '2025069@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(489, '2025070', '2025070@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(490, '2025071', '2025071@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(491, '2025072', '2025072@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(492, '2025073', '2025073@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(493, '2025074', '2025074@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(494, '2025075', '2025075@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(495, '2025076', '2025076@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(496, '2025077', '2025077@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(497, '2025078', '2025078@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(498, '2025079', '2025079@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(499, '2025080', '2025080@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(500, '2025081', '2025081@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(501, '2025082', '2025082@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(502, '2025083', '2025083@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(503, '2025084', '2025084@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(504, '2025085', '2025085@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(505, '2025086', '2025086@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(506, '2025087', '2025087@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(507, '2025088', '2025088@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(508, '2025089', '2025089@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(509, '2025090', '2025090@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(510, '2025091', '2025091@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(511, '2025092', '2025092@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(512, '2025093', '2025093@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(513, '2025094', '2025094@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(514, '2025095', '2025095@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(515, '2025096', '2025096@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(516, '2025097', '2025097@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(517, '2025098', '2025098@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(518, '2025099', '2025099@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(519, '2025100', '2025100@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(520, '2025101', '2025101@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(521, '2025102', '2025102@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(522, '2025103', '2025103@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(523, '2025104', '2025104@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(524, '2025105', '2025105@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(525, '2025106', '2025106@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(526, '2025107', '2025107@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(527, '2025108', '2025108@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(528, '2025109', '2025109@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(529, '2025110', '2025110@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(530, '2025111', '2025111@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(531, '2025112', '2025112@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(532, '2025113', '2025113@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(533, '2025114', '2025114@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(534, '2025115', '2025115@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(535, '2025116', '2025116@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(536, '2025117', '2025117@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(537, '2025118', '2025118@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(538, '2025119', '2025119@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(539, '2025120', '2025120@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(542, '123123', '123123@student.gtba.edu.ph', NULL, '$2y$10$1tEZ0IIpOqcAJcGfuSotK.N0W6aQxs0OGyGEoruxJElHivXq1tLTW', 6, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-11-23 09:31:28', '2025-11-23 09:31:28'),
(543, 'test', 'test@gmail.com', NULL, '$2y$10$mXzykCsrs6UberHfC6wfNOxCr4yBBtuFsJyreISo/Mt07bpM5w/3S', 5, 1, NULL, 0, NULL, NULL, NULL, NULL, '2025-12-05 13:55:18', '2025-12-05 13:59:09');

-- --------------------------------------------------------

--
-- Structure for view `section_assignments`
--
DROP TABLE IF EXISTS `section_assignments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `section_assignments`  AS SELECT `s`.`id` AS `section_id`, `s`.`section_name` AS `section_name`, `s`.`grade_level_id` AS `grade_level_id`, `s`.`school_year_id` AS `school_year_id`, `s`.`current_enrollment` AS `current_enrollment`, `s`.`is_active` AS `section_active`, `gl`.`grade_name` AS `grade_name`, `gl`.`grade_code` AS `grade_code`, `sy`.`year_label` AS `year_label`, `st`.`teacher_id` AS `teacher_id`, `ti`.`full_name` AS `teacher_name`, `ti`.`first_name` AS `first_name`, `ti`.`last_name` AS `last_name`, `st`.`is_primary` AS `is_primary`, `st`.`assigned_date` AS `assigned_date`, `st`.`is_active` AS `assignment_active` FROM ((((`sections` `s` left join `section_teachers` `st` on(((`s`.`id` = `st`.`section_id`) and (`st`.`is_active` = 1)))) left join `teacher_info` `ti` on((`st`.`teacher_id` = `ti`.`user_id`))) left join `grade_levels` `gl` on((`s`.`grade_level_id` = `gl`.`id`))) left join `school_years` `sy` on((`s`.`school_year_id` = `sy`.`id`))) WHERE (`s`.`is_active` = 1) ;

-- --------------------------------------------------------

--
-- Structure for view `teacher_info`
--
DROP TABLE IF EXISTS `teacher_info`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `teacher_info`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`is_active` AS `user_active`, `t`.`id` AS `teacher_table_id`, `t`.`employee_id` AS `employee_id`, `t`.`first_name` AS `first_name`, `t`.`last_name` AS `last_name`, `t`.`middle_name` AS `middle_name`, concat(`t`.`first_name`,' ',ifnull(concat(`t`.`middle_name`,' '),''),`t`.`last_name`) AS `full_name`, `t`.`gender` AS `gender`, `t`.`date_of_birth` AS `date_of_birth`, `t`.`contact_number` AS `contact_number`, `t`.`email_address` AS `email_address`, `t`.`specialization` AS `specialization`, `t`.`employment_status` AS `employment_status`, `t`.`date_hired` AS `date_hired`, `t`.`is_active` AS `teacher_active` FROM (`users` `u` join `teachers` `t` on((`u`.`id` = `t`.`user_id`))) WHERE (`u`.`role_id` = (select `roles`.`id` from `roles` where (`roles`.`name` = 'teacher'))) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `target_grade_level_id` (`target_grade_level_id`);

--
-- Indexes for table `announcement_attachments`
--
ALTER TABLE `announcement_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `school_year_id` (`school_year_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_class_schedules_teacher` (`teacher_id`),
  ADD KEY `idx_class_schedules_section_teacher` (`section_id`,`teacher_id`),
  ADD KEY `idx_class_schedules_activity` (`activity_name`);

--
-- Indexes for table `curriculum`
--
ALTER TABLE `curriculum`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_grade_subject_year` (`grade_level_id`,`subject_id`,`school_year_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `school_year_id` (`school_year_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `grade_levels`
--
ALTER TABLE `grade_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grade_name` (`grade_name`),
  ADD UNIQUE KEY `grade_code` (`grade_code`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_default_per_grade_year` (`school_year_id`,`grade_level_id`,`is_default`),
  ADD KEY `grade_level_id` (`grade_level_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payment_uploads`
--
ALTER TABLE `payment_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year_label` (`year_label`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section_year` (`section_name`,`school_year_id`),
  ADD KEY `school_year_id` (`school_year_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_sections_grade_year` (`grade_level_id`,`school_year_id`);

--
-- Indexes for table `section_teachers`
--
ALTER TABLE `section_teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section_teacher_active` (`section_id`,`teacher_id`,`is_active`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_section_id` (`section_id`),
  ADD KEY `idx_teacher_id` (`teacher_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD KEY `father_id` (`father_id`),
  ADD KEY `mother_id` (`mother_id`),
  ADD KEY `legal_guardian_id` (`legal_guardian_id`),
  ADD KEY `current_grade_level_id` (`current_grade_level_id`),
  ADD KEY `current_section_id` (`current_section_id`),
  ADD KEY `current_school_year_id` (`current_school_year_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_transfer_date` (`transfer_date`),
  ADD KEY `idx_enrollment_status` (`enrollment_status`),
  ADD KEY `idx_transfer_approved_by` (`transfer_approved_by`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_school_year` (`student_id`,`school_year_id`),
  ADD KEY `school_year_id` (`school_year_id`),
  ADD KEY `grade_level_id` (`grade_level_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_year` (`student_id`,`subject_id`,`school_year_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `school_year_id` (`school_year_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `fk_student_grades_teacher` (`teacher_id`),
  ADD KEY `idx_student_grades_student_teacher` (`student_id`,`teacher_id`);

--
-- Indexes for table `student_guardians`
--
ALTER TABLE `student_guardians`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_payments`
--
ALTER TABLE `student_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_installment` (`student_id`,`payment_term_id`,`payment_type`,`installment_number`),
  ADD KEY `payment_term_id` (`payment_term_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `student_payment_preferences`
--
ALTER TABLE `student_payment_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_preference` (`student_id`,`payment_term_id`),
  ADD KEY `payment_term_id` (`payment_term_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `fk_subjects_grade_level` (`grade_level_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `teacher_schedules`
--
ALTER TABLE `teacher_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_teacher_schedules_teacher` (`teacher_id`),
  ADD KEY `fk_teacher_schedules_subject` (`subject_id`),
  ADD KEY `fk_teacher_schedules_section` (`section_id`),
  ADD KEY `fk_teacher_schedules_school_year` (`school_year_id`),
  ADD KEY `fk_teacher_schedules_created_by` (`created_by`),
  ADD KEY `idx_teacher_schedules_day_time` (`day_of_week`,`start_time`,`end_time`);

--
-- Indexes for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_grade_school_year` (`grade_level_id`,`school_year_id`),
  ADD KEY `school_year_id` (`school_year_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_privacy_policy_accepted` (`privacy_policy_accepted`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `announcement_attachments`
--
ALTER TABLE `announcement_attachments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=209;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=644;

--
-- AUTO_INCREMENT for table `curriculum`
--
ALTER TABLE `curriculum`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `grade_levels`
--
ALTER TABLE `grade_levels`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment_terms`
--
ALTER TABLE `payment_terms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `payment_uploads`
--
ALTER TABLE `payment_uploads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `section_teachers`
--
ALTER TABLE `section_teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=473;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1719;

--
-- AUTO_INCREMENT for table `student_guardians`
--
ALTER TABLE `student_guardians`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=665;

--
-- AUTO_INCREMENT for table `student_payments`
--
ALTER TABLE `student_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `student_payment_preferences`
--
ALTER TABLE `student_payment_preferences`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `teacher_schedules`
--
ALTER TABLE `teacher_schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=544;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`target_grade_level_id`) REFERENCES `grade_levels` (`id`);

--
-- Constraints for table `announcement_attachments`
--
ALTER TABLE `announcement_attachments`
  ADD CONSTRAINT `announcement_attachments_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `class_schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `class_schedules_ibfk_4` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `class_schedules_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_class_schedules_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `curriculum`
--
ALTER TABLE `curriculum`
  ADD CONSTRAINT `curriculum_ibfk_1` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `curriculum_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `curriculum_ibfk_3` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `curriculum_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD CONSTRAINT `payment_terms_ibfk_1` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_terms_ibfk_2` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_terms_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `payment_uploads`
--
ALTER TABLE `payment_uploads`
  ADD CONSTRAINT `payment_uploads_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `student_payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `school_years`
--
ALTER TABLE `school_years`
  ADD CONSTRAINT `school_years_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `sections_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `section_teachers`
--
ALTER TABLE `section_teachers`
  ADD CONSTRAINT `section_teachers_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `section_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `section_teachers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_transfer_approved_by` FOREIGN KEY (`transfer_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`father_id`) REFERENCES `student_guardians` (`id`),
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`mother_id`) REFERENCES `student_guardians` (`id`),
  ADD CONSTRAINT `students_ibfk_4` FOREIGN KEY (`legal_guardian_id`) REFERENCES `student_guardians` (`id`),
  ADD CONSTRAINT `students_ibfk_5` FOREIGN KEY (`current_grade_level_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `students_ibfk_6` FOREIGN KEY (`current_section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `students_ibfk_7` FOREIGN KEY (`current_school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `students_ibfk_8` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `student_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_enrollments_ibfk_2` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `student_enrollments_ibfk_3` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `student_enrollments_ibfk_4` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `student_enrollments_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD CONSTRAINT `fk_student_grades_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `student_grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `student_grades_ibfk_4` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `student_grades_ibfk_5` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `student_payments`
--
ALTER TABLE `student_payments`
  ADD CONSTRAINT `student_payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_payments_ibfk_2` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_payments_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_payment_preferences`
--
ALTER TABLE `student_payment_preferences`
  ADD CONSTRAINT `student_payment_preferences_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_payment_preferences_ibfk_2` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_grade_level` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `teacher_schedules`
--
ALTER TABLE `teacher_schedules`
  ADD CONSTRAINT `fk_teacher_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_teacher_schedules_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_schedules_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_teacher_schedules_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_teacher_schedules_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  ADD CONSTRAINT `tuition_fees_ibfk_1` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `tuition_fees_ibfk_2` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `tuition_fees_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
