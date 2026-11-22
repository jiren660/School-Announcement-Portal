<?php
/**
 * Activity Logs Audit Page
 * Follows PDF Section 2.A: Monitor system activity and audit trail.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_specific_role([ROLE_SUPER_ADMIN, 'admin']);

$currentUser = current_user();
$page_title = 'Activity Logs';
$page_subtitle = 'Audit trail of administrative actions, reviews, logins, and publishing events.';
$activeNav = 'activity_logs.php';

$pdo = get_db();
$activity_logs = [];

// Query database activity_logs table if it exists
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 50");
        $activity_logs = $stmt->fetchAll();
    } catch (Exception $e) {
        $activity_logs = [];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-timeline" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">System Activity Log</h2>
            <span class="card-count-badge"><?= count($activity_logs) ?> events</span>
        </div>
        <?php if (!empty($activity_logs)): ?>
            <div class="card-header-right">
                <button type="button" class="btn btn-outline btn-sm" onclick="alert('Exporting audit log CSV.');">
                    <i class="fa-solid fa-file-export"></i> Export CSV
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if (empty($activity_logs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-clock"></i></div>
                <h3 class="empty-state-title">No activity logs recorded yet</h3>
                <p class="empty-state-desc">Administrative activities, publishing events, and account management actions will be logged here in real time.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Event Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_logs as $log): ?>
                            <tr>
                                <td>
                                    <span title="<?= h($log['timestamp'] ?? $log['created_at']) ?>"><?= h(format_datetime($log['timestamp'] ?? $log['created_at'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= h($log['user_name'] ?? 'Admin') ?></strong>
                                </td>
                                <td>
                                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.7rem;">
                                        <?= h(format_role_label($log['role'] ?? 'super_admin')) ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?= h($log['action'] ?? 'ACTION') ?></code>
                                </td>
                                <td><?= h($log['details'] ?? '') ?></td>
                                <td><span style="font-size:0.75rem; color:#64748b;"><?= h($log['ip_address'] ?? '127.0.0.1') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
