<?php
/**
 * Student Dashboard & Announcement Feed
 * Strictly View-Only Access - Follows PDF Section 2.F & 4.F.
 */

define('STUDENT_INIT', true);
require_once __DIR__ . '/includes/auth.php';

$currentStudent = current_student();
$page_title = 'Student Announcement Board';
$page_subtitle = 'Official bulletins, campus advisories, exam schedules, and activities.';

$pdo = get_db();

// Search and Filter Params
$type = trim($_GET['type'] ?? 'home');
$category_filter = trim($_GET['cat'] ?? '');
$search = trim($_GET['q'] ?? '');

$announcements = [];
$urgent_announcements = [];
$upcoming_events = [];
$categories = [];

$widget_latest_count = 0;
$widget_important_count = 0;
$widget_events_count = 0;
$widget_notifications_count = 0;
$widget_saved_count = 0;

if ($pdo) {
    try {
        // Fetch published categories
        $catStmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
        $categories = $catStmt->fetchAll();

        // Build Query for Published Announcements Only
        $where = ["a.status IN ('approved', 'published')"];
        $params = [];

        // Sidebar Navigation Type Filters
        if ($type === 'university') {
            $where[] = "(a.target_audience = 'All' OR a.author_name LIKE '%President%' OR a.author_name LIKE '%University%')";
        } elseif ($type === 'academic') {
            $where[] = "(a.category = 'Academic Affairs' OR a.author_name LIKE '%Registrar%')";
        } elseif ($type === 'college') {
            $where[] = "(a.author_name LIKE '%College%')";
        } elseif ($type === 'department') {
            $where[] = "(a.author_name LIKE '%Department%')";
        } elseif ($type === 'student_affairs') {
            $where[] = "(a.category = 'Student Life' OR a.author_name LIKE '%Student Affairs%')";
        } elseif ($type === 'events') {
            $where[] = "(a.category = 'Campus Events')";
        }

        if (!empty($category_filter)) {
            $where[] = "a.category = ?";
            $params[] = $category_filter;
        }

        if (!empty($search)) {
            $where[] = "(a.title LIKE ? OR a.content LIKE ? OR a.author_name LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare("
            SELECT a.*, u.name AS author_real_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE {$whereSql}
            ORDER BY a.is_pinned DESC, a.is_urgent DESC, a.created_at DESC
        ");
        $stmt->execute($params);
        $announcements = $stmt->fetchAll();

        // Attach multi-photo carousel images
        if (!empty($announcements)) {
            $ids = implode(',', array_map('intval', array_column($announcements, 'id')));
            $imgStmt = $pdo->query("SELECT announcement_id, image_path FROM announcement_images WHERE announcement_id IN ({$ids}) ORDER BY id ASC");
            $all_imgs = [];
            foreach ($imgStmt->fetchAll() as $im) {
                $all_imgs[$im['announcement_id']][] = $im['image_path'];
            }
            foreach ($announcements as &$ann) {
                $ann['images'] = $all_imgs[$ann['id']] ?? (!empty($ann['image_path']) ? [$ann['image_path']] : []);
            }
            unset($ann);
        }

        // Compute Widgets Metrics
        $widget_latest_count = count($announcements);

        $stmtImp = $pdo->query("SELECT COUNT(*) FROM announcements WHERE status IN ('approved', 'published') AND (is_urgent = 1 OR is_pinned = 1)");
        $widget_important_count = (int)$stmtImp->fetchColumn();

        $stmtEve = $pdo->query("SELECT COUNT(*) FROM announcements WHERE status IN ('approved', 'published') AND category = 'Campus Events'");
        $widget_events_count = (int)$stmtEve->fetchColumn();

    } catch (PDOException $e) {
        // Safe fallback
    }
}

// Empty state handled dynamically in view if no announcements exist in database

require_once __DIR__ . '/includes/header.php';
?>

<!-- ==================================================================== -->
<!-- STUDENT DASHBOARD WIDGETS (PDF Section 2.F)                         -->
<!-- ==================================================================== -->
<div class="metrics-grid">
    <!-- Widget 1: Latest Announcements -->
    <a href="index.php?type=all" class="metric-card">
        <div class="metric-icon-box" style="background:#e0f2fe; color:#0284c7;">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($widget_latest_count) ?></span>
            <span class="metric-label">Latest Announcements</span>
            <span style="font-size: 0.72rem; color: #0284c7; font-weight: 600;">Active Bulletins</span>
        </div>
    </a>

    <!-- Widget 2: Important Announcements -->
    <a href="index.php?type=all&cat=Urgent+Alert" class="metric-card">
        <div class="metric-icon-box" style="background:#fee2e2; color:#ef4444;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($widget_important_count) ?></span>
            <span class="metric-label">Important Alerts</span>
            <span style="font-size: 0.72rem; color: #dc2626; font-weight: 700;">Pinned &amp; Urgent</span>
        </div>
    </a>

    <!-- Widget 3: Upcoming Events -->
    <a href="index.php?type=events" class="metric-card">
        <div class="metric-icon-box" style="background:#d1fae5; color:#059669;">
            <i class="fa-solid fa-calendar-star"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($widget_events_count) ?></span>
            <span class="metric-label">Upcoming Events</span>
            <span style="font-size: 0.72rem; color: #059669; font-weight: 600;">Campus Activities</span>
        </div>
    </a>

    <!-- Widget 4: Unread Notifications -->
    <a href="notifications.php" class="metric-card">
        <div class="metric-icon-box" style="background:#fef3c7; color:#d97706;">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value"><?= number_format($widget_notifications_count) ?></span>
            <span class="metric-label">Unread Alerts</span>
            <span style="font-size: 0.72rem; color: #d97706; font-weight: 600;">Broadcast Alerts</span>
        </div>
    </a>

    <!-- Widget 5: Saved Announcements -->
    <a href="saved.php" class="metric-card">
        <div class="metric-icon-box" style="background:#fdf2f8; color:#db2777;">
            <i class="fa-solid fa-bookmark"></i>
        </div>
        <div class="metric-info">
            <span class="metric-value" id="savedCountDisplay"><?= number_format($widget_saved_count) ?></span>
            <span class="metric-label">Saved Announcements</span>
            <span style="font-size: 0.72rem; color: #db2777; font-weight: 600;">Bookmarked</span>
        </div>
    </a>
</div>

<!-- ==================================================================== -->
<!-- SEARCH & FILTER TOOLBAR                                              -->
<!-- ==================================================================== -->
<div class="content-card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="index.php" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="type" value="<?= h($type) ?>">

            <div class="search-input-group" style="flex: 1; min-width: 240px;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" class="form-control-search" placeholder="Search announcements by keywords..." value="<?= h($search) ?>">
            </div>

            <select name="cat" class="form-select-filter" onchange="this.form.submit()" style="min-width: 180px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= h($c['name']) ?>" <?= $category_filter === $c['name'] ? 'selected' : '' ?>>
                        <?= h($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Search</button>

            <?php if (!empty($search) || !empty($category_filter) || $type !== 'home'): ?>
                <a href="index.php" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- ANNOUNCEMENT FEED CARDS                                              -->
<!-- ==================================================================== -->
<div class="student-feed">
    <?php if (empty($announcements)): ?>
        <div class="content-card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                    <h3 class="empty-state-title">No announcements found</h3>
                    <p class="empty-state-desc">There are no published announcements matching your search query or filter.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $ann): ?>
            <article class="student-announcement-card <?= !empty($ann['is_urgent']) ? 'is-urgent' : '' ?> <?= !empty($ann['is_pinned']) ? 'is-pinned' : '' ?>" id="post-<?= (int)$ann['id'] ?>">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <span class="category-pill"><?= h($ann['category'] ?? 'General') ?></span>
                        <?php if (!empty($ann['is_urgent'])): ?>
                            <span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:0.7rem; font-weight:700;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Urgent Alert
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($ann['is_pinned'])): ?>
                            <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.7rem; font-weight:700;">
                                <i class="fa-solid fa-thumbtack"></i> Pinned
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <button type="button" class="btn btn-outline btn-sm bookmark-btn" onclick="toggleSavePost(<?= (int)$ann['id'] ?>, <?= h(json_encode($ann['title'])) ?>)" id="bm-<?= (int)$ann['id'] ?>" title="Save for later">
                            <i class="fa-regular fa-bookmark"></i> <span>Save</span>
                        </button>
                    </div>
                </div>

                <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; line-height: 1.35; margin-bottom: 0.5rem;">
                    <?= h($ann['title']) ?>
                </h2>

                <p style="font-size: 0.92rem; color: #475569; line-height: 1.6; margin-bottom: 1rem;">
                    <?= nl2br(h($ann['summary'] ?? mb_substr(strip_tags($ann['content']), 0, 200) . '...')) ?>
                </p>

                <!-- Attached Photos Carousel if any -->
                <?php if (!empty($ann['images'])): ?>
                    <div id="carousel-post-<?= (int)$ann['id'] ?>" style="margin-bottom: 1rem;"></div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            window.renderInstagramCarousel(<?= json_encode($ann['images']) ?>, 'carousel-post-<?= (int)$ann['id'] ?>', '../');
                        });
                    </script>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 0.85rem; font-size: 0.82rem; color: #64748b; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span><i class="fa-solid fa-building-columns"></i> <?= h($ann['author_name'] ?? 'University Administration') ?></span>
                        <span><i class="fa-regular fa-calendar"></i> <?= h(format_datetime($ann['created_at'])) ?></span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span><i class="fa-solid fa-eye"></i> <?= number_format($ann['views_count'] ?? 0) ?> views</span>
                        <button type="button" class="btn btn-primary btn-sm" onclick="showAnnouncementDetail(<?= h(json_encode($ann)) ?>)">
                            Read Full Details <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Student Detail Modal -->
<div class="modal-overlay" id="studentDetailModal">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title">Announcement Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('studentDetailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap;">
                <span class="category-pill" id="mCat">Category</span>
                <span style="font-size: 0.8rem; color: #64748b; margin-left: auto;" id="mDate"></span>
            </div>

            <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;" id="mTitle"></h2>

            <div style="font-size: 0.85rem; color: #0284c7; font-weight: 600; margin-bottom: 1rem;" id="mAuthor"></div>

            <div id="mCarouselContainer"></div>

            <div class="form-group">
                <label class="form-label" style="font-weight: 700; color: #334155;">Full Content &amp; Instructions</label>
                <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.95rem; line-height: 1.7; white-space: pre-wrap;" id="mContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('studentDetailModal')">Close</button>
        </div>
    </div>
</div>

<script>
// Detail Modal Viewer
function showAnnouncementDetail(ann) {
    document.getElementById('mTitle').textContent = ann.title || '';
    document.getElementById('mCat').textContent = ann.category || 'General';
    document.getElementById('mAuthor').innerHTML = '<i class="fa-solid fa-building-columns"></i> Published by ' + (ann.author_name || 'Administration');
    document.getElementById('mDate').textContent = ann.created_at || '';
    document.getElementById('mContent').textContent = ann.content || ann.summary || '';

    window.renderInstagramCarousel(ann.images || [], 'mCarouselContainer', '../');

    // Record view asynchronously
    if (ann.id) {
        fetch('../record_view.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(ann.id)
        }).catch(() => {});
    }

    openModal('studentDetailModal');
}

// Bookmark / Save Posts in Local Storage
function getSavedPosts() {
    try {
        return JSON.parse(localStorage.getItem('iscp_saved_posts') || '[]');
    } catch(e) {
        return [];
    }
}

function updateBookmarkButtons() {
    const saved = getSavedPosts();
    const countEl = document.getElementById('savedCountDisplay');
    if (countEl) countEl.textContent = saved.length;

    document.querySelectorAll('.bookmark-btn').forEach(btn => {
        const id = parseInt(btn.id.replace('bm-', ''), 10);
        const isSaved = saved.some(p => p.id === id);
        if (isSaved) {
            btn.innerHTML = '<i class="fa-solid fa-bookmark" style="color: #f59e0b;"></i> <span>Saved</span>';
            btn.classList.add('btn-saved');
        } else {
            btn.innerHTML = '<i class="fa-regular fa-bookmark"></i> <span>Save</span>';
            btn.classList.remove('btn-saved');
        }
    });
}

function toggleSavePost(id, title) {
    let saved = getSavedPosts();
    const idx = saved.findIndex(p => p.id === id);
    if (idx >= 0) {
        saved.splice(idx, 1);
    } else {
        saved.push({ id, title, savedAt: new Date().toISOString() });
    }
    localStorage.setItem('iscp_saved_posts', JSON.stringify(saved));
    updateBookmarkButtons();
}

document.addEventListener('DOMContentLoaded', updateBookmarkButtons);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
