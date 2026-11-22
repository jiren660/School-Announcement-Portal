<?php
/**
 * Shared Reusable Announcement Creation Form
 * Follows Phase 5 of University Announcement Portal Planning Guide.
 * 
 * Enforces Role-Based Publishing Scope:
 * - Super Administrator: Can select any authorized publishing unit or University-wide.
 * - University Administrator: University-wide announcements.
 * - Office Administrator: Automatically locked to assigned office only.
 * - College Administrator: Automatically locked to assigned college only.
 * - Department Administrator: Automatically locked to assigned department only.
 * - Students: Cannot access this form (guarded by require_admin).
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Create Announcement';
$page_subtitle = 'Draft, schedule, or publish official university advisories and bulletins.';
$activeNav = 'create.php';

$pdo = get_db();

// Standard Registered Publishing Units for Super Admin selector
$all_offices = [
    'Office of the University President',
    'Office of the University Registrar',
    'Office of Student Affairs (OSA)',
    'Information & Communications Technology (ICT)',
    'University Library Services',
    'Admissions & Enrollment Office',
    'Health and Safety Services',
    'University Athletics & Cultural Affairs'
];

$all_colleges = [
    'College of Computer Studies',
    'College of Engineering & Architecture',
    'College of Business & Accountancy',
    'College of Arts and Sciences',
    'College of Education',
    'College of Nursing & Health Sciences'
];

$all_departments = [
    'Department of Computer Science',
    'Department of Information Technology',
    'Department of Civil Engineering',
    'Department of Mechanical Engineering',
    'Department of Accountancy',
    'Department of Communication'
];

// Fetch categories from DB
$categories = [];
if ($pdo) {
    try {
        $categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
    } catch (Exception $e) {
        $categories = [];
    }
}
if (empty($categories)) {
    $categories = [
        ['id' => 1, 'name' => 'Urgent Alert', 'badge_color' => 'danger'],
        ['id' => 2, 'name' => 'Academic Affairs', 'badge_color' => 'primary'],
        ['id' => 3, 'name' => 'Campus Events', 'badge_color' => 'success'],
        ['id' => 4, 'name' => 'Student Life', 'badge_color' => 'warning'],
        ['id' => 5, 'name' => 'Admissions & Enrollment', 'badge_color' => 'info'],
    ];
}

$error = '';
$form_data = [
    'title' => '',
    'category' => $categories[0]['name'] ?? 'Academic Affairs',
    'category_id' => $categories[0]['id'] ?? 1,
    'publishing_unit' => $assignedUnit,
    'target_audience' => 'All',
    'summary' => '',
    'content' => '',
    'schedule_date' => '',
    'schedule_time' => '',
    'is_urgent' => false,
    'is_pinned' => false,
    'action_type' => 'publish' // 'publish', 'draft', 'approval'
];

// ====================================================================
// FORM SUBMISSION PROCESSING
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security validation failed (CSRF token expired). Please resubmit.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $category_name = trim($_POST['category'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 1);
        $target_audience = trim($_POST['target_audience'] ?? 'All');
        $summary = trim($_POST['summary'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        $submission_action = trim($_POST['submission_action'] ?? 'publish');
        $schedule_date = trim($_POST['schedule_date'] ?? '');
        $schedule_time = trim($_POST['schedule_time'] ?? '');

        // Determine & Enforce Backend Publishing Unit Scope
        $publishing_unit = $assignedUnit;
        if (is_super_admin()) {
            $selected_unit = trim($_POST['publishing_unit'] ?? '');
            if (!empty($selected_unit)) {
                $publishing_unit = $selected_unit;
            }
        } elseif ($currentRole === ROLE_UNIVERSITY_ADMIN) {
            $publishing_unit = 'University-Wide (International State College of the Philippines)';
        } elseif ($currentRole === ROLE_OFFICE_ADMIN) {
            $publishing_unit = $currentUser['assigned_office'] ?? $assignedUnit;
        } elseif ($currentRole === ROLE_COLLEGE_ADMIN) {
            $publishing_unit = $currentUser['assigned_college'] ?? $assignedUnit;
        } elseif ($currentRole === ROLE_DEPARTMENT_ADMIN) {
            $publishing_unit = $currentUser['assigned_department'] ?? $assignedUnit;
        }

        // Validation
        if (empty($title)) {
            $error = 'Announcement title is required.';
        } elseif (strlen($title) < 5) {
            $error = 'Title must be at least 5 characters.';
        } elseif (empty($content)) {
            $error = 'Announcement body content is required.';
        } elseif (!$pdo) {
            $error = 'Database is currently unreachable.';
        } else {
            // Determine initial status based on action and authority
            $status = 'approved'; // Direct publishing for authorized admin
            if ($submission_action === 'draft') {
                $status = 'pending'; // Safe fallback for existing ENUM
                if (empty($summary)) {
                    $summary = '[Draft] ' . mb_substr(strip_tags($content), 0, 160);
                } else {
                    $summary = '[Draft] ' . $summary;
                }
            } elseif ($submission_action === 'approval') {
                $status = 'pending';
            } elseif (!empty($schedule_date)) {
                $status = 'pending';
                $summary = '[Scheduled: ' . $schedule_date . ' ' . $schedule_time . '] ' . $summary;
            }

            // Auto-generate teaser summary if not provided
            if (empty($summary)) {
                $summary = mb_substr(strip_tags($content), 0, 180) . '...';
            }

            // Generate clean URL slug
            $clean_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
            $slug = ($clean_slug !== '' ? $clean_slug : 'announcement') . '-' . substr(md5(uniqid()), 0, 6);

            try {
                $now = date('Y-m-d H:i:s');
                $reviewer_id = ($status === 'approved') ? $currentUser['id'] : null;
                $reviewed_at = ($status === 'approved') ? $now : null;

                $ins = $pdo->prepare("
                    INSERT INTO announcements 
                    (title, slug, summary, content, category, category_id, created_by, author_name, status, reviewed_by, reviewed_at, target_audience, is_urgent, is_pinned, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([
                    $title,
                    $slug,
                    $summary,
                    $content,
                    $category_name,
                    $category_id,
                    $currentUser['id'],
                    $publishing_unit,
                    $status,
                    $reviewer_id,
                    $reviewed_at,
                    $target_audience,
                    $is_urgent,
                    $is_pinned,
                    $now,
                    $now
                ]);

                $newId = (int)$pdo->lastInsertId();

                // Save multi-photo attachments if uploaded
                if (!empty($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    save_announcement_images($newId, $_FILES['images']);
                }

                if ($submission_action === 'draft') {
                    set_flash('info', 'Announcement "' . h($title) . '" has been saved as draft.');
                    header('Location: drafts.php');
                } elseif ($status === 'pending') {
                    set_flash('success', 'Announcement "' . h($title) . '" submitted for review / scheduling.');
                    header('Location: my_announcements.php');
                } else {
                    set_flash('success', 'Announcement "' . h($title) . '" was successfully published live to the university portal!');
                    header('Location: view_announcements.php?status=published');
                }
                exit;

            } catch (PDOException $e) {
                $error = 'Database error creating announcement: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-pen-nib" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Compose University Announcement</h2>
        </div>
        <div class="card-header-right">
            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                <i class="fa-solid fa-sitemap"></i> Scope: <?= h($assignedUnit) ?>
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

        <!-- Two Column Layout: Form & Live Preview Panel -->
        <div style="display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.85fr); gap: 1.75rem; align-items: start;">
            
            <!-- Left: Reusable Announcement Form -->
            <form method="POST" action="create.php" enctype="multipart/form-data" id="announcementForm">
                <?= csrf_field() ?>
                <input type="hidden" name="submission_action" id="submissionAction" value="publish">

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
                            value="<?= h($_POST['title'] ?? '') ?>"
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
                                    <option value="<?= h($cat['name']) ?>" data-id="<?= (int)$cat['id'] ?>">
                                        <?= h($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="category_id" id="categoryIdHidden" value="<?= (int)($categories[0]['id'] ?? 1) ?>">
                        </div>

                        <!-- 3. Target Audience -->
                        <div class="form-group">
                            <label for="annAudience" class="form-label">
                                <i class="fa-solid fa-users" style="color: var(--primary);"></i>
                                <span>Target Audience</span>
                                <span class="required">*</span>
                            </label>
                            <select id="annAudience" name="target_audience" class="form-control">
                                <option value="All">All Campus Members (Students, Faculty, Staff)</option>
                                <option value="Students">Students Only</option>
                                <option value="Faculty">Faculty & Academic Staff</option>
                                <option value="Parents">Parents & Guardians</option>
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
                                <optgroup label="University Offices">
                                    <?php foreach ($all_offices as $off): ?>
                                        <option value="<?= h($off) ?>"><?= h($off) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Colleges">
                                    <?php foreach ($all_colleges as $col): ?>
                                        <option value="<?= h($col) ?>"><?= h($col) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Departments">
                                    <?php foreach ($all_departments as $dep): ?>
                                        <option value="<?= h($dep) ?>"><?= h($dep) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <div class="form-hint" style="color: #0369a1;">
                                <i class="fa-solid fa-lock-open"></i>
                                <span>As Super Administrator, you may author under any official university unit.</span>
                            </div>
                        <?php else: ?>
                            <input 
                                type="text" 
                                id="annUnit" 
                                name="publishing_unit" 
                                class="form-control" 
                                value="<?= h($assignedUnit) ?>" 
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
                        <span>Content & Messaging</span>
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
                        ><?= h($_POST['summary'] ?? '') ?></textarea>
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
                        ><?= h($_POST['content'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Section 3: Media & Attachments -->
                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="fa-solid fa-images"></i>
                        <span>Media & Attachments</span>
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
                        <div class="form-hint">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Select one or multiple images (JPG, PNG, WebP). Images automatically format into an interactive multi-photo carousel on the announcement board.</span>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Publishing Timing & Prominence -->
                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="fa-regular fa-calendar-check"></i>
                        <span>Publication Timing & Prominence</span>
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
                            <input type="checkbox" id="annUrgent" name="is_urgent" value="1" style="accent-color: #ef4444;">
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
                            <input type="checkbox" id="annPinned" name="is_pinned" value="1" style="accent-color: #0284c7;">
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
                    <button type="button" class="btn btn-primary" onclick="submitWithAction('publish')" style="padding: 0.7rem 1.4rem;">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Publish Now</span>
                    </button>
                    
                    <button type="button" class="btn btn-outline" onclick="submitWithAction('draft')" style="padding: 0.7rem 1.25rem;">
                        <i class="fa-regular fa-floppy-disk"></i>
                        <span>Save as Draft</span>
                    </button>

                    <button type="button" class="btn btn-outline" onclick="submitWithAction('approval')" style="padding: 0.7rem 1.25rem;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Submit for Approval</span>
                    </button>

                    <a href="index.php" class="btn btn-outline" style="margin-left: auto; padding: 0.7rem 1.25rem;">
                        <span>Cancel</span>
                    </a>
                </div>
            </form>

            <!-- Right: Interactive Live Preview Card -->
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
                            Academic Affairs
                        </span>
                        <span class="badge" id="prevUrgentBadge" style="display: none; background: #fee2e2; color: #b91c1c; font-size: 0.68rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Urgent
                        </span>
                        <span class="badge" id="prevPinnedBadge" style="display: none; background: #e0f2fe; color: #0369a1; font-size: 0.68rem;">
                            <i class="fa-solid fa-thumbtack"></i> Pinned
                        </span>
                    </div>

                    <h3 id="prevTitle" style="font-size: 1.15rem; font-weight: 800; color: #0f172a; line-height: 1.35; margin-bottom: 0.5rem;">
                        Your announcement title will appear here
                    </h3>

                    <p id="prevSummary" style="font-size: 0.86rem; color: #64748b; line-height: 1.5; margin-bottom: 0.85rem;">
                        Short teaser summary will be displayed on the student feed cards.
                    </p>

                    <div style="font-size: 0.82rem; color: #334155; line-height: 1.6; max-height: 180px; overflow-y: auto; background: #f8fafc; padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid #e2e8f0; white-space: pre-wrap;" id="prevContent">Full content details...</div>

                    <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 0.74rem; color: #94a3b8;">
                        <span id="prevUnit"><i class="fa-solid fa-landmark"></i> <?= h($assignedUnit) ?></span>
                        <span id="prevAudience"><i class="fa-solid fa-users"></i> All</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
        </div>
    </div>
</div>

<script>
// Live Preview Synchronization
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
});

function submitWithAction(actionType) {
    document.getElementById('submissionAction').value = actionType;
    document.getElementById('announcementForm').submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
