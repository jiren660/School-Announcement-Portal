<?php
/**
 * Admin Home Dashboard
 * Displays overview metric cards and latest announcements.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$page_title = 'Home Dashboard';
$page_subtitle = 'Overview of system activity, announcement requests, and registered users.';
$activeNav = 'index.php';

$pdo = get_db();

// Metrics
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$total_users = 0;
$announcer_count = 0;
$latest_announcements = [];

if ($pdo) {
    try {
        $pending_count = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'pending'")->fetchColumn();
        $approved_count = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'approved'")->fetchColumn();
        $rejected_count = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'rejected'")->fetchColumn();
        $total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $announcer_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'announcer'")->fetchColumn();

        $stmt = $pdo->query("
            SELECT a.*, u.name AS author_name, u.email AS author_email
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
            LIMIT 7
        ");
        $latest_announcements = $stmt->fetchAll();
    } catch (PDOException $e) {
        set_flash('danger', 'Error loading dashboard metrics: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Metric Cards Grid -->
<div class="metrics-grid">
    <!-- Card 1: Pending Requests -->
    <a href="pending_requests.php" class="metric-card">
        <div class="metric-icon-box icon-pending">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($pending_count) ?></span>
            <span class="metric-label">Pending Requests</span>
            <?php if ($pending_count > 0): ?>
                <span style="font-size: 0.72rem; color: #d97706; font-weight: 700; margin-top: 0.2rem;">
                    <i class="fa-solid fa-bell"></i> Needs Review
                </span>
            <?php endif; ?>
        </div>
    </a>

    <!-- Card 2: Approved Announcements -->
    <a href="view_announcements.php" class="metric-card">
        <div class="metric-icon-box icon-approved">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($approved_count) ?></span>
            <span class="metric-label">Approved Announcements</span>
            <span style="font-size: 0.72rem; color: #059669; font-weight: 600; margin-top: 0.2rem;">
                <i class="fa-solid fa-globe"></i> Live on Portal
            </span>
        </div>
    </a>

    <!-- Card 3: Rejected Announcements -->
    <a href="rejected.php" class="metric-card">
        <div class="metric-icon-box icon-rejected">
            <i class="fa-solid fa-circle-xmark"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($rejected_count) ?></span>
            <span class="metric-label">Rejected Announcements</span>
            <span style="font-size: 0.72rem; color: #dc2626; font-weight: 600; margin-top: 0.2rem;">
                <i class="fa-solid fa-file-circle-xmark"></i> With feedback
            </span>
        </div>
    </a>

    <!-- Card 4: Total Users -->
    <a href="users.php" class="metric-card">
        <div class="metric-icon-box icon-users">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($total_users) ?></span>
            <span class="metric-label">Total Users</span>
            <span style="font-size: 0.72rem; color: #0284c7; font-weight: 600; margin-top: 0.2rem;">
                <?= $announcer_count ?> Announcer<?= $announcer_count === 1 ? '' : 's' ?>
            </span>
        </div>
    </a>
</div>

<!-- Latest Announcements Section -->
<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-bullhorn" style="color: var(--primary);"></i>
            <h2 class="card-title">Latest Announcements Activity</h2>
            <span class="card-count-badge"><?= count($latest_announcements) ?> recent</span>
        </div>
        <div class="card-header-right">
            <a href="view_announcements.php" class="btn btn-outline btn-sm">
                View All Approved <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($latest_announcements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-regular fa-folder-open"></i>
                </div>
                <h3 class="empty-state-title">No announcements recorded yet</h3>
                <p class="empty-state-desc">When announcers submit posts or requests, they will appear here in real time.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Announcement Title</th>
                            <th>Category</th>
                            <th>Author (Announcer)</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latest_announcements as $ann): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); max-width: 340px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= h($ann['title']) ?>">
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
                                    <div style="font-weight: 500;"><?= h($ann['author_name'] ?? 'Unknown') ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= h($ann['author_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php
                                    $st = strtolower($ann['status']);
                                    $badgeClass = 'badge-status-pending';
                                    $statusIcon = 'fa-clock';
                                    if ($st === 'approved') {
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
                                    <?php if ($st === 'pending'): ?>
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

<!-- View Announcement Modal -->
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
                <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);" id="modalDetailHeading"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Submitted By</label>
                <div style="font-size: 0.9rem; color: var(--text-main);" id="modalDetailAuthor"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Content</label>
                <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1rem; font-size: 0.92rem; line-height: 1.6; white-space: pre-wrap;" id="modalDetailContent"></div>
            </div>

            <div class="form-group" id="modalRejectionGroup" style="display: none;">
                <label class="form-label" style="color: var(--danger);">Rejection Reason</label>
                <div class="rejection-callout" id="modalDetailRejectionReason"></div>
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
    document.getElementById('modalDetailAuthor').textContent = (ann.author_name || 'Announcer') + (ann.author_email ? ' (' + ann.author_email + ')' : '');
    document.getElementById('modalDetailDate').textContent = 'Created: ' + (ann.created_at || '');

    const statusEl = document.getElementById('modalDetailStatus');
    const st = (ann.status || 'pending').toLowerCase();
    statusEl.className = 'badge badge-status-' + st;
    statusEl.textContent = st.charAt(0).toUpperCase() + st.slice(1);

    const rejGroup = document.getElementById('modalRejectionGroup');
    if (st === 'rejected' && ann.rejection_reason) {
        rejGroup.style.display = 'block';
        document.getElementById('modalDetailRejectionReason').textContent = ann.rejection_reason;
    } else {
        rejGroup.style.display = 'none';
    }

    openModal('viewDetailModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
