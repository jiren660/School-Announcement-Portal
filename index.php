<?php
require_once __DIR__ . '/config/database.php';

$pdo = get_db();
$is_connected = is_db_connected();
$db_error = get_db_error();

$categories = [];
$announcements = [];
$urgent_alerts = [];
$urgent_alert = null;
$using_mock_data = false;

if ($is_connected && $pdo) {
    try {
        // Fetch categories
        $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
        $categories = $cat_stmt->fetchAll();

        // Fetch announcements with category details
        $sql = "
            SELECT a.*, c.name AS category_name, c.badge_color, c.slug AS category_slug 
            FROM announcements a
            JOIN categories c ON a.category_id = c.id
            WHERE a.status = 'published'
            ORDER BY a.is_pinned DESC, a.is_urgent DESC, a.published_at DESC
        ";
        $stmt = $pdo->query($sql);
        $announcements = $stmt->fetchAll();

    } catch (PDOException $e) {
        $is_connected = false;
        $db_error['message'] = $e->getMessage();
    }
}

// Fallback Mock Data if VM Database is not yet connected
if (!$is_connected || empty($announcements)) {
    $using_mock_data = true;
    $categories = [
        ['id' => 1, 'name' => 'Urgent Alert', 'slug' => 'urgent-alert', 'badge_color' => 'danger'],
        ['id' => 2, 'name' => 'Academic Affairs', 'slug' => 'academic-affairs', 'badge_color' => 'primary'],
        ['id' => 3, 'name' => 'Campus Events', 'slug' => 'campus-events', 'badge_color' => 'success'],
        ['id' => 4, 'name' => 'Student Life', 'slug' => 'student-life', 'badge_color' => 'warning'],
        ['id' => 5, 'name' => 'Admissions & Enrollment', 'slug' => 'admissions', 'badge_color' => 'info'],
    ];

    $announcements = [
        [
            'id' => 1,
            'title' => 'Suspension of Classes due to Inclement Weather',
            'summary' => 'Please be advised that all onsite and online classes are officially suspended today due to heavy rains and localized flooding.',
            'content' => "In accordance with the advisory issued by the City Disaster Risk Reduction and Management Office (CDRRMO), classes at all levels across all campuses are suspended today.\n\nStudents and faculty members are advised to stay indoors and monitor official communication channels for further updates. Stay safe everyone!",
            'category_name' => 'Urgent Alert',
            'category_slug' => 'urgent-alert',
            'badge_color' => 'danger',
            'is_urgent' => 1,
            'is_pinned' => 1,
            'author_name' => 'Office of the President',
            'views_count' => 428,
            'published_at' => date('M d, Y h:i A')
        ],
        [
            'id' => 2,
            'title' => 'Midterm Examination Schedule for 1st Semester A.Y. 2026-2027',
            'summary' => 'The official exam timetable, permit validation guidelines, and classroom assignments are now available for all departments.',
            'content' => "The Office of the University Registrar announces that Midterm Examinations will commence starting next Monday.\n\nKey Reminders:\n1. Ensure your Examination Permits are validated before Friday.\n2. Bring your valid Student ID card to each testing room.\n3. Strictly observe the university dress code and code of conduct during exam week.\n\nConsult your respective department chairs for specialized laboratory exam schedules.",
            'category_name' => 'Academic Affairs',
            'category_slug' => 'academic-affairs',
            'badge_color' => 'primary',
            'is_urgent' => 0,
            'is_pinned' => 1,
            'author_name' => 'University Registrar',
            'views_count' => 315,
            'published_at' => date('M d, Y', strtotime('-1 day'))
        ],
        [
            'id' => 3,
            'title' => 'Annual University Foundation Week & Sports Fest 2026',
            'summary' => 'Join us for a week full of sports competitions, academic debates, cultural dances, and the much-awaited University Battle of the Bands!',
            'content' => "Get ready for our Grand Foundation Celebration! The Supreme Student Council together with the Athletics Office invites all colleges to submit their team rosters for Basketball, Volleyball, Chess, Badminton, and Esports.\n\nRegistration booths are open at the Student Activity Center until Wednesday, 5:00 PM.",
            'category_name' => 'Campus Events',
            'category_slug' => 'campus-events',
            'badge_color' => 'success',
            'is_urgent' => 0,
            'is_pinned' => 0,
            'author_name' => 'Supreme Student Council',
            'views_count' => 194,
            'published_at' => date('M d, Y', strtotime('-2 days'))
        ],
        [
            'id' => 4,
            'title' => 'Special Enrollment & Adding/Dropping of Subjects Deadline',
            'summary' => 'Last call for students requesting section adjustments, subject overloads, and late enrollment processing.',
            'content' => "The final window for subject adjustment and late enrollment will close this Friday at 4:00 PM. No further extensions will be granted by the Academic Council. Please coordinate directly with your College Dean or Department Secretary.",
            'category_name' => 'Admissions & Enrollment',
            'category_slug' => 'admissions',
            'badge_color' => 'info',
            'is_urgent' => 0,
            'is_pinned' => 0,
            'author_name' => 'Academic Affairs Office',
            'views_count' => 120,
            'published_at' => date('M d, Y', strtotime('-3 days'))
        ]
    ];

}

$urgent_alerts = array_values(array_filter($announcements, function ($item) {
    return !empty($item['is_urgent']);
}));
$urgent_alert = $urgent_alerts[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Announcement Portal - International State College of the Philippines</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Top Navigation Bar -->
    <header class="navbar">
        <div class="nav-container">
            <div class="brand-wrapper">
                <img class="brand-logo" src="assets/images/iscp_logo.png" alt="International State College of the Philippines logo">
                <div class="brand-text">
                    <h1>International State College of the Philippines</h1>
                    <p>Official Announcement Portal</p>
                </div>
            </div>

        </div>
    </header>

    <!-- Category Filters -->
    <section class="filter-bar">
        <div class="category-pills">
            <button class="pill-btn active" data-category="all">All Announcements</button>
            <?php foreach ($categories as $cat): ?>
            <button class="pill-btn" data-category="<?= htmlspecialchars($cat['slug']) ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Database Offline Notice (if VM is not yet active) -->
    <?php if (!$is_connected): ?>
    <div style="background: rgba(245, 158, 11, 0.15); border-bottom: 1px solid rgba(245, 158, 11, 0.3); color: #fde68a; padding: 0.75rem 2rem; font-size: 0.85rem; text-align: center;">
        ℹ️ <strong>Preview Mode Active:</strong> Cannot connect to MariaDB at <code><?= htmlspecialchars($db_error['host'] . ':' . $db_error['port']) ?></code>. 
        Start your VirtualBox VM and run <code>sudo systemctl start mariadb</code> to sync live data!
    </div>
    <?php endif; ?>

    <!-- Urgent Alert Ribbon -->
    <?php if ($urgent_alert): ?>
    <section class="urgent-banner" id="urgent-banner" aria-live="polite">
        <div class="urgent-content">
            <span class="urgent-badge">URGENT</span>
            <span class="urgent-message" id="urgent-message">
                <strong id="urgent-title"><?= htmlspecialchars($urgent_alert['title']) ?>:</strong>
                <span id="urgent-summary"><?= htmlspecialchars($urgent_alert['summary']) ?></span>
            </span>
        </div>
        <div class="urgent-alert-data" id="urgent-alert-data" hidden>
            <?php foreach ($urgent_alerts as $urgent_item): ?>
            <div class="urgent-alert-item"
                 data-title="<?= htmlspecialchars($urgent_item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 data-summary="<?= htmlspecialchars($urgent_item['summary'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <h2 class="hero-title">ISCP Campus Bulletins & Announcements</h2>
        <p class="hero-subtitle">Stay informed with the latest official advisories, academic schedules, events, and campus updates.</p>
        <div class="search-box-wrapper">
            <label class="visually-hidden" for="search-input">Search announcements</label>
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <circle cx="10.8" cy="10.8" r="6.8"></circle>
                    <path d="m16 16 4.5 4.5"></path>
                </svg>
            </span>
            <input type="text" id="search-input" class="search-input" placeholder="Search announcements by title or keywords...">
        </div>
    </section>

    <!-- Main Announcements Feed -->
    <main class="feed-container">
        <div id="no-results-msg" style="display: none; text-align: center; padding: 3rem; color: var(--text-muted);">
            <h3>No announcements found</h3>
            <p>Try refining your search keyword or selecting a different category filter.</p>
        </div>

        <div class="announcements-grid">
            <?php foreach ($announcements as $item): ?>
            <article class="announcement-card <?= ($item['is_urgent'] ?? 0) ? 'is-urgent' : '' ?> <?= ($item['is_pinned'] ?? 0) ? 'is-pinned' : '' ?>"
                     data-id="<?= htmlspecialchars($item['id']) ?>"
                     data-track-views="<?= $using_mock_data ? 'false' : 'true' ?>"
                     data-title="<?= htmlspecialchars($item['title']) ?>"
                     data-summary="<?= htmlspecialchars($item['summary']) ?>"
                     data-content="<?= htmlspecialchars($item['content']) ?>"
                     data-category="<?= htmlspecialchars($item['category_slug'] ?? 'general') ?>"
                     data-category-name="<?= htmlspecialchars($item['category_name'] ?? 'General') ?>"
                     data-badge-color="<?= htmlspecialchars($item['badge_color'] ?? 'primary') ?>"
                     data-author="<?= htmlspecialchars($item['author_name'] ?? 'Administration') ?>"
                     data-date="<?= htmlspecialchars(date('M d, Y', strtotime($item['published_at'] ?? 'now'))) ?>">
                
                <div>
                    <div class="card-top">
                        <span class="badge badge-<?= htmlspecialchars($item['badge_color'] ?? 'primary') ?>">
                            <?= htmlspecialchars($item['category_name'] ?? 'General') ?>
                        </span>
                        
                        <div class="card-flags">
                            <?php if (!empty($item['is_pinned'])): ?>
                                <span title="Pinned to top">📌 Pinned</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 class="card-title open-modal-trigger"><?= htmlspecialchars($item['title']) ?></h3>
                    <p class="card-summary"><?= htmlspecialchars($item['summary']) ?></p>
                </div>

                <div class="card-footer">
                    <div class="author-info">
                        <div class="author-avatar"><?= strtoupper(substr($item['author_name'] ?? 'A', 0, 1)) ?></div>
                        <span><?= htmlspecialchars($item['author_name'] ?? 'Administration') ?></span>
                    </div>
                    <span><?= htmlspecialchars(date('M d, Y', strtotime($item['published_at'] ?? 'now'))) ?></span>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Announcement Detail Interactive Modal -->
    <div id="announcement-modal" class="modal-overlay">
        <div class="modal-card">
            <button id="modal-close-btn" class="modal-close-btn">&times;</button>
            <div style="margin-bottom: 1rem;">
                <span id="modal-category" class="badge badge-primary">Category</span>
                <span id="modal-date" style="font-size: 0.8rem; color: var(--text-dim); margin-left: 0.75rem;">Date</span>
            </div>
            <h2 id="modal-title" style="font-size: 1.6rem; font-weight: 800; line-height: 1.3; margin-bottom: 0.75rem;">Announcement Title</h2>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Posted by: <strong id="modal-author" style="color: var(--text-main);">Author</strong>
            </div>
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 1.5rem;">
            <div id="modal-body" class="modal-body">Announcement content...</div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> International State College of the Philippines — School Announcement Portal</p>
        <a class="footer-admin-link" href="admin/login.php">Admin Login</a>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
