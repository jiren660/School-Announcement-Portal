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

// Handle Add, Edit, and Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = trim($_POST['action'] ?? 'add');

        // 1. ADD COLLEGE
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $dean = trim($_POST['dean'] ?? '');

            if (empty($name) || empty($code)) {
                set_flash('danger', 'College code and name are required.');
            } else {
                if ($pdo) {
                    try {
                        $logo_path = null;
                        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $upload = upload_organization_logo($_FILES['logo'], 'college_');
                            if ($upload['success']) {
                                $logo_path = $upload['path'];
                            } else {
                                set_flash('warning', 'College registered, but logo upload failed: ' . $upload['error']);
                            }
                        }

                        $ins = $pdo->prepare("INSERT INTO colleges (code, name, dean, logo_path, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $ins->execute([$code, $name, $dean, $logo_path]);
                        set_flash('success', 'College "' . h($name) . '" has been registered.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error adding college: ' . $e->getMessage());
                    }
                }
            }
        }

        // 2. EDIT COLLEGE
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $dean = trim($_POST['dean'] ?? '');

            if ($id <= 0 || empty($name) || empty($code)) {
                set_flash('danger', 'College code and name are required.');
            } else {
                if ($pdo) {
                    try {
                        $currentCollege = $pdo->query("SELECT name, logo_path FROM colleges WHERE id = " . (int)$id)->fetch();
                        $oldName = $currentCollege['name'] ?? '';
                        $logo_path = $currentCollege['logo_path'] ?? null;

                        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $upload = upload_organization_logo($_FILES['logo'], 'college_');
                            if ($upload['success']) {
                                $logo_path = $upload['path'];
                            } else {
                                set_flash('warning', 'Logo upload failed: ' . $upload['error']);
                            }
                        }

                        $upd = $pdo->prepare("UPDATE colleges SET code = ?, name = ?, dean = ?, logo_path = ? WHERE id = ?");
                        $upd->execute([$code, $name, $dean, $logo_path, $id]);

                        // Cascade name updates to departments and users
                        if ($oldName && $oldName !== $name) {
                            $updDepts = $pdo->prepare("UPDATE departments SET college = ? WHERE college = ?");
                            $updDepts->execute([$name, $oldName]);

                            $updUsers = $pdo->prepare("UPDATE users SET assigned_college = ? WHERE assigned_college = ?");
                            $updUsers->execute([$name, $oldName]);
                        }
                        set_flash('success', 'College "' . h($name) . '" updated successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error updating college: ' . $e->getMessage());
                    }
                }
            }
        }

        // 3. DELETE COLLEGE
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                set_flash('danger', 'Invalid college ID.');
            } else {
                if ($pdo) {
                    try {
                        $delName = $pdo->query("SELECT name FROM colleges WHERE id = " . (int)$id)->fetchColumn();
                        if ($delName) {
                            // Unlink users assigned to this college
                            $unlUsers = $pdo->prepare("UPDATE users SET assigned_college = NULL WHERE assigned_college = ?");
                            $unlUsers->execute([$delName]);

                            // Remove affiliated departments and unlink department users
                            $depts = $pdo->prepare("SELECT name FROM departments WHERE college = ?");
                            $depts->execute([$delName]);
                            $deptNames = $depts->fetchAll(PDO::FETCH_COLUMN);

                            if (!empty($deptNames)) {
                                foreach ($deptNames as $dName) {
                                    $unlDeptUsers = $pdo->prepare("UPDATE users SET assigned_department = NULL WHERE assigned_department = ?");
                                    $unlDeptUsers->execute([$dName]);
                                }
                                $delDepts = $pdo->prepare("DELETE FROM departments WHERE college = ?");
                                $delDepts->execute([$delName]);
                            }
                        }
                        $del = $pdo->prepare("DELETE FROM colleges WHERE id = ?");
                        $del->execute([$id]);
                        set_flash('success', 'College and its associated department mappings deleted successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error deleting college: ' . $e->getMessage());
                    }
                }
            }
        }

        header('Location: colleges.php');
        exit;
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
                            <th style="width: 80px;">Logo</th>
                            <th style="width: 100px;">Code</th>
                            <th>College Name</th>
                            <th>College Dean</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colleges as $c): ?>
                            <?php 
                            $cLogo = !empty($c['logo_path']) && file_exists(__DIR__ . '/../' . $c['logo_path']) 
                                ? '../' . $c['logo_path'] 
                                : '../assets/images/iscp_logo.png';
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= h($cLogo) ?>" alt="<?= h($c['name']) ?>" style="width: 36px; height: 36px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; padding: 2px;">
                                </td>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800;"><?= h($c['code']) ?></span></td>
                                <td><div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;"><?= h($c['name']) ?></div></td>
                                <td><?= h($c['dean'] ?? 'Designation Pending') ?></td>
                                <td><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Active</span></td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <a href="departments.php?college=<?= urlencode($c['name']) ?>" class="btn btn-outline btn-sm" title="View departments under this college">
                                            <i class="fa-solid fa-sitemap"></i> View Departments
                                        </a>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openEditCollegeModal(<?= h(json_encode($c)) ?>)" title="Edit college">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="openDeleteCollegeModal(<?= (int)$c['id'] ?>, <?= h(json_encode($c['name'])) ?>)" title="Delete college">
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

<!-- Add College Modal -->
<div class="modal-overlay" id="addCollegeModal">
    <div class="modal-dialog">
        <form method="POST" action="colleges.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-graduation-cap"></i> Register College</h3>
                <button type="button" class="modal-close" onclick="closeModal('addCollegeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">College Code (e.g. CCS, COE) <span class="required">*</span></label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. CCS" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">College Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. College of Computer Studies" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">College Dean</label>
                    <input type="text" name="dean" class="form-control" placeholder="e.g. Dr. Jane Smith">
                </div>
                <div class="form-group">
                    <label class="form-label">College Logo / Seal (Optional)</label>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-hint" style="color: #64748b; font-size: 0.8rem; margin-top: 0.25rem;">
                        Max 2MB (PNG, JPG, WEBP). Displayed beside announcements on the public board.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addCollegeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Save College</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit College Modal -->
<div class="modal-overlay" id="editCollegeModal">
    <div class="modal-dialog">
        <form method="POST" action="colleges.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_college_id">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit College</h3>
                <button type="button" class="modal-close" onclick="closeModal('editCollegeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">College Code <span class="required">*</span></label>
                    <input type="text" name="code" id="edit_college_code" class="form-control" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">College Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_college_name" class="form-control" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">College Dean</label>
                    <input type="text" name="dean" id="edit_college_dean" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Update College Logo (Optional)</label>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-hint" style="color: #64748b; font-size: 0.8rem; margin-top: 0.25rem;">
                        Leave blank to keep existing logo. Max 2MB.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editCollegeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete College Modal -->
<div class="modal-overlay" id="deleteCollegeModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="colleges.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_college_id">
            <div class="modal-header">
                <h3 class="modal-title" style="color: #dc2626;"><i class="fa-solid fa-triangle-exclamation"></i> Delete College</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteCollegeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 0.75rem;">Are you sure you want to delete this college?</p>
                <div id="delete_college_display" style="padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fee2e2; border-radius: var(--radius-md); font-weight: 700; color: #991b1b; margin-bottom: 1rem;"></div>
                <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Warning:</strong> Deleting this college will also delete all affiliated academic departments and reset college assignments for associated users.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteCollegeModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete College</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCollegeModal(college) {
    document.getElementById('edit_college_id').value = college.id;
    document.getElementById('edit_college_code').value = college.code || '';
    document.getElementById('edit_college_name').value = college.name || '';
    document.getElementById('edit_college_dean').value = college.dean || '';
    openModal('editCollegeModal');
}

function openDeleteCollegeModal(id, name) {
    document.getElementById('delete_college_id').value = id;
    document.getElementById('delete_college_display').textContent = name;
    openModal('deleteCollegeModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
