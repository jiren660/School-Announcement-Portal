<?php
/**
 * Department Management Page
 * Accessible by Super Admin, College Admin, and Department Admin
 * Follows PDF Section 2.A & 2.D.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Department Management';
$page_subtitle = 'Academic departments, chairperson assignments, and college affiliations.';
$activeNav = 'departments.php';

$pdo = get_db();
$filter_college = trim($_GET['college'] ?? '');
$departments = [];

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM departments ORDER BY id ASC");
        $departments = $stmt->fetchAll();
    } catch (Exception $e) {
        $departments = [];
    }
}

// Scoping: If College Admin, show departments of their college only
if ($currentRole === ROLE_COLLEGE_ADMIN && !empty($currentUser['assigned_college'])) {
    $departments = array_values(array_filter($departments, function($d) use ($currentUser) {
        return stripos($d['college'], $currentUser['assigned_college']) !== false;
    }));
} elseif (!empty($filter_college)) {
    $departments = array_values(array_filter($departments, function($d) use ($filter_college) {
        return stripos($d['college'], $filter_college) !== false;
    }));
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-network-wired" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Academic Departments</h2>
            <span class="card-count-badge"><?= count($departments) ?> departments</span>
        </div>
        <?php if (is_super_admin()): ?>
            <div class="card-header-right">
                <button type="button" class="btn btn-primary btn-sm" onclick="alert('Department registration wizard will connect when your MariaDB tables are migrated.');">
                    <i class="fa-solid fa-plus"></i> Add Department
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if (empty($departments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-network-wired"></i></div>
                <h3 class="empty-state-title">No departments registered yet</h3>
                <p class="empty-state-desc">Academic departments linked to colleges will be listed here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Affiliated College</th>
                            <th>Department Chairperson</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dep): ?>
                            <tr>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800;"><?= h($dep['code']) ?></span></td>
                                <td><div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;"><?= h($dep['name']) ?></div></td>
                                <td><span style="color:#0284c7; font-weight:600;"><?= h($dep['college']) ?></span></td>
                                <td><?= h($dep['chair'] ?? 'Chair Assigned') ?></td>
                                <td><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Active</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
