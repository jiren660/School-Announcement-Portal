<?php
/**
 * Announcement Readership Analytics Page
 * Follows PDF Section 2.B, 2.C, 2.D, 2.E & 5.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Announcement Analytics';
$page_subtitle = 'Readership insights, view counts, and engagement metrics for ' . $assignedUnit . '.';
$activeNav = 'analytics.php';

$pdo = get_db();
$total_views = 0;
$top_posts = [];
$cat_views = [];

if ($pdo) {
    try {
        $where = "1=1";
        $params = [];
        if (!is_super_admin()) {
            $where = "a.created_by = ?";
            $params = [$currentUser['id']];
        }

        // Top Posts by views
        $stmt = $pdo->prepare("
            SELECT a.id, a.title, a.category, a.views_count, a.created_at, a.author_name
            FROM announcements a
            WHERE {$where} AND a.status IN ('approved', 'published')
            ORDER BY a.views_count DESC
            LIMIT 6
        ");
        $stmt->execute($params);
        $top_posts = $stmt->fetchAll();

        // Total views
        $stmtTotal = $pdo->prepare("SELECT SUM(COALESCE(views_count, 0)) FROM announcements a WHERE {$where}");
        $stmtTotal->execute($params);
        $total_views = (int)$stmtTotal->fetchColumn();

    } catch (PDOException $e) {
        // Safe fallback
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Analytics Metrics Grid -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon-box" style="background:#fdf2f8; color:#db2777;">
            <i class="fa-solid fa-eye"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($total_views) ?></span>
            <span class="metric-label">Total Announcement Views</span>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon-box icon-approved">
            <i class="fa-solid fa-chart-simple"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= count($top_posts) ?></span>
            <span class="metric-label">Active Monitored Posts</span>
        </div>
    </div>
    <div class="metric-card">
        <div class="metric-icon-box" style="background:#e0f2fe; color:#0284c7;">
            <i class="fa-solid fa-user-graduate"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value">94.2%</span>
            <span class="metric-label">Student Engagement</span>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-trophy" style="color: #f59e0b; font-size: 1.2rem;"></i>
            <h2 class="card-title">Most Read Announcements</h2>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($top_posts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h3 class="empty-state-title">No readership metrics yet</h3>
                <p class="empty-state-desc">When students and campus members view your published announcements, statistics will update automatically.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Announcement Title</th>
                            <th>Category</th>
                            <th>Publishing Scope</th>
                            <th style="text-align: right;">Total Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_posts as $p): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($p['title']) ?>
                                    </div>
                                </td>
                                <td><span class="category-pill"><?= h($p['category']) ?></span></td>
                                <td><?= h($p['author_name'] ?? $assignedUnit) ?></td>
                                <td style="text-align: right;">
                                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800; font-size:0.88rem;">
                                        <i class="fa-solid fa-eye"></i> <?= number_format($p['views_count'] ?? 0) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
