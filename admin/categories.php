<?php
/**
 * Categories Management Page (Super Administrator)
 * Follows PDF Section 2.A & 5.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_specific_role([ROLE_SUPER_ADMIN, 'admin']);

$currentUser = current_user();
$page_title = 'Announcement Categories';
$page_subtitle = 'Configure announcement categories, tags, badge colors, and topic classifications.';
$activeNav = 'categories.php';

$pdo = get_db();
$categories = [];

if ($pdo) {
    try {
        $categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
    } catch (Exception $e) {
        $categories = [];
    }
}

// Add Category Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $badge_color = trim($_POST['badge_color'] ?? 'primary');
        $icon = 'bullhorn';

        if (empty($name)) {
            set_flash('danger', 'Category name is required.');
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
            if ($pdo) {
                try {
                    $ins = $pdo->prepare("INSERT INTO categories (name, slug, badge_color, icon) VALUES (?, ?, ?, ?)");
                    $ins->execute([$name, $slug, $badge_color, $icon]);
                    set_flash('success', 'Category "' . h($name) . '" has been added.');
                } catch (Exception $e) {
                    set_flash('danger', 'Error adding category: ' . $e->getMessage());
                }
            }
            header('Location: categories.php');
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-tags" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Portal Announcement Categories</h2>
            <span class="card-count-badge"><?= count($categories) ?> categories</span>
        </div>
        <div class="card-header-right">
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addCatModal')">
                <i class="fa-solid fa-plus"></i> New Category
            </button>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Slug</th>
                        <th>Badge Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>#<?= (int)$cat['id'] ?></td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    <?= h($cat['name']) ?>
                                </div>
                            </td>
                            <td><code><?= h($cat['slug']) ?></code></td>
                            <td>
                                <span class="category-pill"><?= h($cat['name']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="addCatModal">
    <div class="modal-dialog">
        <form method="POST" action="categories.php">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-tag"></i> Add New Category</h3>
                <button type="button" class="modal-close" onclick="closeModal('addCatModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Health & Safety" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Badge Theme Color</label>
                    <select name="badge_color" class="form-control">
                        <option value="primary">Primary (Blue)</option>
                        <option value="danger">Danger (Red)</option>
                        <option value="success">Success (Green)</option>
                        <option value="warning">Warning (Amber)</option>
                        <option value="info">Info (Cyan)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addCatModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
