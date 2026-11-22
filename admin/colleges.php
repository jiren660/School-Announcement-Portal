<?php
/**
 * College Management Page (Super Administrator)
 * Follows PDF Section 2.A: Register university colleges.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_specific_role([ROLE_SUPER_ADMIN, 'admin']);

$currentUser = current_user();
$page_title = 'College Management';
$page_subtitle = 'Register and manage university colleges and dean designations.';
$activeNav = 'colleges.php';

$pdo = get_db();
$colleges = [];

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM colleges ORDER BY id ASC");
        $colleges = $stmt->fetchAll();
    } catch (Exception $e) {
        $colleges = [];
    }
}

// Handle Add College
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $dean = trim($_POST['dean'] ?? '');

        if (empty($name) || empty($code)) {
            set_flash('danger', 'College code and name are required.');
        } else {
            if ($pdo) {
                try {
                    $ins = $pdo->prepare("INSERT INTO colleges (code, name, dean, created_at) VALUES (?, ?, ?, NOW())");
                    $ins->execute([$code, $name, $dean]);
                } catch (Exception $e) {
                    // Safe fallback
                }
            }
            set_flash('success', 'College "' . h($name) . '" has been registered.');
            header('Location: colleges.php');
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-graduation-cap" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Registered Colleges</h2>
            <span class="card-count-badge"><?= count($colleges) ?> colleges</span>
        </div>
        <div class="card-header-right">
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addCollegeModal')">
                <i class="fa-solid fa-plus"></i> Register College
            </button>
        </div>
    </div>

    <div class="card-body">
        <?php if (empty($colleges)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <h3 class="empty-state-title">No colleges registered yet</h3>
                <p class="empty-state-desc">When academic colleges are registered, they will appear here and serve as organizational boundaries for College Administrators.</p>
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addCollegeModal')">
                        <i class="fa-solid fa-plus"></i> Register First College
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>College Name</th>
                            <th>College Dean</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colleges as $c): ?>
                            <tr>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800;"><?= h($c['code']) ?></span></td>
                                <td><div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;"><?= h($c['name']) ?></div></td>
                                <td><?= h($c['dean'] ?? 'Designation Pending') ?></td>
                                <td><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Active</span></td>
                                <td style="text-align: right;">
                                    <a href="departments.php?college=<?= urlencode($c['name']) ?>" class="btn btn-outline btn-sm">
                                        <i class="fa-solid fa-sitemap"></i> View Departments
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add College Modal -->
<div class="modal-overlay" id="addCollegeModal">
    <div class="modal-dialog">
        <form method="POST" action="colleges.php">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-graduation-cap"></i> Register College</h3>
                <button type="button" class="modal-close" onclick="closeModal('addCollegeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">College Code (e.g. CCS, COE)</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. CCS" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">College Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. College of Computer Studies" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">College Dean</label>
                    <input type="text" name="dean" class="form-control" placeholder="e.g. Dr. Jane Smith">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addCollegeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save College</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
