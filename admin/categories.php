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

// Handle POST Actions: Add, Edit, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = trim($_POST['action'] ?? 'add');

        // 1. ADD CATEGORY
        if ($action === 'add') {
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
            }
        }

        // 2. EDIT CATEGORY
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $badge_color = trim($_POST['badge_color'] ?? 'primary');

            if ($id <= 0 || empty($name)) {
                set_flash('danger', 'Valid category ID and name are required.');
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                if ($pdo) {
                    try {
                        $upd = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, badge_color = ? WHERE id = ?");
                        $upd->execute([$name, $slug, $badge_color, $id]);
                        set_flash('success', 'Category "' . h($name) . '" updated successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error updating category: ' . $e->getMessage());
                    }
                }
            }
        }

        // 3. DELETE CATEGORY
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                set_flash('danger', 'Invalid category ID.');
            } else {
                if ($pdo) {
                    try {
                        // Unlink category from announcements first for safe deletion
                        $unl = $pdo->prepare("UPDATE announcements SET category_id = NULL WHERE category_id = ?");
                        $unl->execute([$id]);

                        $del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                        $del->execute([$id]);
                        set_flash('success', 'Category has been deleted successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error deleting category: ' . $e->getMessage());
                    }
                }
            }
        }

        header('Location: categories.php');
        exit;
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
        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-tags"></i></div>
                <h3 class="empty-state-title">No categories created yet</h3>
                <p class="empty-state-desc">Categories help organize and filter campus announcements.</p>
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addCatModal')">
                        <i class="fa-solid fa-plus"></i> Add First Category
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Category Name</th>
                            <th>Slug Identifier</th>
                            <th>Badge Preview</th>
                            <th style="text-align: right; width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><span style="font-weight: 700; color: var(--text-muted);">#<?= (int)$cat['id'] ?></span></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($cat['name']) ?>
                                    </div>
                                </td>
                                <td><code><?= h($cat['slug']) ?></code></td>
                                <td>
                                    <span class="badge badge-<?= h($cat['badge_color'] ?? 'primary') ?>" style="font-size: 0.82rem; padding: 0.35rem 0.75rem;">
                                        <i class="fa-solid fa-tag"></i> <?= h($cat['name']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openEditCategoryModal(<?= h(json_encode($cat)) ?>)" title="Edit category">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="openDeleteCategoryModal(<?= (int)$cat['id'] ?>, <?= h(json_encode($cat['name'])) ?>)" title="Delete category">
                                            <i class="fa-solid fa-trash"></i> Delete
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

<!-- Add Category Modal -->
<div class="modal-overlay" id="addCatModal">
    <div class="modal-dialog">
        <form method="POST" action="categories.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-tag"></i> Add New Category</h3>
                <button type="button" class="modal-close" onclick="closeModal('addCatModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Health & Safety" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Badge Theme Color <span class="required">*</span></label>
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
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal-overlay" id="editCatModal">
    <div class="modal-dialog">
        <form method="POST" action="categories.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_cat_id">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Category</h3>
                <button type="button" class="modal-close" onclick="closeModal('editCatModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_cat_name" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Badge Theme Color <span class="required">*</span></label>
                    <select name="badge_color" id="edit_cat_color" class="form-control">
                        <option value="primary">Primary (Blue)</option>
                        <option value="danger">Danger (Red)</option>
                        <option value="success">Success (Green)</option>
                        <option value="warning">Warning (Amber)</option>
                        <option value="info">Info (Cyan)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editCatModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal-overlay" id="deleteCatModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="categories.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_cat_id">
            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger);"><i class="fa-solid fa-trash"></i> Delete Category</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteCatModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 0.75rem;">Are you sure you want to permanently delete this announcement category?</p>
                <div style="padding: 0.85rem 1rem; border-radius: var(--radius-md); background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; font-weight: 700;" id="deleteCatNameDisplay"></div>
                <p style="font-size: 0.82rem; color: #dc2626; margin-top: 1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> Announcements assigned to this category will have their category unlinked.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteCatModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCategoryModal(cat) {
    document.getElementById('edit_cat_id').value = cat.id;
    document.getElementById('edit_cat_name').value = cat.name;
    document.getElementById('edit_cat_color').value = cat.badge_color || 'primary';
    openModal('editCatModal');
}

function openDeleteCategoryModal(id, name) {
    document.getElementById('delete_cat_id').value = id;
    document.getElementById('deleteCatNameDisplay').textContent = name;
    openModal('deleteCatModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
