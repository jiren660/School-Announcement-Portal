<?php
/**
 * Student Official Announcement Portal
 * International State College of the Philippines
 * 
 * Replicates the public announcement board layout with personalized branding:
 * Displays announcements from the student's enrolled College and Department.
 * Ommits administrative dashboards in favor of direct bulletin access.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Ensure user is signed in as a student
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: ../login.php');
    exit;
}

$currentStudent = current_student();
$studentName = $currentStudent['name'] ?? 'Student';
$studentCollege = $currentStudent['enrolled_college'] ?? '';
$studentDepartment = $currentStudent['enrolled_department'] ?? '';

$pdo = get_db();
$is_connected = is_db_connected();
$db_error = get_db_error();

$categories = [];
$announcements = [];
$urgent_alerts = [];
$urgent_alert = null;

if ($is_connected && $pdo) {
    try {
        // Fetch categories
        $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
        $categories = $cat_stmt->fetchAll();

        // Scope conditions: Strictly student's enrolled Department & College announcements ONLY.
        // University-Wide and University Offices announcements are excluded.
        $scopeConditions = [];
        $scopeParams = [];

        // 1. Student's specific enrolled Department
        if (!empty($studentDepartment)) {
            $scopeConditions[] = "(a.publishing_unit = ? OR a.author_name = ?)";
            $scopeParams[] = $studentDepartment;
            $scopeParams[] = $studentDepartment;
        }

        // 2. Student's specific enrolled College
        if (!empty($studentCollege)) {
            $scopeConditions[] = "(a.publishing_unit = ? OR a.author_name = ?)";
            $scopeParams[] = $studentCollege;
            $scopeParams[] = $studentCollege;
        }

        if (!empty($scopeConditions)) {
            $scopeSql = '(' . implode(' OR ', $scopeConditions) . ')';
        } else {
            $scopeSql = '1=0';
        }

        // Fetch announcements with category details
        $sql = "
            SELECT a.*, 
                   COALESCE(a.reviewed_at, a.created_at) AS published_at,
                   COALESCE(c.name, a.category) AS category_name, 
                   COALESCE(c.badge_color, 'primary') AS badge_color, 
                   COALESCE(c.slug, 'general') AS category_slug 
            FROM announcements a
            LEFT JOIN categories c ON (a.category_id = c.id OR a.category = c.name)
            WHERE a.status IN ('published', 'approved')
              AND {$scopeSql}
            ORDER BY a.is_pinned DESC, a.is_urgent DESC, a.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($scopeParams);
        $announcements = $stmt->fetchAll();

        // Attach multi-photo attachments from announcement_images
        $all_ids = array_column($announcements, 'id');
        if (!empty($all_ids)) {
            $inPlaceholders = implode(',', array_fill(0, count($all_ids), '?'));
            try {
                $imgStmt = $pdo->prepare("SELECT announcement_id, image_path FROM announcement_images WHERE announcement_id IN ($inPlaceholders) ORDER BY id ASC");
                $imgStmt->execute($all_ids);
                $all_imgs = [];
                foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $im) {
                    $p = $im['image_path'];
                    if (!empty($p) && strpos($p, 'http') !== 0 && strpos($p, '/') !== 0 && strpos($p, '../') !== 0) {
                        $p = '../' . $p;
                    }
                    $all_imgs[$im['announcement_id']][] = $p;
                }
                foreach ($announcements as &$annItem) {
                    $itemImgs = $all_imgs[$annItem['id']] ?? [];
                    if (empty($itemImgs) && !empty($annItem['image_path'])) {
                        $single = $annItem['image_path'];
                        if (strpos($single, 'http') !== 0 && strpos($single, '/') !== 0 && strpos($single, '../') !== 0) {
                            $single = '../' . $single;
                        }
                        $itemImgs = [$single];
                    }
                    $annItem['images'] = $itemImgs;
                }
                unset($annItem);
            } catch (Exception $e) {
                foreach ($announcements as &$annItem) {
                    $annItem['images'] = !empty($annItem['image_path']) ? ['../' . $annItem['image_path']] : [];
                }
                unset($annItem);
            }
        }

    } catch (PDOException $e) {
        $db_error['message'] = $e->getMessage();
    }
}

// Fetch urgent announcements
$urgent_alerts = array_values(array_filter($announcements, function ($item) {
    return (
        !empty($item['is_urgent']) || 
        strcasecmp($item['category'] ?? '', 'Urgent Alert') === 0 || 
        strcasecmp($item['category_name'] ?? '', 'Urgent Alert') === 0 ||
        strcasecmp($item['category_slug'] ?? '', 'urgent-alert') === 0
    );
}));
$urgent_alert = $urgent_alerts[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($studentDepartment ?: $studentCollege ?: 'Student Announcements') ?> - International State College of the Philippines</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <!-- Top Navigation Bar -->
    <header class="navbar">
        <div class="nav-container">
            <div class="brand-wrapper">
                <img class="brand-logo" src="../assets/images/iscp_logo.png" alt="International State College of the Philippines logo">
                <div class="brand-text">
                    <h1>International State College of the Philippines</h1>
                    <p>Official Announcement Portal</p>
                </div>
            </div>

            <div class="nav-actions" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <!-- Student Identification Badge -->
                <div style="display: flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.12); padding: 0.4rem 0.85rem; border-radius: var(--radius-full); border: 1px solid rgba(255, 255, 255, 0.2); color: #ffffff; font-size: 0.85rem;">
                    <i class="fa-solid fa-user-graduate" style="color: #38bdf8;"></i>
                    <span style="font-weight: 700;"><?= htmlspecialchars($studentName) ?></span>
                    <?php if (!empty($studentDepartment)): ?>
                        <span style="background: #0284c7; color: #ffffff; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.55rem; border-radius: var(--radius-full); max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($studentDepartment) ?>">
                            <?= htmlspecialchars($studentDepartment) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Sign Out -->
                <a href="../logout.php" class="nav-btn-signin" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.35); color: #fca5a5;">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Urgent Alert Ribbon (Placed directly below Navbar with Red Background) -->
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

    <!-- Hero Section (Customized to Student's College & Department) -->
    <section class="hero-section">
        <h2 class="hero-title">
            <?php if (!empty($studentCollege) && !empty($studentDepartment)): ?>
                <?= htmlspecialchars($studentCollege) ?><br><?= htmlspecialchars($studentDepartment) ?>
            <?php else: ?>
                <?= htmlspecialchars($studentCollege ?: $studentDepartment ?: 'Campus Bulletins & Announcements') ?>
            <?php endif; ?>
        </h2>
        <p class="hero-subtitle">
            Official announcements, academic schedules, events, and advisories for enrolled students of <?= htmlspecialchars($studentDepartment ?: $studentCollege) ?>.
        </p>
        
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

    <!-- Student Enrolled Scope Confirmation Notice -->
    <div style="max-width: 1200px; margin: 0 auto 1.5rem; padding: 0.85rem 1.25rem; background: rgba(2, 132, 199, 0.08); border: 1px solid rgba(2, 132, 199, 0.25); border-radius: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.88rem; color: #0284c7;">
            <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
            <span>
                <strong>Signed In as <?= htmlspecialchars($studentName) ?>:</strong> 
                Displaying announcements from <strong><?= htmlspecialchars($studentDepartment ?: 'Department') ?></strong> and <strong><?= htmlspecialchars($studentCollege ?: 'College') ?></strong>.
            </span>
        </div>
        <div style="font-size: 0.8rem; color: #64748b;">
            <i class="fa-solid fa-id-card"></i> Student ID: <strong><?= htmlspecialchars($currentStudent['student_id'] ?? 'Verified') ?></strong>
        </div>
    </div>

    <!-- Main Announcements Feed -->
    <main class="feed-container">
        <!-- Scope Tabs Filter Bar -->
        <div class="scope-tabs-bar" role="tablist" aria-label="Scope Filter">
            <button class="scope-tab-btn active" data-scope="all">
                <i class="fa-solid fa-layer-group"></i> All Bulletins
            </button>
            <?php if (!empty($studentDepartment)): ?>
            <button class="scope-tab-btn" data-scope="department">
                <i class="fa-solid fa-network-wired"></i> <?= htmlspecialchars($studentDepartment) ?>
            </button>
            <?php endif; ?>
            <?php if (!empty($studentCollege)): ?>
            <button class="scope-tab-btn" data-scope="college">
                <i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($studentCollege) ?>
            </button>
            <?php endif; ?>
        </div>

        <div id="no-results-msg" style="display: none; text-align: center; padding: 3rem; color: var(--text-muted);">
            <h3>No announcements found</h3>
            <p>Try refining your search keyword or selecting a different category or authority filter.</p>
        </div>

        <?php if (empty($announcements)): ?>
        <div style="text-align: center; padding: 4.5rem 1.5rem; color: var(--text-muted); width: 100%;">
            <div style="width: 64px; height: 64px; margin: 0 auto 1.25rem; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.75rem;">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">No Announcements Published Yet</h3>
            <p style="font-size: 0.9rem; color: #64748b; max-width: 420px; margin: 0 auto;">Check back soon for academic advisories, exam schedules, and department notices.</p>
        </div>
        <?php else: ?>
        <div class="announcements-grid">
            <?php foreach ($announcements as $item): ?>
            <?php
            $unitAuthor = !empty($item['publishing_unit']) ? $item['publishing_unit'] : ($item['author_name'] ?? 'College');
            $unitLogo = get_organization_logo($unitAuthor, '../assets/images/iscp_logo.png');
            if (!empty($unitLogo) && strpos($unitLogo, 'http') !== 0 && strpos($unitLogo, '/') !== 0 && strpos($unitLogo, '../') !== 0) {
                $unitLogo = '../' . $unitLogo;
            }

            // Determine Scope Classification (Department vs College)
            $isDept = (!empty($studentDepartment) && (strcasecmp($unitAuthor, $studentDepartment) === 0 || stripos($unitAuthor, 'Department of') !== false));

            if ($isDept) {
                $scopeType = 'department';
                $scopeLabel = 'Department Notice';
                $scopeClass = 'is-department';
                $badgeClass = 'authority-badge-department';
                $scopeIcon = 'fa-network-wired';
            } else {
                $scopeType = 'college';
                $scopeLabel = 'College Notice';
                $scopeClass = 'is-college';
                $badgeClass = 'authority-badge-college';
                $scopeIcon = 'fa-graduation-cap';
            }
            ?>
            <article class="announcement-card <?= $scopeClass ?> <?= ($item['is_urgent'] ?? 0) ? 'is-urgent' : '' ?> <?= ($item['is_pinned'] ?? 0) ? 'is-pinned' : '' ?>"
                     data-id="<?= htmlspecialchars($item['id']) ?>"
                     data-scope="<?= $scopeType ?>"
                     data-scope-label="<?= $scopeLabel ?>"
                     data-track-views="true"
                     data-title="<?= htmlspecialchars($item['title']) ?>"
                     data-summary="<?= htmlspecialchars($item['summary']) ?>"
                     data-content="<?= htmlspecialchars($item['content']) ?>"
                     data-category="<?= htmlspecialchars($item['category_slug'] ?? 'general') ?>"
                     data-category-name="<?= htmlspecialchars($item['category_name'] ?? 'General') ?>"
                     data-badge-color="<?= htmlspecialchars($item['badge_color'] ?? 'primary') ?>"
                     data-author="<?= htmlspecialchars($unitAuthor) ?>"
                     data-logo="<?= htmlspecialchars($unitLogo) ?>"
                     data-images="<?= htmlspecialchars(json_encode($item['images'] ?? [])) ?>"
                     data-date="<?= htmlspecialchars(date('M d, Y', strtotime($item['published_at'] ?? 'now'))) ?>">
                
                <div>
                    <div class="card-top">
                        <span class="badge badge-<?= htmlspecialchars($item['badge_color'] ?? 'primary') ?>">
                            <?= htmlspecialchars($item['category_name'] ?? 'General') ?>
                        </span>
                        
                        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                            <span class="authority-badge <?= $badgeClass ?>">
                                <i class="fa-solid <?= $scopeIcon ?>"></i> <?= $scopeLabel ?>
                            </span>
                            <?php if (!empty($item['is_pinned'])): ?>
                                <span title="Pinned to top" style="font-size: 0.75rem; font-weight: 700; color: #0284c7;">📌 Pinned</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 class="card-title open-modal-trigger"><?= htmlspecialchars($item['title']) ?></h3>
                    <p class="card-summary"><?= htmlspecialchars($item['summary']) ?></p>

                    <!-- Preview Thumbnail if Attached Images Exist -->
                    <?php if (!empty($item['images'])): ?>
                        <div class="card-media-preview open-modal-trigger">
                            <img src="<?= htmlspecialchars($item['images'][0]) ?>" alt="Preview" loading="lazy">
                            <?php if (count($item['images']) > 1): ?>
                                <span class="card-photo-count">
                                    <i class="fa-solid fa-images"></i> <?= count($item['images']) ?> Photos
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer">
                    <div class="author-info" style="display: flex; align-items: center; gap: 0.55rem; overflow: hidden; max-width: 72%;">
                        <img src="<?= htmlspecialchars($unitLogo) ?>" alt="<?= htmlspecialchars($unitAuthor) ?>" style="width: 26px; height: 26px; object-fit: contain; border-radius: 5px; background: #fff; border: 1px solid #cbd5e1; padding: 1px; flex-shrink: 0;">
                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; font-size: 0.8rem;" title="<?= htmlspecialchars($unitAuthor) ?>">
                            <?= htmlspecialchars($unitAuthor) ?>
                        </span>
                    </div>
                    <span class="card-read-more open-modal-trigger">
                        <span>Read</span> <i class="fa-solid fa-arrow-right"></i>
                    </span>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Announcement Detail Interactive Modal with Facebook-Format Carousel -->
    <div id="announcement-modal" class="modal-overlay">
        <div class="modal-card">
            <button id="modal-close-btn" class="modal-close-btn">&times;</button>
            <div style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span id="modal-category" class="badge badge-primary">Category</span>
                <span id="modal-scope-badge" class="authority-badge authority-badge-university">Scope</span>
                <span id="modal-date" style="font-size: 0.8rem; color: var(--text-dim); margin-left: auto;">Date</span>
            </div>

            <h2 id="modal-title" style="font-size: 1.5rem; font-weight: 800; line-height: 1.35; margin-bottom: 1rem; color: #0f172a;">Announcement Title</h2>

            <!-- Official Authority Branding Banner in Modal -->
            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 1.25rem;">
                <img id="modal-author-logo" src="../assets/images/iscp_logo.png" style="width: 36px; height: 36px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #cbd5e1; padding: 2px; flex-shrink: 0;">
                <div>
                    <div style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Official Publishing Authority</div>
                    <strong id="modal-author" style="color: #0f172a; font-size: 0.95rem;">Author</strong>
                </div>
            </div>

            <!-- Facebook-Style Image Carousel in Modal -->
            <div id="modal-carousel-container" style="display: none; margin-bottom: 1.25rem;"></div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 1.25rem;">
            <div id="modal-body" class="modal-body" style="font-size: 0.96rem; line-height: 1.8; color: #334155; white-space: pre-wrap;">Announcement content...</div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> International State College of the Philippines — School Announcement Portal</p>
    </footer>

    <script src="../assets/js/app.js"></script>
</body>
</html>
