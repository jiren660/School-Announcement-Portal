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

// 2. Ensure System Administrator exists
$admin_pass_hash = password_hash('AdminPass123!', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@iscp.edu.ph' OR id = 1");
$stmt->execute();
$existingAdmin = $stmt->fetch();
if (!$existingAdmin) {
    $ins = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, status, created_at) VALUES (1, 'System Administrator', 'admin@iscp.edu.ph', ?, 'admin', 'active', ?)");
    $ins->execute([$admin_pass_hash, date('Y-m-d H:i:s')]);
}
echo "✓ Administrator verified.\n";
echo "Done! Database initial setup complete.\n";

