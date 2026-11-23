-- ====================================================================
-- School Announcement Portal - Database Migration Script
-- Safe one-line and idempotent schema alterations
-- ====================================================================

USE `school_portal`;

-- 1. Users table scope columns
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `assigned_college` VARCHAR(150) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `assigned_office` VARCHAR(150) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `assigned_department` VARCHAR(150) DEFAULT NULL;

-- 2. Offices Table
CREATE TABLE IF NOT EXISTS `offices` (`id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(50) NOT NULL UNIQUE, `name` VARCHAR(150) NOT NULL, `email` VARCHAR(150) DEFAULT NULL, `head` VARCHAR(100) DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Colleges Table
CREATE TABLE IF NOT EXISTS `colleges` (`id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(50) NOT NULL UNIQUE, `name` VARCHAR(150) NOT NULL, `dean` VARCHAR(100) DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (`id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(50) NOT NULL UNIQUE, `name` VARCHAR(150) NOT NULL, `college` VARCHAR(150) NOT NULL, `chairperson` VARCHAR(100) DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT DEFAULT NULL, `action` VARCHAR(100) NOT NULL, `description` TEXT DEFAULT NULL, `ip_address` VARCHAR(45) DEFAULT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. Announcements status extension for drafts and approval workflow
ALTER TABLE `announcements` MODIFY COLUMN `status` ENUM('draft', 'pending_approval', 'pending', 'approved', 'published', 'rejected') NOT NULL DEFAULT 'draft';
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `publishing_unit` VARCHAR(150) DEFAULT NULL;

-- 7. Users status extension for pending approvals and student ID
ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active', 'inactive', 'pending_approval', 'rejected') NOT NULL DEFAULT 'active';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `student_id` VARCHAR(50) DEFAULT NULL;

-- 8. Organization Logos
ALTER TABLE `offices` ADD COLUMN IF NOT EXISTS `logo_path` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `colleges` ADD COLUMN IF NOT EXISTS `logo_path` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `departments` ADD COLUMN IF NOT EXISTS `logo_path` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `departments` ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active';
