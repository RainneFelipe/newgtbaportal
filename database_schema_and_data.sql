-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 31, 2025 at 06:51 AM
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
(183, 4, 'UPDATE_PROFILE', 'Profile picture updated', 'users', 4, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-07-30 11:47:38');

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
(18, 6, NULL, 'Break', 2, 'Monday', '12:00:00', '13:00:00', 'Cafeteria', 1, 4, '2025-07-30 12:31:23', '2025-07-30 12:31:23', NULL);

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
(1, 1, 1, 1, 1, 1, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(2, 1, 2, 1, 1, 2, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(3, 1, 3, 1, 1, 3, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(4, 1, 4, 1, 1, 4, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(5, 1, 5, 1, 1, 5, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(6, 2, 1, 1, 1, 1, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(7, 2, 2, 1, 1, 2, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(8, 2, 3, 1, 1, 3, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(9, 2, 4, 1, 1, 4, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(10, 2, 5, 1, 1, 5, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(11, 3, 1, 1, 1, 1, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(12, 3, 2, 1, 1, 2, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(13, 3, 3, 1, 1, 3, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(14, 3, 4, 1, 1, 4, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(15, 3, 5, 1, 1, 5, 1, '2025-07-20 13:24:36', '2025-07-20 13:24:36'),
(18, 7, 16, 2, 1, 1, 4, '2025-07-22 06:40:11', '2025-07-22 06:40:11'),
(19, 3, 3, 2, 1, 2, 4, '2025-07-22 06:41:44', '2025-07-22 06:42:18'),
(20, 3, 5, 2, 1, 1, 4, '2025-07-22 06:42:02', '2025-07-22 06:42:02'),
(21, 3, 11, 2, 1, 1, 4, '2025-07-22 08:40:59', '2025-07-22 08:40:59');

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
(3, '2026-2027', '2026-2027', '2026-11-11', '2027-11-11', 0, 0, 1, '2025-07-22 08:57:39', '2025-07-30 02:13:16');

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
(17, 'Grade 10 - Platinum', 12, 2, 'Room 508', 'Grade 10 junior high section', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:08:32');

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
(10, 6, 12, 0, '2025-07-30', 1, 4, '2025-07-30 10:56:18', '2025-07-30 10:56:18');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `lrn` varchar(12) NOT NULL,
  `student_type` enum('Continuing','Transfer','New') NOT NULL,
  `enrollment_status` enum('Enrolled','Dropped','Graduated','Transferred') DEFAULT 'Enrolled',
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

INSERT INTO `students` (`id`, `user_id`, `student_id`, `lrn`, `student_type`, `enrollment_status`, `first_name`, `last_name`, `middle_name`, `suffix`, `gender`, `date_of_birth`, `place_of_birth`, `religion`, `present_address`, `permanent_address`, `father_id`, `mother_id`, `legal_guardian_id`, `emergency_contact_name`, `emergency_contact_number`, `emergency_contact_relationship`, `current_grade_level_id`, `current_section_id`, `current_school_year_id`, `medical_conditions`, `allergies`, `special_needs`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(241, 310, '2025001', '202500000001', 'New', 'Enrolled', 'Angelo', 'Cruz', 'Santos', NULL, 'Male', '2021-03-15', 'Manila', 'Catholic', '123 Main Street, Manila', '123 Main Street, Manila', 1, 13, NULL, 'Juan Cruz', '09171234567', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 15:52:36'),
(242, 311, '2025002', '202500000002', 'New', 'Enrolled', 'Sofia', 'Reyes', 'Garcia', NULL, 'Female', '2021-07-22', 'Quezon City', 'Catholic', '456 Oak Avenue, Quezon City', '456 Oak Avenue, Quezon City', 2, 14, NULL, 'Jose Reyes', '09182345678', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 11:06:49'),
(243, 312, '2025003', '202500000003', 'New', 'Enrolled', 'Gabriel', 'Santos', 'Lopez', NULL, 'Male', '2021-11-08', 'Pasig City', 'Catholic', '789 Pine Street, Pasig City', '789 Pine Street, Pasig City', 3, 15, NULL, 'Antonio Santos', '09193456789', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 11:06:47'),
(244, 313, '2025004', '202500000004', 'New', 'Enrolled', 'Isabella', 'Gonzales', 'Martinez', NULL, 'Female', '2021-05-30', 'Makati City', 'Catholic', '321 Elm Road, Makati City', '321 Elm Road, Makati City', 4, 16, NULL, 'Pedro Gonzales', '09204567890', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 10:56:15'),
(245, 314, '2025005', '202500000005', 'New', 'Enrolled', 'Miguel', 'Hernandez', 'Rivera', NULL, 'Male', '2021-09-12', 'Taguig City', 'Catholic', '654 Birch Lane, Taguig City', '654 Birch Lane, Taguig City', 5, 17, NULL, 'Miguel Hernandez', '09215678901', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 11:06:45'),
(246, 315, '2025006', '202500000006', 'New', 'Enrolled', 'Sophia', 'Lopez', 'Torres', NULL, 'Female', '2021-01-25', 'Paranaque City', 'Catholic', '987 Cedar Drive, Paranaque City', '987 Cedar Drive, Paranaque City', 6, 18, NULL, 'Carlos Lopez', '09226789012', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 11:06:43'),
(247, 316, '2025007', '202500000007', 'New', 'Enrolled', 'Lorenzo', 'Martinez', 'Flores', NULL, 'Male', '2021-04-18', 'Las Pinas City', 'Catholic', '147 Maple Court, Las Pinas City', '147 Maple Court, Las Pinas City', 7, 19, NULL, 'Roberto Martinez', '09237890123', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 11:06:40'),
(248, 317, '2025008', '202500000008', 'New', 'Enrolled', 'Camila', 'Torres', 'Ramos', NULL, 'Female', '2021-12-03', 'Muntinlupa City', 'Catholic', '258 Walnut Street, Muntinlupa City', '258 Walnut Street, Muntinlupa City', 8, 20, NULL, 'Fernando Torres', '09248901234', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 11:06:37'),
(249, 318, '2025009', '202500000009', 'New', 'Enrolled', 'Sebastian', 'Flores', 'Morales', NULL, 'Male', '2021-08-27', 'Marikina City', 'Catholic', '369 Aspen Avenue, Marikina City', '369 Aspen Avenue, Marikina City', 9, 21, NULL, 'Manuel Flores', '09259012345', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 10:54:38'),
(250, 319, '2025010', '202500000010', 'New', 'Enrolled', 'Valentina', 'Ramos', 'Castro', NULL, 'Female', '2021-06-14', 'Pasay City', 'Catholic', '741 Spruce Road, Pasay City', '741 Spruce Road, Pasay City', 10, 22, NULL, 'Ricardo Ramos', '09260123456', 'Father', 1, 6, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:40:18', '2025-07-30 11:07:02'),
(361, 421, '2025011', '202500000011', 'New', 'Enrolled', 'Mateo', 'Morales', 'Mendoza', NULL, 'Male', '2020-03-10', 'Caloocan City', 'Catholic', '852 Hickory Lane, Caloocan City', '852 Hickory Lane, Caloocan City', 13, 25, NULL, 'Eduardo Morales', '09271234567', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(362, 422, '2025012', '202500000012', 'New', 'Enrolled', 'Emma', 'Castro', 'Jimenez', NULL, 'Female', '2020-07-15', 'Malabon City', 'Catholic', '963 Poplar Street, Malabon City', '963 Poplar Street, Malabon City', 14, 26, NULL, 'Alejandro Castro', '09282345678', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(363, 423, '2025013', '202500000013', 'New', 'Enrolled', 'Diego', 'Cruz', 'Santos', NULL, 'Male', '2020-11-20', 'Navotas City', 'Catholic', '159 Willow Road, Navotas City', '159 Willow Road, Navotas City', 15, 27, NULL, 'Daniel Dela Rosa', '09301234567', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(364, 424, '2025014', '202500000014', 'New', 'Enrolled', 'Mia', 'Reyes', 'Garcia', NULL, 'Female', '2020-05-25', 'Valenzuela City', 'Catholic', '753 Chestnut Ave, Valenzuela City', '753 Chestnut Ave, Valenzuela City', 16, 28, NULL, 'Gabriel Bautista', '09312345678', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(365, 425, '2025015', '202500000015', 'New', 'Enrolled', 'Lucas', 'Santos', 'Lopez', NULL, 'Male', '2020-09-30', 'San Juan City', 'Catholic', '486 Sycamore Dr, San Juan City', '486 Sycamore Dr, San Juan City', 17, 29, NULL, 'Rafael Navarro', '09323456789', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(366, 426, '2025016', '202500000016', 'New', 'Enrolled', 'Zoe', 'Gonzales', 'Martinez', NULL, 'Female', '2020-01-12', 'Mandaluyong City', 'Catholic', '357 Magnolia St, Mandaluyong City', '357 Magnolia St, Mandaluyong City', 18, 30, NULL, 'Samuel Valdez', '09334567890', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(367, 427, '2025017', '202500000017', 'New', 'Enrolled', 'Ethan', 'Hernandez', 'Rivera', NULL, 'Male', '2020-04-08', 'Pateros', 'Catholic', '624 Dogwood Lane, Pateros', '624 Dogwood Lane, Pateros', 19, 31, NULL, 'Benjamin Aguilar', '09345678901', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(368, 428, '2025018', '202500000018', 'New', 'Enrolled', 'Aria', 'Lopez', 'Torres', NULL, 'Female', '2020-12-18', 'Marikina City', 'Catholic', '791 Redwood Road, Marikina City', '791 Redwood Road, Marikina City', 20, 32, NULL, 'Nicolas Velasco', '09356789012', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(369, 429, '2025019', '202500000019', 'New', 'Enrolled', 'Noah', 'Martinez', 'Flores', NULL, 'Male', '2020-08-22', 'Antipolo City', 'Catholic', '135 Sequoia Circle, Antipolo City', '135 Sequoia Circle, Antipolo City', 21, 33, NULL, 'Adrian Castillo', '09367890123', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(370, 430, '2025020', '202500000020', 'New', 'Enrolled', 'Luna', 'Torres', 'Ramos', NULL, 'Female', '2020-06-05', 'Cainta, Rizal', 'Catholic', '802 Bamboo Street, Cainta, Rizal', '802 Bamboo Street, Cainta, Rizal', 22, 34, NULL, 'Victor Medina', '09378901234', 'Father', 2, 7, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(371, 431, '2025021', '202500000021', 'New', 'Enrolled', 'Alexander', 'Dela Rosa', 'Villanueva', NULL, 'Male', '2019-02-15', 'Manila', 'Catholic', '123 Narra Street, Manila', '123 Narra Street, Manila', 23, 35, NULL, 'Francisco Campos', '09390123456', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(372, 432, '2025022', '202500000022', 'New', 'Enrolled', 'Victoria', 'Bautista', 'Salazar', NULL, 'Female', '2019-06-20', 'Quezon City', 'Catholic', '456 Molave Avenue, Quezon City', '456 Molave Avenue, Quezon City', 24, 36, NULL, 'Diego Ortega', '09401234567', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(373, 433, '2025023', '202500000023', 'New', 'Enrolled', 'Leonardo', 'Navarro', 'Cordero', NULL, 'Male', '2019-10-25', 'Pasig City', 'Catholic', '789 Mahogany Road, Pasig City', '789 Mahogany Road, Pasig City', 25, 37, NULL, 'Jorge Lozano', '09412345678', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(374, 434, '2025024', '202500000024', 'New', 'Enrolled', 'Angelica', 'Valdez', 'Gutierrez', NULL, 'Female', '2019-04-30', 'Makati City', 'Catholic', '321 Banyan Street, Makati City', '321 Banyan Street, Makati City', 26, 38, NULL, 'Raul Espinoza', '09423456789', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(375, 435, '2025025', '202500000025', 'New', 'Enrolled', 'Nicolas', 'Aguilar', 'Herrera', NULL, 'Male', '2019-08-14', 'Taguig City', 'Catholic', '654 Ipil Lane, Taguig City', '654 Ipil Lane, Taguig City', 27, 39, NULL, 'Sergio Molina', '09434567890', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(376, 436, '2025026', '202500000026', 'New', 'Enrolled', 'Catalina', 'Velasco', 'Romero', NULL, 'Female', '2019-12-18', 'Paranaque City', 'Catholic', '987 Acacia Drive, Paranaque City', '987 Acacia Drive, Paranaque City', 28, 40, NULL, 'Arturo Pacheco', '09445678901', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(377, 437, '2025027', '202500000027', 'New', 'Enrolled', 'Maximiliano', 'Castillo', 'Vargas', NULL, 'Male', '2019-03-22', 'Las Pinas City', 'Catholic', '147 Bamboo Court, Las Pinas City', '147 Bamboo Court, Las Pinas City', 29, 41, NULL, 'Ignacio Figueroa', '09456789012', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(378, 438, '2025028', '202500000028', 'New', 'Enrolled', 'Francesca', 'Medina', 'Peña', NULL, 'Female', '2019-07-26', 'Muntinlupa City', 'Catholic', '258 Talisay Street, Muntinlupa City', '258 Talisay Street, Muntinlupa City', 30, 42, NULL, 'Emilio Contreras', '09467890123', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(379, 439, '2025029', '202500000029', 'New', 'Enrolled', 'Santiago', 'Guerrero', 'Dominguez', NULL, 'Male', '2019-11-30', 'Marikina City', 'Catholic', '369 Bougainvillea Avenue, Marikina City', '369 Bougainvillea Avenue, Marikina City', 31, 43, NULL, 'Rodrigo Maldonado', '09478901234', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(380, 440, '2025030', '202500000030', 'New', 'Enrolled', 'Esperanza', 'Campos', 'Silva', NULL, 'Female', '2019-05-14', 'Pasay City', 'Catholic', '741 Sampaguita Road, Pasay City', '741 Sampaguita Road, Pasay City', 32, 44, NULL, 'Armando Acosta', '09489012345', 'Father', 3, 8, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(381, 441, '2025031', '202500000031', 'New', 'Enrolled', 'Emmanuel', 'Ortega', 'Ponce', NULL, 'Male', '2018-01-20', 'Manila', 'Catholic', '456 Rose Street, Manila', '456 Rose Street, Manila', 35, 71, NULL, 'Diego Ortega', '09401234567', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(382, 442, '2025032', '202500000032', 'New', 'Enrolled', 'Gabriella', 'Lozano', 'Cervantes', NULL, 'Female', '2018-05-25', 'Quezon City', 'Catholic', '789 Lily Avenue, Quezon City', '789 Lily Avenue, Quezon City', 36, 72, NULL, 'Jorge Lozano', '09412345678', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(383, 443, '2025033', '202500000033', 'New', 'Enrolled', 'Rafael', 'Espinoza', 'Delgado', NULL, 'Male', '2018-09-30', 'Pasig City', 'Catholic', '321 Tulip Road, Pasig City', '321 Tulip Road, Pasig City', 37, 73, NULL, 'Raul Espinoza', '09423456789', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(384, 444, '2025034', '202500000034', 'New', 'Enrolled', 'Valeria', 'Molina', 'Fuentes', NULL, 'Female', '2018-03-14', 'Makati City', 'Catholic', '654 Sunflower Lane, Makati City', '654 Sunflower Lane, Makati City', 38, 74, NULL, 'Sergio Molina', '09434567890', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(385, 445, '2025035', '202500000035', 'New', 'Enrolled', 'Rodrigo', 'Pacheco', 'Sandoval', NULL, 'Male', '2018-07-18', 'Taguig City', 'Catholic', '987 Orchid Drive, Taguig City', '987 Orchid Drive, Taguig City', 39, 75, NULL, 'Arturo Pacheco', '09445678901', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(386, 446, '2025036', '202500000036', 'New', 'Enrolled', 'Ximena', 'Figueroa', 'Escobar', NULL, 'Female', '2018-11-22', 'Paranaque City', 'Catholic', '147 Jasmine Court, Paranaque City', '147 Jasmine Court, Paranaque City', 40, 76, NULL, 'Ignacio Figueroa', '09456789012', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(387, 447, '2025037', '202500000037', 'New', 'Enrolled', 'Thiago', 'Contreras', 'Galvan', NULL, 'Male', '2018-04-26', 'Las Pinas City', 'Catholic', '258 Dahlia Street, Las Pinas City', '258 Dahlia Street, Las Pinas City', 41, 77, NULL, 'Emilio Contreras', '09467890123', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(388, 448, '2025038', '202500000038', 'New', 'Enrolled', 'Amelia', 'Maldonado', 'Barrera', NULL, 'Female', '2018-08-30', 'Muntinlupa City', 'Catholic', '369 Hibiscus Avenue, Muntinlupa City', '369 Hibiscus Avenue, Muntinlupa City', 42, 78, NULL, 'Rodrigo Maldonado', '09478901234', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(389, 449, '2025039', '202500000039', 'New', 'Enrolled', 'Fernando', 'Acosta', 'Cabrera', NULL, 'Male', '2018-12-14', 'Marikina City', 'Catholic', '741 Carnation Road, Marikina City', '741 Carnation Road, Marikina City', 43, 79, NULL, 'Armando Acosta', '09489012345', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(390, 450, '2025040', '202500000040', 'New', 'Enrolled', 'Dulce', 'Vega', 'Cortez', NULL, 'Female', '2018-06-18', 'Pasay City', 'Catholic', '852 Petunia Lane, Pasay City', '852 Petunia Lane, Pasay City', 44, 80, NULL, 'Esteban Vega', '09490123456', 'Father', 4, 9, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(391, 451, '2025041', '202500000041', 'New', 'Enrolled', 'Joaquin', 'Rojas', 'Moreno', NULL, 'Male', '2017-02-10', 'Manila', 'Catholic', '123 Violet Street, Manila', '123 Violet Street, Manila', 45, 81, NULL, 'Cesar Rojas', '09501234567', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(392, 452, '2025042', '202500000042', 'New', 'Enrolled', 'Renata', 'Perez', 'Soto', NULL, 'Female', '2017-06-15', 'Quezon City', 'Catholic', '456 Marigold Avenue, Quezon City', '456 Marigold Avenue, Quezon City', 46, 82, NULL, 'Ruben Perez', '09512345678', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(393, 453, '2025043', '202500000043', 'New', 'Enrolled', 'Adriano', 'Carrasco', 'Restrepo', NULL, 'Male', '2017-10-20', 'Pasig City', 'Catholic', '789 Chrysanthemum Road, Pasig City', '789 Chrysanthemum Road, Pasig City', 47, 83, NULL, 'Andres Carrasco', '09523456789', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(394, 454, '2025044', '202500000044', 'New', 'Enrolled', 'Esperanza', 'Zuniga', 'Osorio', NULL, 'Female', '2017-04-25', 'Makati City', 'Catholic', '321 Lavender Lane, Makati City', '321 Lavender Lane, Makati City', 48, 84, NULL, 'Mauricio Zuniga', '09534567890', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(395, 455, '2025045', '202500000045', 'New', 'Enrolled', 'Maximos', 'Varela', 'Calderon', NULL, 'Male', '2017-08-30', 'Taguig City', 'Catholic', '654 Daffodil Drive, Taguig City', '654 Daffodil Drive, Taguig City', 49, 85, NULL, 'Hector Varela', '09545678901', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(396, 456, '2025046', '202500000046', 'New', 'Enrolled', 'Soledad', 'Hidalgo', 'Espejo', NULL, 'Female', '2017-12-14', 'Paranaque City', 'Catholic', '987 Freesia Court, Paranaque City', '987 Freesia Court, Paranaque City', 50, 86, NULL, 'Oscar Hidalgo', '09556789012', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(397, 457, '2025047', '202500000047', 'New', 'Enrolled', 'Patricio', 'Pantoja', 'Uribe', NULL, 'Male', '2017-03-18', 'Las Pinas City', 'Catholic', '147 Poppy Street, Las Pinas City', '147 Poppy Street, Las Pinas City', 51, 87, NULL, 'Marco Pantoja', '09567890123', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(398, 458, '2025048', '202500000048', 'New', 'Enrolled', 'Milagros', 'Quintero', 'Benitez', NULL, 'Female', '2017-07-22', 'Muntinlupa City', 'Catholic', '258 Zinnia Avenue, Muntinlupa City', '258 Zinnia Avenue, Muntinlupa City', 52, 88, NULL, 'Luis Quintero', '09578901234', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(399, 459, '2025049', '202500000049', 'New', 'Enrolled', 'Cristobal', 'Camacho', 'Palacios', NULL, 'Male', '2017-11-26', 'Marikina City', 'Catholic', '369 Iris Road, Marikina City', '369 Iris Road, Marikina City', 53, 89, NULL, 'Pablo Camacho', '09589012345', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(400, 460, '2025050', '202500000050', 'New', 'Enrolled', 'Remedios', 'Cardenas', 'Montoya', NULL, 'Female', '2017-05-30', 'Pasay City', 'Catholic', '741 Begonia Lane, Pasay City', '741 Begonia Lane, Pasay City', 54, 90, NULL, 'Enrique Cardenas', '09590123456', 'Father', 5, 10, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(401, 461, '2025051', '202500000051', 'New', 'Enrolled', 'Agustin', 'Solis', 'Villalobos', NULL, 'Male', '2016-01-15', 'Manila', 'Catholic', '852 Camellia Street, Manila', '852 Camellia Street, Manila', 55, 91, NULL, 'Javier Solis', '09601234567', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(402, 462, '2025052', '202500000052', 'New', 'Enrolled', 'Concepcion', 'Ibarra', 'Aranda', NULL, 'Female', '2016-05-20', 'Quezon City', 'Catholic', '963 Azalea Avenue, Quezon City', '963 Azalea Avenue, Quezon City', 56, 92, NULL, 'Mario Ibarra', '09612345678', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(403, 463, '2025053', '202500000053', 'New', 'Enrolled', 'Teodoro', 'Meza', 'Coronado', NULL, 'Male', '2016-09-25', 'Pasig City', 'Catholic', '159 Geranium Road, Pasig City', '159 Geranium Road, Pasig City', 57, 93, NULL, 'Felipe Meza', '09623456789', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(404, 464, '2025054', '202500000054', 'New', 'Enrolled', 'Pilar', 'Cano', 'Avalos', NULL, 'Female', '2016-03-30', 'Makati City', 'Catholic', '753 Snapdragon Lane, Makati City', '753 Snapdragon Lane, Makati City', 58, 94, NULL, 'Alvaro Cano', '09634567890', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(405, 465, '2025055', '202500000055', 'New', 'Enrolled', 'Leopoldo', 'Ochoa', 'Bermudez', NULL, 'Male', '2016-07-14', 'Taguig City', 'Catholic', '486 Peony Drive, Taguig City', '486 Peony Drive, Taguig City', 59, 95, NULL, 'Guillermo Ochoa', '09645678901', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(406, 466, '2025056', '202500000056', 'New', 'Enrolled', 'Clementina', 'Paredes', 'Casillas', NULL, 'Female', '2016-11-18', 'Paranaque City', 'Catholic', '357 Pansy Court, Paranaque City', '357 Pansy Court, Paranaque City', 60, 96, NULL, 'Jaime Paredes', '09656789012', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(407, 467, '2025057', '202500000057', 'New', 'Enrolled', 'Evaristo', 'Cruz', 'Santos', NULL, 'Male', '2016-04-22', 'Las Pinas City', 'Catholic', '624 Cosmos Street, Las Pinas City', '624 Cosmos Street, Las Pinas City', 1, 25, NULL, 'Juan Cruz', '09171234567', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(408, 468, '2025058', '202500000058', 'New', 'Enrolled', 'Rosario', 'Reyes', 'Garcia', NULL, 'Female', '2016-08-26', 'Muntinlupa City', 'Catholic', '791 Gladiolus Avenue, Muntinlupa City', '791 Gladiolus Avenue, Muntinlupa City', 2, 26, NULL, 'Jose Reyes', '09182345678', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(409, 469, '2025059', '202500000059', 'New', 'Enrolled', 'Florencio', 'Santos', 'Lopez', NULL, 'Male', '2016-12-30', 'Marikina City', 'Catholic', '135 Aster Road, Marikina City', '135 Aster Road, Marikina City', 3, 27, NULL, 'Antonio Santos', '09193456789', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(410, 470, '2025060', '202500000060', 'New', 'Enrolled', 'Amparo', 'Gonzales', 'Martinez', NULL, 'Female', '2016-06-14', 'Pasay City', 'Catholic', '802 Carnation Lane, Pasay City', '802 Carnation Lane, Pasay City', 4, 28, NULL, 'Pedro Gonzales', '09204567890', 'Father', 6, 11, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(411, 471, '2025061', '202500000061', 'New', 'Enrolled', 'Anastacio', 'Hernandez', 'Rivera', NULL, 'Male', '2015-02-20', 'Manila', 'Catholic', '147 Lotus Street, Manila', '147 Lotus Street, Manila', 5, 29, NULL, 'Miguel Hernandez', '09215678901', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(412, 472, '2025062', '202500000062', 'New', 'Enrolled', 'Trinidad', 'Lopez', 'Torres', NULL, 'Female', '2015-06-25', 'Quezon City', 'Catholic', '258 Tulip Avenue, Quezon City', '258 Tulip Avenue, Quezon City', 6, 30, NULL, 'Carlos Lopez', '09226789012', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(413, 473, '2025063', '202500000063', 'New', 'Enrolled', 'Ireneo', 'Martinez', 'Flores', NULL, 'Male', '2015-10-30', 'Pasig City', 'Catholic', '369 Jasmine Road, Pasig City', '369 Jasmine Road, Pasig City', 7, 31, NULL, 'Roberto Martinez', '09237890123', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(414, 474, '2025064', '202500000064', 'New', 'Enrolled', 'Asuncion', 'Torres', 'Ramos', NULL, 'Female', '2015-04-14', 'Makati City', 'Catholic', '741 Magnolia Lane, Makati City', '741 Magnolia Lane, Makati City', 8, 32, NULL, 'Fernando Torres', '09248901234', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(415, 475, '2025065', '202500000065', 'New', 'Enrolled', 'Crisanto', 'Flores', 'Morales', NULL, 'Male', '2015-08-18', 'Taguig City', 'Catholic', '852 Gardenia Drive, Taguig City', '852 Gardenia Drive, Taguig City', 9, 33, NULL, 'Manuel Flores', '09259012345', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(416, 476, '2025066', '202500000066', 'New', 'Enrolled', 'Encarnacion', 'Ramos', 'Castro', NULL, 'Female', '2015-12-22', 'Paranaque City', 'Catholic', '963 Sunflower Court, Paranaque City', '963 Sunflower Court, Paranaque City', 10, 34, NULL, 'Ricardo Ramos', '09260123456', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(417, 477, '2025067', '202500000067', 'New', 'Enrolled', 'Placido', 'Morales', 'Mendoza', NULL, 'Male', '2015-03-26', 'Las Pinas City', 'Catholic', '159 Lily Street, Las Pinas City', '159 Lily Street, Las Pinas City', 11, 35, NULL, 'Eduardo Morales', '09271234567', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(418, 478, '2025068', '202500000068', 'New', 'Enrolled', 'Natividad', 'Castro', 'Jimenez', NULL, 'Female', '2015-07-30', 'Muntinlupa City', 'Catholic', '753 Rose Avenue, Muntinlupa City', '753 Rose Avenue, Muntinlupa City', 12, 36, NULL, 'Alejandro Castro', '09282345678', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(419, 479, '2025069', '202500000069', 'New', 'Enrolled', 'Basilio', 'Dela Rosa', 'Villanueva', NULL, 'Male', '2015-11-14', 'Marikina City', 'Catholic', '486 Daisy Road, Marikina City', '486 Daisy Road, Marikina City', 25, 61, NULL, 'Daniel Dela Rosa', '09301234567', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(420, 480, '2025070', '202500000070', 'New', 'Enrolled', 'Purificacion', 'Bautista', 'Salazar', NULL, 'Female', '2015-05-18', 'Pasay City', 'Catholic', '357 Orchid Lane, Pasay City', '357 Orchid Lane, Pasay City', 26, 62, NULL, 'Gabriel Bautista', '09312345678', 'Father', 7, 12, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(421, 481, '2025071', '202500000071', 'New', 'Enrolled', 'Fortunato', 'Navarro', 'Cordero', NULL, 'Male', '2014-01-10', 'Manila', 'Catholic', '624 Hibiscus Street, Manila', '624 Hibiscus Street, Manila', 27, 63, NULL, 'Rafael Navarro', '09323456789', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(422, 482, '2025072', '202500000072', 'New', 'Enrolled', 'Guadalupe', 'Valdez', 'Gutierrez', NULL, 'Female', '2014-05-15', 'Quezon City', 'Catholic', '791 Dahlia Avenue, Quezon City', '791 Dahlia Avenue, Quezon City', 28, 64, NULL, 'Samuel Valdez', '09334567890', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(423, 483, '2025073', '202500000073', 'New', 'Enrolled', 'Leoncio', 'Aguilar', 'Herrera', NULL, 'Male', '2014-09-20', 'Pasig City', 'Catholic', '135 Petunia Road, Pasig City', '135 Petunia Road, Pasig City', 29, 65, NULL, 'Benjamin Aguilar', '09345678901', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(424, 484, '2025074', '202500000074', 'New', 'Enrolled', 'Inmaculada', 'Velasco', 'Romero', NULL, 'Female', '2014-03-25', 'Makati City', 'Catholic', '802 Violet Lane, Makati City', '802 Violet Lane, Makati City', 30, 66, NULL, 'Nicolas Velasco', '09356789012', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(425, 485, '2025075', '202500000075', 'New', 'Enrolled', 'Teodulo', 'Castillo', 'Vargas', NULL, 'Male', '2014-07-30', 'Taguig City', 'Catholic', '147 Marigold Drive, Taguig City', '147 Marigold Drive, Taguig City', 31, 67, NULL, 'Adrian Castillo', '09367890123', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(426, 486, '2025076', '202500000076', 'New', 'Enrolled', 'Presentacion', 'Medina', 'Peña', NULL, 'Female', '2014-11-14', 'Paranaque City', 'Catholic', '258 Poppy Court, Paranaque City', '258 Poppy Court, Paranaque City', 32, 68, NULL, 'Victor Medina', '09378901234', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(427, 487, '2025077', '202500000077', 'New', 'Enrolled', 'Ambrosio', 'Guerrero', 'Dominguez', NULL, 'Male', '2014-04-18', 'Las Pinas City', 'Catholic', '369 Azalea Street, Las Pinas City', '369 Azalea Street, Las Pinas City', 33, 69, NULL, 'Alejandro Guerrero', '09389012345', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(428, 488, '2025078', '202500000078', 'New', 'Enrolled', 'Visitacion', 'Campos', 'Silva', NULL, 'Female', '2014-08-22', 'Muntinlupa City', 'Catholic', '741 Chrysanthemum Avenue, Muntinlupa City', '741 Chrysanthemum Avenue, Muntinlupa City', 34, 70, NULL, 'Francisco Campos', '09390123456', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(429, 489, '2025079', '202500000079', 'New', 'Enrolled', 'Primitivo', 'Ortega', 'Ponce', NULL, 'Male', '2014-12-26', 'Marikina City', 'Catholic', '852 Freesia Road, Marikina City', '852 Freesia Road, Marikina City', 35, 71, NULL, 'Diego Ortega', '09401234567', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(430, 490, '2025080', '202500000080', 'New', 'Enrolled', 'Salvacion', 'Lozano', 'Cervantes', NULL, 'Female', '2014-06-30', 'Pasay City', 'Catholic', '963 Cosmos Lane, Pasay City', '963 Cosmos Lane, Pasay City', 36, 72, NULL, 'Jorge Lozano', '09412345678', 'Father', 8, 13, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(431, 491, '2025081', '202500000081', 'New', 'Enrolled', 'Domingo', 'Espinoza', 'Delgado', NULL, 'Male', '2013-02-14', 'Manila', 'Catholic', '159 Begonia Street, Manila', '159 Begonia Street, Manila', 37, 73, NULL, 'Raul Espinoza', '09423456789', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(432, 492, '2025082', '202500000082', 'New', 'Enrolled', 'Socorro', 'Molina', 'Fuentes', NULL, 'Female', '2013-06-18', 'Quezon City', 'Catholic', '753 Camellia Avenue, Quezon City', '753 Camellia Avenue, Quezon City', 38, 74, NULL, 'Sergio Molina', '09434567890', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(433, 493, '2025083', '202500000083', 'New', 'Enrolled', 'Eusebio', 'Pacheco', 'Sandoval', NULL, 'Male', '2013-10-22', 'Pasig City', 'Catholic', '486 Gladiolus Road, Pasig City', '486 Gladiolus Road, Pasig City', 39, 75, NULL, 'Arturo Pacheco', '09445678901', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(434, 494, '2025084', '202500000084', 'New', 'Enrolled', 'Milagros', 'Figueroa', 'Escobar', NULL, 'Female', '2013-04-26', 'Makati City', 'Catholic', '357 Snapdragon Lane, Makati City', '357 Snapdragon Lane, Makati City', 40, 76, NULL, 'Ignacio Figueroa', '09456789012', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(435, 495, '2025085', '202500000085', 'New', 'Enrolled', 'Prudencio', 'Contreras', 'Galvan', NULL, 'Male', '2013-08-30', 'Taguig City', 'Catholic', '624 Carnation Drive, Taguig City', '624 Carnation Drive, Taguig City', 41, 77, NULL, 'Emilio Contreras', '09467890123', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(436, 496, '2025086', '202500000086', 'New', 'Enrolled', 'Conception', 'Maldonado', 'Barrera', NULL, 'Female', '2013-12-14', 'Paranaque City', 'Catholic', '791 Peony Court, Paranaque City', '791 Peony Court, Paranaque City', 42, 78, NULL, 'Rodrigo Maldonado', '09478901234', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(437, 497, '2025087', '202500000087', 'New', 'Enrolled', 'Eufemio', 'Acosta', 'Cabrera', NULL, 'Male', '2013-03-18', 'Las Pinas City', 'Catholic', '135 Geranium Street, Las Pinas City', '135 Geranium Street, Las Pinas City', 43, 79, NULL, 'Armando Acosta', '09489012345', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(438, 498, '2025088', '202500000088', 'New', 'Enrolled', 'Remedios', 'Vega', 'Cortez', NULL, 'Female', '2013-07-22', 'Muntinlupa City', 'Catholic', '802 Aster Avenue, Muntinlupa City', '802 Aster Avenue, Muntinlupa City', 44, 80, NULL, 'Esteban Vega', '09490123456', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(439, 499, '2025089', '202500000089', 'New', 'Enrolled', 'Clementino', 'Rojas', 'Moreno', NULL, 'Male', '2013-11-26', 'Marikina City', 'Catholic', '147 Pansy Road, Marikina City', '147 Pansy Road, Marikina City', 45, 81, NULL, 'Cesar Rojas', '09501234567', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(440, 500, '2025090', '202500000090', 'New', 'Enrolled', 'Soledad', 'Perez', 'Soto', NULL, 'Female', '2013-05-30', 'Pasay City', 'Catholic', '258 Daffodil Lane, Pasay City', '258 Daffodil Lane, Pasay City', 46, 82, NULL, 'Ruben Perez', '09512345678', 'Father', 9, 14, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(441, 501, '2025091', '202500000091', 'New', 'Enrolled', 'Genaro', 'Carrasco', 'Restrepo', NULL, 'Male', '2012-01-12', 'Manila', 'Catholic', '369 Tulip Street, Manila', '369 Tulip Street, Manila', 47, 83, NULL, 'Andres Carrasco', '09523456789', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(442, 502, '2025092', '202500000092', 'New', 'Enrolled', 'Filomena', 'Zuniga', 'Osorio', NULL, 'Female', '2012-05-16', 'Quezon City', 'Catholic', '741 Iris Avenue, Quezon City', '741 Iris Avenue, Quezon City', 48, 84, NULL, 'Mauricio Zuniga', '09534567890', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(443, 503, '2025093', '202500000093', 'New', 'Enrolled', 'Honorio', 'Varela', 'Calderon', NULL, 'Male', '2012-09-20', 'Pasig City', 'Catholic', '852 Lavender Road, Pasig City', '852 Lavender Road, Pasig City', 49, 85, NULL, 'Hector Varela', '09545678901', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(444, 504, '2025094', '202500000094', 'New', 'Enrolled', 'Perpetua', 'Hidalgo', 'Espejo', NULL, 'Female', '2012-03-24', 'Makati City', 'Catholic', '963 Jasmine Lane, Makati City', '963 Jasmine Lane, Makati City', 50, 86, NULL, 'Oscar Hidalgo', '09556789012', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(445, 505, '2025095', '202500000095', 'New', 'Enrolled', 'Isidoro', 'Pantoja', 'Uribe', NULL, 'Male', '2012-07-28', 'Taguig City', 'Catholic', '159 Zinnia Drive, Taguig City', '159 Zinnia Drive, Taguig City', 51, 87, NULL, 'Marco Pantoja', '09567890123', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(446, 506, '2025096', '202500000096', 'New', 'Enrolled', 'Consolacion', 'Quintero', 'Benitez', NULL, 'Female', '2012-11-12', 'Paranaque City', 'Catholic', '753 Cosmos Court, Paranaque City', '753 Cosmos Court, Paranaque City', 52, 88, NULL, 'Luis Quintero', '09578901234', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(447, 507, '2025097', '202500000097', 'New', 'Enrolled', 'Macario', 'Camacho', 'Palacios', NULL, 'Male', '2012-04-16', 'Las Pinas City', 'Catholic', '486 Lily Street, Las Pinas City', '486 Lily Street, Las Pinas City', 53, 89, NULL, 'Pablo Camacho', '09589012345', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(448, 508, '2025098', '202500000098', 'New', 'Enrolled', 'Caridad', 'Cardenas', 'Montoya', NULL, 'Female', '2012-08-20', 'Muntinlupa City', 'Catholic', '357 Sunflower Avenue, Muntinlupa City', '357 Sunflower Avenue, Muntinlupa City', 54, 90, NULL, 'Enrique Cardenas', '09590123456', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(449, 509, '2025099', '202500000099', 'New', 'Enrolled', 'Nemesio', 'Solis', 'Villalobos', NULL, 'Male', '2012-12-24', 'Marikina City', 'Catholic', '624 Magnolia Road, Marikina City', '624 Magnolia Road, Marikina City', 55, 91, NULL, 'Javier Solis', '09601234567', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(450, 510, '2025100', '202500000100', 'New', 'Enrolled', 'Epifania', 'Ibarra', 'Aranda', NULL, 'Female', '2012-06-28', 'Pasay City', 'Catholic', '791 Orchid Lane, Pasay City', '791 Orchid Lane, Pasay City', 56, 92, NULL, 'Mario Ibarra', '09612345678', 'Father', 10, 15, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(451, 511, '2025101', '202500000101', 'New', 'Enrolled', 'Evaristo', 'Meza', 'Coronado', NULL, 'Male', '2011-02-08', 'Manila', 'Catholic', '135 Dahlia Street, Manila', '135 Dahlia Street, Manila', 57, 93, NULL, 'Felipe Meza', '09623456789', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(452, 512, '2025102', '202500000102', 'New', 'Enrolled', 'Felicitas', 'Cano', 'Avalos', NULL, 'Female', '2011-06-12', 'Quezon City', 'Catholic', '802 Petunia Avenue, Quezon City', '802 Petunia Avenue, Quezon City', 58, 94, NULL, 'Alvaro Cano', '09634567890', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(453, 513, '2025103', '202500000103', 'New', 'Enrolled', 'Melquiades', 'Ochoa', 'Bermudez', NULL, 'Male', '2011-10-16', 'Pasig City', 'Catholic', '147 Hibiscus Road, Pasig City', '147 Hibiscus Road, Pasig City', 59, 95, NULL, 'Guillermo Ochoa', '09645678901', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(454, 514, '2025104', '202500000104', 'New', 'Enrolled', 'Candelaria', 'Paredes', 'Casillas', NULL, 'Female', '2011-04-20', 'Makati City', 'Catholic', '258 Freesia Lane, Makati City', '258 Freesia Lane, Makati City', 60, 96, NULL, 'Jaime Paredes', '09656789012', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(455, 515, '2025105', '202500000105', 'New', 'Enrolled', 'Apolinario', 'Cruz', 'Santos', NULL, 'Male', '2011-08-24', 'Taguig City', 'Catholic', '369 Marigold Drive, Taguig City', '369 Marigold Drive, Taguig City', 1, 25, NULL, 'Juan Cruz', '09171234567', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(456, 516, '2025106', '202500000106', 'New', 'Enrolled', 'Encarnacion', 'Reyes', 'Garcia', NULL, 'Female', '2011-12-28', 'Paranaque City', 'Catholic', '741 Gladiolus Court, Paranaque City', '741 Gladiolus Court, Paranaque City', 2, 26, NULL, 'Jose Reyes', '09182345678', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(457, 517, '2025107', '202500000107', 'New', 'Enrolled', 'Bartolome', 'Santos', 'Lopez', NULL, 'Male', '2011-05-12', 'Las Pinas City', 'Catholic', '852 Chrysanthemum Street, Las Pinas City', '852 Chrysanthemum Street, Las Pinas City', 3, 27, NULL, 'Antonio Santos', '09193456789', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(458, 518, '2025108', '202500000108', 'New', 'Enrolled', 'Esperanza', 'Gonzales', 'Martinez', NULL, 'Female', '2011-09-16', 'Muntinlupa City', 'Catholic', '963 Azalea Avenue, Muntinlupa City', '963 Azalea Avenue, Muntinlupa City', 4, 28, NULL, 'Pedro Gonzales', '09204567890', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(459, 519, '2025109', '202500000109', 'New', 'Enrolled', 'Sinforoso', 'Hernandez', 'Rivera', NULL, 'Male', '2011-01-20', 'Marikina City', 'Catholic', '159 Begonia Road, Marikina City', '159 Begonia Road, Marikina City', 5, 29, NULL, 'Miguel Hernandez', '09215678901', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(460, 520, '2025110', '202500000110', 'New', 'Enrolled', 'Remedios', 'Lopez', 'Torres', NULL, 'Female', '2011-07-24', 'Pasay City', 'Catholic', '486 Camellia Lane, Pasay City', '486 Camellia Lane, Pasay City', 6, 30, NULL, 'Carlos Lopez', '09226789012', 'Father', 11, 16, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(461, 521, '2025111', '202500000111', 'New', 'Enrolled', 'Floriano', 'Martinez', 'Flores', NULL, 'Male', '2010-01-05', 'Manila', 'Catholic', '753 Violet Street, Manila', '753 Violet Street, Manila', 7, 31, NULL, 'Roberto Martinez', '09237890123', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(462, 522, '2025112', '202500000112', 'New', 'Enrolled', 'Catalina', 'Torres', 'Ramos', NULL, 'Female', '2010-05-10', 'Quezon City', 'Catholic', '624 Daffodil Avenue, Quezon City', '624 Daffodil Avenue, Quezon City', 8, 32, NULL, 'Fernando Torres', '09248901234', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(463, 523, '2025113', '202500000113', 'New', 'Enrolled', 'Nicanor', 'Flores', 'Morales', NULL, 'Male', '2010-09-14', 'Pasig City', 'Catholic', '791 Lavender Road, Pasig City', '791 Lavender Road, Pasig City', 9, 33, NULL, 'Manuel Flores', '09259012345', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(464, 524, '2025114', '202500000114', 'New', 'Enrolled', 'Dionisia', 'Ramos', 'Castro', NULL, 'Female', '2010-03-18', 'Makati City', 'Catholic', '135 Jasmine Lane, Makati City', '135 Jasmine Lane, Makati City', 10, 34, NULL, 'Ricardo Ramos', '09260123456', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(465, 525, '2025115', '202500000115', 'New', 'Enrolled', 'Policarpo', 'Morales', 'Mendoza', NULL, 'Male', '2010-07-22', 'Taguig City', 'Catholic', '802 Poppy Drive, Taguig City', '802 Poppy Drive, Taguig City', 11, 35, NULL, 'Eduardo Morales', '09271234567', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(466, 526, '2025116', '202500000116', 'New', 'Enrolled', 'Consolacion', 'Castro', 'Jimenez', NULL, 'Female', '2010-11-26', 'Paranaque City', 'Catholic', '147 Geranium Court, Paranaque City', '147 Geranium Court, Paranaque City', 12, 36, NULL, 'Alejandro Castro', '09282345678', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(467, 527, '2025117', '202500000117', 'New', 'Enrolled', 'Anastasio', 'Dela Rosa', 'Villanueva', NULL, 'Male', '2010-04-30', 'Las Pinas City', 'Catholic', '258 Snapdragon Street, Las Pinas City', '258 Snapdragon Street, Las Pinas City', 25, 61, NULL, 'Daniel Dela Rosa', '09301234567', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(468, 528, '2025118', '202500000118', 'New', 'Enrolled', 'Feliciana', 'Bautista', 'Salazar', NULL, 'Female', '2010-08-14', 'Muntinlupa City', 'Catholic', '369 Carnation Avenue, Muntinlupa City', '369 Carnation Avenue, Muntinlupa City', 26, 62, NULL, 'Gabriel Bautista', '09312345678', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(469, 529, '2025119', '202500000119', 'New', 'Enrolled', 'Hermenegildo', 'Navarro', 'Cordero', NULL, 'Male', '2010-12-18', 'Marikina City', 'Catholic', '741 Peony Road, Marikina City', '741 Peony Road, Marikina City', 27, 63, NULL, 'Rafael Navarro', '09323456789', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20'),
(470, 530, '2025120', '202500000120', 'New', 'Enrolled', 'Esperanza', 'Valdez', 'Gutierrez', NULL, 'Female', '2010-06-22', 'Pasay City', 'Catholic', '852 Cosmos Lane, Pasay City', '852 Cosmos Lane, Pasay City', 28, 64, NULL, 'Samuel Valdez', '09334567890', 'Father', 12, 17, 2, NULL, NULL, NULL, 1, 1, '2025-07-30 10:50:35', '2025-07-30 11:23:20');

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
  `enrollment_status` enum('Enrolled','Dropped','Graduated','Transferred') DEFAULT 'Enrolled',
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
(193, 470, 2, 12, 17, '2025-08-15', 'Enrolled', NULL, NULL, NULL, NULL, 1, '2025-07-30 10:50:35', '2025-07-30 11:38:48');

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
(393, 'Mother', 'Lidia', 'Paredes', 'Restrepo', '1987-12-22', 'Sales Lady', 'Catholic', '09656789013', 'lidia.paredes@email.com', '2025-07-30 10:50:35', '2025-07-30 10:50:35');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(200) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'PLAY-N', 'Play-based Learning', 'Play-based learning activities for nursery', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(2, 'LANG-N', 'Language Development', 'Basic language and communication skills', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(3, 'NUM-N', 'Number Concepts', 'Basic number recognition and counting', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(4, 'ART-N', 'Arts and Crafts', 'Creative arts and fine motor skills development', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(5, 'PLAY-K', 'Play-based Learning', 'Play-based learning activities for kindergarten', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(6, 'LANG-K', 'Language Arts', 'Reading readiness and basic literacy', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(7, 'MATH-K', 'Mathematics', 'Basic mathematics concepts', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(8, 'SCI-K', 'Science', 'Basic science exploration', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(9, 'ENG-ELEM', 'English', 'English Language Arts', 0, '2025-07-19 07:49:43', '2025-07-20 15:27:02'),
(10, 'FIL-ELEM', 'Filipino', 'Filipino Language', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(11, 'MATH-ELEM', 'Mathematics', 'Elementary Mathematics', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(12, 'SCI-ELEM', 'Science', 'Elementary Science', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(13, 'AP-ELEM', 'Araling Panlipunan', 'Social Studies', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(14, 'ESP-ELEM', 'Edukasyon sa Pagpapakatao', 'Values Education', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(15, 'MAPEH-ELEM', 'MAPEH', 'Music, Arts, Physical Education, and Health', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(16, 'TLE-ELEM', 'Technology and Livelihood Education', 'Basic TLE skills', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(17, 'ENG-JHS', 'English', 'English Language and Literature', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(18, 'FIL-JHS', 'Filipino', 'Filipino Language and Literature', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(19, 'MATH-JHS', 'Mathematics', 'Secondary Mathematics', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(20, 'SCI-JHS', 'Science', 'Integrated Science', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(21, 'AP-JHS', 'Araling Panlipunan', 'Social Studies', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(22, 'ESP-JHS', 'Edukasyon sa Pagpapakatao', 'Values Education', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(23, 'MAPEH-JHS', 'MAPEH', 'Music, Arts, Physical Education, and Health', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(24, 'TLE-JHS', 'Technology and Livelihood Education', 'Technology and Livelihood Education', 1, '2025-07-19 07:49:43', '2025-07-19 07:49:43');

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
(25, 1, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(26, 2, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(27, 3, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(28, 4, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(29, 5, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(30, 6, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(31, 7, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(32, 8, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(33, 9, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(34, 10, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(35, 11, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22'),
(36, 12, 2, 0.00, 0.00, 0.00, 1, 2, '2025-07-30 02:24:22', '2025-07-30 02:24:22');

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
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `profile_picture`, `password`, `role_id`, `is_active`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NULL, NULL, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(2, 'finance', 'finance@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1, NULL, NULL, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(3, 'registrar', 'registrar@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 1, NULL, NULL, '2025-07-19 07:49:43', '2025-07-19 07:49:43'),
(4, 'principal', 'principal@gtba.edu.ph', 'uploads/profiles/profile_4_1753876058.png', '$2y$10$QlKucTMa9Z2toEb7T6oHTeM2CrVUOy4xK6mdJmANZvGRkVIx.v0tO', 2, 1, NULL, NULL, '2025-07-19 07:49:43', '2025-07-30 11:47:38'),
(5, 'student', 'student@gtba.edu.ph', NULL, '$2y$10$1h4T2556r/2pf9J3X2CozeSAUAeaXYLE5yZKcKUPO0u74Cykxr8Hi', 6, 1, NULL, NULL, '2025-07-19 11:21:02', '2025-07-19 11:21:52'),
(6, 'teacher', 'teacher@gtba.edu.ph', NULL, '$2y$10$I18cCqCUc9o7hwSwhBEyqOk5lhqR1anTCl89tdP4trekNUu3Cc/I.', 5, 1, NULL, NULL, '2025-07-19 11:24:50', '2025-07-19 11:24:50'),
(11, 'john.doe', 'john.doe@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-22 07:50:18'),
(12, 'jane.smith', 'jane.smith@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(13, 'mike.johnson', 'mike.johnson@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(14, 'sarah.wilson', 'sarah.wilson@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(310, '2025001', '2025001@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(311, '2025002', '2025002@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(312, '2025003', '2025003@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(313, '2025004', '2025004@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(314, '2025005', '2025005@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(315, '2025006', '2025006@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(316, '2025007', '2025007@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(317, '2025008', '2025008@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(318, '2025009', '2025009@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(319, '2025010', '2025010@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:40:18', '2025-07-30 10:40:18'),
(430, '2025011', '2025011@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(431, '2025012', '2025012@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(432, '2025013', '2025013@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(433, '2025014', '2025014@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(434, '2025015', '2025015@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(435, '2025016', '2025016@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(436, '2025017', '2025017@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(437, '2025018', '2025018@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(438, '2025019', '2025019@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(439, '2025020', '2025020@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(440, '2025021', '2025021@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(441, '2025022', '2025022@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(442, '2025023', '2025023@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(443, '2025024', '2025024@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(444, '2025025', '2025025@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(445, '2025026', '2025026@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(446, '2025027', '2025027@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(447, '2025028', '2025028@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(448, '2025029', '2025029@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(449, '2025030', '2025030@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(450, '2025031', '2025031@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(451, '2025032', '2025032@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(452, '2025033', '2025033@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(453, '2025034', '2025034@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(454, '2025035', '2025035@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(455, '2025036', '2025036@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(456, '2025037', '2025037@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(457, '2025038', '2025038@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(458, '2025039', '2025039@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(459, '2025040', '2025040@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(460, '2025041', '2025041@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(461, '2025042', '2025042@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(462, '2025043', '2025043@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(463, '2025044', '2025044@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(464, '2025045', '2025045@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(465, '2025046', '2025046@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(466, '2025047', '2025047@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(467, '2025048', '2025048@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(468, '2025049', '2025049@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(469, '2025050', '2025050@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(470, '2025051', '2025051@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(471, '2025052', '2025052@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(472, '2025053', '2025053@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(473, '2025054', '2025054@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(474, '2025055', '2025055@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(475, '2025056', '2025056@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(476, '2025057', '2025057@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(477, '2025058', '2025058@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(478, '2025059', '2025059@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(479, '2025060', '2025060@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(480, '2025061', '2025061@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(481, '2025062', '2025062@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(482, '2025063', '2025063@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(483, '2025064', '2025064@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(484, '2025065', '2025065@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(485, '2025066', '2025066@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(486, '2025067', '2025067@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(487, '2025068', '2025068@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(488, '2025069', '2025069@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(489, '2025070', '2025070@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(490, '2025071', '2025071@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(491, '2025072', '2025072@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(492, '2025073', '2025073@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(493, '2025074', '2025074@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(494, '2025075', '2025075@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(495, '2025076', '2025076@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(496, '2025077', '2025077@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(497, '2025078', '2025078@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(498, '2025079', '2025079@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(499, '2025080', '2025080@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(500, '2025081', '2025081@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(501, '2025082', '2025082@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(502, '2025083', '2025083@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(503, '2025084', '2025084@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(504, '2025085', '2025085@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(505, '2025086', '2025086@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(506, '2025087', '2025087@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(507, '2025088', '2025088@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(508, '2025089', '2025089@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(509, '2025090', '2025090@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(510, '2025091', '2025091@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(511, '2025092', '2025092@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(512, '2025093', '2025093@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(513, '2025094', '2025094@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(514, '2025095', '2025095@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(515, '2025096', '2025096@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(516, '2025097', '2025097@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(517, '2025098', '2025098@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(518, '2025099', '2025099@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(519, '2025100', '2025100@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(520, '2025101', '2025101@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(521, '2025102', '2025102@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(522, '2025103', '2025103@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(523, '2025104', '2025104@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(524, '2025105', '2025105@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(525, '2025106', '2025106@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(526, '2025107', '2025107@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(527, '2025108', '2025108@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(528, '2025109', '2025109@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(529, '2025110', '2025110@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(530, '2025111', '2025111@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(531, '2025112', '2025112@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(532, '2025113', '2025113@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(533, '2025114', '2025114@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(534, '2025115', '2025115@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(535, '2025116', '2025116@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(536, '2025117', '2025117@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(537, '2025118', '2025118@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(538, '2025119', '2025119@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35'),
(539, '2025120', '2025120@student.gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, NULL, '2025-07-30 10:50:35', '2025-07-30 10:50:35');

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
  ADD KEY `created_by` (`created_by`);

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
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `created_by` (`created_by`);

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
  ADD KEY `role_id` (`role_id`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `curriculum`
--
ALTER TABLE `curriculum`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

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
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `section_teachers`
--
ALTER TABLE `section_teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=471;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_guardians`
--
ALTER TABLE `student_guardians`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=394;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=540;

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
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

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
