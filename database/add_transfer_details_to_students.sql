-- Add transfer details columns to students table
-- Migration: Add transfer tracking fields
-- Date: December 7, 2025

ALTER TABLE `students` 
ADD COLUMN `transferred_to_school` VARCHAR(255) NULL AFTER `enrollment_status`,
ADD COLUMN `transfer_date` DATE NULL AFTER `transferred_to_school`,
ADD COLUMN `transfer_reason` TEXT NULL AFTER `transfer_date`;

-- Add index for transfer date queries
ALTER TABLE `students` 
ADD INDEX `idx_transfer_date` (`transfer_date`);

-- Add index for enrollment status queries (if not exists)
ALTER TABLE `students` 
ADD INDEX `idx_enrollment_status` (`enrollment_status`);
