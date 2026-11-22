<?php
/**
 * Student Notifications Page
 * Follows PDF Section 2.F & 4.F: Alerts for students.
 */

define('STUDENT_INIT', true);
require_once __DIR__ . '/includes/auth.php';

$currentStudent = current_student();
$page_title = 'Student Notifications';
$page_subtitle = 'University alerts, deadline reminders, and campus updates.';

$pdo = get_db();
$notifications = [];

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM announcements WHERE status IN ('approved', 'published') AND is_urgent = 1 ORDER BY created_at DESC LIMIT 5");
        $notifications = $stmt->fetchAll();
    } catch (Exception $e) {
        $notifications = [];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-bell" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">My Alerts &amp; Advisories</h2>
        </div>
    </div>

    <div class="card-body">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-bell-slash"></i></div>
                <h3 class="empty-state-title">No notifications</h3>
                <p class="empty-state-desc">You are completely caught up. There are no new announcements or alerts at this time.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach ($notifications as $n): ?>
                    <div style="display: flex; gap: 1rem; align-items: flex-start; padding: 1.15rem; border: 1px solid var(--card-border); border-radius: var(--radius-md); background: #fef2f2;">
                        <div style="width: 40px; height: 40px; border-radius: 9999px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 700; color: #991b1b; margin: 0 0 0.25rem;"><?= h($n['title']) ?></h4>
                            <p style="font-size: 0.85rem; color: #475569; margin: 0;"><?= h($n['summary'] ?? mb_substr($n['content'], 0, 150)) ?></p>
                            <span style="font-size: 0.74rem; color: #94a3b8; margin-top: 0.35rem; display: block;"><?= h(time_ago($n['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
