<?php
/**
 * Announcer Portal - Submit New Announcement
 * With Multi-Photo Instagram-Style Upload & Live Preview
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_announcer();

$page_title = 'Submit Announcement';
$page_subtitle = 'Draft and submit a new announcement with photos for administrative approval.';
$activeNav = 'create.php';

$pdo = get_db();
$currentUser = current_user();

$categories = [];
if ($pdo) {
    try {
        $categories = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC")->fetchAll();
    } catch (PDOException $e) {
        $categories = [];
    }
}

$errors = [];
$form_data = [
    'title' => '',
    'category' => 'Campus Events',
    'content' => '',
    'is_urgent' => 0
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
            $errors[] = 'Announcement title is required.';
        } elseif (strlen($form_data['title']) < 5) {
            $errors[] = 'Title must be at least 5 characters long.';
        }

        if (empty($form_data['category'])) {
            $errors[] = 'Please select a valid category.';
        }

        if (empty($form_data['content'])) {
            $errors[] = 'Announcement content is required.';
        }

        if (empty($errors) && $pdo) {
            try {
                // Find category ID
                $catStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
                $catStmt->execute([$form_data['category']]);
                $categoryId = $catStmt->fetchColumn() ?: 1;

                // Auto-generate teaser summary from first 180 chars of content
                $auto_summary = mb_substr(strip_tags($form_data['content']), 0, 180) . '...';

                // Generate slug and author name for database compatibility
                $clean_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $form_data['title']), '-'));
                $slug = ($clean_slug !== '' ? $clean_slug : 'announcement') . '-' . substr(md5(uniqid()), 0, 6);
                $author_name = $currentUser['name'] ?? 'Faculty / Staff';

                $ins = $pdo->prepare("
                    INSERT INTO announcements 
                    (title, slug, summary, content, category, category_id, created_by, author_name, status, is_urgent, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                ");
                $now = date('Y-m-d H:i:s');
                $ins->execute([
                    $form_data['title'],
                    $slug,
                    $auto_summary,
                    $form_data['content'],
                    $form_data['category'],
                    $categoryId,
                    $currentUser['id'],
                    $author_name,
                    $form_data['is_urgent'],
                    $now,
                    $now
                ]);

                $newAnnId = (int)$pdo->lastInsertId();

                // Save multiple uploaded photos if provided
                if (!empty($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    save_announcement_images($newAnnId, $_FILES['images']);
                }

                set_flash('success', 'Your announcement "' . h($form_data['title']) . '" was successfully submitted! It is now pending administrative review.');
                header('Location: index.php');
                exit;

            } catch (PDOException $e) {
                $errors[] = 'Database error submitting announcement: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card" style="max-width: 860px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-pen-nib" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Create Announcement Request</h2>
        </div>
        <div class="card-header-right">
            <a href="index.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to My Announcements
            </a>
        </div>
    </div>

    <div class="card-body">
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

        <form method="POST" action="create.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Title -->
            <div class="form-group">
                <label for="title" class="form-label">Announcement Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-input" placeholder="e.g. Grand Foundation Week Schedule and Activities" value="<?= h($form_data['title']) ?>" required autofocus>
                <div class="form-hint">Make the title clear and informative.</div>
            </div>

            <!-- Category -->
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

            <!-- Full Content -->
            <div class="form-group">
                <label for="content" class="form-label">Announcement Details & Content <span class="required">*</span></label>
                <textarea id="content" name="content" class="form-textarea" rows="7" placeholder="Type the complete announcement text, instructions, dates, deadlines, or contact details here..." required><?= h($form_data['content']) ?></textarea>
            </div>

            <!-- Photos Upload Section -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-images" style="color: var(--primary);"></i> Photo Attachments (Upload 1 or more photos)
                </label>

                <!-- Hidden file input -->
                <input type="file" name="images[]" id="imageInput" multiple accept="image/*" style="display: none;" onchange="handleFileSelect(this)">

                <!-- Dropzone Area -->
                <div class="image-upload-dropzone" onclick="document.getElementById('imageInput').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                        Click to select photos
                    </div>
                    <div style="font-size: 0.82rem; color: var(--text-muted);">
                        Upload multiple photos (JPG, PNG, WEBP). They will be displayed in an interactive photo slider when previewed.
                    </div>
                </div>

                <!-- Live Carousel Preview Container -->
                <div id="liveCarouselPreview" style="margin-top: 1.25rem; display: none;">
                    <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em;">
                        <i class="fa-solid fa-eye"></i> Photo Slider Preview:
                    </div>
                    <div id="previewCarouselContainer"></div>
                </div>

                <!-- Thumbnails bar -->
                <div class="upload-thumbnails-grid" id="thumbnailsGrid"></div>
            </div>

            <!-- Urgent Checkbox -->
            <div class="form-group" style="background: #f8fafc; border: 1px solid var(--card-border); padding: 1rem; border-radius: var(--radius-md);">
                <label style="display: flex; align-items: center; gap: 0.65rem; cursor: pointer; font-weight: 600;">
                    <input type="checkbox" name="is_urgent" value="1" <?= $form_data['is_urgent'] ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span><i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i> Mark as Urgent Alert</span>
                </label>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-left: 2rem; margin-top: 0.25rem;">
                    Check this if the announcement concerns critical campus safety, urgent class suspensions, or mandatory deadlines.
                </p>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--card-border);">
                <a href="index.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Submit for Admin Review
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let selectedImageUrls = [];

function handleFileSelect(input) {
    const files = Array.from(input.files || []);
    if (files.length === 0) return;

    selectedImageUrls = [];
    const thumbGrid = document.getElementById('thumbnailsGrid');
    thumbGrid.innerHTML = '';

    let readCount = 0;
    files.forEach((file, index) => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const dataUrl = e.target.result;
            selectedImageUrls.push(dataUrl);

            // Thumbnail card
            const card = document.createElement('div');
            card.className = 'upload-thumb-card';
            card.innerHTML = `
                <img src="${dataUrl}" alt="Thumbnail ${index + 1}">
            `;
            thumbGrid.appendChild(card);

            readCount++;
            if (readCount === files.length) {
                // Render live Instagram-style carousel preview
                const previewWrapper = document.getElementById('liveCarouselPreview');
                previewWrapper.style.display = 'block';
                window.renderInstagramCarousel(selectedImageUrls, 'previewCarouselContainer', '');
            }
        };
        reader.readAsDataURL(file);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
