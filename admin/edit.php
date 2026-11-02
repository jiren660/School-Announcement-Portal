<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_db();
$is_connected = is_db_connected();
$id = (int)($_GET['id'] ?? 0);
$error = '';
$announcement = null;
$categories = [];

if ($is_connected && $pdo) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $announcement = $stmt->fetch();
}

if (!$announcement) {
    // Mock item for preview
    $announcement = [
        'id' => $id,
        'title' => 'Sample Announcement',
        'summary' => 'Sample summary for editing...',
        'content' => 'Sample full content text...',
        'category_id' => 1,
        'target_audience' => 'All',
        'is_urgent' => 0,
        'is_pinned' => 0,
        'author_name' => 'Administration'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 1);
    $target_audience = $_POST['target_audience'] ?? 'All';
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $author_name = trim($_POST['author_name'] ?? 'School Administration');

    if (empty($title) || empty($content)) {
        $error = 'Title and content are required.';
    } else {
        if ($is_connected && $pdo) {
            try {
                $sql = "UPDATE announcements SET 
                        title = ?, summary = ?, content = ?, category_id = ?, 
                        target_audience = ?, is_urgent = ?, is_pinned = ?, author_name = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $title, $summary, $content, $category_id, 
                    $target_audience, $is_urgent, $is_pinned, $author_name, $id
                ]);

                header('Location: index.php?msg=Announcement+updated+successfully!');
                exit;
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            header('Location: index.php?msg=Changes+previewed!+(Connect+VM+to+save)');
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
    <title>Edit Announcement - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-container { max-width: 800px; margin: 2.5rem auto; padding: 0 1.5rem; }
        .form-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; backdrop-filter: blur(16px); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
        .form-control { width: 100%; padding: 0.85rem 1rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: var(--text-main); font-size: 0.95rem; outline: none; }
        .checkbox-group { display: flex; gap: 2rem; margin-top: 1rem; }
        .checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="nav-container">
            <div class="brand-wrapper">
                <div class="brand-icon">🎓</div>
                <div class="brand-text">
                    <h1>Portal Administration</h1>
                    <p>Edit Announcement #<?= $id ?></p>
                </div>
            </div>
            <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </header>

    <div class="form-container">
        <div class="form-card">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Edit Announcement</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 2rem;">Modify the announcement content or visibility settings.</p>

            <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 0.85rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="edit.php?id=<?= $id ?>">
                <div class="form-group">
                    <label for="title">Announcement Title *</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($announcement['title']) ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" class="form-control" style="background: #1e293b;">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($announcement['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="target_audience">Target Audience</label>
                        <select id="target_audience" name="target_audience" class="form-control" style="background: #1e293b;">
                            <option value="All" <?= ($announcement['target_audience'] == 'All') ? 'selected' : '' ?>>All Campus Members</option>
                            <option value="Students" <?= ($announcement['target_audience'] == 'Students') ? 'selected' : '' ?>>Students Only</option>
                            <option value="Faculty" <?= ($announcement['target_audience'] == 'Faculty') ? 'selected' : '' ?>>Faculty & Staff</option>
                            <option value="Parents" <?= ($announcement['target_audience'] == 'Parents') ? 'selected' : '' ?>>Parents & Guardians</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="author_name">Author / Office Name</label>
                    <input type="text" id="author_name" name="author_name" class="form-control" value="<?= htmlspecialchars($announcement['author_name']) ?>">
                </div>

                <div class="form-group">
                    <label for="summary">Short Summary</label>
                    <textarea id="summary" name="summary" class="form-control" rows="2"><?= htmlspecialchars($announcement['summary']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="content">Full Content *</label>
                    <textarea id="content" name="content" class="form-control" rows="7" required><?= htmlspecialchars($announcement['content']) ?></textarea>
                </div>

                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_urgent" value="1" <?= !empty($announcement['is_urgent']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--danger);">
                        <span>🚨 <strong>Urgent Alert</strong> (Displays on top marquee)</span>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" name="is_pinned" value="1" <?= !empty($announcement['is_pinned']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>📌 <strong>Pin Announcement</strong></span>
                    </label>
                </div>

                <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.85rem 1.75rem;">
                        Save Changes 💾
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
