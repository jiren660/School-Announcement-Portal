<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Auth Guard
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_db();
$is_connected = is_db_connected();
$announcements = [];
$categories = [];
$total_views = 0;
$urgent_count = 0;

if ($is_connected && $pdo) {
    try {
        $stmt = $pdo->query("
            SELECT a.*, c.name AS category_name, c.badge_color 
            FROM announcements a
            LEFT JOIN categories c ON a.category_id = c.id
            ORDER BY a.is_pinned DESC, a.created_at DESC
        ");
        $announcements = $stmt->fetchAll();

        foreach ($announcements as $a) {
            $total_views += (int)($a['views_count'] ?? 0);
            if (!empty($a['is_urgent'])) $urgent_count++;
        }

        $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
} else {
    // Mock announcements for dashboard preview
    $announcements = [
        [
            'id' => 1,
            'title' => 'Suspension of Classes due to Inclement Weather',
            'category_name' => 'Urgent Alert',
            'badge_color' => 'danger',
            'author_name' => 'Office of the President',
            'is_urgent' => 1,
            'is_pinned' => 1,
            'status' => 'published',
            'views_count' => 428,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'title' => 'Midterm Examination Schedule for 1st Semester A.Y. 2026-2027',
            'category_name' => 'Academic Affairs',
            'badge_color' => 'primary',
            'author_name' => 'University Registrar',
            'is_urgent' => 0,
            'is_pinned' => 1,
            'status' => 'published',
            'views_count' => 315,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'id' => 3,
            'title' => 'Annual University Foundation Week & Sports Fest 2026',
            'category_name' => 'Campus Events',
            'badge_color' => 'success',
            'author_name' => 'Supreme Student Council',
            'is_urgent' => 0,
            'is_pinned' => 0,
            'status' => 'published',
            'views_count' => 194,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]
    ];
    $total_views = 937;
    $urgent_count = 1;
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - School Announcement Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            backdrop-filter: blur(12px);
        }
        .stat-num {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0.25rem 0;
        }
        .stat-label {
            font-size: 0.825rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .table-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th, td {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
        }
        th {
            background: rgba(15, 23, 42, 0.4);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        .actions-cell {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="nav-container">
            <div class="brand-wrapper">
                <div class="brand-icon">🎓</div>
                <div class="brand-text">
                    <h1>Portal Administration</h1>
                    <p>Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></p>
                </div>
            </div>

            <div class="nav-actions">
                <a href="../index.php" target="_blank" class="btn btn-secondary">🌐 View Public Feed</a>
                <a href="logout.php" class="btn btn-danger" style="padding: 0.5rem 1rem;">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="admin-container">

        <?php if (!empty($msg)): ?>
        <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; padding: 1rem 1.5rem; border-radius: var(--radius-sm); margin-bottom: 2rem;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <?php if (!$is_connected): ?>
        <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fde68a; padding: 1rem 1.5rem; border-radius: var(--radius-sm); margin-bottom: 2rem;">
            ⚠️ <strong>VirtualBox VM Database is not connected:</strong> Any new posts will only be previewed until MariaDB is running on your VM.
        </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Announcements</div>
                <div class="stat-num"><?= count($announcements) ?></div>
                <div style="color: var(--primary); font-size: 0.75rem;">Active in database</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Urgent Bulletins</div>
                <div class="stat-num" style="color: var(--danger);"><?= $urgent_count ?></div>
                <div style="color: var(--text-dim); font-size: 0.75rem;">Top banner alerts</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Portal Views</div>
                <div class="stat-num"><?= number_format($total_views) ?></div>
                <div style="color: var(--success); font-size: 0.75rem;">Student impressions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">DB Connection</div>
                <div class="stat-num" style="font-size: 1.6rem; color: <?= $is_connected ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= $is_connected ? '🟢 Connected' : '🔴 Offline' ?>
                </div>
                <div style="color: var(--text-dim); font-size: 0.75rem;">VirtualBox MariaDB</div>
            </div>
        </div>

        <!-- Management Table -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700;">All Announcements</h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem;">Manage publications, urgency tags, and pinning</p>
                </div>
                <a href="create.php" class="btn btn-primary">+ Post New Announcement</a>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Title & Summary</th>
                            <th>Category</th>
                            <th>Urgent</th>
                            <th>Pinned</th>
                            <th>Views</th>
                            <th>Author</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($announcements)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No announcements found. Click "+ Post New Announcement" to publish one!
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($announcements as $row): ?>
                        <tr>
                            <td style="max-width: 320px;">
                                <strong style="display: block; color: var(--text-main);"><?= htmlspecialchars($row['title']) ?></strong>
                                <span style="font-size: 0.75rem; color: var(--text-dim);"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($row['badge_color'] ?? 'primary') ?>">
                                    <?= htmlspecialchars($row['category_name'] ?? 'General') ?>
                                </span>
                            </td>
                            <td>
                                <?= !empty($row['is_urgent']) ? '<span style="color: var(--danger); font-weight: 700;">🚨 Yes</span>' : '<span style="color: var(--text-dim);">No</span>' ?>
                            </td>
                            <td>
                                <?= !empty($row['is_pinned']) ? '📌 Pinned' : '<span style="color: var(--text-dim);">-</span>' ?>
                            </td>
                            <td>👁️ <?= (int)$row['views_count'] ?></td>
                            <td><?= htmlspecialchars($row['author_name']) ?></td>
                            <td>
                                <div class="actions-cell">
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;">✏️ Edit</a>
                                    <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="return confirm('Are you sure you want to delete this announcement?');">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>
