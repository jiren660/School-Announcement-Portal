#!/bin/bash
# ====================================================================
# Automated VM Database Setup Script for School Announcement System
# Run this inside your VirtualBox VM (Kali Linux / Ubuntu):
#   sudo ./database/setup_vm.sh
# ====================================================================

set -e

echo "=========================================================="
echo " [1/4] Installing & Starting MariaDB Server... "
echo "=========================================================="
apt-get update -y
apt-get install -y mariadb-server
systemctl start mariadb
systemctl enable mariadb

echo "=========================================================="
echo " [2/4] Configuring MariaDB for Remote Connections... "
echo "=========================================================="
CNF_FILES=(
    "/etc/mysql/mariadb.conf.d/50-server.cnf"
    "/etc/mysql/my.cnf"
    "/etc/mysql/mariadb.cnf"
)

for file in "${CNF_FILES[@]}"; do
    if [ -f "$file" ]; then
        sed -i 's/bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/' "$file"
        echo "Updated bind-address to 0.0.0.0 in: $file"
    fi
done

systemctl restart mariadb

echo "=========================================================="
echo " [3/4] Creating Database, Remote User, and Tables... "
echo "=========================================================="
mysql -u root << "EOF"
CREATE DATABASE IF NOT EXISTS `school_portal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Remote user for Windows host
CREATE USER IF NOT EXISTS 'school_user'@'%' IDENTIFIED BY 'SchoolPass123!';
GRANT ALL PRIVILEGES ON `school_portal`.* TO 'school_user'@'%';

-- Root remote access for safety
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'rootpassword';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;

USE `school_portal`;

-- Users Table
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

-- Ensure scope columns exist if users table already existed previously
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `assigned_college` VARCHAR(150) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `assigned_office` VARCHAR(150) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `assigned_department` VARCHAR(150) DEFAULT NULL;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `badge_color` VARCHAR(50) DEFAULT 'primary',
    `icon` VARCHAR(50) DEFAULT 'megaphone',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements Table
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
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcement Images Table (Multi-photo attachments)
CREATE TABLE IF NOT EXISTS `announcement_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `announcement_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offices Table (for Office Administrators)
CREATE TABLE IF NOT EXISTS `offices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `head` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colleges Table (for College Administrators)
CREATE TABLE IF NOT EXISTS `colleges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `dean` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments Table (for Department Administrators)
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `college` VARCHAR(150) NOT NULL,
    `chairperson` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table (for Audit Trail)
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default categories
INSERT INTO `categories` (`id`, `name`, `slug`, `badge_color`, `icon`) VALUES
(1, 'Urgent Alert', 'urgent-alert', 'danger', 'alert-triangle'),
(2, 'Academic Affairs', 'academic-affairs', 'primary', 'book-open'),
(3, 'Campus Events', 'campus-events', 'success', 'calendar'),
(4, 'Student Life', 'student-life', 'warning', 'users'),
(5, 'Admissions & Enrollment', 'admissions', 'info', 'file-text')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Seed default account:
-- Super Admin: admin@iscp.edu.ph / AdminPass123!
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `assigned_college`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@iscp.edu.ph', '$2y$12$DmTKME.6btq/BEVsRst01.Hb6PuA7oekF3bdYjzxIXZCgeMGWwUOy', 'super_admin', NULL, 'active', NOW())
ON DUPLICATE KEY UPDATE `password_hash`=VALUES(`password_hash`), `role`='super_admin';

EOF

echo "=========================================================="
echo " [4/4] SUCCESS! Database is ready for connection."
echo "=========================================================="
echo ""
echo "=== VM IP ADDRESSES DETECTED ==="
hostname -I
echo "----------------------------------------------------------"
echo "Database Name: school_portal"
echo "Username:      school_user"
echo "Password:      SchoolPass123!"
echo "Port:          3306"
echo "----------------------------------------------------------"
echo "INSTRUCTIONS FOR WINDOWS HOST:"
echo "1. Hanapin sa taas ang IP na nagsisimula sa 192.168.56.xxx"
echo "2. Buksan ang '.env' sa Windows at ilagay:"
echo "   DB_HOST=192.168.56.xxx"
echo "=========================================================="
