<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_db();
$is_connected = is_db_connected();
$error = '';
$categories = [];
$form_data = [
    'title' => '',
    'summary' => '',
    'content' => '',
    'category_id' => 1,
    'target_audience' => 'All',
    'is_urgent' => false,
    'is_pinned' => false,
    'author_name' => 'Office of Student Affairs'
];

if ($is_connected && $pdo) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
} else {
    $categories = [
        ['id' => 1, 'name' => 'Urgent Alert'],
        ['id' => 2, 'name' => 'Academic Affairs'],
        ['id' => 3, 'name' => 'Campus Events'],
        ['id' => 4, 'name' => 'Student Life'],
        ['id' => 5, 'name' => 'Admissions & Enrollment']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['title'] = trim($_POST['title'] ?? '');
    $form_data['summary'] = trim($_POST['summary'] ?? '');
    $form_data['content'] = trim($_POST['content'] ?? '');
    $form_data['category_id'] = (int)($_POST['category_id'] ?? 1);
    $form_data['target_audience'] = $_POST['target_audience'] ?? 'All';
    $form_data['is_urgent'] = isset($_POST['is_urgent']);
    $form_data['is_pinned'] = isset($_POST['is_pinned']);
    $form_data['author_name'] = trim($_POST['author_name'] ?? 'School Administration');

    $title = $form_data['title'];
    $summary = $form_data['summary'];
    $content = $form_data['content'];
    $category_id = $form_data['category_id'];
    $target_audience = $form_data['target_audience'];
    $is_urgent = $form_data['is_urgent'] ? 1 : 0;
    $is_pinned = $form_data['is_pinned'] ? 1 : 0;
    $author_name = $form_data['author_name'];

    if (empty($title) || empty($content)) {
        $error = 'Title and announcement content are required.';
    } else {
        // Slug generation
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

        if ($is_connected && $pdo) {
            try {
                $sql = "INSERT INTO announcements 
                        (title, slug, summary, content, category_id, target_audience, is_urgent, is_pinned, author_name, status, published_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $title, $slug, $summary, $content, $category_id, $target_audience, $is_urgent, $is_pinned, $author_name
                ]);

                header('Location: index.php?msg=Announcement+published+successfully!');
                exit;
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            // Simulated success in offline preview mode
            header('Location: index.php?msg=Preview+Saved!+(Connect+VM+to+save+to+MySQL)');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post New Announcement - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .create-container {
            width: calc(100% - 3rem);
            max-width: 1440px;
            margin: 2.25rem auto 3rem;
        }
        .dashboard-back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            min-height: 44px;
            padding: 0.65rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 0.7rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), #dcecff);
            color: #073b82;
            box-shadow: 0 5px 14px rgba(0, 20, 54, 0.16);
            font-size: 0.85rem;
            font-weight: 700;
            transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease;
        }
        .dashboard-back-link:hover {
            transform: translateY(-2px);
            background: #fff;
            box-shadow: 0 8px 20px rgba(0, 20, 54, 0.23);
        }
        .dashboard-back-link:focus-visible {
            outline: 3px solid #bfdbfe;
            outline-offset: 3px;
        }
        .dashboard-back-link svg {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }
        .create-heading {
            margin-bottom: 1.5rem;
            padding: clamp(1.4rem, 3vw, 2.2rem);
            border: 1px solid #c5ddf8;
            border-radius: 1rem;
            background: linear-gradient(115deg, #eaf3ff, #d5e8ff 55%, #c7e0ff);
            box-shadow: 0 12px 30px rgba(16, 55, 102, 0.09);
        }
        .create-heading h2 {
            color: #073b82;
            font-size: clamp(1.55rem, 3vw, 2rem);
            letter-spacing: -0.035em;
        }
        .create-heading p {
            margin-top: 0.4rem;
            color: #355b85;
        }
        .create-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.8fr);
            align-items: start;
            gap: 1.5rem;
        }
        .form-card,
        .preview-panel {
            border: 1px solid #d6e5f6;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 14px 36px rgba(16, 55, 102, 0.1);
        }
        .form-card {
            padding: clamp(1.25rem, 2.5vw, 2rem);
        }
        .form-card-header {
            margin-bottom: 1.5rem;
        }
        .form-card-header h3 {
            color: #073b82;
            font-size: 1.15rem;
        }
        .form-card-header p {
            margin-top: 0.25rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 1rem;
        }
        .form-group {
            min-width: 0;
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.45rem;
            color: #355b85;
            font-size: 0.83rem;
            font-weight: 700;
        }
        .form-control {
            width: 100%;
            padding: 0.78rem 0.9rem;
            border: 1px solid #c7dcf6;
            border-radius: 0.6rem;
            background: #fff;
            color: var(--text-main);
            font: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        textarea.form-control {
            resize: vertical;
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin: 0.3rem 0 1.5rem;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.7rem 0.85rem;
            border: 1px solid #d6e5f6;
            border-radius: 0.65rem;
            background: #f8fbff;
            color: #24476f;
            cursor: pointer;
            font-size: 0.82rem;
        }
        .checkbox-label input {
            width: 17px;
            height: 17px;
        }
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding-top: 1rem;
            border-top: 1px solid #e1ebf7;
        }
        .form-error {
            margin-bottom: 1.25rem;
            padding: 0.85rem 1rem;
            border: 1px solid #fecdd3;
            border-radius: 0.65rem;
            background: #fff1f2;
            color: #9f1239;
        }
        .preview-panel {
            position: sticky;
            top: 105px;
            overflow: hidden;
        }
        .preview-header {
            padding: 1.15rem 1.25rem;
            border-bottom: 1px solid #d9e8f7;
            background: linear-gradient(135deg, #f8fbff, #eaf3ff);
        }
        .preview-header h3 {
            color: #073b82;
            font-size: 1rem;
        }
        .preview-header p {
            margin-top: 0.2rem;
            color: var(--text-muted);
            font-size: 0.78rem;
        }
        .preview-content {
            padding: 1.25rem;
        }
        .preview-card {
            position: relative;
            overflow: hidden;
            padding: 1.25rem;
            border: 1px solid #d6e5f6;
            border-radius: 0.9rem;
            background: linear-gradient(145deg, #fff, #f8fbff);
        }
        .preview-card::before {
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: #0755b8;
            content: "";
        }
        .preview-card.is-urgent::before {
            background: linear-gradient(90deg, #dc2626, #fb7185);
        }
        .preview-topline,
        .preview-meta,
        .preview-flags {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .preview-topline {
            justify-content: space-between;
            margin: 0.15rem 0 1rem;
        }
        .preview-category {
            max-width: 100%;
            padding: 0.3rem 0.65rem;
            overflow: hidden;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            background: #eff6ff;
            color: #0755b8;
            font-size: 0.68rem;
            font-weight: 800;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .preview-flags {
            color: #7186a0;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .preview-urgent {
            display: none;
            color: #be123c;
        }
        .preview-card.is-urgent .preview-urgent,
        .preview-card.is-pinned .preview-pinned {
            display: inline-flex;
        }
        .preview-title {
            color: #102a4c;
            font-size: 1.1rem;
            font-weight: 800;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }
        .preview-summary {
            margin-top: 0.65rem;
            color: #49627f;
            font-size: 0.85rem;
            line-height: 1.6;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }
        .preview-content-text {
            max-height: 180px;
            margin-top: 1rem;
            padding-top: 0.85rem;
            overflow: auto;
            border-top: 1px solid #e1ebf7;
            color: #355b85;
            font-size: 0.82rem;
            line-height: 1.6;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }
        .preview-meta {
            margin-top: 1rem;
            padding-top: 0.8rem;
            border-top: 1px solid #e1ebf7;
            color: #7186a0;
            font-size: 0.74rem;
        }
        .preview-placeholder {
            color: #7186a0;
            font-style: italic;
        }
        @media (max-width: 900px) {
            .create-layout {
                grid-template-columns: minmax(0, 1fr);
            }
            .preview-panel {
                position: static;
            }
        }
        @media (max-width: 600px) {
            .create-container {
                width: calc(100% - 2rem);
                margin-top: 1.25rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="nav-container">
            <div class="brand-wrapper">
                <img class="brand-logo" src="../assets/images/iscp_logo.png" alt="International State College of the Philippines logo">
                <div class="brand-text">
                    <h1>Portal Administration</h1>
                    <p>Post New Announcement</p>
                </div>
            </div>
            <a href="index.php" class="dashboard-back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m15 18-6-6 6-6"></path><path d="M20 12H9"></path><path d="M9 5H5v14h4"></path>
                </svg>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </header>

    <main class="create-container">
        <section class="create-heading">
            <h2>Create New Announcement</h2>
            <p>Compose a campus advisory and preview how it will appear in the announcement feed.</p>
        </section>

        <div class="create-layout">
            <section class="form-card" aria-labelledby="announcement-form-title">
                <div class="form-card-header">
                    <h3 id="announcement-form-title">Announcement Details</h3>
                    <p>Fields marked with * are required.</p>
                </div>

                <?php if (!empty($error)): ?>
                <div class="form-error" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="create.php">
                    <div class="form-group">
                        <label for="title">Announcement Title *</label>
                        <input type="text" id="title" name="title" class="form-control" maxlength="255" placeholder="e.g. Schedule for Second Trimester Final Exams" value="<?= htmlspecialchars($form_data['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= ((int)$form_data['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="target_audience">Target Audience</label>
                            <select id="target_audience" name="target_audience" class="form-control">
                                <option value="All" <?= $form_data['target_audience'] === 'All' ? 'selected' : '' ?>>All Campus Members</option>
                                <option value="Students" <?= $form_data['target_audience'] === 'Students' ? 'selected' : '' ?>>Students Only</option>
                                <option value="Faculty" <?= $form_data['target_audience'] === 'Faculty' ? 'selected' : '' ?>>Faculty &amp; Staff</option>
                                <option value="Parents" <?= $form_data['target_audience'] === 'Parents' ? 'selected' : '' ?>>Parents &amp; Guardians</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="author_name">Author / Office Name</label>
                        <input type="text" id="author_name" name="author_name" class="form-control" value="<?= htmlspecialchars($form_data['author_name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label for="summary">Short Summary</label>
                        <textarea id="summary" name="summary" class="form-control" rows="3" placeholder="Brief 1-2 sentence overview..."><?= htmlspecialchars($form_data['summary'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="content">Full Announcement Content *</label>
                        <textarea id="content" name="content" class="form-control" rows="7" placeholder="Detailed bulletin content, instructions, schedules, requirements..." required><?= htmlspecialchars($form_data['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="is_urgent" name="is_urgent" value="1" <?= $form_data['is_urgent'] ? 'checked' : '' ?> style="accent-color: var(--danger);">
                            <span><strong>Urgent Alert</strong></span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="is_pinned" name="is_pinned" value="1" <?= $form_data['is_pinned'] ? 'checked' : '' ?> style="accent-color: var(--primary);">
                            <span><strong>Pin Announcement</strong></span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Publish Announcement</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </section>

            <aside class="preview-panel" aria-labelledby="preview-title">
                <div class="preview-header">
                    <h3 id="preview-title">Live Preview</h3>
                    <p>This updates as you edit the announcement.</p>
                </div>
                <div class="preview-content">
                    <article class="preview-card" id="preview-card">
                        <div class="preview-topline">
                            <span class="preview-category" id="preview-category">General</span>
                            <span class="preview-flags">
                                <span class="preview-urgent" id="preview-urgent">Urgent</span>
                                <span class="preview-pinned" id="preview-pinned" hidden>Pinned</span>
                            </span>
                        </div>
                        <h4 class="preview-title preview-placeholder" id="preview-title-text">Your announcement title</h4>
                        <p class="preview-summary preview-placeholder" id="preview-summary">Your short summary will appear here.</p>
                        <div class="preview-content-text preview-placeholder" id="preview-content">Full announcement details will appear here.</div>
                        <div class="preview-meta">
                            <span id="preview-author">Office of Student Affairs</span>
                            <span aria-hidden="true">&bull;</span>
                            <span id="preview-audience">All Campus Members</span>
                        </div>
                    </article>
                </div>
            </aside>
        </div>
    </main>

    <script>
        const previewFields = {
            title: document.getElementById('title'),
            summary: document.getElementById('summary'),
            content: document.getElementById('content'),
            category: document.getElementById('category_id'),
            audience: document.getElementById('target_audience'),
            author: document.getElementById('author_name'),
            urgent: document.getElementById('is_urgent'),
            pinned: document.getElementById('is_pinned')
        };
        const previewCard = document.getElementById('preview-card');
        const previewTitle = document.getElementById('preview-title-text');
        const previewSummary = document.getElementById('preview-summary');
        const previewContent = document.getElementById('preview-content');
        const previewCategory = document.getElementById('preview-category');
        const previewAuthor = document.getElementById('preview-author');
        const previewAudience = document.getElementById('preview-audience');
        const previewPinned = document.getElementById('preview-pinned');

        function updatePreview() {
            const title = previewFields.title.value.trim();
            const summary = previewFields.summary.value.trim();
            const content = previewFields.content.value.trim();
            const author = previewFields.author.value.trim();

            previewTitle.textContent = title || 'Your announcement title';
            previewTitle.classList.toggle('preview-placeholder', !title);
            previewSummary.textContent = summary || 'Your short summary will appear here.';
            previewSummary.classList.toggle('preview-placeholder', !summary);
            previewContent.textContent = content || 'Full announcement details will appear here.';
            previewContent.classList.toggle('preview-placeholder', !content);
            const selectedCategory = previewFields.category.options[previewFields.category.selectedIndex];
            const selectedAudience = previewFields.audience.options[previewFields.audience.selectedIndex];
            previewCategory.textContent = selectedCategory ? selectedCategory.textContent.trim() : 'General';
            previewAuthor.textContent = author || 'School Administration';
            previewAudience.textContent = selectedAudience ? selectedAudience.textContent.trim() : 'All Campus Members';
            previewCard.classList.toggle('is-urgent', previewFields.urgent.checked);
            previewCard.classList.toggle('is-pinned', previewFields.pinned.checked);
            previewPinned.hidden = !previewFields.pinned.checked;
        }

        Object.values(previewFields).forEach(field => {
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        });
        updatePreview();
    </script>
</body>
</html>
