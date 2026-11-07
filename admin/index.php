<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Auth Guard
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['msg']) && is_string($_GET['msg'])) {
    $_SESSION['admin_flash_message'] = $_GET['msg'];
    header('Location: index.php');
    exit;
}

$msg = $_SESSION['admin_flash_message'] ?? '';
unset($_SESSION['admin_flash_message']);

$pdo = get_db();
$is_connected = is_db_connected();
$announcements = [];
$categories = [];
$error = '';
$total_views = 0;
$urgent_count = 0;
$published_count = 0;

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
            if (($a['status'] ?? '') === 'published') $published_count++;
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
    $published_count = count($announcements);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_announcement') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 1);
    $target_audience = $_POST['target_audience'] ?? 'All';
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $author_name = trim($_POST['author_name'] ?? 'School Administration');

    if ($title === '' || $content === '') {
        $error = 'Title and content are required.';
    } elseif ($is_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("
                UPDATE announcements
                SET title = ?, summary = ?, content = ?, category_id = ?,
                    target_audience = ?, is_urgent = ?, is_pinned = ?, author_name = ?
                WHERE id = ?
            ");
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
        $msg = 'Changes previewed! (Connect VM to save)';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - International State College of the Philippines</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-navbar {
            padding-block: 0.8rem;
            box-shadow: 0 8px 24px rgba(2, 31, 74, 0.2);
        }
        .admin-navbar .nav-container {
            max-width: 1440px;
        }
        .admin-navbar .brand-logo {
            padding: 0;
            background: transparent;
            border: 0;
        }
        .admin-navbar .nav-actions {
            gap: 0.75rem;
        }
        .admin-navbar .nav-actions .btn {
            min-height: 42px;
            justify-content: center;
            padding: 0.65rem 1rem;
            border-radius: 0.7rem;
            font-size: 0.84rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0, 20, 54, 0.16);
        }
        .admin-navbar .feed-link {
            color: #073b82;
            border: 1px solid rgba(255, 255, 255, 0.75);
            background: linear-gradient(135deg, #fff, #dcecff);
        }
        .admin-navbar .feed-link:hover {
            background: #fff;
            box-shadow: 0 7px 18px rgba(0, 20, 54, 0.24);
        }
        .admin-navbar .sign-out-link {
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: linear-gradient(135deg, #e34f61, #be263c);
        }
        .admin-navbar .sign-out-link:hover {
            background: linear-gradient(135deg, #d93d52, #a91f34);
            box-shadow: 0 7px 18px rgba(120, 17, 38, 0.28);
        }
        .admin-navbar .nav-actions .btn:focus-visible {
            outline: 3px solid #bfdbfe;
            outline-offset: 3px;
        }
        .admin-navbar .nav-icon {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }
        .ui-icon {
            width: 1em;
            height: 1em;
            flex: 0 0 auto;
            vertical-align: -0.15em;
        }
        .icon-label {
            display: inline-flex;
            align-items: center;
            gap: 0.35em;
        }
        .admin-container {
            max-width: 1440px;
            margin: 0 auto 2.5rem;
            padding: 0 2rem;
        }
        .welcome-banner {
            margin-bottom: 1.25rem;
            padding: 0.85rem 1.1rem;
            color: #fff;
            background: linear-gradient(105deg, #063b82, #0755b8 55%, #3184e8);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 0.75rem;
            box-shadow: 0 6px 16px rgba(2, 31, 74, 0.12);
            font-size: clamp(1.35rem, 2vw, 1.65rem);
            font-weight: 600;
        }
        .welcome-banner strong {
            color: inherit;
            font-weight: 800;
            font-size: inherit;
        }
        .dashboard-intro {
            margin-bottom: 1.5rem;
        }
        .dashboard-intro h2 {
            color: #073b82;
            font-size: clamp(1.6rem, 3vw, 2.25rem);
            letter-spacing: -0.035em;
        }
        .dashboard-intro p {
            margin-top: 0.35rem;
            color: #355b85;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(205px, 1fr));
            gap: 1rem;
            margin-bottom: 0;
        }
        .dashboard-overview {
            width: 100vw;
            margin-left: calc(50% - 50vw);
            margin-bottom: 2rem;
            padding: 1.75rem max(2rem, calc((100vw - 1440px) / 2 + 2rem));
            border: 1px solid #c5ddf8;
            border-radius: 1rem;
            background: linear-gradient(120deg, #e5f1ff, #d5e8ff 55%, #c7e0ff);
            box-shadow: 0 12px 32px rgba(16, 55, 102, 0.1);
        }
        .stat-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(232, 242, 255, 0.94));
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem 1.4rem;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: linear-gradient(90deg, #0755b8, #60a5fa);
        }
        .stat-card.stat-urgent::before {
            background: linear-gradient(90deg, #dc2626, #fb7185);
        }
        .stat-card.stat-views::before {
            background: linear-gradient(90deg, #0284c7, #38bdf8);
        }
        .stat-card.stat-connection::before {
            background: linear-gradient(90deg, #059669, #34d399);
        }
        .stat-num {
            font-size: 2rem;
            line-height: 1.2;
            font-weight: 800;
            color: var(--text-main);
            margin: 0.45rem 0;
        }
        .stat-detail {
            color: var(--text-muted);
            font-size: 0.78rem;
        }
        .table-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(0, 31, 82, 0.2);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1.4rem 1.6rem;
            border-bottom: 1px solid var(--border-color);
        }
        .table-toolbar {
            display: flex;
            gap: 0.75rem;
            padding: 1rem 1.6rem;
            background: linear-gradient(135deg, #f8fbff, #edf5ff);
            border-bottom: 1px solid var(--border-color);
        }
        .table-filter {
            min-width: 0;
            padding: 0.7rem 0.9rem;
            background: #fff;
            border: 1px solid #c7dcf6;
            border-radius: var(--radius-sm);
            color: var(--text-main);
            font: inherit;
        }
        .table-search {
            flex: 1;
        }
        .table-filter:focus {
            outline: 3px solid rgba(7, 85, 184, 0.16);
            border-color: var(--primary);
        }
        .edit-modal {
            position: fixed;
            inset: 0;
            z-index: 1100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            background: rgba(8, 30, 62, 0.62);
            backdrop-filter: blur(8px);
        }
        .edit-modal.is-open {
            display: flex;
        }
        .edit-modal-card {
            width: min(100%, 820px);
            max-height: min(90vh, 900px);
            overflow-y: auto;
            padding: clamp(1.25rem, 3vw, 2rem);
            border: 1px solid #d5e5f7;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 24px 70px rgba(0, 20, 54, 0.3);
        }
        .edit-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .edit-modal-header h2 {
            color: #073b82;
            font-size: 1.4rem;
        }
        .edit-modal-header p {
            margin-top: 0.3rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .edit-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            border: 1px solid #d5e5f7;
            border-radius: 0.65rem;
            background: #f3f8ff;
            color: #16477f;
            font-size: 1.35rem;
            cursor: pointer;
        }
        .edit-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 1rem;
        }
        .edit-modal .form-group {
            margin-bottom: 1rem;
        }
        .edit-modal .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #355b85;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .edit-modal .form-control {
            width: 100%;
            padding: 0.7rem 0.8rem;
            border: 1px solid #c7dcf6;
            border-radius: 0.55rem;
            background: #fff;
            color: var(--text-main);
            font: inherit;
        }
        .edit-modal .form-control:focus {
            outline: 3px solid rgba(7, 85, 184, 0.14);
            border-color: var(--primary);
        }
        .edit-modal-checks,
        .edit-modal-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
        }
        .edit-modal-checks {
            margin: 0.25rem 0 1.5rem;
        }
        .edit-modal-checks label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-main);
            font-size: 0.88rem;
            font-weight: 600;
        }
        .edit-modal-checks input {
            width: 17px;
            height: 17px;
            accent-color: var(--primary);
        }
        .edit-modal-actions {
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .delete-modal-card {
            width: min(100%, 460px);
            padding: 1.75rem;
            border: 1px solid #f3c6cb;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 24px 70px rgba(0, 20, 54, 0.3);
        }
        .delete-modal-card h2 {
            color: #9f1239;
            font-size: 1.35rem;
        }
        .delete-modal-card p {
            margin-top: 0.55rem;
            color: var(--text-muted);
        }
        .delete-modal-title {
            display: block;
            margin-top: 0.7rem;
            color: var(--text-main);
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .delete-modal-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .feedback-toast {
            position: fixed;
            right: 1.5rem;
            bottom: 1.5rem;
            z-index: 1200;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            width: min(420px, calc(100vw - 2rem));
            padding: 1rem 1.2rem;
            border: 1px solid #c9def8;
            border-left: 4px solid #0755b8;
            border-radius: 0.9rem;
            background: linear-gradient(145deg, #fff, #f3f8ff);
            box-shadow: 0 16px 40px rgba(0, 31, 82, 0.22);
            color: #073b82;
            transition: opacity 350ms ease, transform 350ms ease;
        }
        .feedback-toast.is-hiding {
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
        }
        .feedback-toast-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            border-radius: 50%;
            background: #e3efff;
            color: #0755b8;
        }
        .feedback-toast-icon svg {
            width: 21px;
            height: 21px;
        }
        .feedback-toast-message {
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.4;
        }
        @media (max-width: 520px) {
            .feedback-toast {
                right: 1rem;
                bottom: 1rem;
                width: calc(100vw - 2rem);
            }
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th, td {
            padding: 0.95rem 1.1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.84rem;
            vertical-align: middle;
        }
        th {
            background: linear-gradient(135deg, #eaf3ff, #f8fbff);
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        tbody tr:last-child td {
            border-bottom: 0;
        }
        tr:hover td {
            background: rgba(219, 234, 254, 0.35);
        }
        .announcement-name {
            display: block;
            max-width: 360px;
            color: var(--text-main);
            font-weight: 700;
            line-height: 1.45;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .status-published {
            color: #047857;
            background: #d1fae5;
        }
        .status-draft {
            color: #92400e;
            background: #fef3c7;
        }
        .status-archived {
            color: #475569;
            background: #e2e8f0;
        }
        .actions-cell {
            display: flex;
            gap: 0.45rem;
            white-space: nowrap;
        }
        .empty-filter-row td {
            padding: 2.5rem;
            color: var(--text-muted);
            text-align: center;
        }
        @media (max-width: 700px) {
            .admin-navbar {
                padding: 0.85rem 1rem;
            }
            .admin-navbar .nav-container {
                gap: 0.75rem;
            }
            .admin-navbar .nav-actions {
                width: 100%;
                justify-content: center;
            }
            .admin-navbar .nav-actions .btn {
                flex: 1;
            }
            .admin-container {
                margin: 0 auto 1.5rem;
                padding: 0 1rem;
            }
            .welcome-banner {
                padding: 0.85rem 1rem;
            }
            .dashboard-overview {
                padding: 1.25rem;
            }
            .edit-modal-grid {
                grid-template-columns: 1fr;
            }
            .table-header {
                align-items: stretch;
                flex-direction: column;
                padding: 1.2rem;
            }
            .table-header .btn {
                justify-content: center;
            }
            .table-toolbar {
                flex-direction: column;
                padding: 1rem 1.2rem;
            }
            .table-filter {
                width: 100%;
            }
            th, td {
                padding: 0.8rem;
            }
        }
    </style>
</head>
<body>

    <header class="navbar admin-navbar">
        <div class="nav-container">
            <div class="brand-wrapper">
                <img class="brand-logo" src="../assets/images/iscp_logo.png" alt="International State College of the Philippines logo">
                <div class="brand-text">
                    <h1>Portal Administration</h1>
                </div>
            </div>

            <div class="nav-actions">
                <a href="../index.php" target="_blank" rel="noopener noreferrer" class="btn feed-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 3h7v7"></path>
                        <path d="M10 14 21 3"></path>
                        <path d="M19 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"></path>
                    </svg>
                    <span>View Public Feed</span>
                </a>
                <a href="logout.php" class="btn sign-out-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 17l5-5-5-5"></path>
                        <path d="M15 12H3"></path>
                        <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path>
                    </svg>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </header>

    <div class="admin-container">

        <?php if (!empty($error)): ?>
        <div role="alert" style="background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; padding: 1rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!$is_connected): ?>
        <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fde68a; padding: 1rem 1.5rem; border-radius: var(--radius-sm); margin-bottom: 2rem;">
            <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.1l-8-14a2 2 0 0 0-3.4 0Z"></path>
                <path d="M12 9v4"></path><path d="M12 17h.01"></path>
            </svg>
            <strong>VirtualBox VM Database is not connected:</strong> Any new posts will only be previewed until MariaDB is running on your VM.
        </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <section class="dashboard-overview" aria-labelledby="dashboard-overview-title">
            <div class="dashboard-intro">
                 Welcome, <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></strong>
                <h2 id="dashboard-overview-title">Dashboard Overview</h2>
                <p>Monitor announcements and manage what your campus sees.</p>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">All Announcements</div>
                    <div class="stat-num"><?= count($announcements) ?></div>
                    <div class="stat-detail">Across all publication statuses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Published</div>
                    <div class="stat-num"><?= $published_count ?></div>
                    <div class="stat-detail">Visible on the public feed</div>
                </div>
                <div class="stat-card stat-urgent">
                    <div class="stat-label">Urgent Bulletins</div>
                    <div class="stat-num" style="color: var(--danger);"><?= $urgent_count ?></div>
                    <div class="stat-detail">Marked as urgent</div>
                </div>
                <div class="stat-card stat-views">
                    <div class="stat-label">Total Portal Views</div>
                    <div class="stat-num"><?= number_format($total_views) ?></div>
                    <div class="stat-detail">Across all announcements</div>
                </div>
                <div class="stat-card stat-connection">
                    <div class="stat-label">DB Connection</div>
                    <div class="stat-num" style="font-size: 1.25rem; color: <?= $is_connected ? 'var(--success)' : 'var(--danger)' ?>;">
                        <span class="icon-label">
                            <?php if ($is_connected): ?>
                            <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16 9"></path>
                            </svg>
                            Connected
                            <?php else: ?>
                            <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle><path d="m9 9 6 6"></path><path d="m15 9-6 6"></path>
                            </svg>
                            Offline
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="stat-detail">VirtualBox MariaDB</div>
                </div>
            </div>
        </section>

        <!-- Management Table -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700;">All Announcements</h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem;"><?= count($announcements) ?> total · Manage publication status, urgency, and pinning</p>
                </div>
                <a href="create.php" class="btn btn-primary">+ Post New Announcement</a>
            </div>

            <div class="table-toolbar">
                <label class="visually-hidden" for="announcement-search">Search announcements</label>
                <input class="table-filter table-search" type="search" id="announcement-search" placeholder="Search title, category, or author...">
                <label class="visually-hidden" for="announcement-status">Filter by status</label>
                <select class="table-filter" id="announcement-status">
                    <option value="all">All statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Title & Summary</th>
                            <th>Category</th>
                            <th>Status</th>
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
                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No announcements found. Click "+ Post New Announcement" to publish one!
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($announcements as $row): ?>
                        <tr class="announcement-row" data-status="<?= htmlspecialchars($row['status'] ?? 'draft') ?>">
                            <td>
                                <strong class="announcement-name"><?= htmlspecialchars($row['title']) ?></strong>
                                <span style="font-size: 0.75rem; color: var(--text-dim);"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($row['badge_color'] ?? 'primary') ?>">
                                    <?= htmlspecialchars($row['category_name'] ?? 'General') ?>
                                </span>
                            </td>
                            <td>
                                <?php $status = $row['status'] ?? 'draft'; ?>
                                <span class="status-badge status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($row['is_urgent'])): ?>
                                <span class="icon-label" style="color: var(--danger); font-weight: 700;">
                                    <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>
                                    </svg>
                                    Yes
                                </span>
                                <?php else: ?>
                                <span style="color: var(--text-dim);">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['is_pinned'])): ?>
                                <span class="icon-label">
                                    <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m16 3 5 5-4 1-4 4 3 3-1 1-4-2-5 5-1-1 5-5-2-4 1-1 3 3 4-4z"></path>
                                    </svg>
                                    Pinned
                                </span>
                                <?php else: ?>
                                <span style="color: var(--text-dim);">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)($row['views_count'] ?? 0) ?></td>
                            <td><?= htmlspecialchars($row['author_name']) ?></td>
                            <td>
                                <div class="actions-cell">
                                    <button type="button" class="btn btn-secondary icon-label edit-announcement-button" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
                                        data-announcement="<?= htmlspecialchars(json_encode([
                                            'id' => $row['id'],
                                            'title' => $row['title'] ?? '',
                                            'summary' => $row['summary'] ?? '',
                                            'content' => $row['content'] ?? '',
                                            'category_id' => $row['category_id'] ?? ($categories[0]['id'] ?? 1),
                                            'target_audience' => $row['target_audience'] ?? 'All',
                                            'author_name' => $row['author_name'] ?? '',
                                            'is_urgent' => !empty($row['is_urgent']),
                                            'is_pinned' => !empty($row['is_pinned'])
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>">
                                        <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="m16 4 4 4"></path><path d="M4 20l4.5-1 11-11a2.1 2.1 0 0 0-3-3l-11 11L4 20Z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button type="button" class="btn btn-danger icon-label delete-announcement-button" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 14H6L5 6"></path><path d="M10 11v5"></path><path d="M14 11v5"></path>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="empty-filter-row" id="no-announcements-match" hidden>
                            <td colspan="8">No announcements match these filters.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php if (!empty($msg)): ?>
    <div class="feedback-toast" id="feedback-toast" role="status" aria-live="polite">
        <span class="feedback-toast-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"></path>
            </svg>
        </span>
        <span class="feedback-toast-message"><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endif; ?>

    <div class="edit-modal" id="edit-announcement-modal" aria-hidden="true">
        <section class="edit-modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
            <div class="edit-modal-header">
                <div>
                    <h2 id="edit-modal-title">Edit Announcement</h2>
                    <p>Update announcement content and visibility settings.</p>
                </div>
                <button type="button" class="edit-modal-close" aria-label="Close edit dialog">&times;</button>
            </div>
            <form method="post" action="index.php" id="edit-announcement-form">
                <input type="hidden" name="action" value="update_announcement">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-group">
                    <label for="edit-title">Announcement Title *</label>
                    <input type="text" class="form-control" id="edit-title" name="title" required>
                </div>
                <div class="edit-modal-grid">
                    <div class="form-group">
                        <label for="edit-category-id">Category *</label>
                        <select class="form-control" id="edit-category-id" name="category_id">
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <option value="1">General</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-target-audience">Target Audience</label>
                        <select class="form-control" id="edit-target-audience" name="target_audience">
                            <option value="All">All Campus Members</option>
                            <option value="Students">Students Only</option>
                            <option value="Faculty">Faculty &amp; Staff</option>
                            <option value="Parents">Parents &amp; Guardians</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit-author-name">Author / Office Name</label>
                    <input type="text" class="form-control" id="edit-author-name" name="author_name">
                </div>
                <div class="form-group">
                    <label for="edit-summary">Short Summary</label>
                    <textarea class="form-control" id="edit-summary" name="summary" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit-content">Full Content *</label>
                    <textarea class="form-control" id="edit-content" name="content" rows="6" required></textarea>
                </div>
                <div class="edit-modal-checks">
                    <label><input type="checkbox" name="is_urgent" id="edit-is-urgent" value="1"> Urgent Alert</label>
                    <label><input type="checkbox" name="is_pinned" id="edit-is-pinned" value="1"> Pin Announcement</label>
                </div>
                <div class="edit-modal-actions">
                    <button type="button" class="btn btn-secondary edit-modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </section>
    </div>

    <div class="edit-modal" id="delete-announcement-modal" aria-hidden="true">
        <section class="delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title" aria-describedby="delete-modal-description">
            <h2 id="delete-modal-title">Delete Announcement?</h2>
            <p id="delete-modal-description">This action cannot be undone. Are you sure you want to delete:</p>
            <span class="delete-modal-title" id="delete-announcement-title"></span>
            <form method="get" action="delete.php" id="delete-announcement-form">
                <input type="hidden" name="id" id="delete-announcement-id">
                <div class="delete-modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-delete-announcement">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Announcement</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        const searchField = document.getElementById('announcement-search');
        const statusFilter = document.getElementById('announcement-status');
        const announcementRows = document.querySelectorAll('.announcement-row');
        const noMatchesRow = document.getElementById('no-announcements-match');
        const editModal = document.getElementById('edit-announcement-modal');
        const editForm = document.getElementById('edit-announcement-form');
        const editButtons = document.querySelectorAll('.edit-announcement-button');
        const closeEditModalButton = editModal.querySelector('.edit-modal-close');
        const feedbackToast = document.getElementById('feedback-toast');
        const deleteModal = document.getElementById('delete-announcement-modal');
        const deleteButtons = document.querySelectorAll('.delete-announcement-button');
        const deleteIdField = document.getElementById('delete-announcement-id');
        const deleteTitle = document.getElementById('delete-announcement-title');
        let lastDeleteTrigger = null;
        let lastEditTrigger = null;

        function filterAnnouncements() {
            const query = searchField.value.trim().toLowerCase();
            const selectedStatus = statusFilter.value;
            let visibleCount = 0;

            announcementRows.forEach(row => {
                const matchesQuery = row.textContent.toLowerCase().includes(query);
                const matchesStatus = selectedStatus === 'all' || row.dataset.status === selectedStatus;
                row.hidden = !(matchesQuery && matchesStatus);
                if (!row.hidden) visibleCount++;
            });

            if (noMatchesRow) noMatchesRow.hidden = visibleCount > 0;
        }

        searchField.addEventListener('input', filterAnnouncements);
        statusFilter.addEventListener('change', filterAnnouncements);

        function closeEditModal() {
            editModal.classList.remove('is-open');
            editModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (lastEditTrigger) lastEditTrigger.focus();
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('is-open');
            deleteModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (lastDeleteTrigger) lastDeleteTrigger.focus();
        }

        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const announcement = JSON.parse(button.dataset.announcement);
                lastEditTrigger = button;
                editForm.elements.id.value = announcement.id;
                editForm.elements.title.value = announcement.title;
                editForm.elements.summary.value = announcement.summary;
                editForm.elements.content.value = announcement.content;
                editForm.elements.category_id.value = announcement.category_id;
                editForm.elements.target_audience.value = announcement.target_audience;
                editForm.elements.author_name.value = announcement.author_name;
                editForm.elements.is_urgent.checked = announcement.is_urgent;
                editForm.elements.is_pinned.checked = announcement.is_pinned;
                editModal.classList.add('is-open');
                editModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                document.getElementById('edit-title').focus();
            });
        });

        deleteButtons.forEach(button => {
            button.addEventListener('click', () => {
                lastDeleteTrigger = button;
                deleteIdField.value = button.dataset.id;
                deleteTitle.textContent = button.dataset.title;
                deleteModal.classList.add('is-open');
                deleteModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                document.getElementById('cancel-delete-announcement').focus();
            });
        });

        closeEditModalButton.addEventListener('click', closeEditModal);
        editModal.querySelector('.edit-modal-cancel').addEventListener('click', closeEditModal);
        document.getElementById('cancel-delete-announcement').addEventListener('click', closeDeleteModal);
        editModal.addEventListener('click', event => {
            if (event.target === editModal) closeEditModal();
        });
        deleteModal.addEventListener('click', event => {
            if (event.target === deleteModal) closeDeleteModal();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && editModal.classList.contains('is-open')) {
                closeEditModal();
            }
            if (event.key === 'Escape' && deleteModal.classList.contains('is-open')) {
                closeDeleteModal();
            }
        });

        if (feedbackToast) {
            window.setTimeout(() => {
                feedbackToast.classList.add('is-hiding');
                window.setTimeout(() => feedbackToast.remove(), 350);
            }, 3000);
        }
    </script>
</body>
</html>
