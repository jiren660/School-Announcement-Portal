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
