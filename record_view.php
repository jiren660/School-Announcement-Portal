<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$announcement_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$announcement_id || $announcement_id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid announcement ID is required.']);
    exit;
}

require_once __DIR__ . '/config/database.php';
$pdo = get_db();

if (!is_db_connected() || !$pdo) {
    http_response_code(503);
    echo json_encode(['error' => 'Announcement views are unavailable while the database is offline.']);
    exit;
}

if (!isset($_SESSION['viewed_announcements']) || !is_array($_SESSION['viewed_announcements'])) {
    $_SESSION['viewed_announcements'] = [];
}

try {
    if (isset($_SESSION['viewed_announcements'][$announcement_id])) {
        $stmt = $pdo->prepare("SELECT views_count FROM announcements WHERE id = ? AND status IN ('published', 'approved')");
        $stmt->execute([$announcement_id]);
        $views_count = $stmt->fetchColumn();

        if ($views_count === false) {
            http_response_code(404);
            echo json_encode(['error' => 'Announcement not found.']);
            exit;
        }
    } else {
        $stmt = $pdo->prepare("
            UPDATE announcements
            SET views_count = COALESCE(views_count, 0) + 1
            WHERE id = ? AND status IN ('published', 'approved')
        ");
        $stmt->execute([$announcement_id]);

        if ($stmt->rowCount() !== 1) {
            http_response_code(404);
            echo json_encode(['error' => 'Announcement not found.']);
            exit;
        }

        $_SESSION['viewed_announcements'][$announcement_id] = true;
        $stmt = $pdo->prepare("SELECT views_count FROM announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);
        $views_count = $stmt->fetchColumn();
    }

    echo json_encode(['views_count' => (int)$views_count]);
} catch (PDOException $e) {
    error_log('Unable to record announcement view: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to record announcement view.']);
}
