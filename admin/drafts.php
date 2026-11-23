<?php
/**
 * Draft Announcements View
 * Filtered to unpublished draft announcements within the administrator's scope.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Draft Announcements';
$page_subtitle = 'Unpublished announcements saved for later review, revision, or scheduled broadcasting.';
$activeNav = 'drafts.php';

$pdo = get_db();
$drafts = [];

if ($pdo) {
    try {
        $where = ["(a.status = 'draft' OR a.summary LIKE '[Draft]%')"];
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
            ORDER BY a.updated_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $drafts = $stmt->fetchAll();
    } catch (PDOException $e) {
        set_flash('danger', 'Error loading drafts: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-file-pen" style="color: #d97706; font-size: 1.25rem;"></i>
            <h2 class="card-title">Saved Drafts</h2>
            <span class="card-count-badge" style="background: #fef3c7; color: #d97706;">
                <?= count($drafts) ?> drafts
            </span>
        </div>
        <div class="card-header-right">
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Compose New
            </a>
        </div>
    </div>

    <div class="card-body">
        <?php if (empty($drafts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-file"></i></div>
                <h3 class="empty-state-title">No drafts saved</h3>
                <p class="empty-state-desc">When you save announcements as drafts instead of publishing immediately, they will be listed here.</p>
                <div style="margin-top: 1rem;">
                    <a href="create.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Start a Draft</a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Draft Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Publishing Scope</th>
                            <th>Last Modified</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drafts as $d): ?>
                            <tr style="cursor: pointer;" onclick="if (!event.target.closest('a, button')) window.location.href='edit.php?id=<?= (int)$d['id'] ?>'">
                                <td>
                                    <a href="edit.php?id=<?= (int)$d['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                                        <div style="font-weight: 700; color: var(--primary); font-size: 0.95rem;">
                                            <i class="fa-regular fa-file-lines" style="margin-right: 0.35rem; color: #d97706;"></i>
                                            <?= h($d['title']) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem;">
                                            <?= h(mb_substr(str_replace('[Draft]', '', $d['summary'] ?? ''), 0, 90)) ?>...
                                        </div>
                                    </a>
                                </td>
                                <td><span class="category-pill"><?= h($d['category'] ?? 'General') ?></span></td>
                                <td><span class="badge" style="background: #fef3c7; color: #b45309; font-weight: 700;"><i class="fa-solid fa-file-pen"></i> Draft</span></td>
                                <td><span style="font-weight: 600; font-size: 0.85rem; color: #334155;"><?= h($d['publishing_unit'] ?? $d['author_name'] ?? $assignedUnit) ?></span></td>
                                <td><?= h(time_ago($d['updated_at'] ?? $d['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <a href="edit.php?id=<?= (int)$d['id'] ?>" class="btn btn-primary btn-sm" title="Click to edit and modify draft">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit &amp; Publish
                                        </a>
                                        <a href="delete.php?id=<?= (int)$d['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this draft?');" title="Delete draft">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
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
