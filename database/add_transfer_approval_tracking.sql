-- Add transfer approval tracking columns to students table
-- Migration: Add transfer approval fields
-- Date: December 7, 2025

ALTER TABLE `students` 
ADD COLUMN `transfer_approved_by` INT NULL AFTER `transfer_reason`,
ADD COLUMN `transfer_approved_at` TIMESTAMP NULL AFTER `transfer_approved_by`;

-- Add foreign key constraint for the approver
ALTER TABLE `students`
ADD CONSTRAINT `fk_transfer_approved_by`
  FOREIGN KEY (`transfer_approved_by`)
  REFERENCES `users` (`id`)
  ON DELETE SET NULL
  ON UPDATE CASCADE;

-- Add index for better query performance
ALTER TABLE `students` 
ADD INDEX `idx_transfer_approved_by` (`transfer_approved_by`);
