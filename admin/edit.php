<?php
/**
 * Shared Reusable Announcement Edit Form
 * Validates organizational scope and updates announcements.
 * Matches exact two-column layout and live Facebook preview of create.php.
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

// If this is a draft, highlight drafts navigation
if ($ann['status'] === 'draft') {
    $activeNav = 'drafts.php';
}

// Load Registered Publishing Units dynamically from database
$all_offices = [];
$all_colleges = [];
$all_departments = [];

if ($pdo) {
    try {
        $all_offices = $pdo->query("SELECT name FROM offices ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $all_colleges = $pdo->query("SELECT name FROM colleges ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $all_departments = $pdo->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $all_offices = [];
        $all_colleges = [];
        $all_departments = [];
    }
}

// Fetch categories
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Fetch existing images
$existingImages = get_announcement_images($id);
$existingImagePaths = array_column($existingImages, 'image_path');
if (empty($existingImagePaths) && !empty($ann['image_path'])) {
    $existingImages = [['id' => 0, 'image_path' => $ann['image_path']]];
    $existingImagePaths = [$ann['image_path']];
}

$error = '';

// ====================================================================
// FORM SUBMISSION PROCESSING
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security validation failed (CSRF token mismatch). Please resubmit.';
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
        $schedule_date = trim($_POST['schedule_date'] ?? '');
        $schedule_time = trim($_POST['schedule_time'] ?? '');

        // Determine Publishing Unit
        $publishing_unit = $ann['publishing_unit'] ?? $ann['author_name'] ?? $assignedUnit;
        if (is_super_admin()) {
            $selected_unit = trim($_POST['publishing_unit'] ?? '');
            if (!empty($selected_unit)) {
                $publishing_unit = $selected_unit;
            }
        }

        if (empty($title)) {
            $error = 'Announcement title is required.';
        } elseif (empty($content)) {
            $error = 'Content is required.';
        } else {
            // Auto-generate teaser summary if empty
            if (empty($summary)) {
                $summary = mb_substr(strip_tags($content), 0, 180) . '...';
            }

            // Remove previous draft tag if publishing
            if ($action_type === 'publish' && strpos($summary, '[Draft]') === 0) {
                $summary = trim(substr($summary, 7));
            }

            if (!empty($schedule_date)) {
                if (strpos($summary, '[Scheduled:') === false) {
                    $summary = '[Scheduled: ' . $schedule_date . ' ' . $schedule_time . '] ' . $summary;
                }
            }

            if ($action_type === 'draft') {
                $new_status = 'draft';
            } else {
                $new_status = is_super_admin() ? 'published' : 'pending_approval';
            }

            try {
                $now = date('Y-m-d H:i:s');
                $reviewer_id = ($new_status === 'published') ? $currentUser['id'] : null;
                $reviewed_at = ($new_status === 'published') ? $now : null;

                $upd = $pdo->prepare("
                    UPDATE announcements
                    SET title = ?,
                        summary = ?,
                        content = ?,
                        category = ?,
                        category_id = ?,
                        publishing_unit = ?,
                        target_audience = ?,
                        is_urgent = ?,
                        is_pinned = ?,
                        status = ?,
                        reviewed_by = ?,
                        reviewed_at = ?,
                        updated_at = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $title,
                    $summary,
                    $content,
                    $category_name,
                    $category_id,
                    $publishing_unit,
                    $target_audience,
                    $is_urgent,
                    $is_pinned,
                    $new_status,
                    $reviewer_id,
                    $reviewed_at,
                    $now,
                    $id
                ]);

                // 1. Remove deleted existing images
                $deleted_ids_raw = trim($_POST['deleted_image_ids'] ?? '');
                if (!empty($deleted_ids_raw)) {
                    $deleted_ids = array_filter(array_map('intval', explode(',', $deleted_ids_raw)));
                    if (!empty($deleted_ids)) {
                        $placeholders = implode(',', array_fill(0, count($deleted_ids), '?'));
                        try {
                            $delQuery = $pdo->prepare("SELECT image_path FROM announcement_images WHERE id IN ($placeholders) AND announcement_id = ?");
                            $delQuery->execute(array_merge($deleted_ids, [$id]));
                            $toDeleteFiles = $delQuery->fetchAll(PDO::FETCH_COLUMN);

                            foreach ($toDeleteFiles as $delFile) {
                                if (!empty($delFile)) {
                                    $diskFile = dirname(__DIR__) . '/' . ltrim($delFile, '/');
                                    if (file_exists($diskFile)) {
                                        @unlink($diskFile);
                                    }
                                }
                            }

                            $delStmt = $pdo->prepare("DELETE FROM announcement_images WHERE id IN ($placeholders) AND announcement_id = ?");
                            $delStmt->execute(array_merge($deleted_ids, [$id]));
                        } catch (Exception $ex) {
                            error_log("Error deleting images: " . $ex->getMessage());
                        }
                    }
                }

                // 2. Upload and save newly attached images
                if (!empty($_FILES['images']) && !empty($_FILES['images']['name'])) {
                    save_announcement_images($id, $_FILES['images']);
                }

                // 3. Ensure primary image in announcements table is kept in sync
                try {
                    $stmtImg = $pdo->prepare("SELECT image_path FROM announcement_images WHERE announcement_id = ? ORDER BY id ASC LIMIT 1");
                    $stmtImg->execute([$id]);
                    $primaryPath = $stmtImg->fetchColumn();
                    if (!empty($primaryPath)) {
                        $updImg = $pdo->prepare("UPDATE announcements SET image_path = ? WHERE id = ?");
                        $updImg->execute([$primaryPath, $id]);
                    } elseif (!empty($deleted_ids_raw)) {
                        $updImg = $pdo->prepare("UPDATE announcements SET image_path = NULL WHERE id = ?");
                        $updImg->execute([$id]);
                    }
                } catch (Exception $ex) {
                    // ignore
                }

                if ($new_status === 'draft') {
                    set_flash('info', 'Announcement draft #' . $id . ' has been updated.');
                    header('Location: drafts.php');
                } elseif ($new_status === 'pending_approval') {
                    set_flash('success', 'Announcement #' . $id . ' has been updated and submitted for Super Administrator approval.');
                    header('Location: my_announcements.php');
                } else {
                    set_flash('success', 'Announcement #' . $id . ' has been published live to the school portal.');
                    header('Location: view_announcements.php?status=published');
                }
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
            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                <i class="fa-solid fa-sitemap"></i> Scope: <?= h($ann['publishing_unit'] ?? $ann['author_name'] ?? $assignedUnit) ?>
            </span>
        </div>
    </div>

    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                <div class="alert-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="alert-text"><?= h($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Two Column Layout: Form & Live Preview Panel (Same as Create Announcement) -->
        <div style="display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.85fr); gap: 1.75rem; align-items: start;">
            
            <!-- Left: Reusable Announcement Edit Form -->
            <form method="POST" action="edit.php?id=<?= (int)$ann['id'] ?>" enctype="multipart/form-data" id="announcementForm">
                <?= csrf_field() ?>
                <input type="hidden" name="submission_action" id="submissionAction" value="publish">
                <input type="hidden" name="deleted_image_ids" id="deletedImageIds" value="">

                <!-- Section 1: Core Announcement Details -->
                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="fa-solid fa-bullhorn"></i>
                        <span>Announcement Details</span>
                    </div>

                    <!-- 1. Title -->
                    <div class="form-group">
                        <label for="annTitle" class="form-label">
                            <i class="fa-solid fa-heading" style="color: var(--primary);"></i>
                            <span>Announcement Title</span>
                            <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="annTitle" 
                            name="title" 
                            class="form-control" 
                            placeholder="e.g. Midterm Examination Timetable for 1st Semester A.Y. 2026-2027" 
                            required 
                            maxlength="255"
                            value="<?= h($_POST['title'] ?? $ann['title']) ?>"
                        >
                        <div class="form-hint">
                            <i class="fa-regular fa-lightbulb"></i>
                            <span>Make the title clear, descriptive, and relevant to students and faculty.</span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                        <!-- 2. Category -->
                        <div class="form-group">
                            <label for="annCategory" class="form-label">
                                <i class="fa-solid fa-tag" style="color: var(--primary);"></i>
                                <span>Category</span>
                                <span class="required">*</span>
                            </label>
                            <select id="annCategory" name="category" class="form-control">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= h($cat['name']) ?>" data-id="<?= (int)$cat['id'] ?>" <?= ($cat['name'] === ($ann['category'] ?? '')) ? 'selected' : '' ?>>
                                        <?= h($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="category_id" id="categoryIdHidden" value="<?= (int)($ann['category_id'] ?? 1) ?>">
                        </div>

                        <!-- 3. Target Audience -->
                        <div class="form-group">
                            <label for="annAudience" class="form-label">
                                <i class="fa-solid fa-users" style="color: var(--primary);"></i>
                                <span>Target Audience</span>
                                <span class="required">*</span>
                            </label>
                            <select id="annAudience" name="target_audience" class="form-control">
                                <option value="All" <?= (($ann['target_audience'] ?? 'All') === 'All') ? 'selected' : '' ?>>All Campus Members (Students, Faculty, Staff)</option>
                                <option value="Students" <?= (($ann['target_audience'] ?? '') === 'Students') ? 'selected' : '' ?>>Students Only</option>
                                <option value="Faculty" <?= (($ann['target_audience'] ?? '') === 'Faculty') ? 'selected' : '' ?>>Faculty &amp; Academic Staff</option>
                                <option value="Parents" <?= (($ann['target_audience'] ?? '') === 'Parents') ? 'selected' : '' ?>>Parents &amp; Guardians</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Publishing Unit (Role-Restricted Scope) -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="annUnit" class="form-label">
                            <i class="fa-solid fa-landmark" style="color: var(--primary);"></i>
                            <span>Publishing Authority / Scope</span>
                            <span class="required">*</span>
                        </label>
                        <?php if (is_super_admin()): ?>
                            <select id="annUnit" name="publishing_unit" class="form-control">
                                <option value="University-Wide (International State College of the Philippines)">
                                    University-Wide (International State College of the Philippines)
                                </option>
                                <?php if (!empty($all_offices)): ?>
                                    <optgroup label="University Offices">
                                        <?php foreach ($all_offices as $off): ?>
                                            <option value="<?= h($off) ?>" <?= (($ann['publishing_unit'] ?? '') === $off) ? 'selected' : '' ?>><?= h($off) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($all_colleges)): ?>
                                    <optgroup label="Colleges">
                                        <?php foreach ($all_colleges as $col): ?>
                                            <option value="<?= h($col) ?>" <?= (($ann['publishing_unit'] ?? '') === $col) ? 'selected' : '' ?>><?= h($col) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($all_departments)): ?>
                                    <optgroup label="Departments">
                                        <?php foreach ($all_departments as $dep): ?>
                                            <option value="<?= h($dep) ?>" <?= (($ann['publishing_unit'] ?? '') === $dep) ? 'selected' : '' ?>><?= h($dep) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <div class="form-hint" style="color: #0369a1;">
                                <i class="fa-solid fa-lock-open"></i>
                                <span>As Super Administrator, you may author or transfer under any official university unit.</span>
                            </div>
                        <?php else: ?>
                            <input 
                                type="text" 
                                id="annUnit" 
                                name="publishing_unit" 
                                class="form-control" 
                                value="<?= h($ann['publishing_unit'] ?? $ann['author_name'] ?? $assignedUnit) ?>" 
                                readonly 
                                style="background: #f8fafc; cursor: not-allowed; font-weight: 600;"
                            >
                            <div class="form-hint" style="color: #0369a1;">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Bound to your assigned authority: <?= h(format_role_label($currentRole)) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 2: Announcement Content -->
                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>Content &amp; Messaging</span>
                    </div>

                    <!-- 5. Short Summary -->
                    <div class="form-group">
                        <label for="annSummary" class="form-label">
                            <i class="fa-solid fa-align-left" style="color: var(--primary);"></i>
                            <span>Short Summary (Teaser / Preview)</span>
                        </label>
                        <textarea 
                            id="annSummary" 
                            name="summary" 
                            class="form-control" 
                            rows="2" 
                            placeholder="1-2 brief sentences highlighting key bulletin dates, rules, or main takeaway..."
                        ><?= h($_POST['summary'] ?? $ann['summary']) ?></textarea>
                        <div class="form-hint">
                            <i class="fa-regular fa-eye"></i>
                            <span>Appears on portal cards and push notifications before expanding the full article.</span>
                        </div>
                    </div>

                    <!-- 6. Announcement Content -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="annContent" class="form-label">
                            <i class="fa-solid fa-newspaper" style="color: var(--primary);"></i>
                            <span>Full Announcement Body</span>
                            <span class="required">*</span>
                        </label>
                        <textarea 
                            id="annContent" 
                            name="content" 
                            class="form-control" 
                            rows="8" 
                            placeholder="Provide full details, comprehensive instructions, relevant dates, academic guidelines, contact persons, or links..." 
                            required
                        ><?= h($_POST['content'] ?? $ann['content']) ?></textarea>
                    </div>
                </div>

                <!-- Section 3: Media & Attachments -->
                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="fa-solid fa-images"></i>
                        <span>Media &amp; Attachments</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="annImages" class="form-label">
                            <i class="fa-solid fa-cloud-arrow-up" style="color: var(--primary);"></i>
                            <span>Upload Images / Infographics</span>
                        </label>
                        <input 
                            type="file" 
                            id="annImages" 
                            name="images[]" 
                            class="form-control" 
                            multiple 
                            accept="image/jpeg,image/png,image/webp,image/gif"
                        >
                        <div class="form-hint" style="margin-bottom: 0.75rem;">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Select one or multiple images (JPG, PNG, WebP). All attached images appear below and can be individually removed using the 'x' icon.</span>
                        </div>

                        <!-- Combined Media Files List with Remove (x) Buttons -->
                        <div id="mediaFilesList" class="media-files-list"></div>
                    </div>
                </div>

                <!-- Section 4: Publishing Timing & Prominence -->
                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="fa-regular fa-calendar-check"></i>
                        <span>Publication Timing &amp; Prominence</span>
                    </div>

                    <!-- Scheduling -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem;">
                        <label class="form-label" style="margin-bottom: 0.5rem;">
                            <i class="fa-regular fa-clock" style="color: var(--primary);"></i>
                            <span>Scheduled Publication (Optional)</span>
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem;">
                            <div>
                                <label style="font-size: 0.78rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 0.35rem;">Publication Date</label>
                                <input type="date" id="scheduleDate" name="schedule_date" class="form-control" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div>
                                <label style="font-size: 0.78rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 0.35rem;">Publication Time</label>
                                <input type="time" id="scheduleTime" name="schedule_time" class="form-control">
                            </div>
                        </div>
                        <div class="form-hint">
                            <span>Leave blank to publish or submit immediately.</span>
                        </div>
                    </div>

                    <!-- Prominence Toggles -->
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <label class="toggle-option-card">
                            <input type="checkbox" id="annUrgent" name="is_urgent" value="1" <?= !empty($ann['is_urgent']) ? 'checked' : '' ?> style="accent-color: #ef4444;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.9rem; color: #dc2626; display: flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <span>Urgent Alert</span>
                                </div>
                                <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.15rem;">
                                    Highlights this notice in red banner across the student feed.
                                </div>
                            </div>
                        </label>

                        <label class="toggle-option-card">
                            <input type="checkbox" id="annPinned" name="is_pinned" value="1" <?= !empty($ann['is_pinned']) ? 'checked' : '' ?> style="accent-color: #0284c7;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.9rem; color: #0369a1; display: flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-thumbtack"></i>
                                    <span>Pin Announcement</span>
                                </div>
                                <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.15rem;">
                                    Keeps this announcement anchored at the top of the board.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Section 5: Actions Toolbar -->
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; padding-top: 0.5rem;">
                    <button type="button" class="btn btn-primary" onclick="handlePublishClick()" style="padding: 0.7rem 1.4rem;">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Save Changes &amp; Publish</span>
                    </button>
                    
                    <button type="button" class="btn btn-outline" onclick="submitWithAction('draft')" style="padding: 0.7rem 1.25rem;">
                        <i class="fa-regular fa-floppy-disk"></i>
                        <span>Save as Draft</span>
                    </button>

                    <a href="<?= ($ann['status'] === 'draft') ? 'drafts.php' : 'my_announcements.php' ?>" class="btn btn-outline" style="margin-left: auto; padding: 0.7rem 1.25rem;">
                        <span>Cancel</span>
                    </a>
                </div>
            </form>

            <!-- Right: Interactive Live Preview Card (Same as Create Announcement) -->
            <aside style="position: sticky; top: 90px; background: #ffffff; border: 1.5px solid var(--card-border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); overflow: hidden;">
                <div style="padding: 0.9rem 1.15rem; background: linear-gradient(105deg, #025c8d, #0373af); color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
                    <div style="font-size: 0.88rem; font-weight: 700; display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-eye"></i>
                        <span>Student Board Live Preview</span>
                    </div>
                    <span class="badge" style="background: rgba(255, 255, 255, 0.2); color: #ffffff; font-size: 0.68rem; border: 1px solid rgba(255, 255, 255, 0.35);">
                        Real-time
                    </span>
                </div>
                
                <div style="padding: 1.25rem;">
                    <div style="display: flex; gap: 0.4rem; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap;">
                        <span class="category-pill" id="prevCategory" style="font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.6rem; border-radius: 4px;">
                            <?= h($ann['category'] ?? 'Academic Affairs') ?>
                        </span>
                        <span class="badge" id="prevUrgentBadge" style="<?= !empty($ann['is_urgent']) ? 'display: inline-flex;' : 'display: none;' ?> background: #fee2e2; color: #b91c1c; font-size: 0.68rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Urgent
                        </span>
                        <span class="badge" id="prevPinnedBadge" style="<?= !empty($ann['is_pinned']) ? 'display: inline-flex;' : 'display: none;' ?> background: #e0f2fe; color: #0369a1; font-size: 0.68rem;">
                            <i class="fa-solid fa-thumbtack"></i> Pinned
                        </span>
                    </div>

                    <h3 id="prevTitle" style="font-size: 1.15rem; font-weight: 800; color: #0f172a; line-height: 1.35; margin-bottom: 0.5rem;">
                        <?= h($ann['title']) ?>
                    </h3>

                    <p id="prevSummary" style="font-size: 0.86rem; color: #64748b; line-height: 1.5; margin-bottom: 0.85rem;">
                        <?= h($ann['summary']) ?>
                    </p>

                    <!-- Facebook-Style Live Image Carousel Preview -->
                    <div id="previewCarouselContainer" style="display: none; margin-bottom: 1rem;"></div>

                    <div style="font-size: 0.82rem; color: #334155; line-height: 1.6; max-height: 180px; overflow-y: auto; background: #f8fafc; padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid #e2e8f0; white-space: pre-wrap;" id="prevContent"><?= h($ann['content']) ?></div>

                    <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 0.74rem; color: #94a3b8;">
                        <span id="prevUnit"><i class="fa-solid fa-landmark"></i> <?= h($ann['publishing_unit'] ?? $ann['author_name'] ?? $assignedUnit) ?></span>
                        <span id="prevAudience"><i class="fa-solid fa-users"></i> <?= h($ann['target_audience'] ?? 'All') ?></span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<script>
// Live Preview Synchronization & Media Attachment File Manager
document.addEventListener('DOMContentLoaded', function () {
    const titleInput = document.getElementById('annTitle');
    const catSelect = document.getElementById('annCategory');
    const catHidden = document.getElementById('categoryIdHidden');
    const audienceSelect = document.getElementById('annAudience');
    const unitInput = document.getElementById('annUnit');
    const summaryInput = document.getElementById('annSummary');
    const contentInput = document.getElementById('annContent');
    const urgentCheck = document.getElementById('annUrgent');
    const pinnedCheck = document.getElementById('annPinned');

    const prevTitle = document.getElementById('prevTitle');
    const prevCat = document.getElementById('prevCategory');
    const prevAudience = document.getElementById('prevAudience');
    const prevUnit = document.getElementById('prevUnit');
    const prevSummary = document.getElementById('prevSummary');
    const prevContent = document.getElementById('prevContent');
    const prevUrgent = document.getElementById('prevUrgentBadge');
    const prevPinned = document.getElementById('prevPinnedBadge');

    function updatePreview() {
        const titleVal = titleInput.value.trim();
        prevTitle.textContent = titleVal || 'Your announcement title will appear here';
        prevTitle.style.color = titleVal ? '#0f172a' : '#94a3b8';

        prevCat.textContent = catSelect.value;
        const opt = catSelect.options[catSelect.selectedIndex];
        if (opt && opt.dataset.id) {
            catHidden.value = opt.dataset.id;
        }

        prevAudience.innerHTML = '<i class="fa-solid fa-users"></i> ' + audienceSelect.value;
        const unitVal = unitInput.value || '<?= h($assignedUnit) ?>';
        prevUnit.innerHTML = '<i class="fa-solid fa-building-columns"></i> ' + unitVal;

        prevSummary.textContent = summaryInput.value.trim() || 'Short teaser summary will be displayed on the student feed cards.';
        prevContent.textContent = contentInput.value.trim() || 'Full content details will appear here...';

        prevUrgent.style.display = urgentCheck.checked ? 'inline-flex' : 'none';
        prevPinned.style.display = pinnedCheck.checked ? 'inline-flex' : 'none';
    }

    [titleInput, catSelect, audienceSelect, unitInput, summaryInput, contentInput, urgentCheck, pinnedCheck].forEach(el => {
        if (el) {
            el.addEventListener('input', updatePreview);
            el.addEventListener('change', updatePreview);
        }
    });

    updatePreview();

    // ====================================================================
    // Media & Attachments: Combined Existing & New File Manager
    // ====================================================================
    const fileInput = document.getElementById('annImages');
    const mediaFilesList = document.getElementById('mediaFilesList');
    const previewCarouselContainer = document.getElementById('previewCarouselContainer');
    const deletedImageIdsInput = document.getElementById('deletedImageIds');

    // Existing uploaded images loaded from server
    let existingMedia = <?= json_encode($existingImages) ?> || [];
    let deletedImageIds = [];
    let selectedNewFiles = [];

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
        const dt = new DataTransfer();
        selectedNewFiles.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
    }

    function renderMediaFilesList() {
        mediaFilesList.innerHTML = '';
        const allCarouselUrls = [];

        // 1. Render Existing Attachments
        existingMedia.forEach((imgObj, idx) => {
            const rawPath = imgObj.image_path || '';
            const webSrc = (rawPath.startsWith('http') || rawPath.startsWith('/') || rawPath.startsWith('data:')) ? rawPath : ('../' + rawPath);
            allCarouselUrls.push(webSrc);

            const fileName = rawPath.split('/').pop() || ('Image #' + (idx + 1));

            const item = document.createElement('div');
            item.className = 'media-file-item';
            item.innerHTML = `
                <div class="media-file-left">
                    <img src="${webSrc}" class="media-file-thumb" alt="Existing thumb">
                    <div class="media-file-info">
                        <div class="media-file-name" title="${escapeHtml(fileName)}">${escapeHtml(fileName)}</div>
                        <div class="media-file-meta">
                            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 0.68rem; padding: 0.15rem 0.4rem;">Attached</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="media-file-remove" title="Remove ${escapeHtml(fileName)}" data-existing-idx="${idx}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;

            const removeBtn = item.querySelector('.media-file-remove');
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                removeExistingImage(idx);
            });

            mediaFilesList.appendChild(item);
        });

        // 2. Render Newly Attached Files
        selectedNewFiles.forEach((file, idx) => {
            const objectUrl = URL.createObjectURL(file);
            allCarouselUrls.push(objectUrl);

            const item = document.createElement('div');
            item.className = 'media-file-item';
            item.innerHTML = `
                <div class="media-file-left">
                    <img src="${objectUrl}" class="media-file-thumb" alt="New thumb">
                    <div class="media-file-info">
                        <div class="media-file-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                        <div class="media-file-meta">
                            <span class="badge" style="background: #dcfce7; color: #15803d; font-size: 0.68rem; padding: 0.15rem 0.4rem;">New</span>
                            <span>${formatBytes(file.size)}</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="media-file-remove" title="Remove ${escapeHtml(file.name)}" data-new-idx="${idx}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;

            const removeBtn = item.querySelector('.media-file-remove');
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                removeNewFile(idx);
            });

            mediaFilesList.appendChild(item);
        });

        // Toggle list visibility
        if (existingMedia.length === 0 && selectedNewFiles.length === 0) {
            mediaFilesList.style.display = 'none';
            previewCarouselContainer.style.display = 'none';
            previewCarouselContainer.innerHTML = '';
        } else {
            mediaFilesList.style.display = 'flex';
            // Render Live Facebook Carousel Preview with Right Arrow as default!
            if (window.renderInstagramCarousel) {
                previewCarouselContainer.style.display = 'block';
                window.renderInstagramCarousel(allCarouselUrls, 'previewCarouselContainer', '');
            }
        }

        syncFileInput();
    }

    function removeExistingImage(index) {
        const removed = existingMedia.splice(index, 1)[0];
        if (removed && removed.id && removed.id > 0) {
            deletedImageIds.push(removed.id);
            deletedImageIdsInput.value = deletedImageIds.join(',');
        }
        renderMediaFilesList();
    }

    function removeNewFile(index) {
        selectedNewFiles.splice(index, 1);
        renderMediaFilesList();
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const newFiles = Array.from(fileInput.files || []);
            newFiles.forEach(f => {
                const isDuplicate = selectedNewFiles.some(existing => existing.name === f.name && existing.size === f.size && existing.lastModified === f.lastModified);
                if (!isDuplicate) {
                    selectedNewFiles.push(f);
                }
            });
            renderMediaFilesList();
        });
    }

    // Initial render of existing media and preview carousel
    renderMediaFilesList();
});

const isSuperAdmin = <?= is_super_admin() ? 'true' : 'false' ?>;

function handlePublishClick() {
    const form = document.getElementById('announcementForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    if (isSuperAdmin) {
        submitWithAction('publish');
    } else {
        openModal('publishApprovalConfirmModal');
    }
}

function submitWithAction(actionType) {
    document.getElementById('submissionAction').value = actionType;
    document.getElementById('announcementForm').submit();
}
</script>

<!-- Publish Approval Confirmation Modal (for non-super-admins) -->
<div class="modal-overlay" id="publishApprovalConfirmModal">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" style="color: #0284c7;"><i class="fa-solid fa-clock-rotate-left"></i> Super Administrator Approval Required</h3>
            <button type="button" class="modal-close" onclick="closeModal('publishApprovalConfirmModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem 1.25rem;">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h4 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; text-align: center; margin-bottom: 0.65rem;">
                Submit for Super Administrator Review?
            </h4>
            <p style="font-size: 0.88rem; color: #64748b; line-height: 1.55; text-align: center; margin-bottom: 0;">
                As an organizational administrator, this announcement will not be published immediately. It must first be reviewed and authorized by the Super Administrator before becoming visible on the public campus announcement board.
            </p>
        </div>
        <div class="modal-footer" style="display: flex; gap: 0.75rem; justify-content: flex-end; background: #f8fafc; padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0;">
            <button type="button" class="btn btn-outline" onclick="closeModal('publishApprovalConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitWithAction('publish')">
                <i class="fa-solid fa-paper-plane"></i> Confirm &amp; Submit
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
