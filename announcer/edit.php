<?php
/**
 * Announcer Portal - Edit Announcement
 * Allows updating details, adding more photos, and resubmitting.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_announcer();

$page_title = 'Edit Announcement';
$page_subtitle = 'Update announcement details and resubmit for administrative review.';
$activeNav = 'index.php';

$pdo = get_db();
$currentUser = current_user();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 || !$pdo) {
    set_flash('danger', 'Invalid announcement ID.');
    header('Location: index.php');
    exit;
}

// Fetch announcement and verify ownership
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ? AND created_by = ?");
$stmt->execute([$id, $currentUser['id']]);
$announcement = $stmt->fetch();

if (!$announcement) {
    set_flash('danger', 'Announcement not found or access denied.');
    header('Location: index.php');
    exit;
}

if ($announcement['status'] === 'approved') {
    set_flash('warning', 'Approved announcements cannot be edited directly. Please coordinate with an Administrator.');
    header('Location: index.php');
    exit;
}

// Fetch existing photos
$existingImages = get_announcement_images($id);
$existingImagePaths = array_column($existingImages, 'image_path');

$categories = [];
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$errors = [];
$form_data = [
    'title' => $announcement['title'],
    'category' => $announcement['category'],
    'content' => $announcement['content'],
    'is_urgent' => (int)($announcement['is_urgent'] ?? 0)
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid. Please resubmit the form.';
    } else {
        $form_data['title'] = trim($_POST['title'] ?? '');
        $form_data['category'] = trim($_POST['category'] ?? '');
        $form_data['content'] = trim($_POST['content'] ?? '');
        $form_data['is_urgent'] = isset($_POST['is_urgent']) ? 1 : 0;

        if (empty($form_data['title'])) {
            $errors[] = 'Title is required.';
        }
        if (empty($form_data['category'])) {
            $errors[] = 'Category is required.';
        }
        if (empty($form_data['content'])) {
            $errors[] = 'Content is required.';
        }

        if (empty($errors)) {
            try {
                $catStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
                $catStmt->execute([$form_data['category']]);
                $categoryId = $catStmt->fetchColumn() ?: 1;

                $auto_summary = mb_substr(strip_tags($form_data['content']), 0, 180) . '...';

                // Update and reset to pending review (clears previous rejection reason if any)
                $upd = $pdo->prepare("
                    UPDATE announcements 
                    SET title = ?,
                        summary = ?,
                        content = ?,
                        category = ?,
                        category_id = ?,
                        is_urgent = ?,
                        status = 'pending',
                        rejection_reason = NULL,
                        reviewed_by = NULL,
                        reviewed_at = NULL,
                        updated_at = ?
                    WHERE id = ? AND created_by = ?
                ");
                $now = date('Y-m-d H:i:s');
                $upd->execute([
                    $form_data['title'],
                    $auto_summary,
                    $form_data['content'],
                    $form_data['category'],
                    $categoryId,
                    $form_data['is_urgent'],
                    $now,
                    $id,
                    $currentUser['id']
                ]);

                // Save additional uploaded photos if provided
                if (!empty($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    save_announcement_images($id, $_FILES['images']);
                }

                set_flash('success', 'Announcement #' . $id . ' has been updated and resubmitted to the Admin review queue.');
                header('Location: index.php');
                exit;

            } catch (PDOException $e) {
                $errors[] = 'Database update error: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card" style="max-width: 860px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-pen-to-square" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Edit Announcement</h2>
        </div>
        <div class="card-header-right">
            <a href="index.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Cancel
            </a>
        </div>
    </div>

    <div class="card-body">
        <?php if ($announcement['status'] === 'rejected' && !empty($announcement['rejection_reason'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div class="alert-text">
                    <strong>Admin Rejection Note:</strong><br>
                    <?= nl2br(h($announcement['rejection_reason'])) ?>
                    <div style="font-size: 0.8rem; margin-top: 0.4rem; opacity: 0.9;">
                        Please address this feedback before resubmitting. Saving changes will automatically place this announcement back into the review queue.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div class="alert-text">
                    <ul style="margin-left: 1.2rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= h($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?= $id ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="title" class="form-label">Announcement Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-input" value="<?= h($form_data['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="category" class="form-label">Category <span class="required">*</span></label>
                <select id="category" name="category" class="form-select" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= h($cat['name']) ?>" <?= $form_data['category'] === $cat['name'] ? 'selected' : '' ?>>
                            <?= h($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Choose which bulletin category this announcement belongs to.</div>
            </div>

            <div class="form-group">
                <label for="content" class="form-label">Content <span class="required">*</span></label>
                <textarea id="content" name="content" class="form-textarea" rows="8" required><?= h($form_data['content']) ?></textarea>
            </div>

            <!-- Existing Photos & Add More Section -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-images" style="color: var(--primary);"></i> Photo Attachments
                </label>

                <!-- If announcement has existing photos, display Photo Slider -->
                <?php if (!empty($existingImagePaths)): ?>
                    <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em;">
                        Current Attached Photos (<?= count($existingImagePaths) ?>):
                    </div>
                    <div id="existingCarouselContainer"></div>
                <?php endif; ?>

                <input type="file" name="images[]" id="editImageInput" multiple accept="image/*" style="display: none;" onchange="handleEditFileSelect(this)">

                <div class="image-upload-dropzone" onclick="document.getElementById('editImageInput').click()" style="margin-top: 1rem;">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">
                        <?= !empty($existingImagePaths) ? 'Click to add more photos' : 'Click to attach photos' ?>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                        Select multiple photos to attach to this announcement.
                    </div>
                </div>

                <!-- Selected Media Files List with Remove (x) Buttons -->
                <div id="mediaFilesList" class="media-files-list" style="display: none; margin-top: 1rem;"></div>

                <!-- Preview container for new uploads -->
                <div id="newCarouselPreview" style="margin-top: 1rem; display: none;">
                    <div style="font-size: 0.82rem; font-weight: 700; color: var(--primary); text-transform: uppercase; margin-bottom: 0.5rem;">
                        New Photos to be added (Facebook Format):
                    </div>
                    <div id="newCarouselContainer"></div>
                </div>
            </div>

            <div class="form-group" style="background: #f8fafc; border: 1px solid var(--card-border); padding: 1rem; border-radius: var(--radius-md);">
                <label style="display: flex; align-items: center; gap: 0.65rem; cursor: pointer; font-weight: 600;">
                    <input type="checkbox" name="is_urgent" value="1" <?= $form_data['is_urgent'] ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span><i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i> Mark as Urgent Alert</span>
                </label>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--card-border);">
                <a href="index.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Save & Resubmit for Review
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let selectedMediaFiles = [];

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function syncFileInput() {
    const input = document.getElementById('editImageInput');
    const dt = new DataTransfer();
    selectedMediaFiles.forEach(file => dt.items.add(file));
    input.files = dt.files;
}

function renderMediaFilesList() {
    const mediaFilesList = document.getElementById('mediaFilesList');
    const newCarouselPreview = document.getElementById('newCarouselPreview');
    const fileInput = document.getElementById('editImageInput');

    if (selectedMediaFiles.length === 0) {
        mediaFilesList.style.display = 'none';
        mediaFilesList.innerHTML = '';
        fileInput.value = '';
        newCarouselPreview.style.display = 'none';
        document.getElementById('newCarouselContainer').innerHTML = '';
        return;
    }

    mediaFilesList.style.display = 'flex';
    mediaFilesList.innerHTML = '';

    const previewUrls = [];

    selectedMediaFiles.forEach((file, index) => {
        const objectUrl = URL.createObjectURL(file);
        previewUrls.push(objectUrl);

        const item = document.createElement('div');
        item.className = 'media-file-item';
        item.innerHTML = `
            <div class="media-file-left">
                <img src="${objectUrl}" class="media-file-thumb" alt="Preview thumb">
                <div class="media-file-info">
                    <div class="media-file-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                    <div class="media-file-meta">
                        <i class="fa-regular fa-file-image"></i>
                        <span>${formatBytes(file.size)}</span>
                    </div>
                </div>
            </div>
            <button type="button" class="media-file-remove" title="Remove ${escapeHtml(file.name)}" data-index="${index}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        const removeBtn = item.querySelector('.media-file-remove');
        removeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            removeMediaFile(index);
        });

        mediaFilesList.appendChild(item);
    });

    syncFileInput();

    if (window.renderInstagramCarousel) {
        newCarouselPreview.style.display = 'block';
        window.renderInstagramCarousel(previewUrls, 'newCarouselContainer', '');
    }
}

function removeMediaFile(indexToRemove) {
    selectedMediaFiles.splice(indexToRemove, 1);
    renderMediaFilesList();
}

function handleEditFileSelect(input) {
    const newFiles = Array.from(input.files || []);
    newFiles.forEach(f => {
        const isDuplicate = selectedMediaFiles.some(existing => existing.name === f.name && existing.size === f.size && existing.lastModified === f.lastModified);
        if (!isDuplicate) {
            selectedMediaFiles.push(f);
        }
    });
    renderMediaFilesList();
}

document.addEventListener('DOMContentLoaded', function () {
    const existing = <?= json_encode($existingImagePaths) ?>;
    if (existing && existing.length > 0 && window.renderInstagramCarousel) {
        window.renderInstagramCarousel(existing, 'existingCarouselContainer', '../');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
