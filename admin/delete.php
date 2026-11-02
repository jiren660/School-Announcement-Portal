<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$pdo = get_db();
$is_connected = is_db_connected();

if ($id > 0 && $is_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: index.php?msg=Announcement+deleted+successfully.');
        exit;
    } catch (PDOException $e) {
        header('Location: index.php?msg=Error+deleting+announcement:+' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php?msg=Action+completed+in+preview+mode.');
    exit;
}
