<?php
/**
 * Announcer Portal - My Announcements Dashboard
 * Teachers and Staff can view, filter, track status, and manage their announcements.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_announcer();

$page_title = 'My Announcements';
$page_subtitle = 'Track the approval status and feedback of your announcement submissions.';
$activeNav = 'index.php';

$pdo = get_db();
$currentUser = current_user();
$userId = (int)$currentUser['id'];

$filter_status = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

$total_submissions = 0;
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$my_announcements = [];

if ($pdo) {
    try {
        // Compute user's metrics
        $stmtMetrics = $pdo->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
            FROM announcements
            WHERE created_by = ?
        ");
        $stmtMetrics->execute([$userId]);
        $metrics = $stmtMetrics->fetch();

        $total_submissions = (int)($metrics['total'] ?? 0);
        $pending_count = (int)($metrics['pending'] ?? 0);
        $approved_count = (int)($metrics['approved'] ?? 0);
        $rejected_count = (int)($metrics['rejected'] ?? 0);

        // Fetch announcements for this user
        $where = ["a.created_by = ?"];
        $params = [$userId];

        if (!empty($filter_status)) {
            $where[] = "a.status = ?";
            $params[] = $filter_status;
        }

        if (!empty($search)) {
            $where[] = "(a.title LIKE ? OR a.content LIKE ? OR a.category LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT a.*, rev.name AS reviewer_name
            FROM announcements a
            LEFT JOIN users rev ON a.reviewed_by = rev.id
            WHERE {$whereClause}
            ORDER BY a.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $my_announcements = $stmt->fetchAll();

        // Attach images to each announcement
        if (!empty($my_announcements)) {
            $ids = implode(',', array_map('intval', array_column($my_announcements, 'id')));
            $imgStmt = $pdo->query("SELECT announcement_id, image_path FROM announcement_images WHERE announcement_id IN ({$ids}) ORDER BY id ASC");
            $my_images = [];
            foreach ($imgStmt->fetchAll() as $im) {
                $my_images[$im['announcement_id']][] = $im['image_path'];
            }
            foreach ($my_announcements as &$mAnn) {
                $mAnn['images'] = $my_images[$mAnn['id']] ?? (!empty($mAnn['image_path']) ? [$mAnn['image_path']] : []);
            }
            unset($mAnn);
        }

    } catch (PDOException $e) {
        set_flash('danger', 'Error loading your announcements: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Metric Cards Grid -->
<div class="metrics-grid">
    <!-- Card 1: Total Submitted -->
    <a href="index.php" class="metric-card">
        <div class="metric-icon-box" style="background: #e0f2fe; color: #0284c7;">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($total_submissions) ?></span>
            <span class="metric-label">Total Submissions</span>
        </div>
    </a>

    <!-- Card 2: Pending Review -->
    <a href="index.php?status=pending" class="metric-card">
        <div class="metric-icon-box icon-pending">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($pending_count) ?></span>
            <span class="metric-label">Pending Admin Review</span>
            <?php if ($pending_count > 0): ?>
                <span style="font-size: 0.72rem; color: #d97706; font-weight: 700; margin-top: 0.2rem;">
                    In Queue
                </span>
            <?php endif; ?>
        </div>
    </a>

    <!-- Card 3: Approved & Live -->
    <a href="index.php?status=approved" class="metric-card">
        <div class="metric-icon-box icon-approved">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($approved_count) ?></span>
            <span class="metric-label">Approved & Published</span>
            <span style="font-size: 0.72rem; color: #059669; font-weight: 600; margin-top: 0.2rem;">
                Live on Bulletin
            </span>
        </div>
    </a>

    <!-- Card 4: Rejected / Needs Edit -->
    <a href="index.php?status=rejected" class="metric-card">
        <div class="metric-icon-box icon-rejected">
            <i class="fa-solid fa-circle-xmark"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($rejected_count) ?></span>
            <span class="metric-label">Rejected Submissions</span>
            <?php if ($rejected_count > 0): ?>
                <span style="font-size: 0.72rem; color: #dc2626; font-weight: 700; margin-top: 0.2rem;">
                    Feedback available to edit
                </span>
            <?php endif; ?>
        </div>
    </a>
</div>

<!-- Main Table Container -->
<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-list-check" style="color: var(--primary); font-size: 1.2rem;"></i>
            <h2 class="card-title">My Announcements</h2>
            <span class="card-count-badge"><?= count($my_announcements) ?> items</span>
        </div>
        <div class="card-header-right">
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Submit New Announcement
            </a>
        </div>
    </div>

    <div class="card-body">
        <!-- Filter Tabs & Search Bar -->
        <form method="GET" action="index.php" class="toolbar-bar">
            <div class="toolbar-filters">
                <div class="search-input-group">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" class="form-control-search" placeholder="Search my announcements..." value="<?= h($search) ?>">
                </div>

                <select name="status" class="form-select-filter" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending Review (<?= $pending_count ?>)</option>
                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved & Live (<?= $approved_count ?>)</option>
                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected (<?= $rejected_count ?>)</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>

                <?php if (!empty($search) || !empty($filter_status)): ?>
                    <a href="index.php" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($my_announcements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-feather-pointed"></i>
                </div>
                <h3 class="empty-state-title">No announcements found</h3>
                <p class="empty-state-desc">
                    <?= (!empty($search) || !empty($filter_status)) ? 'No announcements matched your search or status filter.' : 'You have not submitted any announcements yet. Click the button below to submit your first announcement!' ?>
                </p>
                <div style="margin-top: 1.25rem;">
                    <a href="create.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> Submit Announcement
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title & Subject</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Submitted Date</th>
                            <th>Admin Review Info</th>
                            <th style="text-align: right; min-width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_announcements as $ann): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($ann['title']) ?>
                                    </div>
                                    <?php if (!empty($ann['is_urgent'])): ?>
                                        <div style="margin-top: 0.2rem;">
                                            <span class="badge" style="background: #fee2e2; color: #b91c1c; font-size: 0.68rem;">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Urgent
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($ann['status'] === 'rejected' && !empty($ann['rejection_reason'])): ?>
                                        <div class="rejection-callout" style="margin-top: 0.6rem; font-size: 0.82rem; padding: 0.5rem 0.75rem;">
                                            <strong><i class="fa-solid fa-triangle-exclamation"></i> Rejection Reason:</strong>
                                            <?= nl2br(h($ann['rejection_reason'])) ?>
                                        </div>
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
                                <td>
                                    <?php if ($st === 'approved'): ?>
                                        <div style="color: #059669; font-weight: 600; font-size: 0.82rem;">
                                            <i class="fa-solid fa-check-double"></i> Approved by <?= h($ann['reviewer_name'] ?? 'Admin') ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= h(time_ago($ann['reviewed_at'])) ?></div>
                                    <?php elseif ($st === 'rejected'): ?>
                                        <div style="color: #dc2626; font-weight: 600; font-size: 0.82rem;">
                                            <i class="fa-solid fa-ban"></i> Reviewed by <?= h($ann['reviewer_name'] ?? 'Admin') ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= h(time_ago($ann['reviewed_at'])) ?></div>
                                    <?php else: ?>
                                        <div style="color: #d97706; font-size: 0.82rem;">
                                            <i class="fa-solid fa-hourglass-half"></i> Awaiting Administrator
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="showAnnouncementModal(<?= h(json_encode($ann)) ?>)">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>

                                        <?php if ($st === 'pending' || $st === 'rejected'): ?>
                                            <a href="edit.php?id=<?= (int)$ann['id'] ?>" class="btn btn-outline btn-sm" title="Edit and Resubmit">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= (int)$ann['id'] ?>, <?= h(json_encode($ann['title'])) ?>)" title="Delete announcement">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal-overlay" id="announcementDetailModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Announcement Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('announcementDetailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem;">
                <span class="category-pill" id="mCat">Category</span>
                <span class="badge" id="mStatus">Status</span>
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: auto;" id="mDate"></span>
            </div>

            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem;" id="mTitle"></h2>

            <div class="form-group" id="mRejectionBox" style="display: none;">
                <label class="form-label" style="color: var(--danger);">Administrator Rejection Feedback</label>
                <div class="rejection-callout" id="mRejectionText"></div>
            </div>

            <div id="myAnnouncementCarouselContainer"></div>

            <div class="form-group">
                <label class="form-label">Full Content</label>
                <div style="background: #ffffff; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.95rem; line-height: 1.65; white-space: pre-wrap;" id="mContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('announcementDetailModal')">Close</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteConfirmModal">
    <div class="modal-dialog" style="max-width: 440px;">
        <form method="POST" action="delete.php">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger);"><i class="fa-solid fa-trash"></i> Delete Announcement</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteConfirmModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this announcement?</p>
                <div style="padding: 0.75rem 1rem; background: #fff1f2; border: 1px solid #fecdd3; border-radius: var(--radius-md); font-weight: 600; color: #9f1239; margin-top: 0.75rem;" id="deleteTitleDisplay"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteConfirmModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAnnouncementModal(ann) {
    document.getElementById('mTitle').textContent = ann.title || '';
    document.getElementById('mCat').textContent = ann.category || 'General';
    document.getElementById('mDate').textContent = 'Submitted: ' + (ann.created_at || '');
    document.getElementById('mContent').textContent = ann.content || ann.summary || '';

    const statusEl = document.getElementById('mStatus');
    const st = (ann.status || 'pending').toLowerCase();
    statusEl.className = 'badge badge-status-' + st;
    statusEl.textContent = st.charAt(0).toUpperCase() + st.slice(1);

    const rejBox = document.getElementById('mRejectionBox');
    if (st === 'rejected' && ann.rejection_reason) {
        rejBox.style.display = 'block';
        document.getElementById('mRejectionText').textContent = ann.rejection_reason;
    } else {
        rejBox.style.display = 'none';
    }

    window.renderInstagramCarousel(ann.images || [], 'myAnnouncementCarouselContainer', '../');

    openModal('announcementDetailModal');
}

function confirmDelete(id, title) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteTitleDisplay').textContent = title;
    openModal('deleteConfirmModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

