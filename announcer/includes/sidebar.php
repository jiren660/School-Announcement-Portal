<?php
/**
 * Announcer Sidebar Navigation
 */
if (!defined('APP_INIT')) {
    exit;
}

$activeNav = $activeNav ?? basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <a href="index.php" class="brand-link">
            <div class="brand-icon" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title">ISCP Portal</span>
                <span class="brand-subtitle" style="color: #38bdf8;">Faculty & Staff</span>
            </div>
        </a>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">MY WORKSPACE</div>
        <ul class="nav-list">
            <!-- 1. My Announcements -->
            <li class="nav-item">
                <a href="index.php" class="nav-link <?= $activeNav === 'index.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-house nav-icon"></i>
                    <span class="nav-text">My Announcements</span>
                </a>
            </li>

            <!-- 2. Submit New Announcement -->
            <li class="nav-item">
                <a href="create.php" class="nav-link <?= $activeNav === 'create.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-pen-nib nav-icon"></i>
                    <span class="nav-text">Submit Announcement</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label" style="margin-top: 1.5rem;">QUICK LINKS</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../index.php" target="_blank" class="nav-link">
                    <i class="fa-solid fa-arrow-up-right-from-square nav-icon"></i>
                    <span class="nav-text">Public Bulletin Board</span>
                </a>
            </li>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                <li class="nav-item">
                    <a href="../admin/index.php" class="nav-link" style="color: #f59e0b;">
                        <i class="fa-solid fa-shield-halved nav-icon"></i>
                        <span class="nav-text">Admin Control Panel</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Sidebar Footer / Account Info -->
    <div class="sidebar-footer">
        <div class="user-card-compact">
            <div class="user-avatar-sm" style="background: linear-gradient(135deg, #0284c7, #2563eb);">
                <?= strtoupper(substr($currentUser['name'] ?? 'T', 0, 1)) ?>
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

