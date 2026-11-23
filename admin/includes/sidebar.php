<?php
/**
 * Role-Based Dynamic Administrator Sidebar Navigation
 * Matches University Announcement Portal Planning Guide (PDF) specifications
 */
if (!defined('APP_INIT')) {
    exit;
}

$currentUser = current_user();
$role = $currentUser['role'] ?? 'super_admin';
$assignedUnit = get_user_assigned_unit($currentUser);
$pendingCount = get_pending_count();
$activeNav = $activeNav ?? basename($_SERVER['PHP_SELF']);
$currentQuery = $_SERVER['QUERY_STRING'] ?? '';
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Header with Official University Logo -->
    <div class="sidebar-brand">
        <a href="index.php" class="brand-link" title="International State College of the Philippines">
            <div class="brand-logo-wrap">
                <img src="../assets/images/iscp_logo.png" alt="ISCP Official Logo" class="sidebar-logo-img">
            </div>
            <div class="brand-text">
                <span class="brand-title">ISCP Portal</span>
                <span class="brand-subtitle"><?= h(format_role_label($role)) ?></span>
            </div>
        </a>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Dynamic Role-Based Navigation Menu -->
    <nav class="sidebar-nav">
        <?php if (in_array($role, ['super_admin', 'admin'], true)): ?>
            <!-- A. SUPER ADMINISTRATOR SIDEBAR -->
            <div class="nav-section-label">MAIN CONTROL</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $activeNav === 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="create.php" class="nav-link <?= $activeNav === 'create.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-pen-to-square nav-icon"></i>
                        <span class="nav-text">Create Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_announcements.php" class="nav-link <?= $activeNav === 'my_announcements.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-bullhorn nav-icon"></i>
                        <span class="nav-text">My Announcements</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="drafts.php" class="nav-link <?= $activeNav === 'drafts.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-pen nav-icon"></i>
                        <span class="nav-text">Drafts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pending_requests.php" class="nav-link <?= $activeNav === 'pending_requests.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-clock-rotate-left nav-icon"></i>
                        <span class="nav-text">Pending Approvals</span>
                        <?php if ($pendingCount > 0): ?>
                            <span class="nav-badge badge-pulse"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <div class="nav-section-label" style="margin-top: 1.25rem;">INSTITUTION MANAGEMENT</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?= $activeNav === 'users.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users nav-icon"></i>
                        <span class="nav-text">User Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="offices.php" class="nav-link <?= $activeNav === 'offices.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-building-columns nav-icon"></i>
                        <span class="nav-text">Office Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="colleges.php" class="nav-link <?= $activeNav === 'colleges.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-graduation-cap nav-icon"></i>
                        <span class="nav-text">College Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="departments.php" class="nav-link <?= $activeNav === 'departments.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-network-wired nav-icon"></i>
                        <span class="nav-text">Department Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_announcements.php" class="nav-link <?= ($activeNav === 'view_announcements.php' && empty($currentQuery)) ? 'active' : '' ?>">
                        <i class="fa-solid fa-bullhorn nav-icon"></i>
                        <span class="nav-text">Announcement Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link <?= $activeNav === 'categories.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-tags nav-icon"></i>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>
            </ul>

            <div class="nav-section-label" style="margin-top: 1.25rem;">SECURITY & AUDIT</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="roles.php" class="nav-link <?= $activeNav === 'roles.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-shield-halved nav-icon"></i>
                        <span class="nav-text">Roles & Permissions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="activity_logs.php" class="nav-link <?= $activeNav === 'activity_logs.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-timeline nav-icon"></i>
                        <span class="nav-text">Activity Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?= $activeNav === 'settings.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-sliders nav-icon"></i>
                        <span class="nav-text">System Settings</span>
                    </a>
                </li>
            </ul>

        <?php elseif ($role === 'university_admin'): ?>
            <!-- B. UNIVERSITY ADMINISTRATOR SIDEBAR -->
            <div class="nav-section-label">UNIVERSITY ANNOUNCEMENTS</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $activeNav === 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="create.php" class="nav-link <?= $activeNav === 'create.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_announcements.php" class="nav-link <?= $activeNav === 'my_announcements.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-bullhorn nav-icon"></i>
                        <span class="nav-text">My Announcements</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_announcements.php?status=published" class="nav-link <?= ($activeNav === 'view_announcements.php' && strpos($currentQuery, 'status=published') !== false) ? 'active' : '' ?>">
                        <i class="fa-solid fa-globe nav-icon"></i>
                        <span class="nav-text">Published</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="drafts.php" class="nav-link <?= $activeNav === 'drafts.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-pen nav-icon"></i>
                        <span class="nav-text">Drafts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scheduled.php" class="nav-link <?= $activeNav === 'scheduled.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calendar-days nav-icon"></i>
                        <span class="nav-text">Scheduled</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="analytics.php" class="nav-link <?= $activeNav === 'analytics.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-line nav-icon"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link <?= $activeNav === 'notifications.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?= $activeNav === 'profile.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-gear nav-icon"></i>
                        <span class="nav-text">Profile Settings</span>
                    </a>
                </li>
            </ul>

        <?php elseif ($role === 'office_admin'): ?>
            <!-- C. OFFICE ADMINISTRATOR SIDEBAR -->
            <div class="nav-section-label">OFFICE ANNOUNCEMENTS</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $activeNav === 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="create.php" class="nav-link <?= $activeNav === 'create.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_announcements.php" class="nav-link <?= $activeNav === 'my_announcements.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-building-user nav-icon"></i>
                        <span class="nav-text">My Announcements</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="drafts.php" class="nav-link <?= $activeNav === 'drafts.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-pen nav-icon"></i>
                        <span class="nav-text">Drafts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_announcements.php?status=published" class="nav-link <?= ($activeNav === 'view_announcements.php' && strpos($currentQuery, 'status=published') !== false) ? 'active' : '' ?>">
                        <i class="fa-solid fa-globe nav-icon"></i>
                        <span class="nav-text">Published</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scheduled.php" class="nav-link <?= $activeNav === 'scheduled.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calendar-days nav-icon"></i>
                        <span class="nav-text">Scheduled</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link <?= $activeNav === 'notifications.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?= $activeNav === 'profile.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-building-circle-check nav-icon"></i>
                        <span class="nav-text">Office Profile</span>
                    </a>
                </li>
            </ul>

        <?php elseif ($role === 'college_admin'): ?>
            <!-- D. COLLEGE ADMINISTRATOR SIDEBAR -->
            <div class="nav-section-label">COLLEGE ANNOUNCEMENTS</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $activeNav === 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="create.php" class="nav-link <?= $activeNav === 'create.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_announcements.php" class="nav-link <?= $activeNav === 'my_announcements.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-graduation-cap nav-icon"></i>
                        <span class="nav-text">College Announcements</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="drafts.php" class="nav-link <?= $activeNav === 'drafts.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-pen nav-icon"></i>
                        <span class="nav-text">Drafts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_announcements.php?status=published" class="nav-link <?= ($activeNav === 'view_announcements.php' && strpos($currentQuery, 'status=published') !== false) ? 'active' : '' ?>">
                        <i class="fa-solid fa-globe nav-icon"></i>
                        <span class="nav-text">Published</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scheduled.php" class="nav-link <?= $activeNav === 'scheduled.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calendar-days nav-icon"></i>
                        <span class="nav-text">Scheduled</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="departments.php" class="nav-link <?= $activeNav === 'departments.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-sitemap nav-icon"></i>
                        <span class="nav-text">Departments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?= $activeNav === 'users.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users-gear nav-icon"></i>
                        <span class="nav-text">Department Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link <?= $activeNav === 'notifications.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?= $activeNav === 'profile.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-school nav-icon"></i>
                        <span class="nav-text">College Profile</span>
                    </a>
                </li>
            </ul>

        <?php elseif ($role === 'department_admin' || $role === 'announcer'): ?>
            <!-- E. DEPARTMENT ADMINISTRATOR SIDEBAR -->
            <div class="nav-section-label">DEPARTMENT WORKSPACE</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $activeNav === 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="create.php" class="nav-link <?= $activeNav === 'create.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Announcement</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_announcements.php" class="nav-link <?= $activeNav === 'my_announcements.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chalkboard-user nav-icon"></i>
                        <span class="nav-text">Department Announcements</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="drafts.php" class="nav-link <?= $activeNav === 'drafts.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-pen nav-icon"></i>
                        <span class="nav-text">Drafts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_announcements.php?status=published" class="nav-link <?= ($activeNav === 'view_announcements.php' && strpos($currentQuery, 'status=published') !== false) ? 'active' : '' ?>">
                        <i class="fa-solid fa-globe nav-icon"></i>
                        <span class="nav-text">Published</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scheduled.php" class="nav-link <?= $activeNav === 'scheduled.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calendar-days nav-icon"></i>
                        <span class="nav-text">Scheduled</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link <?= $activeNav === 'notifications.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-bell nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?= $activeNav === 'profile.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-address-card nav-icon"></i>
                        <span class="nav-text">Department Profile</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>

        <!-- Portals Switcher / External View -->
        <div class="nav-section-label" style="margin-top: 1.5rem;">QUICK ACCESS</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../index.php" target="_blank" class="nav-link">
                    <i class="fa-solid fa-arrow-up-right-from-square nav-icon"></i>
                    <span class="nav-text">Public Bulletin Board</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer / Account Info -->
    <div class="sidebar-footer">
        <div class="user-card-compact">
            <div class="user-avatar-sm" title="<?= h($currentUser['name']) ?>">
                <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-details">
                <div class="name" title="<?= h($currentUser['name']) ?>"><?= h($currentUser['name']) ?></div>
                <div class="role-email" title="<?= h($currentUser['email']) ?>"><?= h($currentUser['email']) ?></div>
            </div>
            <a href="logout.php" onclick="if (window.openModal) { openModal('signOutModal'); return false; }" class="btn-sidebar-logout" title="Sign Out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
