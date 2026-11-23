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

// Handle Add, Edit, and Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = trim($_POST['action'] ?? 'add');

        // 1. ADD OFFICE
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $head = trim($_POST['head'] ?? '');

            if (empty($name) || empty($code)) {
                set_flash('danger', 'Office name and code are required.');
            } else {
                if ($pdo) {
                    try {
                        $logo_path = null;
                        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $upload = upload_organization_logo($_FILES['logo'], 'office_');
                            if ($upload['success']) {
                                $logo_path = $upload['path'];
                            } else {
                                set_flash('warning', 'Office created, but logo upload failed: ' . $upload['error']);
                            }
                        }

                        $ins = $pdo->prepare("INSERT INTO offices (code, name, email, head, logo_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $ins->execute([$code, $name, $email, $head, $logo_path]);
                        set_flash('success', 'University Office "' . h($name) . '" has been registered.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error adding office: ' . $e->getMessage());
                    }
                }
            }
        }

        // 2. EDIT OFFICE
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $head = trim($_POST['head'] ?? '');

            if ($id <= 0 || empty($name) || empty($code)) {
                set_flash('danger', 'Office code and name are required.');
            } else {
                if ($pdo) {
                    try {
                        $currentOffice = $pdo->query("SELECT name, logo_path FROM offices WHERE id = " . (int)$id)->fetch();
                        $oldName = $currentOffice['name'] ?? '';
                        $logo_path = $currentOffice['logo_path'] ?? null;

                        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $upload = upload_organization_logo($_FILES['logo'], 'office_');
                            if ($upload['success']) {
                                $logo_path = $upload['path'];
                            } else {
                                set_flash('warning', 'Logo upload failed: ' . $upload['error']);
                            }
                        }

                        $upd = $pdo->prepare("UPDATE offices SET code = ?, name = ?, email = ?, head = ?, logo_path = ? WHERE id = ?");
                        $upd->execute([$code, $name, $email, $head, $logo_path, $id]);

                        // Update assigned users if name was modified
                        if ($oldName && $oldName !== $name) {
                            $updUsers = $pdo->prepare("UPDATE users SET assigned_office = ? WHERE assigned_office = ?");
                            $updUsers->execute([$name, $oldName]);
                        }
                        set_flash('success', 'University Office "' . h($name) . '" updated successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error updating office: ' . $e->getMessage());
                    }
                }
            }
        }

        // 3. DELETE OFFICE
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                set_flash('danger', 'Invalid office ID.');
            } else {
                if ($pdo) {
                    try {
                        $delName = $pdo->query("SELECT name FROM offices WHERE id = " . (int)$id)->fetchColumn();
                        if ($delName) {
                            $unlUsers = $pdo->prepare("UPDATE users SET assigned_office = NULL WHERE assigned_office = ?");
                            $unlUsers->execute([$delName]);
                        }
                        $del = $pdo->prepare("DELETE FROM offices WHERE id = ?");
                        $del->execute([$id]);
                        set_flash('success', 'University Office has been deleted successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error deleting office: ' . $e->getMessage());
                    }
                }
            }
        }

        header('Location: offices.php');
        exit;
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
                            <th style="width: 80px;">Logo</th>
                            <th style="width: 100px;">Code</th>
                            <th>Office Name</th>
                            <th>Office Head / Director</th>
                            <th>Official Email</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offices as $off): ?>
                            <?php 
                            $offLogo = !empty($off['logo_path']) && file_exists(__DIR__ . '/../' . $off['logo_path']) 
                                ? '../' . $off['logo_path'] 
                                : '../assets/images/iscp_logo.png';
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= h($offLogo) ?>" alt="<?= h($off['name']) ?>" style="width: 36px; height: 36px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; padding: 2px;">
                                </td>
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
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openEditOfficeModal(<?= h(json_encode($off)) ?>)" title="Edit office">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="openDeleteOfficeModal(<?= (int)$off['id'] ?>, <?= h(json_encode($off['name'])) ?>)" title="Delete office">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </div>
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
        <form method="POST" action="offices.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-building-columns"></i> Register University Office</h3>
                <button type="button" class="modal-close" onclick="closeModal('addOfficeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Office Code (e.g. OSA, OUR, ICT) <span class="required">*</span></label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. OSA" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Office Full Name <span class="required">*</span></label>
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
                <div class="form-group">
                    <label class="form-label">Office Logo / Profile Photo (Optional)</label>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-hint" style="color: #64748b; font-size: 0.8rem; margin-top: 0.25rem;">
                        Max 2MB (PNG, JPG, WEBP). Displayed beside announcements on the public board.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addOfficeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Save Office</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Office Modal -->
<div class="modal-overlay" id="editOfficeModal">
    <div class="modal-dialog">
        <form method="POST" action="offices.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_office_id">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit University Office</h3>
                <button type="button" class="modal-close" onclick="closeModal('editOfficeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Office Code (e.g. OSA, OUR, ICT) <span class="required">*</span></label>
                    <input type="text" name="code" id="edit_office_code" class="form-control" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Office Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_office_name" class="form-control" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Official Office Email</label>
                    <input type="email" name="email" id="edit_office_email" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Office Head / Director</label>
                    <input type="text" name="head" id="edit_office_head" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Update Office Logo (Optional)</label>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-hint" style="color: #64748b; font-size: 0.8rem; margin-top: 0.25rem;">
                        Leave blank to keep existing logo. Max 2MB.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editOfficeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Office Modal -->
<div class="modal-overlay" id="deleteOfficeModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="offices.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_office_id">
            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger);"><i class="fa-solid fa-trash"></i> Delete Office</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteOfficeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 0.75rem;">Are you sure you want to permanently delete this university office?</p>
                <div style="padding: 0.85rem 1rem; border-radius: var(--radius-md); background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; font-weight: 700;" id="deleteOfficeNameDisplay"></div>
                <p style="font-size: 0.82rem; color: #dc2626; margin-top: 1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> Administrators assigned to this office will have their office assignment cleared.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteOfficeModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete Office</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditOfficeModal(off) {
    document.getElementById('edit_office_id').value = off.id;
    document.getElementById('edit_office_code').value = off.code || '';
    document.getElementById('edit_office_name').value = off.name || '';
    document.getElementById('edit_office_email').value = off.email || '';
    document.getElementById('edit_office_head').value = off.head || '';
    openModal('editOfficeModal');
}

function openDeleteOfficeModal(id, name) {
    document.getElementById('delete_office_id').value = id;
    document.getElementById('deleteOfficeNameDisplay').textContent = name;
    openModal('deleteOfficeModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
