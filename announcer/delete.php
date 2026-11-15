<?php
/**
 * Announcer Portal - Delete Announcement Handler
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_announcer();

$pdo = get_db();
$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
        header('Location: index.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $pdo) {
        try {
            // Check ownership and status
            $stmt = $pdo->prepare("SELECT title, status FROM announcements WHERE id = ? AND created_by = ?");
            $stmt->execute([$id, $currentUser['id']]);
            $ann = $stmt->fetch();

            if (!$ann) {
                set_flash('danger', 'Announcement not found or access denied.');
            } elseif ($ann['status'] === 'approved') {
                set_flash('warning', 'Approved announcements cannot be deleted.');
            } else {
                $del = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND created_by = ?");
                $del->execute([$id, $currentUser['id']]);
                set_flash('success', 'Announcement "' . h($ann['title']) . '" was successfully deleted.');
            }
        } catch (PDOException $e) {
            set_flash('danger', 'Database error deleting announcement: ' . $e->getMessage());
        }
    }
}

header('Location: index.php');
exit;

