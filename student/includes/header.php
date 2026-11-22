<?php
/**
 * Student Layout Header
 * Top navigation with official university logo, notifications, search, and student profile.
 */

if (!defined('STUDENT_INIT')) {
    define('STUDENT_INIT', true);
}
require_once __DIR__ . '/auth.php';

$currentStudent = current_student();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? h($page_title) . ' - ' : '' ?>Student Portal | ISCP</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        .sidebar-logo-img {
            max-width: 38px;
            max-height: 38px;
            height: auto;
            object-fit: contain;
            border-radius: 50%;
            display: block;
        }
        .brand-logo-wrap {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
            padding: 3px;
            overflow: hidden;
        }
        .student-announcement-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
        }
        .student-announcement-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }
        .student-announcement-card.is-urgent {
            border-left: 5px solid #ef4444;
        }
        .student-announcement-card.is-pinned {
            border-top: 3px solid #0284c7;
        }
    </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
    <!-- Student Navigation Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="page-header-info">
                    <h1 class="page-title"><?= isset($page_title) ? h($page_title) : 'Student Portal' ?></h1>
                    <?php if (isset($page_subtitle)): ?>
                        <p class="page-subtitle"><?= h($page_subtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="topbar-right">
                <a href="saved.php" class="btn btn-outline btn-sm desktop-only" title="View Bookmarked Announcements">
                    <i class="fa-solid fa-bookmark" style="color: #f59e0b;"></i>
                    <span>Saved Posts</span>
                </a>

                <a href="notifications.php" class="topbar-notification-btn" title="View Student Alerts">
                    <i class="fa-regular fa-bell"></i>
                </a>

                <div class="user-profile-badge">
                    <div class="avatar-circle" style="background: linear-gradient(135deg, #0284c7, #38bdf8);">
                        S
                    </div>
                    <div class="user-meta desktop-only">
                        <span class="user-name"><?= h($currentStudent['name']) ?></span>
                        <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 0.68rem; font-weight: 700;">Student</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content">
