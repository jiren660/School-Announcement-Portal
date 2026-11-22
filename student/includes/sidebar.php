<?php
/**
 * Student Sidebar Navigation
 * Follows PDF Section 2.F: Exact 11 sidebar links for Students.
 * View-Only experience: No administrative or publishing functions.
 */

if (!defined('STUDENT_INIT')) {
    exit;
}

$activeType = $_GET['type'] ?? 'home';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Header with Official University Logo -->
    <div class="sidebar-brand">
        <a href="index.php" class="brand-link" title="Student Announcement Portal">
            <div class="brand-logo-wrap">
                <img src="../assets/images/iscp_logo.png" alt="ISCP Official Logo" class="sidebar-logo-img">
            </div>
            <div class="brand-text">
                <span class="brand-title">ISCP Student</span>
                <span class="brand-subtitle" style="color: #38bdf8;">Announcement Feed</span>
            </div>
        </a>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Student Navigation Menu (PDF Section 2.F) -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">STUDENT PORTAL</div>
        <ul class="nav-list">
            <!-- 1. Home -->
            <li class="nav-item">
                <a href="index.php" class="nav-link <?= ($currentScript === 'index.php' && $activeType === 'home') ? 'active' : '' ?>">
                    <i class="fa-solid fa-house nav-icon"></i>
                    <span class="nav-text">Home</span>
                </a>
            </li>

            <!-- 2. All Announcements -->
            <li class="nav-item">
                <a href="index.php?type=all" class="nav-link <?= ($activeType === 'all') ? 'active' : '' ?>">
                    <i class="fa-solid fa-bullhorn nav-icon"></i>
                    <span class="nav-text">All Announcements</span>
                </a>
            </li>

            <!-- 3. University Announcements -->
            <li class="nav-item">
                <a href="index.php?type=university" class="nav-link <?= ($activeType === 'university') ? 'active' : '' ?>">
                    <i class="fa-solid fa-building-columns nav-icon"></i>
                    <span class="nav-text">University Announcements</span>
                </a>
            </li>

            <!-- 4. Academic Announcements -->
            <li class="nav-item">
                <a href="index.php?type=academic" class="nav-link <?= ($activeType === 'academic') ? 'active' : '' ?>">
                    <i class="fa-solid fa-book-open nav-icon"></i>
                    <span class="nav-text">Academic Announcements</span>
                </a>
            </li>

            <!-- 5. College Announcements -->
            <li class="nav-item">
                <a href="index.php?type=college" class="nav-link <?= ($activeType === 'college') ? 'active' : '' ?>">
                    <i class="fa-solid fa-graduation-cap nav-icon"></i>
                    <span class="nav-text">College Announcements</span>
                </a>
            </li>

            <!-- 6. Department Announcements -->
            <li class="nav-item">
                <a href="index.php?type=department" class="nav-link <?= ($activeType === 'department') ? 'active' : '' ?>">
                    <i class="fa-solid fa-network-wired nav-icon"></i>
                    <span class="nav-text">Department Announcements</span>
                </a>
            </li>

            <!-- 7. Student Affairs -->
            <li class="nav-item">
                <a href="index.php?type=student_affairs" class="nav-link <?= ($activeType === 'student_affairs') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users nav-icon"></i>
                    <span class="nav-text">Student Affairs</span>
                </a>
            </li>

            <!-- 8. Events and Activities -->
            <li class="nav-item">
                <a href="index.php?type=events" class="nav-link <?= ($activeType === 'events') ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-star nav-icon"></i>
                    <span class="nav-text">Events &amp; Activities</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label" style="margin-top: 1.25rem;">MY CORNER</div>
        <ul class="nav-list">
            <!-- 9. Saved Announcements -->
            <li class="nav-item">
                <a href="saved.php" class="nav-link <?= ($currentScript === 'saved.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-bookmark nav-icon" style="color: #f59e0b;"></i>
                    <span class="nav-text">Saved Announcements</span>
                </a>
            </li>

            <!-- 10. Notifications -->
            <li class="nav-item">
                <a href="notifications.php" class="nav-link <?= ($currentScript === 'notifications.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-bell nav-icon"></i>
                    <span class="nav-text">Notifications</span>
                </a>
            </li>

            <!-- 11. My Profile -->
            <li class="nav-item">
                <a href="profile.php" class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-graduate nav-icon"></i>
                    <span class="nav-text">My Profile</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label" style="margin-top: 1.25rem;">EXTERNAL LINKS</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../index.php" target="_blank" class="nav-link">
                    <i class="fa-solid fa-arrow-up-right-from-square nav-icon"></i>
                    <span class="nav-text">Public Bulletin Board</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../login.php" class="nav-link" style="color: #38bdf8;">
                    <i class="fa-solid fa-shield-halved nav-icon"></i>
                    <span class="nav-text">Admin Sign In</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Student Footer -->
    <div class="sidebar-footer">
        <div class="user-card-compact">
            <div class="user-avatar-sm" style="background: linear-gradient(135deg, #0284c7, #38bdf8);">
                S
            </div>
            <div class="user-details">
                <div class="name"><?= h($currentStudent['name']) ?></div>
                <div class="role-email">Student Portal &bull; Read-Only</div>
            </div>
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="../admin/logout.php" class="btn-sidebar-logout" title="Sign Out">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
