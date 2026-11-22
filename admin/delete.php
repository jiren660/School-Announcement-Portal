<?php
/**
 * Secure Announcement Deletion Handler
 * Enforces Role-Based Access Control and Organizational Scope Checks.
 */

session_start();
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed (CSRF token mismatch).');
        header('Location: my_announcements.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
} else {
    $id = (int)($_GET['id'] ?? 0);
}

if ($id <= 0) {
    set_flash('danger', 'Invalid announcement ID.');
    header('Location: index.php');
    exit;
}

if (!$pdo) {
    set_flash('danger', 'Database service is currently unreachable.');
    header('Location: index.php');
    exit;
}

try {
    // 1. Fetch announcement to verify scope
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $ann = $stmt->fetch();

    if (!$ann) {
        set_flash('warning', 'Announcement #' . $id . ' was not found.');
        header('Location: my_announcements.php');
        exit;
    }

    // 2. Validate Organizational Scope
    if (!can_manage_announcement($currentUser, $ann)) {
        http_response_code(403);
        render_access_denied('You do not have permission to delete announcements outside your assigned organizational scope.');
        exit;
    }

    // 3. Delete Announcement
    $del = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $del->execute([$id]);

    set_flash('success', 'Announcement "' . h($ann['title']) . '" has been permanently removed.');
} catch (PDOException $e) {
    set_flash('danger', 'Database error deleting announcement: ' . $e->getMessage());
}

$redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'my_announcements.php';
header('Location: ' . $redirect);
exit;
