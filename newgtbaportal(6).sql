-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 30, 2025 at 09:56 AM
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
(10, 'Enrollment for 1st Semester AY 2025-2026 Now Open', 'Enrollment for the First Semester of Academic Year 2025–2026 is now open. Please log in to your student portal and complete your enrollment requirements before August 15, 2025.', 'General', 'Students', NULL, 'Normal', 1, 0, '2025-07-30', '2025-08-15', 0, 0, 4, '2025-07-30 09:22:30', '2025-07-30 09:22:30'),
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
(1, '2024-2025', '2024-2025', '2024-08-15', '2025-05-30', 0, 1, 1, '2025-07-19 07:49:43', '2025-07-20 11:41:17'),
(2, '2025-2026', '2025-2026', '2025-08-15', '2026-05-30', 1, 0, 1, '2025-07-19 07:49:43', '2025-07-30 02:13:16'),
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
(6, 'Nursery - Daisy', 1, 2, 'Room 103', 'Nursery section for 3-4 year olds', 0, 1, 3, '2025-07-22 10:22:54', '2025-07-22 11:07:39'),
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
(4, 'principal', 'principal@gtba.edu.ph', 'uploads/profiles/profile_4_1753431279.png', '$2y$10$QlKucTMa9Z2toEb7T6oHTeM2CrVUOy4xK6mdJmANZvGRkVIx.v0tO', 2, 1, NULL, NULL, '2025-07-19 07:49:43', '2025-07-25 10:45:04'),
(5, 'student', 'student@gtba.edu.ph', NULL, '$2y$10$1h4T2556r/2pf9J3X2CozeSAUAeaXYLE5yZKcKUPO0u74Cykxr8Hi', 6, 1, NULL, NULL, '2025-07-19 11:21:02', '2025-07-19 11:21:52'),
(6, 'teacher', 'teacher@gtba.edu.ph', NULL, '$2y$10$I18cCqCUc9o7hwSwhBEyqOk5lhqR1anTCl89tdP4trekNUu3Cc/I.', 5, 1, NULL, NULL, '2025-07-19 11:24:50', '2025-07-19 11:24:50'),
(11, 'john.doe', 'john.doe@gtba.edu.ph', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-22 07:50:18'),
(12, 'jane.smith', 'jane.smith@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(13, 'mike.johnson', 'mike.johnson@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33'),
(14, 'sarah.wilson', 'sarah.wilson@gtba.edu.ph', NULL, '$2y$10$mK9vF5s3PIu67h0iGo/BMucwSntUsL.VZTUgQntpgeyFUi8MV7ibO', 5, 1, NULL, NULL, '2025-07-20 09:19:33', '2025-07-20 09:19:33');

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_guardians`
--
ALTER TABLE `student_guardians`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

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
