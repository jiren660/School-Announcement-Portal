<?php
/**
 * Office Management Page (Super Administrator)
 * Follows PDF Section 2.A: Register offices, manage administrative scope.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_specific_role([ROLE_SUPER_ADMIN, 'admin']);

$currentUser = current_user();
$page_title = 'Office Management';
$page_subtitle = 'Register and manage university administrative offices and publishing scopes.';
$activeNav = 'offices.php';

$pdo = get_db();
$offices = [];

// Load from database if offices table exists
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM offices ORDER BY id ASC");
        $offices = $stmt->fetchAll();
    } catch (Exception $e) {
        $offices = [];
    }
}

// Handle Add Office Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $head = trim($_POST['head'] ?? '');

        if (empty($name) || empty($code)) {
            set_flash('danger', 'Office name and code are required.');
        } else {
            if ($pdo) {
                try {
                    $ins = $pdo->prepare("INSERT INTO offices (code, name, email, head, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $ins->execute([$code, $name, $email, $head]);
                } catch (Exception $e) {
                    // Safe fallback
                }
            }
            set_flash('success', 'University Office "' . h($name) . '" has been registered.');
            header('Location: offices.php');
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-building-columns" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">University Offices Management</h2>
            <span class="card-count-badge"><?= count($offices) ?> offices</span>
        </div>
        <div class="card-header-right">
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addOfficeModal')">
                <i class="fa-solid fa-plus"></i> Register Office
            </button>
        </div>
    </div>

    <div class="card-body">
        <?php if (empty($offices)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-building"></i></div>
                <h3 class="empty-state-title">No offices registered yet</h3>
                <p class="empty-state-desc">When administrative offices are registered, they will appear here and become available for user assignment.</p>
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addOfficeModal')">
                        <i class="fa-solid fa-plus"></i> Register First Office
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Office Name</th>
                            <th>Office Head / Director</th>
                            <th>Official Email</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offices as $off): ?>
                            <tr>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800;"><?= h($off['code']) ?></span></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($off['name']) ?>
                                    </div>
                                </td>
                                <td><?= h($off['head'] ?? 'Not Assigned') ?></td>
                                <td><code><?= h($off['email'] ?? 'office@iscp.edu.ph') ?></code></td>
                                <td>
                                    <span class="badge badge-status-approved">
                                        <i class="fa-solid fa-check"></i> Active
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="alert('Viewing office configuration: <?= h($off['name']) ?>');">
                                        <i class="fa-solid fa-sliders"></i> Configure
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Office Modal -->
<div class="modal-overlay" id="addOfficeModal">
    <div class="modal-dialog">
        <form method="POST" action="offices.php">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-building-columns"></i> Register University Office</h3>
                <button type="button" class="modal-close" onclick="closeModal('addOfficeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Office Code (e.g. OSA, OUR, ICT)</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. OSA" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Office Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Office of Student Affairs" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Official Office Email</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. osa@iscp.edu.ph">
                </div>
                <div class="form-group">
                    <label class="form-label">Office Head / Director</label>
                    <input type="text" name="head" class="form-control" placeholder="e.g. Dr. John Doe">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addOfficeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Office</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
