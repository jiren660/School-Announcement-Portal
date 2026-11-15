<?php
/**
 * Rejected Announcements Page
 * Table showing reason, reviewer, and date.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$page_title = 'Rejected Announcements';
$page_subtitle = 'Audit trail of rejected announcement submissions with feedback reasons and reviewer details.';
$activeNav = 'rejected.php';

$pdo = get_db();

$search = trim($_GET['q'] ?? '');
$filter_category = trim($_GET['cat'] ?? '');
$rejected_list = [];
$categories = [];

if ($pdo) {
    try {
        $categories = $pdo->query("SELECT DISTINCT category FROM announcements WHERE status = 'rejected' AND category IS NOT NULL ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

        $where = ["a.status = 'rejected'"];
        $params = [];

        if (!empty($search)) {
            $where[] = "(a.title LIKE ? OR a.rejection_reason LIKE ? OR u.name LIKE ? OR rev.name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filter_category)) {
            $where[] = "a.category = ?";
            $params[] = $filter_category;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT a.*, 
                   u.name AS author_name, u.email AS author_email,
                   rev.name AS reviewer_name, rev.email AS reviewer_email
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN users rev ON a.reviewed_by = rev.id
            WHERE {$whereClause}
            ORDER BY a.reviewed_at DESC, a.updated_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rejected_list = $stmt->fetchAll();

        // Attach images
        if (!empty($rejected_list)) {
            $ids = implode(',', array_map('intval', array_column($rejected_list, 'id')));
            $imgStmt = $pdo->query("SELECT announcement_id, image_path FROM announcement_images WHERE announcement_id IN ({$ids}) ORDER BY id ASC");
            $rej_images = [];
            foreach ($imgStmt->fetchAll() as $im) {
                $rej_images[$im['announcement_id']][] = $im['image_path'];
            }
            foreach ($rejected_list as &$rAnn) {
                $rAnn['images'] = $rej_images[$rAnn['id']] ?? (!empty($rAnn['image_path']) ? [$rAnn['image_path']] : []);
            }
            unset($rAnn);
        }

    } catch (PDOException $e) {
        set_flash('danger', 'Error retrieving rejected announcements: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-ban" style="color: var(--danger); font-size: 1.25rem;"></i>
            <h2 class="card-title">Rejected Submissions Log</h2>
            <span class="card-count-badge" style="background: var(--danger-light); color: var(--danger);">
                <?= count($rejected_list) ?> rejected
            </span>
        </div>
        <div class="card-header-right">
            <span style="font-size: 0.85rem; color: var(--text-muted);">
                Announcements rejected by administrators along with justification notes.
            </span>
        </div>
    </div>

    <div class="card-body">
        <!-- Search & Filter Bar -->
        <form method="GET" action="rejected.php" class="toolbar-bar">
            <div class="toolbar-filters">
                <div class="search-input-group">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" class="form-control-search" placeholder="Search by title, reason, reviewer, author..." value="<?= h($search) ?>">
                </div>

                <?php if (!empty($categories)): ?>
                    <select name="cat" class="form-select-filter" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>>
                                <?= h($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <?php if (!empty($search) || !empty($filter_category)): ?>
                    <a href="rejected.php" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($rejected_list)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
                <h3 class="empty-state-title">No rejected announcements</h3>
                <p class="empty-state-desc">
                    <?= (!empty($search) || !empty($filter_category)) ? 'No rejected records matched your query.' : 'There are currently no rejected announcements in the system.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Announcement Title</th>
                            <th>Category</th>
                            <th>Author (Announcer)</th>
                            <th style="min-width: 250px;">Rejection Reason</th>
                            <th>Reviewer</th>
                            <th>Date Reviewed</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejected_list as $ann): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($ann['title']) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        Created: <?= h(format_datetime($ann['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="category-pill"><?= h($ann['category'] ?? 'General') ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= h($ann['author_name'] ?? 'Announcer') ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?= h($ann['author_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="rejection-callout" style="padding: 0.6rem 0.8rem; font-size: 0.85rem;">
                                        <?= nl2br(h($ann['rejection_reason'] ?? 'No reason recorded.')) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #0f172a;">
                                        <i class="fa-solid fa-user-shield" style="color: var(--primary); font-size: 0.85rem; margin-right: 0.25rem;"></i>
                                        <?= h($ann['reviewer_name'] ?? 'Administrator') ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= h($ann['reviewer_email'] ?? '') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; font-size: 0.85rem;">
                                        <?= h(format_datetime($ann['reviewed_at'])) ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= h(time_ago($ann['reviewed_at'])) ?>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="showRejectedModal(<?= h(json_encode($ann)) ?>)">
                                        <i class="fa-solid fa-eye"></i> Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rejected Detail Modal -->
<div class="modal-overlay" id="rejectedModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" style="color: var(--danger);">
                <i class="fa-solid fa-ban"></i> Rejected Announcement Details
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('rejectedModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem;">
                <span class="category-pill" id="rejCat">Category</span>
                <span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> Rejected</span>
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: auto;" id="rejReviewedDate"></span>
            </div>

            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem;" id="rejTitle"></h2>

            <div id="rejCarouselContainer"></div>

            <div class="form-group">
                <label class="form-label" style="color: var(--danger); font-weight: 700;">Rejection Reason & Feedback</label>
                <div class="rejection-callout" id="rejReasonBody" style="font-size: 0.95rem; line-height: 1.6;"></div>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <div style="margin-bottom: 0.25rem;"><strong>Submitted By:</strong> <span id="rejAuthor"></span></div>
                <div style="margin-bottom: 0.25rem;"><strong>Reviewed By Admin:</strong> <span id="rejReviewer"></span></div>
                <div><strong>Submission Date:</strong> <span id="rejCreatedDate"></span></div>
            </div>

            <div class="form-group">
                <label class="form-label">Original Content Submitted</label>
                <div style="background: #ffffff; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.92rem; line-height: 1.65; white-space: pre-wrap;" id="rejContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('rejectedModal')">Close</button>
        </div>
    </div>
</div>

<script>
function showRejectedModal(ann) {
    document.getElementById('rejTitle').textContent = ann.title || '';
    document.getElementById('rejCat').textContent = ann.category || 'General';
    document.getElementById('rejReviewedDate').textContent = 'Reviewed: ' + (ann.reviewed_at || 'N/A');
    document.getElementById('rejAuthor').textContent = (ann.author_name || 'Announcer') + (ann.author_email ? ' (' + ann.author_email + ')' : '');
    document.getElementById('rejReviewer').textContent = (ann.reviewer_name || 'Administrator') + (ann.reviewer_email ? ' (' + ann.reviewer_email + ')' : '');
    document.getElementById('rejCreatedDate').textContent = ann.created_at || 'N/A';
    document.getElementById('rejReasonBody').textContent = ann.rejection_reason || 'No specific reason given.';
    document.getElementById('rejContent').textContent = ann.content || ann.summary || '';

    openModal('rejectedModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

