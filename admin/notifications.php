<?php
/**
 * Notifications Management Page
 * Follows PDF Section 2 (All Admin Roles): Alerts and workflow updates.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'System Notifications';
$page_subtitle = 'Institutional broadcast alerts, review approvals, and system notifications.';
$activeNav = 'notifications.php';

$pdo = get_db();
$pendingCount = get_pending_count();
$notifications = [];

// Only push notification if there are genuine items awaiting action
if ($pendingCount > 0 && is_super_admin()) {
    $notifications[] = [
        'id' => 1,
        'title' => 'Pending Review Submissions Awaiting Action',
        'message' => 'There are ' . $pendingCount . ' announcement submission(s) currently awaiting administrative review in the queue.',
        'type' => 'warning',
        'icon' => 'fa-clock',
        'time' => 'Just now',
        'read' => false
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-bell" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Notifications &amp; Alerts</h2>
            <span class="card-count-badge"><?= count($notifications) ?> items</span>
        </div>
        <?php if (!empty($notifications)): ?>
            <div class="card-header-right">
                <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('.content-card').querySelector('.card-body').innerHTML='<div class=\'empty-state\'><div class=\'empty-state-icon\'><i class=\'fa-regular fa-bell-slash\'></i></div><h3 class=\'empty-state-title\'>All caught up</h3><p class=\'empty-state-desc\'>No pending notifications or alerts.</p></div>';">
                    <i class="fa-solid fa-check-double"></i> Mark all as read
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-bell-slash"></i></div>
                <h3 class="empty-state-title">No notifications</h3>
                <p class="empty-state-desc">You have no unread notifications or pending system alerts at this time.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach ($notifications as $n): ?>
                    <div style="display: flex; gap: 1rem; align-items: flex-start; padding: 1rem 1.25rem; border: 1px solid var(--card-border); border-radius: var(--radius-md); background: <?= $n['read'] ? '#ffffff' : '#f0f9ff' ?>; transition: var(--transition);">
                        <div style="width: 40px; height: 40px; border-radius: var(--radius-full); background: <?= $n['type'] === 'warning' ? '#fef3c7' : ($n['type'] === 'success' ? '#d1fae5' : '#e0f2fe') ?>; color: <?= $n['type'] === 'warning' ? '#d97706' : ($n['type'] === 'success' ? '#059669' : '#0284c7') ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem;">
                            <i class="fa-solid <?= h($n['icon']) ?>"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0;"><?= h($n['title']) ?></h4>
                                <span style="font-size: 0.75rem; color: #94a3b8;"><?= h($n['time']) ?></span>
                            </div>
                            <p style="font-size: 0.85rem; color: #64748b; margin: 0; line-height: 1.5;"><?= h($n['message']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
