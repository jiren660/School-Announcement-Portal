<?php
/**
 * Shared Administrator Layout Header
 * Consistent University-Themed Top Navigation & Design System
 * 
 * Features:
 * - Official ISCP University Logo integration
 * - Portal name & breadcrumbs
 * - Real-time notification badge
 * - Profile menu with account details, role badge, settings, and logout
 * - Mobile responsive navigation triggers
 */
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);
$pendingCount = get_pending_count();
$currentScript = basename($_SERVER['PHP_SELF']);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? h($page_title) . ' - ' : '' ?>University Announcement Portal | ISCP</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Admin & Portal Stylesheet -->
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        /* Shared Header & Profile Menu Styles */
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
        .sidebar-scope-banner {
            padding: 0.65rem 1rem;
            margin: 0.75rem 1rem 0;
            background: rgba(2, 132, 199, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: var(--radius-md);
        }
        .sidebar-scope-banner .scope-label {
            font-size: 0.65rem;
            font-weight: 800;
            color: #38bdf8;
            letter-spacing: 0.06em;
            margin-bottom: 0.15rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .sidebar-scope-banner .scope-value {
            font-size: 0.8rem;
            color: #f1f5f9;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar-portal-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-right: 0.75rem;
        }
        .topbar-logo-img {
            max-height: 36px;
            max-width: 36px;
            object-fit: contain;
            display: block;
        }
        .topbar-notification-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid #e2e8f0;
        }
        .topbar-notification-btn:hover {
            background: #e0f2fe;
            color: #0284c7;
            border-color: #bae6fd;
        }
        .notification-indicator-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            background: #ef4444;
            color: #ffffff;
            border-radius: var(--radius-full);
            font-size: 0.65rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #ffffff;
        }
        .profile-menu-container {
            position: relative;
        }
        .profile-menu-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 260px;
            background: #ffffff;
            border-radius: var(--radius-md);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--card-border);
            padding: 0.75rem 0;
            z-index: 200;
            display: none;
            animation: fadeInDropdown 0.2s ease;
        }
        .profile-menu-dropdown.show {
            display: block;
        }
        @keyframes fadeInDropdown {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .profile-menu-header {
            padding: 0.5rem 1.25rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .profile-menu-header .name {
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-menu-header .email {
            font-size: 0.76rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.25rem;
            color: #334155;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .profile-menu-item:hover {
            background: #f8fafc;
            color: var(--primary);
        }
        .profile-menu-item.logout-item:hover {
            background: #fef2f2;
            color: #dc2626;
        }
    </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
    <!-- Role-Based Navigation Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Top Navigation Bar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
                <div class="page-header-info">
                    <h1 class="page-title"><?= isset($page_title) ? h($page_title) : 'Dashboard' ?></h1>
                    <?php if (isset($page_subtitle)): ?>
                        <p class="page-subtitle"><?= h($page_subtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="topbar-right">
                <!-- Notifications Button with Badge -->
                <a href="notifications.php" class="topbar-notification-btn" title="View Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($pendingCount > 0 && is_super_admin()): ?>
                        <span class="notification-indicator-badge"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- Public Bulletin Board Link -->
                <a href="../index.php" target="_blank" class="btn btn-outline btn-sm desktop-only" title="View Public Student Announcement Board">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>Public Bulletin</span>
                </a>

                <!-- User Profile & Dropdown Menu -->
                <div class="profile-menu-container">
                    <div class="user-profile-badge" id="profileBadgeTrigger" style="cursor: pointer;" title="Click to view account menu">
                        <div class="avatar-circle">
                            <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="user-meta desktop-only">
                            <span class="user-name"><?= h($currentUser['name']) ?></span>
                            <span class="badge badge-admin"><?= h(format_role_label($currentRole)) ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem; color: #94a3b8; margin-left: 0.2rem;"></i>
                    </div>

                    <div class="profile-menu-dropdown" id="profileMenuDropdown">
                        <div class="profile-menu-header">
                            <div class="name"><?= h($currentUser['name']) ?></div>
                            <div class="email"><?= h($currentUser['email']) ?></div>
                            <div style="margin-top: 0.35rem;">
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 0.7rem; font-weight: 700;">
                                    <?= h(format_role_label($currentRole)) ?>
                                </span>
                            </div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">
                                <i class="fa-solid fa-building-columns"></i> <?= h($assignedUnit) ?>
                            </div>
                        </div>
                        <a href="profile.php" class="profile-menu-item">
                            <i class="fa-solid fa-user-gear"></i> Account Settings
                        </a>
                        <a href="notifications.php" class="profile-menu-item">
                            <i class="fa-solid fa-bell"></i> Notifications
                        </a>
                        <?php if (is_super_admin()): ?>
                            <a href="settings.php" class="profile-menu-item">
                                <i class="fa-solid fa-sliders"></i> System Settings
                            </a>
                        <?php endif; ?>
                        <div style="height: 1px; background: #f1f5f9; margin: 0.35rem 0;"></div>
                        <a href="logout.php" onclick="if (window.openModal) { openModal('signOutModal'); return false; }" class="profile-menu-item logout-item">
                            <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Body Container -->
        <main class="admin-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible" id="flashAlert">
                    <div class="alert-icon">
                        <?php if ($flash['type'] === 'success'): ?>
                            <i class="fa-solid fa-circle-check"></i>
                        <?php elseif ($flash['type'] === 'danger'): ?>
                            <i class="fa-solid fa-circle-exclamation"></i>
                        <?php elseif ($flash['type'] === 'warning'): ?>
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-info"></i>
                        <?php endif; ?>
                    </div>
                    <div class="alert-text"><?= h($flash['message']) ?></div>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove();">&times;</button>
                </div>
            <?php endif; ?>

<script>
// Toggle Profile Menu Dropdown
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('profileBadgeTrigger');
    const dropdown = document.getElementById('profileMenuDropdown');
    if (trigger && dropdown) {
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    }
});
</script>
