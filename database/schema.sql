-- ====================================================================
-- School Announcement Portal Database Schema
-- Designed for Remote MySQL / MariaDB (VirtualBox VM)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `school_portal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_portal`;

-- 1. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `badge_color` VARCHAR(50) DEFAULT 'primary',
    `icon` VARCHAR(50) DEFAULT 'megaphone',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `summary` VARCHAR(350) DEFAULT NULL,
    `content` TEXT NOT NULL,
    `category_id` INT NOT NULL,
    `target_audience` ENUM('All', 'Students', 'Faculty', 'Parents') DEFAULT 'All',
    `is_urgent` TINYINT(1) DEFAULT 0,
    `is_pinned` TINYINT(1) DEFAULT 0,
    `author_name` VARCHAR(100) DEFAULT 'School Administration',
    `status` ENUM('published', 'draft', 'archived') DEFAULT 'published',
    `views_count` INT DEFAULT 0,
    `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Admin / Faculty Users Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `role` ENUM('Super Admin', 'Faculty', 'Moderator') DEFAULT 'Super Admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====================================================================
-- Seed Initial Data
-- ====================================================================

-- Default Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `badge_color`, `icon`) VALUES
(1, 'Urgent Alert', 'urgent-alert', 'danger', 'alert-triangle'),
(2, 'Academic Affairs', 'academic-affairs', 'primary', 'book-open'),
(3, 'Campus Events', 'campus-events', 'success', 'calendar'),
(4, 'Student Life', 'student-life', 'warning', 'users'),
(5, 'Admissions & Enrollment', 'admissions', 'info', 'file-text')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Default Admin User (username: admin | password: password123)
-- Hash generated using password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO `admins` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`) VALUES
(1, 'admin', '$2y$10$wT0o3q6bI1LzV7jG7rM2xe4kZ9mYqG/qXwT4nUeJ5dM8dY2eH1n0y', 'Head Administrator', 'admin@school.edu.ph', 'Super Admin')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

-- Default Announcements
INSERT INTO `announcements` (`title`, `slug`, `summary`, `content`, `category_id`, `target_audience`, `is_urgent`, `is_pinned`, `author_name`, `status`, `views_count`, `published_at`) VALUES
(
    'Suspension of Classes due to Inclement Weather',
    'suspension-of-classes-inclement-weather',
    'Please be advised that all onsite and online classes are officially suspended today due to heavy rains and localized flooding.',
    'In accordance with the advisory issued by the City Disaster Risk Reduction and Management Office (CDRRMO), classes at all levels across all campuses are suspended today.\n\nStudents and faculty members are advised to stay indoors and monitor official communication channels for further updates. Stay safe everyone!',
    1,
    'All',
    1,
    1,
    'Office of the President',
    'published',
    428,
    NOW()
),
(
    'Midterm Examination Schedule for 1st Semester A.Y. 2026-2027',
    'midterm-examination-schedule-ay-2026-2027',
    'The official exam timetable, permit validation guidelines, and classroom assignments are now available for all departments.',
    'The Office of the University Registrar announces that Midterm Examinations will commence starting next Monday. \n\nKey Reminders:\n1. Ensure your Examination Permits are validated before Friday.\n2. Bring your valid Student ID card to each testing room.\n3. Strictly observe the university dress code and code of conduct during exam week.\n\nConsult your respective department chairs for specialized laboratory exam schedules.',
    2,
    'Students',
    0,
    1,
    'University Registrar',
    'published',
    315,
    NOW() - INTERVAL 1 DAY
),
(
    'Annual University Foundation Week & Sports Fest 2026',
    'annual-foundation-week-sports-fest-2026',
    'Join us for a week full of sports competitions, academic debates, cultural dances, and the much-awaited University Battle of the Bands!',
    'Get ready for our Grand Foundation Celebration! The Supreme Student Council together with the Athletics Office invites all colleges to submit their team rosters for Basketball, Volleyball, Chess, Badminton, and Esports.\n\nRegistration booths are open at the Student Activity Center until Wednesday, 5:00 PM.',
    3,
    'All',
    0,
    0,
    'Supreme Student Council',
    'published',
    194,
    NOW() - INTERVAL 2 DAY
),
(
    'Special Enrollment & Adding/Dropping of Subjects Deadline',
    'special-enrollment-adding-dropping-deadline',
    'Last call for students requesting section adjustments, subject overloads, and late enrollment processing.',
    'The final window for subject adjustment and late enrollment will close this Friday at 4:00 PM. No further extensions will be granted by the Academic Council. Please coordinate directly with your College Dean or Department Secretary.',
    5,
    'Students',
    0,
    0,
    'Academic Affairs Office',
    'published',
    120,
    NOW() - INTERVAL 3 DAY
);
