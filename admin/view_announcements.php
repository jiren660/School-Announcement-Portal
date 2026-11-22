<?php
/**
 * View Approved Announcements Page
 * Table of approved announcements with live search, category filtering, and pagination.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$page_title = 'View Announcements';
$page_subtitle = 'List of all approved announcements currently published to the school portal.';
$activeNav = 'view_announcements.php';

$pdo = get_db();

// Search and Filter parameters
$search = trim($_GET['q'] ?? '');
$filter_category = trim($_GET['cat'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 8;
$offset = ($page - 1) * $per_page;

$announcements = [];
$categories = [];
$total_count = 0;

if ($pdo) {
    try {
        // Fetch categories for the filter dropdown
        $categories = $pdo->query("SELECT DISTINCT category FROM announcements WHERE status = 'approved' AND category IS NOT NULL ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
        
        // Base query
        $status_param = trim($_GET['status'] ?? '');
        if ($status_param === 'pending') {
            $where = ["a.status = 'pending'"];
        } elseif ($status_param === 'rejected') {
            $where = ["a.status = 'rejected'"];
        } else {
            $where = ["a.status IN ('approved', 'published')"];
        }
        $params = [];

        if (!empty($search)) {
            $where[] = "(a.title LIKE ? OR a.content LIKE ? OR u.name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filter_category)) {
            $where[] = "a.category = ?";
            $params[] = $filter_category;
        }

        $whereClause = implode(' AND ', $where);

        // Count total matching records
        $countSql = "
            SELECT COUNT(*) 
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE {$whereClause}
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total_count = (int)$countStmt->fetchColumn();

        // Fetch paginated records
        $fetchSql = "
            SELECT a.*, 
                   u.name AS author_name, u.email AS author_email,
                   rev.name AS reviewer_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN users rev ON a.reviewed_by = rev.id
            WHERE {$whereClause}
            ORDER BY a.is_pinned DESC, a.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $fetchParams = array_merge($params, [$per_page, $offset]);
        $fetchStmt = $pdo->prepare($fetchSql);
        $fetchStmt->execute($fetchParams);
        $announcements = $fetchStmt->fetchAll();

        // Attach images
        $imgStmt = $pdo->query("SELECT announcement_id, image_path FROM announcement_images ORDER BY id ASC");
        $all_images = [];
        foreach ($imgStmt->fetchAll() as $im) {
            $all_images[$im['announcement_id']][] = $im['image_path'];
        }

        foreach ($announcements as &$annItem) {
            $annItem['images'] = $all_images[$annItem['id']] ?? (!empty($annItem['image_path']) ? [$annItem['image_path']] : []);
        }
        unset($annItem);

    } catch (PDOException $e) {
        set_flash('danger', 'Error retrieving announcements: ' . $e->getMessage());
    }
}

$total_pages = ceil($total_count / $per_page);

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-circle-check" style="color: var(--success); font-size: 1.25rem;"></i>
            <h2 class="card-title">Approved Announcements</h2>
            <span class="card-count-badge"><?= $total_count ?> total</span>
        </div>
        <div class="card-header-right">
            <a href="../index.php" target="_blank" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Live Public View
            </a>
        </div>
    </div>

    <div class="card-body">
        <!-- Search & Filter Bar -->
        <form method="GET" action="view_announcements.php" class="toolbar-bar">
            <div class="toolbar-filters">
                <!-- Search Input -->
                <div class="search-input-group">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" class="form-control-search" placeholder="Search by title, author, content..." value="<?= h($search) ?>">
                </div>

                <!-- Category Filter -->
                <select name="cat" class="form-select-filter" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= h($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>>
                            <?= h($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <?php if (!empty($search) || !empty($filter_category)): ?>
                    <a href="view_announcements.php" class="btn btn-outline btn-sm" title="Clear filters">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Announcements Table -->
        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-file-magnifying-glass"></i>
                </div>
                <h3 class="empty-state-title">No approved announcements found</h3>
                <p class="empty-state-desc">
                    <?= (!empty($search) || !empty($filter_category)) ? 'No results matched your search or category filter. Try clearing the filters.' : 'There are currently no approved announcements.' ?>
                </p>
                <?php if (!empty($search) || !empty($filter_category)): ?>
                    <div style="margin-top: 1rem;">
                        <a href="view_announcements.php" class="btn btn-outline btn-sm">Clear All Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table" id="approvedAnnouncementsTable">
                    <thead>
                        <tr>
                            <th>Announcement</th>
                            <th>Category</th>
                            <th>Author (Announcer)</th>
                            <th>Reviewed By</th>
                            <th>Views</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $ann): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($ann['title']) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; gap: 0.5rem; align-items: center;">
                                        <span><i class="fa-regular fa-calendar"></i> <?= h(format_datetime($ann['created_at'])) ?></span>
                                        <?php if (!empty($ann['is_urgent'])): ?>
                                            <span class="badge" style="background: #fee2e2; color: #b91c1c; font-size: 0.68rem;">Urgent</span>
                                        <?php endif; ?>
                                        <?php if (!empty($ann['is_pinned'])): ?>
                                            <span class="badge" style="background: #e0f2fe; color: #0284c7; font-size: 0.68rem;">Pinned</span>
                                        <?php endif; ?>
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
                                    <div style="font-weight: 500; color: #0f172a;">
                                        <?= h($ann['reviewer_name'] ?? 'Administrator') ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= !empty($ann['reviewed_at']) ? h(time_ago($ann['reviewed_at'])) : 'Auto-Approved' ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">
                                        <i class="fa-regular fa-eye"></i> <?= number_format($ann['views_count'] ?? 0) ?> views
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="viewAnnouncementModal(<?= h(json_encode($ann)) ?>)">
                                        <i class="fa-solid fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div>
                        Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_count) ?> of <?= $total_count ?> announcements
                    </div>
                    <ul class="pagination-links">
                        <!-- Prev -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        </li>

                        <!-- Page numbers -->
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next -->
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- View Announcement Modal -->
<div class="modal-overlay" id="announcementModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Approved Announcement Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('announcementModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem;">
                <span class="category-pill" id="modalCat">Category</span>
                <span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Approved</span>
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: auto;" id="modalDate"></span>
            </div>

            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem;" id="modalTitle"></h2>

            <!-- Instagram-Style Carousel -->
            <div id="approvedModalCarouselContainer"></div>

            <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <div style="margin-bottom: 0.25rem;"><strong>Author:</strong> <span id="modalAuthor"></span></div>
                <div><strong>Reviewed By:</strong> <span id="modalReviewer"></span></div>
            </div>

            <div class="form-group">
                <label class="form-label">Full Content</label>
                <div style="background: #ffffff; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.95rem; line-height: 1.65; white-space: pre-wrap;" id="modalContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('announcementModal')">Close</button>
        </div>
    </div>
</div>

<script>
function viewAnnouncementModal(ann) {
    document.getElementById('modalTitle').textContent = ann.title || '';
    document.getElementById('modalCat').textContent = ann.category || 'General';
    document.getElementById('modalDate').textContent = 'Created: ' + (ann.created_at || '');
    document.getElementById('modalAuthor').textContent = (ann.author_name || 'Announcer') + (ann.author_email ? ' (' + ann.author_email + ')' : '');
    document.getElementById('modalReviewer').textContent = (ann.reviewer_name || 'Admin') + (ann.reviewed_at ? ' on ' + ann.reviewed_at : '');
    document.getElementById('modalContent').textContent = ann.content || ann.summary || '';

    // Render Instagram Carousel
    window.renderInstagramCarousel(ann.images || [], 'approvedModalCarouselContainer', '../');

    openModal('announcementModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

