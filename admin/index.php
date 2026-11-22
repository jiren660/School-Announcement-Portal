<?php
/**
 * Shared Dynamic Administrator Home Dashboard
 * Supports Super Administrator, University Administrator, Office Administrator,
 * College Administrator, and Department Administrator.
 * 
 * Implements Phase 3 & Phase 4 of the University Announcement Portal Planning Guide.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Dashboard Overview';
$page_subtitle = 'Welcome to the university administrative command center.';
$activeNav = 'index.php';

$pdo = get_db();

// Standard metrics initial state
$metric_total_announcements = 0;
$metric_published_count = 0;
$metric_drafts_count = 0;
$metric_scheduled_count = 0;
$metric_pending_count = 0;
$metric_views_count = 0;
$metric_users_count = 0;
$metric_offices_count = 0;
$metric_colleges_count = 0;
$metric_departments_count = 0;

$recent_announcements = [];
$recent_activities = [];

if ($pdo) {
    try {
        // 1. Calculate Scope Filter for Queries
        $scopeWhere = "1=1";
        $scopeParams = [];

        if ($currentRole === ROLE_OFFICE_ADMIN && !empty($currentUser['assigned_office'])) {
            $scopeWhere = "(a.created_by = ? OR a.author_name LIKE ?)";
            $scopeParams[] = $currentUser['id'];
            $scopeParams[] = '%' . $currentUser['assigned_office'] . '%';
        } elseif ($currentRole === ROLE_COLLEGE_ADMIN && !empty($currentUser['assigned_college'])) {
            $scopeWhere = "(a.created_by = ? OR a.author_name LIKE ?)";
            $scopeParams[] = $currentUser['id'];
            $scopeParams[] = '%' . $currentUser['assigned_college'] . '%';
        } elseif ($currentRole === ROLE_DEPARTMENT_ADMIN && !empty($currentUser['assigned_department'])) {
            $scopeWhere = "(a.created_by = ? OR a.author_name LIKE ?)";
            $scopeParams[] = $currentUser['id'];
            $scopeParams[] = '%' . $currentUser['assigned_department'] . '%';
        }

        // Total announcements in scope
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM announcements a WHERE {$scopeWhere}");
        $stmt->execute($scopeParams);
        $metric_total_announcements = (int)$stmt->fetchColumn();

        // Published announcements in scope
        $stmtPub = $pdo->prepare("SELECT COUNT(*) FROM announcements a WHERE {$scopeWhere} AND a.status IN ('approved', 'published')");
        $stmtPub->execute($scopeParams);
        $metric_published_count = (int)$stmtPub->fetchColumn();

        // Pending announcements
        if (is_super_admin()) {
            $metric_pending_count = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'pending'")->fetchColumn();
        } else {
            $stmtPend = $pdo->prepare("SELECT COUNT(*) FROM announcements a WHERE {$scopeWhere} AND a.status = 'pending'");
            $stmtPend->execute($scopeParams);
            $metric_pending_count = (int)$stmtPend->fetchColumn();
        }

        // Drafts and Scheduled counts (compatible with existing or future schema)
        $stmtDraft = $pdo->prepare("SELECT COUNT(*) FROM announcements a WHERE {$scopeWhere} AND a.status = 'draft'");
        $stmtDraft->execute($scopeParams);
        $metric_drafts_count = (int)$stmtDraft->fetchColumn();

        $stmtSched = $pdo->prepare("SELECT COUNT(*) FROM announcements a WHERE {$scopeWhere} AND a.status = 'scheduled'");
        $stmtSched->execute($scopeParams);
        $metric_scheduled_count = (int)$stmtSched->fetchColumn();

        // Total Views in scope
        $stmtViews = $pdo->prepare("SELECT SUM(COALESCE(a.views_count, 0)) FROM announcements a WHERE {$scopeWhere}");
        $stmtViews->execute($scopeParams);
        $metric_views_count = (int)$stmtViews->fetchColumn();

        // Super Admin Global Metrics
        if (is_super_admin()) {
            $metric_users_count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

            // Try to count registered offices if table exists
            try {
                $metric_offices_count = (int)$pdo->query("SELECT COUNT(*) FROM offices")->fetchColumn();
            } catch (Exception $e) {
                $metric_offices_count = 0;
            }
        }

        // Recent Announcements for Main Table
        $stmtLatest = $pdo->prepare("
            SELECT a.*, u.name AS creator_name, u.email AS creator_email
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE {$scopeWhere}
            ORDER BY a.created_at DESC
            LIMIT 7
        ");
        $stmtLatest->execute($scopeParams);
        $recent_announcements = $stmtLatest->fetchAll();

    } catch (PDOException $e) {
        set_flash('danger', 'Unable to retrieve dashboard metrics: ' . $e->getMessage());
    }
}

// Fetch activity logs if table exists, otherwise empty
$recent_activities = [];
if ($pdo) {
    try {
        $stmtAct = $pdo->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 5");
        $recent_activities = $stmtAct->fetchAll();
    } catch (Exception $e) {
        $recent_activities = [];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ==================================================================== -->
<!-- DASHBOARD WELCOME & SCOPE HEADER                                     -->
<!-- ==================================================================== -->
<div class="dashboard-welcome-banner" style="background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%); border: 1px solid #c7ddf8; border-radius: var(--radius-lg); padding: 1.5rem 1.75rem; margin-bottom: 1.75rem; box-shadow: var(--shadow-sm); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
            <span class="badge" style="background: #0284c7; color: #ffffff; font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                <i class="fa-solid fa-id-badge"></i> <?= h(format_role_label($currentRole)) ?>
            </span>
            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                <i class="fa-solid fa-sitemap"></i> <?= h($assignedUnit) ?>
            </span>
        </div>
        <h2 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
            Welcome back, <?= h($currentUser['name']) ?>!
        </h2>
        <p style="font-size: 0.88rem; color: #475569; margin-top: 0.2rem;">
            <?php if (is_super_admin()): ?>
                You have system-wide management authority over user accounts, registered offices, colleges, and university announcements.
            <?php elseif ($currentRole === ROLE_UNIVERSITY_ADMIN): ?>
                You have authority to draft, schedule, and publish university-wide campus advisories.
            <?php elseif ($currentRole === ROLE_OFFICE_ADMIN): ?>
                You have publishing and management authority for announcements belonging to <strong><?= h($assignedUnit) ?></strong>.
            <?php elseif ($currentRole === ROLE_COLLEGE_ADMIN): ?>
                You have administrative authority over announcements for <strong><?= h($assignedUnit) ?></strong> and its affiliated departments.
            <?php else: ?>
                You have authority to publish class schedules, exam notices, and activities for <strong><?= h($assignedUnit) ?></strong>.
            <?php endif; ?>
        </p>
    </div>

    <div style="display: flex; gap: 0.6rem; align-items: center;">
        <a href="create.php" class="btn btn-primary">
            <i class="fa-solid fa-plus-circle"></i> Create Announcement
        </a>
    </div>
</div>

<!-- ==================================================================== -->
<!-- ROLE-BASED METRIC WIDGETS (PDF Section 2)                            -->
<!-- ==================================================================== -->
<div class="metrics-grid">
    <?php if (is_super_admin()): ?>
        <!-- A. SUPER ADMINISTRATOR WIDGETS -->
        <!-- Widget 1: Total Users -->
        <a href="users.php" class="metric-card">
            <div class="metric-icon-box icon-users">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_users_count) ?></span>
                <span class="metric-label">Total Users</span>
                <span style="font-size: 0.72rem; color: #0284c7; font-weight: 600; margin-top: 0.2rem;">
                    All registered accounts
                </span>
            </div>
        </a>

        <!-- Widget 2: Total Offices -->
        <a href="offices.php" class="metric-card">
            <div class="metric-icon-box" style="background: #e0e7ff; color: #4338ca;">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_offices_count) ?></span>
                <span class="metric-label">Registered Offices</span>
                <span style="font-size: 0.72rem; color: #4338ca; font-weight: 600; margin-top: 0.2rem;">
                    Campus Administrative Units
                </span>
            </div>
        </a>

        <!-- Widget 3: Total Announcements -->
        <a href="view_announcements.php" class="metric-card">
            <div class="metric-icon-box icon-approved">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_total_announcements) ?></span>
                <span class="metric-label">Total Announcements</span>
                <span style="font-size: 0.72rem; color: #059669; font-weight: 600; margin-top: 0.2rem;">
                    <?= number_format($metric_published_count) ?> currently published
                </span>
            </div>
        </a>

        <!-- Widget 4: Pending Announcements -->
        <a href="pending_requests.php" class="metric-card">
            <div class="metric-icon-box icon-pending">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_pending_count) ?></span>
                <span class="metric-label">Pending Announcements</span>
                <?php if ($metric_pending_count > 0): ?>
                    <span style="font-size: 0.72rem; color: #d97706; font-weight: 700; margin-top: 0.2rem;">
                        <i class="fa-solid fa-bell"></i> Awaiting Approval
                    </span>
                <?php else: ?>
                    <span style="font-size: 0.72rem; color: #059669; font-weight: 600; margin-top: 0.2rem;">
                        Queue cleared
                    </span>
                <?php endif; ?>
            </div>
        </a>

    <?php elseif ($currentRole === ROLE_UNIVERSITY_ADMIN): ?>
        <!-- B. UNIVERSITY ADMINISTRATOR WIDGETS -->
        <a href="view_announcements.php" class="metric-card">
            <div class="metric-icon-box" style="background: #e0f2fe; color: #0284c7;">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_total_announcements) ?></span>
                <span class="metric-label">University Posts</span>
            </div>
        </a>
        <a href="view_announcements.php?status=published" class="metric-card">
            <div class="metric-icon-box icon-approved">
                <i class="fa-solid fa-globe"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_published_count) ?></span>
                <span class="metric-label">Published</span>
                <span style="font-size: 0.72rem; color: #059669; font-weight: 600;">Currently Visible</span>
            </div>
        </a>
        <a href="drafts.php" class="metric-card">
            <div class="metric-icon-box" style="background: #f1f5f9; color: #475569;">
                <i class="fa-solid fa-file-pen"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_drafts_count) ?></span>
                <span class="metric-label">Drafts</span>
                <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">Unpublished</span>
            </div>
        </a>
        <a href="analytics.php" class="metric-card">
            <div class="metric-icon-box" style="background: #fdf2f8; color: #db2777;">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_views_count) ?></span>
                <span class="metric-label">Total Views</span>
                <span style="font-size: 0.72rem; color: #db2777; font-weight: 600;">Student Readership</span>
            </div>
        </a>

    <?php elseif ($currentRole === ROLE_OFFICE_ADMIN): ?>
        <!-- C. OFFICE ADMINISTRATOR WIDGETS -->
        <a href="my_announcements.php" class="metric-card">
            <div class="metric-icon-box" style="background: #e0f2fe; color: #0284c7;">
                <i class="fa-solid fa-building-user"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_total_announcements) ?></span>
                <span class="metric-label">Office Announcements</span>
                <span style="font-size: 0.72rem; color: #0284c7; font-weight: 600;"><?= h($assignedUnit) ?></span>
            </div>
        </a>
        <a href="view_announcements.php?status=published" class="metric-card">
            <div class="metric-icon-box icon-approved">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_published_count) ?></span>
                <span class="metric-label">Active Published</span>
            </div>
        </a>
        <a href="drafts.php" class="metric-card">
            <div class="metric-icon-box" style="background: #fef3c7; color: #d97706;">
                <i class="fa-solid fa-file-pen"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_drafts_count) ?></span>
                <span class="metric-label">Saved Drafts</span>
            </div>
        </a>
        <div class="metric-card">
            <div class="metric-icon-box" style="background: #fdf2f8; color: #db2777;">
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_views_count) ?></span>
                <span class="metric-label">Total Views</span>
                <span style="font-size: 0.72rem; color: #db2777; font-weight: 600;">Office Readership</span>
            </div>
        </div>

    <?php elseif ($currentRole === ROLE_COLLEGE_ADMIN): ?>
        <!-- D. COLLEGE ADMINISTRATOR WIDGETS -->
        <a href="my_announcements.php" class="metric-card">
            <div class="metric-icon-box" style="background: #e0f2fe; color: #0284c7;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_total_announcements) ?></span>
                <span class="metric-label">College Announcements</span>
            </div>
        </a>
        <a href="view_announcements.php?status=published" class="metric-card">
            <div class="metric-icon-box icon-approved">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_published_count) ?></span>
                <span class="metric-label">Published</span>
                <span style="font-size: 0.72rem; color: #059669; font-weight: 600;">Active in College</span>
            </div>
        </a>
        <a href="departments.php" class="metric-card">
            <div class="metric-icon-box" style="background: #f5f3ff; color: #7c3aed;">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= $metric_colleges_count ?></span>
                <span class="metric-label">Affiliated Depts</span>
            </div>
        </a>
        <div class="metric-card">
            <div class="metric-icon-box" style="background: #fdf2f8; color: #db2777;">
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_views_count) ?></span>
                <span class="metric-label">Announcement Views</span>
            </div>
        </div>

    <?php else: ?>
        <!-- E. DEPARTMENT ADMINISTRATOR / ANNOUNCER WIDGETS -->
        <a href="my_announcements.php" class="metric-card">
            <div class="metric-icon-box" style="background: #e0f2fe; color: #0284c7;">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_total_announcements) ?></span>
                <span class="metric-label">Department Posts</span>
            </div>
        </a>
        <a href="view_announcements.php?status=published" class="metric-card">
            <div class="metric-icon-box icon-approved">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_published_count) ?></span>
                <span class="metric-label">Published</span>
            </div>
        </a>
        <a href="drafts.php" class="metric-card">
            <div class="metric-icon-box" style="background: #fef3c7; color: #d97706;">
                <i class="fa-solid fa-file-pen"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_drafts_count) ?></span>
                <span class="metric-label">Drafts</span>
            </div>
        </a>
        <div class="metric-card">
            <div class="metric-icon-box" style="background: #fdf2f8; color: #db2777;">
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="metric-info">
                <span class="metric-value"><?= number_format($metric_views_count) ?></span>
                <span class="metric-label">Total Views</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ==================================================================== -->
<!-- MAIN TWO-COLUMN DASHBOARD CONTENT AREA                               -->
<!-- ==================================================================== -->
<div style="display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(320px, 1fr); gap: 1.5rem; align-items: start;">
    
    <!-- Left Column: Announcements Table in User Scope -->
    <div class="content-card">
        <div class="card-header">
            <div class="card-header-left">
                <i class="fa-solid fa-bullhorn" style="color: var(--primary);"></i>
                <h2 class="card-title">Recent Announcements Activity</h2>
                <span class="card-count-badge"><?= count($recent_announcements) ?> in scope</span>
            </div>
            <div class="card-header-right">
                <a href="view_announcements.php" class="btn btn-outline btn-sm">
                    View All <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <?php if (empty($recent_announcements)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fa-regular fa-folder-open"></i>
                    </div>
                    <h3 class="empty-state-title">No announcements recorded yet</h3>
                    <p class="empty-state-desc">New announcements within your administrative scope will appear here in real time.</p>
                    <div style="margin-top: 1rem;">
                        <a href="create.php" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-plus"></i> Compose First Announcement
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_announcements as $ann): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main); max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= h($ann['title']) ?>">
                                            <?= h($ann['title']) ?>
                                        </div>
                                        <?php if (!empty($ann['is_urgent'])): ?>
                                            <span class="badge" style="background: #fee2e2; color: #b91c1c; font-size: 0.68rem; margin-top: 0.2rem;">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Urgent
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="category-pill"><?= h($ann['category'] ?? 'General') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $st = strtolower($ann['status']);
                                        $badgeClass = 'badge-status-pending';
                                        $statusIcon = 'fa-clock';
                                        if (in_array($st, ['approved', 'published'], true)) {
                                            $badgeClass = 'badge-status-approved';
                                            $statusIcon = 'fa-check';
                                        } elseif ($st === 'rejected') {
                                            $badgeClass = 'badge-status-rejected';
                                            $statusIcon = 'fa-xmark';
                                        }
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <i class="fa-solid <?= $statusIcon ?>"></i>
                                            <?= ucfirst($st) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span title="<?= h(format_datetime($ann['created_at'])) ?>">
                                            <?= h(time_ago($ann['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($st === 'pending' && is_super_admin()): ?>
                                            <a href="pending_requests.php" class="btn btn-primary btn-sm">
                                                <i class="fa-solid fa-gavel"></i> Review
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="showAnnouncementDetail(<?= h(json_encode($ann)) ?>)">
                                                <i class="fa-solid fa-eye"></i> View
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Recent Activities & Fast Actions -->
    <div>
        <!-- Recent Activities Feed (PDF Widget) -->
        <div class="content-card">
            <div class="card-header">
                <div class="card-header-left">
                    <i class="fa-solid fa-timeline" style="color: #6366f1;"></i>
                    <h3 class="card-title" style="font-size: 1rem;">Recent Activities</h3>
                </div>
                <?php if (is_super_admin()): ?>
                    <a href="activity_logs.php" class="btn btn-outline btn-sm">Full Log</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <div style="text-align: center; padding: 2rem 1rem; color: #64748b; font-size: 0.88rem;">
                        <i class="fa-regular fa-clock" style="font-size: 1.75rem; margin-bottom: 0.5rem; display: block; color: #94a3b8;"></i>
                        No recent activity recorded yet.
                    </div>
                <?php else: ?>
                    <div class="activities-timeline" style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($recent_activities as $act): ?>
                            <div style="display: flex; gap: 0.85rem; align-items: flex-start;">
                                <div style="width: 36px; height: 36px; border-radius: var(--radius-full); background: #0284c718; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem;">
                                    <i class="fa-solid fa-bolt"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 0.88rem; font-weight: 700; color: #0f172a;"><?= h($act['action'] ?? $act['title'] ?? 'Activity') ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.15rem; line-height: 1.4;"><?= h($act['details'] ?? $act['desc'] ?? '') ?></div>
                                    <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 0.2rem;"><?= h(time_ago($act['timestamp'] ?? $act['created_at'] ?? '')) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- View Announcement Detail Modal -->
<div class="modal-overlay" id="viewDetailModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="modalDetailTitle">Announcement Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('viewDetailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <span class="category-pill" id="modalDetailCategory">Category</span>
                <span class="badge" id="modalDetailStatus">Status</span>
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: auto;" id="modalDetailDate"></span>
            </div>

            <div class="form-group">
                <label class="form-label">Title</label>
                <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-main);" id="modalDetailHeading"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Publishing Scope / Author</label>
                <div style="font-size: 0.9rem; color: var(--text-main); font-weight: 600;" id="modalDetailAuthor"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Announcement Content</label>
                <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1.15rem; font-size: 0.92rem; line-height: 1.65; white-space: pre-wrap;" id="modalDetailContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('viewDetailModal')">Close</button>
        </div>
    </div>
</div>

<script>
function showAnnouncementDetail(ann) {
    document.getElementById('modalDetailHeading').textContent = ann.title || '';
    document.getElementById('modalDetailCategory').textContent = ann.category || 'General';
    document.getElementById('modalDetailContent').textContent = ann.content || ann.summary || '';
    document.getElementById('modalDetailAuthor').textContent = (ann.author_name || ann.creator_name || 'Administration');
    document.getElementById('modalDetailDate').textContent = 'Created: ' + (ann.created_at || '');

    const statusEl = document.getElementById('modalDetailStatus');
    const st = (ann.status || 'pending').toLowerCase();
    statusEl.className = 'badge badge-status-' + (st === 'published' ? 'approved' : st);
    statusEl.textContent = st.charAt(0).toUpperCase() + st.slice(1);

    openModal('viewDetailModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
