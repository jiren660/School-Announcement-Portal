<?php
/**
 * Admin Sidebar Navigation
 */
if (!defined('APP_INIT')) {
    exit;
}

$pendingCount = get_pending_count();
$activeNav = $activeNav ?? basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <a href="index.php" class="brand-link">
            <div class="brand-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title">ISCP Portal</span>
                <span class="brand-subtitle">Admin Control Panel</span>
            </div>
        </a>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">MAIN MENU</div>
        <ul class="nav-list">
            <!-- 1. Home -->
            <li class="nav-item">
                <a href="index.php" class="nav-link <?= $activeNav === 'index.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-house nav-icon"></i>
                    <span class="nav-text">Home</span>
                </a>
            </li>

            <!-- 2. View Announcements -->
            <li class="nav-item">
                <a href="view_announcements.php" class="nav-link <?= $activeNav === 'view_announcements.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bullhorn nav-icon"></i>
                    <span class="nav-text">View Announcements</span>
                </a>
            </li>

            <!-- 3. Pending Announcement Requests -->
            <li class="nav-item">
                <a href="pending_requests.php" class="nav-link <?= $activeNav === 'pending_requests.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left nav-icon"></i>
                    <span class="nav-text">Pending Announcement Requests</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="nav-badge badge-pulse" title="<?= $pendingCount ?> pending requests"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- 4. Rejected Announcements -->
            <li class="nav-item">
                <a href="rejected.php" class="nav-link <?= $activeNav === 'rejected.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-ban nav-icon"></i>
                    <span class="nav-text">Rejected Announcements</span>
                </a>
            </li>

            <!-- 5. Users -->
            <li class="nav-item">
                <a href="users.php" class="nav-link <?= $activeNav === 'users.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users nav-icon"></i>
                    <span class="nav-text">Users</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer / Account Info -->
    <div class="sidebar-footer">
        <div class="user-card-compact">
            <div class="user-avatar-sm">
                <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-details">
                <div class="name" title="<?= h($currentUser['name']) ?>"><?= h($currentUser['name']) ?></div>
                <div class="role-email"><?= h($currentUser['email']) ?></div>
            </div>
            <a href="logout.php" class="btn-sidebar-logout" title="Sign Out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

