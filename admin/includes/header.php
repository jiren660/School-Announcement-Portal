<?php
/**
 * Admin Layout Header
 * Requires $page_title to be set before inclusion.
 */
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/auth.php';
require_admin();

$currentUser = current_user();
$pendingCount = get_pending_count();
$currentScript = basename($_SERVER['PHP_SELF']);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? h($page_title) . ' - ' : '' ?>Admin Portal | ISCP</title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Admin Stylesheet -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Top Navbar -->
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
                <a href="../index.php" target="_blank" class="btn btn-outline btn-sm" title="View Public Student Portal">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span class="desktop-only">Public Portal</span>
                </a>

                <div class="user-profile-badge">
                    <div class="avatar-circle">
                        <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="user-meta desktop-only">
                        <span class="user-name"><?= h($currentUser['name']) ?></span>
                        <span class="badge badge-admin"><?= h(format_role_label($currentUser['role'])) ?></span>
                    </div>
                </div>

                <a href="logout.php" class="btn btn-icon btn-logout" title="Sign Out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </header>

        <!-- Content Body -->
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

