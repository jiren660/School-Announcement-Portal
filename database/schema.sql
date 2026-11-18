-- ====================================================================
-- School Announcement System - Database Schema
-- Modules: Admin & Announcer Portal
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `school_portal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_portal`;

-- 1. Users Table (Roles: super_admin, university_admin, office_admin, college_admin)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'college_admin',
    `assigned_college` VARCHAR(150) DEFAULT NULL,
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

-- Default Users:
-- Admin: admin@iscp.edu.ph / AdminPass123!
-- Announcer 1: announcer@iscp.edu.ph / AnnouncerPass123!
-- Announcer 2: maria.santos@iscp.edu.ph / AnnouncerPass123!
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@iscp.edu.ph', '$2y$12$DmTKME.6btq/BEVsRst01.Hb6PuA7oekF3bdYjzxIXZCgeMGWwUOy', 'admin', 'active', NOW()),
(2, 'John Doe (Announcer)', 'announcer@iscp.edu.ph', '$2y$12$.zP3xWtpc9WzC.iK1se4Y.Jwq0Mr3bQDBGdIIuWXONk9qRpWWjNBe', 'announcer', 'active', NOW()),
(3, 'Prof. Maria Santos', 'maria.santos@iscp.edu.ph', '$2y$12$.zP3xWtpc9WzC.iK1se4Y.Jwq0Mr3bQDBGdIIuWXONk9qRpWWjNBe', 'announcer', 'active', NOW())
ON DUPLICATE KEY UPDATE `password_hash`=VALUES(`password_hash`), `name`=VALUES(`name`);

-- Sample Announcements
INSERT INTO `announcements` (`id`, `title`, `summary`, `content`, `category`, `category_id`, `created_by`, `status`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `target_audience`, `is_urgent`, `is_pinned`, `views_count`, `created_at`) VALUES
(
    1,
    'Suspension of Classes due to Inclement Weather',
    'Please be advised that all onsite and online classes are officially suspended today due to heavy rains and localized flooding.',
    'In accordance with the advisory issued by the City Disaster Risk Reduction and Management Office (CDRRMO), classes at all levels across all campuses are suspended today.\n\nStudents and faculty members are advised to stay indoors and monitor official communication channels for further updates. Stay safe everyone!',
    'Urgent Alert',
    1,
    2,
    'approved',
    NULL,
    1,
    NOW() - INTERVAL 2 DAY,
    'All',
    1,
    1,
    428,
    NOW() - INTERVAL 2 DAY
),
(
    2,
    'Midterm Examination Schedule for 1st Semester A.Y. 2026-2027',
    'The official exam timetable, permit validation guidelines, and classroom assignments are now available for all departments.',
    'The Office of the University Registrar announces that Midterm Examinations will commence starting next Monday.\n\nKey Reminders:\n1. Ensure your Examination Permits are validated before Friday.\n2. Bring your valid Student ID card to each testing room.\n3. Strictly observe the university dress code and code of conduct during exam week.\n\nConsult your respective department chairs for specialized laboratory exam schedules.',
    'Academic Affairs',
    2,
    2,
    'approved',
    NULL,
    1,
    NOW() - INTERVAL 1 DAY,
    'Students',
    0,
    1,
    315,
    NOW() - INTERVAL 1 DAY
),
(
    3,
    'Annual University Foundation Week & Sports Fest 2026',
    'Join us for a week full of sports competitions, academic debates, cultural dances, and the much-awaited University Battle of the Bands!',
    'Get ready for our Grand Foundation Celebration! The Supreme Student Council together with the Athletics Office invites all colleges to submit their team rosters for Basketball, Volleyball, Chess, Badminton, and Esports.\n\nRegistration booths are open at the Student Activity Center until Wednesday, 5:00 PM.',
    'Campus Events',
    3,
    3,
    'pending',
    NULL,
    NULL,
    NULL,
    'All',
    0,
    0,
    0,
    NOW() - INTERVAL 4 HOUR
),
(
    4,
    'Call for Submissions: University Literary Folio 2026',
    'The official university publications office is now accepting poetry, short stories, essays, and digital artwork.',
    'We welcome submissions from all bona fide students. Theme for this year: "Voices of Resilience and Hope".\n\nSubmission Guidelines:\n- Maximum 2,000 words for prose.\n- High-resolution (300 DPI) for illustrations/photos.\n- Submit before the end of the month.',
    'Student Life',
    4,
    2,
    'pending',
    NULL,
    NULL,
    NULL,
    'Students',
    0,
    0,
    0,
    NOW() - INTERVAL 2 HOUR
),
(
    5,
    'Unofficial Off-Campus Party Announcement',
    'Private party happening this Friday night outside school premises.',
    'Party with free drinks and live DJ. Not an official school sanctioned event.',
    'Student Life',
    4,
    3,
    'rejected',
    'The announcement refers to an unapproved and unofficial commercial event that violates the university student handbook guidelines regarding off-campus safety and alcohol.',
    1,
    NOW() - INTERVAL 1 DAY,
    'Students',
    0,
    0,
    0,
    NOW() - INTERVAL 3 DAY
)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);
