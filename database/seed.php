<?php
/**
 * Database Seeder for School Announcement Portal
 * Can be run via CLI: php database/seed.php
 */

require_once dirname(__DIR__) . '/config/database.php';

$pdo = get_db();

if (!$pdo) {
    echo "Error: Database connection is not available.\n";
    exit(1);
}

echo "Database connected successfully (" . get_db_driver() . "). Seeding initial data...\n";

// 1. Ensure Categories exist
$categories = [
    ['id' => 1, 'name' => 'Urgent Alert', 'slug' => 'urgent-alert', 'badge_color' => 'danger', 'icon' => 'alert-triangle'],
    ['id' => 2, 'name' => 'Academic Affairs', 'slug' => 'academic-affairs', 'badge_color' => 'primary', 'icon' => 'book-open'],
    ['id' => 3, 'name' => 'Campus Events', 'slug' => 'campus-events', 'badge_color' => 'success', 'icon' => 'calendar'],
    ['id' => 4, 'name' => 'Student Life', 'slug' => 'student-life', 'badge_color' => 'warning', 'icon' => 'users'],
    ['id' => 5, 'name' => 'Admissions & Enrollment', 'slug' => 'admissions', 'badge_color' => 'info', 'icon' => 'file-text'],
];

foreach ($categories as $cat) {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? OR slug = ?");
    $stmt->execute([$cat['id'], $cat['slug']]);
    if (!$stmt->fetch()) {
        $ins = $pdo->prepare("INSERT INTO categories (id, name, slug, badge_color, icon) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$cat['id'], $cat['name'], $cat['slug'], $cat['badge_color'], $cat['icon']]);
    }
}
echo "✓ Categories checked/seeded.\n";

// 2. Ensure Users exist
$admin_pass_hash = password_hash('AdminPass123!', PASSWORD_DEFAULT);
$announcer_pass_hash = password_hash('AnnouncerPass123!', PASSWORD_DEFAULT);

$users = [
    [
        'id' => 1,
        'name' => 'System Administrator',
        'email' => 'admin@iscp.edu.ph',
        'password_hash' => $admin_pass_hash,
        'role' => 'admin',
        'status' => 'active'
    ],
    [
        'id' => 2,
        'name' => 'John Doe (Announcer)',
        'email' => 'announcer@iscp.edu.ph',
        'password_hash' => $announcer_pass_hash,
        'role' => 'announcer',
        'status' => 'active'
    ],
    [
        'id' => 3,
        'name' => 'Prof. Maria Santos',
        'email' => 'maria.santos@iscp.edu.ph',
        'password_hash' => $announcer_pass_hash,
        'role' => 'announcer',
        'status' => 'active'
    ],
    [
        'id' => 4,
        'name' => 'Inactive Test Announcer',
        'email' => 'inactive@iscp.edu.ph',
        'password_hash' => $announcer_pass_hash,
        'role' => 'announcer',
        'status' => 'inactive'
    ]
];

foreach ($users as $u) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$u['email']]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $ins = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$u['id'], $u['name'], $u['email'], $u['password_hash'], $u['role'], $u['status'], date('Y-m-d H:i:s')]);
    } else {
        $upd = $pdo->prepare("UPDATE users SET password_hash = ?, role = ?, status = ? WHERE email = ?");
        $upd->execute([$u['password_hash'], $u['role'], $u['status'], $u['email']]);
    }
}
echo "✓ Users checked/seeded.\n";

// 3. Ensure Announcements exist
$sample_announcements = [
    [
        'id' => 1,
        'title' => 'Suspension of Classes due to Inclement Weather',
        'summary' => 'Please be advised that all onsite and online classes are officially suspended today due to heavy rains and localized flooding.',
        'content' => "In accordance with the advisory issued by the City Disaster Risk Reduction and Management Office (CDRRMO), classes at all levels across all campuses are suspended today.\n\nStudents and faculty members are advised to stay indoors and monitor official communication channels for further updates. Stay safe everyone!",
        'category' => 'Urgent Alert',
        'category_id' => 1,
        'created_by' => 2,
        'status' => 'approved',
        'rejection_reason' => null,
        'reviewed_by' => 1,
        'reviewed_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'target_audience' => 'All',
        'is_urgent' => 1,
        'is_pinned' => 1,
        'views_count' => 428,
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
    ],
    [
        'id' => 2,
        'title' => 'Midterm Examination Schedule for 1st Semester A.Y. 2026-2027',
        'summary' => 'The official exam timetable, permit validation guidelines, and classroom assignments are now available for all departments.',
        'content' => "The Office of the University Registrar announces that Midterm Examinations will commence starting next Monday.\n\nKey Reminders:\n1. Ensure your Examination Permits are validated before Friday.\n2. Bring your valid Student ID card to each testing room.\n3. Strictly observe the university dress code and code of conduct during exam week.\n\nConsult your respective department chairs for specialized laboratory exam schedules.",
        'category' => 'Academic Affairs',
        'category_id' => 2,
        'created_by' => 2,
        'status' => 'approved',
        'rejection_reason' => null,
        'reviewed_by' => 1,
        'reviewed_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'target_audience' => 'Students',
        'is_urgent' => 0,
        'is_pinned' => 1,
        'views_count' => 315,
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'id' => 3,
        'title' => 'Annual University Foundation Week & Sports Fest 2026',
        'summary' => 'Join us for a week full of sports competitions, academic debates, cultural dances, and the much-awaited University Battle of the Bands!',
        'content' => "Get ready for our Grand Foundation Celebration! The Supreme Student Council together with the Athletics Office invites all colleges to submit their team rosters for Basketball, Volleyball, Chess, Badminton, and Esports.\n\nRegistration booths are open at the Student Activity Center until Wednesday, 5:00 PM.",
        'category' => 'Campus Events',
        'category_id' => 3,
        'created_by' => 3,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_by' => null,
        'reviewed_at' => null,
        'target_audience' => 'All',
        'is_urgent' => 0,
        'is_pinned' => 0,
        'views_count' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
    ],
    [
        'id' => 4,
        'title' => 'Call for Submissions: University Literary Folio 2026',
        'summary' => 'The official university publications office is now accepting poetry, short stories, essays, and digital artwork.',
        'content' => "We welcome submissions from all bona fide students. Theme for this year: \"Voices of Resilience and Hope\".\n\nSubmission Guidelines:\n- Maximum 2,000 words for prose.\n- High-resolution (300 DPI) for illustrations/photos.\n- Submit before the end of the month.",
        'category' => 'Student Life',
        'category_id' => 4,
        'created_by' => 2,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_by' => null,
        'reviewed_at' => null,
        'target_audience' => 'Students',
        'is_urgent' => 0,
        'is_pinned' => 0,
        'views_count' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
    ],
    [
        'id' => 5,
        'title' => 'Unofficial Off-Campus Gathering & Party',
        'summary' => 'Private party happening this Friday night outside school premises.',
        'content' => "Party with free drinks and live DJ. Note: this is not an official school sanctioned event.",
        'category' => 'Student Life',
        'category_id' => 4,
        'created_by' => 3,
        'status' => 'rejected',
        'rejection_reason' => 'The announcement refers to an unapproved and unofficial commercial event that violates university student handbook guidelines regarding off-campus safety and alcohol.',
        'reviewed_by' => 1,
        'reviewed_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'target_audience' => 'Students',
        'is_urgent' => 0,
        'is_pinned' => 0,
        'views_count' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
    ]
];

foreach ($sample_announcements as $ann) {
    $stmt = $pdo->prepare("SELECT id FROM announcements WHERE id = ?");
    $stmt->execute([$ann['id']]);
    if (!$stmt->fetch()) {
        $ins = $pdo->prepare("
            INSERT INTO announcements 
            (id, title, summary, content, category, category_id, created_by, status, rejection_reason, reviewed_by, reviewed_at, target_audience, is_urgent, is_pinned, views_count, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $ann['id'],
            $ann['title'],
            $ann['summary'],
            $ann['content'],
            $ann['category'],
            $ann['category_id'],
            $ann['created_by'],
            $ann['status'],
            $ann['rejection_reason'],
            $ann['reviewed_by'],
            $ann['reviewed_at'],
            $ann['target_audience'],
            $ann['is_urgent'],
            $ann['is_pinned'],
            $ann['views_count'],
            $ann['created_at'],
            $ann['created_at']
        ]);
    }
}
echo "✓ Sample announcements checked/seeded.\n";
echo "Done! Initial database setup complete.\n";

