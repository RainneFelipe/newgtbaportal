-- Add privacy policy acceptance tracking to users table
-- Migration: Add privacy policy acceptance
-- Date: December 7, 2025

ALTER TABLE `users` 
ADD COLUMN `privacy_policy_accepted` TINYINT(1) DEFAULT 0 AFTER `archived_at`,
ADD COLUMN `privacy_policy_accepted_at` TIMESTAMP NULL AFTER `privacy_policy_accepted`,
ADD COLUMN `privacy_policy_ip_address` VARCHAR(45) NULL AFTER `privacy_policy_accepted_at`;

-- Add index for privacy policy queries
ALTER TABLE `users` 
ADD INDEX `idx_privacy_policy_accepted` (`privacy_policy_accepted`);
