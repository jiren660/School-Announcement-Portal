<?php
/**
 * Shared Reusable Announcement Edit Form
 * Validates organizational scope and updates announcements.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Edit Announcement';
$page_subtitle = 'Update announcement contents, categories, audience, and publication settings.';
$activeNav = 'my_announcements.php';

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid announcement ID.');
    header('Location: my_announcements.php');
    exit;
}

if (!$pdo) {
    set_flash('danger', 'Database service is currently unreachable.');
    header('Location: my_announcements.php');
    exit;
}

// 1. Fetch Announcement
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->execute([$id]);
$ann = $stmt->fetch();

if (!$ann) {
    set_flash('warning', 'Announcement #' . $id . ' was not found.');
    header('Location: my_announcements.php');
    exit;
}

// 2. Validate Scope
if (!can_manage_announcement($currentUser, $ann)) {
    http_response_code(403);
    render_access_denied('You do not have permission to edit this announcement. It belongs to a different organizational scope.');
    exit;
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security validation failed (CSRF token mismatch).';
    } else {
        $title = trim($_POST['title'] ?? '');
        $category_name = trim($_POST['category'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 1);
        $target_audience = trim($_POST['target_audience'] ?? 'All');
        $summary = trim($_POST['summary'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        $action_type = trim($_POST['submission_action'] ?? 'publish');

        if (empty($title)) {
            $error = 'Announcement title is required.';
        } elseif (empty($content)) {
            $error = 'Content is required.';
        } else {
            if (empty($summary)) {
                $summary = mb_substr(strip_tags($content), 0, 180) . '...';
            }

            // Remove previous draft prefix if publishing
            if ($action_type === 'publish' && strpos($summary, '[Draft]') === 0) {
                $summary = trim(substr($summary, 7));
            }

            $new_status = ($action_type === 'draft') ? 'pending' : 'approved';
            if ($action_type === 'draft' && strpos($summary, '[Draft]') !== 0) {
                $summary = '[Draft] ' . $summary;
            }

            try {
                $now = date('Y-m-d H:i:s');
                $upd = $pdo->prepare("
                    UPDATE announcements
                    SET title = ?,
                        summary = ?,
                        content = ?,
                        category = ?,
                        category_id = ?,
                        target_audience = ?,
                        is_urgent = ?,
                        is_pinned = ?,
                        status = ?,
                        updated_at = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $title,
                    $summary,
                    $content,
                    $category_name,
                    $category_id,
                    $target_audience,
                    $is_urgent,
                    $is_pinned,
                    $new_status,
                    $now,
                    $id
                ]);

                // Upload additional images if provided
                if (!empty($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    save_announcement_images($id, $_FILES['images']);
                }

                set_flash('success', 'Announcement #' . $id . ' has been successfully updated.');
                header('Location: my_announcements.php');
                exit;

            } catch (PDOException $e) {
                $error = 'Database error updating announcement: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-pen-to-square" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Edit Announcement #<?= (int)$ann['id'] ?></h2>
        </div>
        <div class="card-header-right">
            <a href="my_announcements.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                <div class="alert-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="alert-text"><?= h($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?= (int)$ann['id'] ?>" enctype="multipart/form-data" id="editForm">
            <?= csrf_field() ?>
            <input type="hidden" name="submission_action" id="subAction" value="publish">

            <div class="form-group">
                <label for="title" class="form-label">Announcement Title <span style="color: #ef4444;">*</span></label>
                <input type="text" id="title" name="title" class="form-control" required maxlength="255" value="<?= h($_POST['title'] ?? $ann['title']) ?>">
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" name="category" class="form-control">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat['name']) ?>" <?= ($cat['name'] === ($ann['category'] ?? '')) ? 'selected' : '' ?>>
                                <?= h($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="target_audience" class="form-label">Target Audience</label>
                    <select id="target_audience" name="target_audience" class="form-control">
                        <option value="All" <?= ($ann['target_audience'] === 'All') ? 'selected' : '' ?>>All Campus Members</option>
                        <option value="Students" <?= ($ann['target_audience'] === 'Students') ? 'selected' : '' ?>>Students Only</option>
                        <option value="Faculty" <?= ($ann['target_audience'] === 'Faculty') ? 'selected' : '' ?>>Faculty &amp; Staff</option>
                        <option value="Parents" <?= ($ann['target_audience'] === 'Parents') ? 'selected' : '' ?>>Parents &amp; Guardians</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Publishing Scope Unit</label>
                <input type="text" class="form-control" value="<?= h($ann['author_name'] ?? $assignedUnit) ?>" readonly style="background: #f1f5f9; cursor: not-allowed; font-weight: 600;">
            </div>

            <div class="form-group">
                <label for="summary" class="form-label">Short Summary Teaser</label>
                <textarea id="summary" name="summary" class="form-control" rows="2"><?= h($_POST['summary'] ?? $ann['summary']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="content" class="form-label">Full Content <span style="color: #ef4444;">*</span></label>
                <textarea id="content" name="content" class="form-control" rows="8" required><?= h($_POST['content'] ?? $ann['content']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="images" class="form-label">Attach Additional Photos</label>
                <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/*">
            </div>

            <div style="display: flex; gap: 1.25rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.88rem; font-weight: 600;">
                    <input type="checkbox" name="is_urgent" value="1" <?= !empty($ann['is_urgent']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #ef4444;">
                    <span style="color: #dc2626;"><i class="fa-solid fa-triangle-exclamation"></i> Urgent Alert</span>
                </label>

                <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.88rem; font-weight: 600;">
                    <input type="checkbox" name="is_pinned" value="1" <?= !empty($ann['is_pinned']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #0284c7;">
                    <span style="color: #0369a1;"><i class="fa-solid fa-thumbtack"></i> Pin to Top</span>
                </label>
            </div>

            <div style="display: flex; gap: 0.75rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem;">
                <button type="submit" class="btn btn-primary" onclick="document.getElementById('subAction').value='publish'">
                    <i class="fa-solid fa-check"></i> Save Changes &amp; Publish
                </button>
                <button type="submit" class="btn btn-outline" onclick="document.getElementById('subAction').value='draft'">
                    <i class="fa-regular fa-floppy-disk"></i> Save as Draft
                </button>
                <a href="my_announcements.php" class="btn btn-outline" style="margin-left: auto;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
