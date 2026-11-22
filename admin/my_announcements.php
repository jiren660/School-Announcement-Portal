<?php
/**
 * Role-Scoped Announcements Management
 * Follows Role-Based Access Control from PDF Section 2 & 5.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'My Announcements';
if ($currentRole === ROLE_COLLEGE_ADMIN) {
    $page_title = 'College Announcements';
} elseif ($currentRole === ROLE_DEPARTMENT_ADMIN) {
    $page_title = 'Department Announcements';
} elseif ($currentRole === ROLE_OFFICE_ADMIN) {
    $page_title = 'Office Announcements';
}
$page_subtitle = 'Manage, edit, track status, and inspect readership for ' . $assignedUnit . '.';
$activeNav = 'my_announcements.php';

$pdo = get_db();

$filter_status = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

$announcements = [];
$total_count = 0;
$published_count = 0;
$pending_count = 0;
$draft_count = 0;

if ($pdo) {
    try {
        // Enforce role-based scope
        $where = ["1=1"];
        $params = [];

        if (!is_super_admin()) {
            if ($currentRole === ROLE_UNIVERSITY_ADMIN) {
                $where[] = "(a.created_by = ? OR a.target_audience = 'All')";
                $params[] = $currentUser['id'];
            } elseif ($currentRole === ROLE_OFFICE_ADMIN && !empty($currentUser['assigned_office'])) {
                $where[] = "(a.created_by = ? OR a.author_name LIKE ?)";
                $params[] = $currentUser['id'];
                $params[] = '%' . $currentUser['assigned_office'] . '%';
            } elseif ($currentRole === ROLE_COLLEGE_ADMIN && !empty($currentUser['assigned_college'])) {
                $where[] = "(a.created_by = ? OR a.author_name LIKE ?)";
                $params[] = $currentUser['id'];
                $params[] = '%' . $currentUser['assigned_college'] . '%';
            } elseif ($currentRole === ROLE_DEPARTMENT_ADMIN && !empty($currentUser['assigned_department'])) {
                $where[] = "(a.created_by = ? OR a.author_name LIKE ?)";
                $params[] = $currentUser['id'];
                $params[] = '%' . $currentUser['assigned_department'] . '%';
            } else {
                $where[] = "a.created_by = ?";
                $params[] = $currentUser['id'];
            }
        }

        // Metrics for this scope
        $scopeWhere = implode(' AND ', $where);
        $mStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('approved', 'published') THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'draft' OR summary LIKE '[Draft]%' THEN 1 ELSE 0 END) AS drafts
            FROM announcements a
            WHERE {$scopeWhere}
        ");
        $mStmt->execute($params);
        $mRow = $mStmt->fetch();
        $total_count = (int)($mRow['total'] ?? 0);
        $published_count = (int)($mRow['published'] ?? 0);
        $pending_count = (int)($mRow['pending'] ?? 0);
        $draft_count = (int)($mRow['drafts'] ?? 0);

        // Additional filter by status
        if ($filter_status === 'published') {
            $where[] = "a.status IN ('approved', 'published')";
        } elseif ($filter_status === 'pending') {
            $where[] = "a.status = 'pending'";
        } elseif ($filter_status === 'draft') {
            $where[] = "(a.status = 'draft' OR a.summary LIKE '[Draft]%')";
        } elseif ($filter_status === 'rejected') {
            $where[] = "a.status = 'rejected'";
        }

        // Filter by search
        if (!empty($search)) {
            $where[] = "(a.title LIKE ? OR a.content LIKE ? OR a.author_name LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $finalWhere = implode(' AND ', $where);
        $sql = "
            SELECT a.*, u.name AS author_real_name, u.email AS author_email,
                   rev.name AS reviewer_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN users rev ON a.reviewed_by = rev.id
            WHERE {$finalWhere}
            ORDER BY a.is_pinned DESC, a.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $announcements = $stmt->fetchAll();

    } catch (PDOException $e) {
        set_flash('danger', 'Error loading announcements: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Metric Summary Row -->
<div class="metrics-grid" style="margin-bottom: 1.5rem;">
    <a href="my_announcements.php" class="metric-card">
        <div class="metric-icon-box" style="background:#e0f2fe; color:#0284c7;">
            <i class="fa-solid fa-folder-open"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($total_count) ?></span>
            <span class="metric-label">Total In Scope</span>
        </div>
    </a>
    <a href="my_announcements.php?status=published" class="metric-card">
        <div class="metric-icon-box icon-approved">
            <i class="fa-solid fa-globe"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($published_count) ?></span>
            <span class="metric-label">Published</span>
        </div>
    </a>
    <a href="my_announcements.php?status=pending" class="metric-card">
        <div class="metric-icon-box icon-pending">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($pending_count) ?></span>
            <span class="metric-label">Pending / In Review</span>
        </div>
    </a>
    <a href="my_announcements.php?status=draft" class="metric-card">
        <div class="metric-icon-box" style="background:#fef3c7; color:#d97706;">
            <i class="fa-solid fa-file-pen"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($draft_count) ?></span>
            <span class="metric-label">Saved Drafts</span>
        </div>
    </a>
</div>

<!-- Announcements Table Container -->
<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-list-check" style="color: var(--primary);"></i>
            <h2 class="card-title"><?= h($page_title) ?></h2>
            <span class="card-count-badge"><?= count($announcements) ?> records</span>
        </div>
        <div class="card-header-right">
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> New Announcement
            </a>
        </div>
    </div>

    <div class="card-body">
        <!-- Search and Filter Form -->
        <form method="GET" action="my_announcements.php" class="toolbar-bar">
            <div class="toolbar-filters">
                <div class="search-input-group">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" class="form-control-search" placeholder="Search title or content..." value="<?= h($search) ?>">
                </div>

                <select name="status" class="form-select-filter" onchange="this.form.submit()">
                    <option value="">All Statuses (<?= $total_count ?>)</option>
                    <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Published (<?= $published_count ?>)</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending Review (<?= $pending_count ?>)</option>
                    <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Drafts (<?= $draft_count ?>)</option>
                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($search) || !empty($filter_status)): ?>
                    <a href="my_announcements.php" class="btn btn-outline btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                <h3 class="empty-state-title">No announcements found</h3>
                <p class="empty-state-desc">No announcements matched your search or status filter within <?= h($assignedUnit) ?>.</p>
                <div style="margin-top: 1rem;">
                    <a href="create.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Create Announcement</a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Announcement Title</th>
                            <th>Category</th>
                            <th>Publishing Scope</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align: right; min-width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $ann): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($ann['title']) ?>
                                    </div>
                                    <?php if (!empty($ann['is_urgent'])): ?>
                                        <span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:0.68rem; margin-top:0.2rem;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Urgent
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($ann['is_pinned'])): ?>
                                        <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.68rem; margin-top:0.2rem;">
                                            <i class="fa-solid fa-thumbtack"></i> Pinned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="category-pill"><?= h($ann['category'] ?? 'General') ?></span></td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 600; color: #334155;">
                                        <?= h($ann['author_name'] ?? $assignedUnit) ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: var(--text-muted);">
                                        Audience: <?= h($ann['target_audience'] ?? 'All') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $st = strtolower($ann['status']);
                                    $is_draft = strpos($ann['summary'] ?? '', '[Draft]') === 0;
                                    $badgeClass = 'badge-status-pending';
                                    $statusIcon = 'fa-clock';
                                    $displayStatus = ucfirst($st);

                                    if ($is_draft) {
                                        $badgeClass = 'badge';
                                        $statusIcon = 'fa-file-pen';
                                        $displayStatus = 'Draft';
                                    } elseif (in_array($st, ['approved', 'published'], true)) {
                                        $badgeClass = 'badge-status-approved';
                                        $statusIcon = 'fa-circle-check';
                                        $displayStatus = 'Published';
                                    } elseif ($st === 'rejected') {
                                        $badgeClass = 'badge-status-rejected';
                                        $statusIcon = 'fa-ban';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>" <?= $is_draft ? 'style="background:#fef3c7; color:#d97706;"' : '' ?>>
                                        <i class="fa-solid <?= $statusIcon ?>"></i> <?= $displayStatus ?>
                                    </span>
                                </td>
                                <td>
                                    <span title="<?= h(format_datetime($ann['created_at'])) ?>">
                                        <?= h(time_ago($ann['created_at'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <a href="edit.php?id=<?= (int)$ann['id'] ?>" class="btn btn-outline btn-sm" title="Edit Announcement">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= (int)$ann['id'] ?>, <?= h(json_encode($ann['title'])) ?>)" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-dialog" style="max-width: 440px;">
        <form method="POST" action="delete.php">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger);"><i class="fa-solid fa-trash"></i> Delete Announcement</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete this announcement?</p>
                <div style="padding: 0.75rem 1rem; background: #fff1f2; border: 1px solid #fecdd3; border-radius: var(--radius-md); font-weight: 600; color: #9f1239; margin-top: 0.75rem;" id="deleteTitle"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(id, title) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteTitle').textContent = title;
    openModal('deleteModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
