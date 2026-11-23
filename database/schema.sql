-- ====================================================================
-- School Announcement System - Database Schema
-- Modules: Admin & Announcer Portal
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `school_portal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_portal`;

-- 1. Users Table (Roles: super_admin, university_admin, office_admin, college_admin, department_admin)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'college_admin',
    `assigned_college` VARCHAR(150) DEFAULT NULL,
    `assigned_office` VARCHAR(150) DEFAULT NULL,
    `assigned_department` VARCHAR(150) DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Categories Table (For categorizing announcements)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `badge_color` VARCHAR(50) DEFAULT 'primary',
    `icon` VARCHAR(50) DEFAULT 'megaphone',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) DEFAULT NULL,
    `summary` VARCHAR(350) DEFAULT NULL,
    `content` TEXT NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `category_id` INT DEFAULT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `created_by` INT NOT NULL,
    `author_name` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT DEFAULT NULL,
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `target_audience` ENUM('All', 'Students', 'Faculty', 'Parents') DEFAULT 'All',
    `is_urgent` TINYINT(1) DEFAULT 0,
    `is_pinned` TINYINT(1) DEFAULT 0,
    `views_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_announcements_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_announcements_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Announcement Images Table (Multi-photo Instagram carousel support)
CREATE TABLE IF NOT EXISTS `announcement_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `announcement_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_images_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Offices Table (for Office Administrators)
CREATE TABLE IF NOT EXISTS `offices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `head` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Colleges Table (for College Administrators)
CREATE TABLE IF NOT EXISTS `colleges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `dean` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Departments Table (for Department Administrators)
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `college` VARCHAR(150) NOT NULL,
    `chairperson` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Activity Logs Table (for Audit Trail)
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- Initial Seed Data
-- ====================================================================

-- Default Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `badge_color`, `icon`) VALUES
(1, 'Urgent Alert', 'urgent-alert', 'danger', 'alert-triangle'),
(2, 'Academic Affairs', 'academic-affairs', 'primary', 'book-open'),
(3, 'Campus Events', 'campus-events', 'success', 'calendar'),
(4, 'Student Life', 'student-life', 'warning', 'users'),
(5, 'Admissions & Enrollment', 'admissions', 'info', 'file-text')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Default User:
-- Super Admin: admin@iscp.edu.ph / AdminPass123!
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@iscp.edu.ph', '$2y$12$DmTKME.6btq/BEVsRst01.Hb6PuA7oekF3bdYjzxIXZCgeMGWwUOy', 'super_admin', 'active', NOW())
ON DUPLICATE KEY UPDATE `password_hash`=VALUES(`password_hash`), `role`='super_admin', `name`=VALUES(`name`);


