<?php
/**
 * Scheduled Announcements View
 * Filtered to upcoming scheduled announcements within the administrator's scope.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Scheduled Announcements';
$page_subtitle = 'Announcements scheduled for automatic broadcast at a future date and time.';
$activeNav = 'scheduled.php';

$pdo = get_db();
$scheduled = [];

if ($pdo) {
    try {
        $where = ["(a.status = 'scheduled' OR a.summary LIKE '[Scheduled:%')"];
        $params = [];

        if (!is_super_admin()) {
            $where[] = "a.created_by = ?";
            $params[] = $currentUser['id'];
        }

        $sql = "
            SELECT a.*, u.name AS author_real_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $scheduled = $stmt->fetchAll();
    } catch (PDOException $e) {
        set_flash('danger', 'Error loading scheduled posts: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-calendar-days" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Scheduled Bulletins</h2>
            <span class="card-count-badge"><?= count($scheduled) ?> items</span>
        </div>
        <div class="card-header-right">
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Schedule New
            </a>
        </div>
    </div>

    <div class="card-body">
        <?php if (empty($scheduled)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-calendar"></i></div>
                <h3 class="empty-state-title">No announcements scheduled</h3>
                <p class="empty-state-desc">You can set publication dates and times in advance when composing an announcement.</p>
                <div style="margin-top: 1rem;">
                    <a href="create.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Schedule Announcement</a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Scope</th>
                            <th>Scheduled Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduled as $s): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($s['title']) ?>
                                    </div>
                                </td>
                                <td><span class="category-pill"><?= h($s['category'] ?? 'General') ?></span></td>
                                <td><span style="font-weight: 600; font-size: 0.85rem;"><?= h($s['author_name'] ?? $assignedUnit) ?></span></td>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1;"><i class="fa-regular fa-clock"></i> Upcoming</span></td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <a href="edit.php?id=<?= (int)$s['id'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                        <a href="delete.php?id=<?= (int)$s['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel and delete this scheduled post?');"><i class="fa-solid fa-trash"></i></a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
