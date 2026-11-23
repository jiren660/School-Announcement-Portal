<?php
/**
 * Department Management Page
 * Accessible by Super Admin, College Admin, and Department Admin
 * Follows Phase 5 and Project Requirements:
 * - Dedicated department management view per college (?college=...)
 * - Displays college header with logo & back navigation
 * - Allows logo upload for departments
 * - Supports Activate / Deactivate status toggle
 * - Displays assigned Department Administrators
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
$colleges = [];
$selected_college = null;

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM departments ORDER BY id ASC");
        $departments = $stmt->fetchAll();
    } catch (Exception $e) {
        $departments = [];
    }

    try {
        $colStmt = $pdo->query("SELECT id, code, name, logo_path FROM colleges ORDER BY name ASC");
        $colleges = $colStmt->fetchAll();
    } catch (Exception $e) {
        $colleges = [];
    }
}

// Scoping: If College Admin, force filter to their authorized college
if ($currentRole === ROLE_COLLEGE_ADMIN && !empty($currentUser['assigned_college'])) {
    $filter_college = $currentUser['assigned_college'];
}

// Fetch selected college details if filtered
if (!empty($filter_college) && $pdo) {
    foreach ($colleges as $col) {
        if (strcasecmp($col['name'], $filter_college) === 0) {
            $selected_college = $col;
            break;
        }
    }
}

// Handle Add, Edit, Toggle, and Delete Actions (Super Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_super_admin()) {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = trim($_POST['action'] ?? 'add');
        $redirect_college = trim($_POST['redirect_college'] ?? $filter_college);

        // 1. ADD DEPARTMENT
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $college = trim($_POST['college'] ?? '');
            $chairperson = trim($_POST['chairperson'] ?? '');

            if (empty($name) || empty($code) || empty($college)) {
                set_flash('danger', 'Department code, name, and affiliated college are required.');
            } else {
                if ($pdo) {
                    try {
                        $logo_path = null;
                        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $upload = upload_organization_logo($_FILES['logo'], 'dept_');
                            if ($upload['success']) {
                                $logo_path = $upload['path'];
                            } else {
                                set_flash('warning', 'Department registered, but logo upload failed: ' . $upload['error']);
                            }
                        }

                        $ins = $pdo->prepare("INSERT INTO departments (code, name, college, chairperson, logo_path, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                        $ins->execute([$code, $name, $college, $chairperson, $logo_path]);
                        set_flash('success', 'Academic Department "' . h($name) . '" registered successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error adding department: ' . $e->getMessage());
                    }
                }
            }
        }

        // 2. EDIT DEPARTMENT
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $college = trim($_POST['college'] ?? '');
            $chairperson = trim($_POST['chairperson'] ?? '');

            if ($id <= 0 || empty($name) || empty($code) || empty($college)) {
                set_flash('danger', 'Department code, name, and affiliated college are required.');
            } else {
                if ($pdo) {
                    try {
                        $currentDept = $pdo->query("SELECT name, logo_path FROM departments WHERE id = " . (int)$id)->fetch();
                        $oldName = $currentDept['name'] ?? '';
                        $logo_path = $currentDept['logo_path'] ?? null;

                        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                            $upload = upload_organization_logo($_FILES['logo'], 'dept_');
                            if ($upload['success']) {
                                $logo_path = $upload['path'];
                            } else {
                                set_flash('warning', 'Logo upload failed: ' . $upload['error']);
                            }
                        }

                        $upd = $pdo->prepare("UPDATE departments SET code = ?, name = ?, college = ?, chairperson = ?, logo_path = ? WHERE id = ?");
                        $upd->execute([$code, $name, $college, $chairperson, $logo_path, $id]);

                        // Cascade department name changes to assigned users
                        if ($oldName && $oldName !== $name) {
                            $updUsers = $pdo->prepare("UPDATE users SET assigned_department = ? WHERE assigned_department = ?");
                            $updUsers->execute([$name, $oldName]);
                        }
                        set_flash('success', 'Academic Department "' . h($name) . '" updated successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error updating department: ' . $e->getMessage());
                    }
                }
            }
        }

        // 3. TOGGLE ACTIVATE / DEACTIVATE STATUS
        elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && $pdo) {
                try {
                    $current = $pdo->query("SELECT status, name FROM departments WHERE id = " . $id)->fetch();
                    if ($current) {
                        $newStatus = ($current['status'] === 'active') ? 'inactive' : 'active';
                        $uStmt = $pdo->prepare("UPDATE departments SET status = ? WHERE id = ?");
                        $uStmt->execute([$newStatus, $id]);
                        $label = ($newStatus === 'active') ? 'activated' : 'deactivated';
                        set_flash('success', 'Department "' . h($current['name']) . '" has been ' . $label . '.');
                    }
                } catch (Exception $e) {
                    set_flash('danger', 'Error updating status: ' . $e->getMessage());
                }
            }
        }

        // 4. DELETE DEPARTMENT
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                set_flash('danger', 'Invalid department ID.');
            } else {
                if ($pdo) {
                    try {
                        $delName = $pdo->query("SELECT name FROM departments WHERE id = " . (int)$id)->fetchColumn();
                        if ($delName) {
                            // Unlink users assigned to this department
                            $unlUsers = $pdo->prepare("UPDATE users SET assigned_department = NULL WHERE assigned_department = ?");
                            $unlUsers->execute([$delName]);
                        }
                        $del = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                        $del->execute([$id]);
                        set_flash('success', 'Academic Department deleted successfully.');
                    } catch (Exception $e) {
                        set_flash('danger', 'Error deleting department: ' . $e->getMessage());
                    }
                }
            }
        }

        $redirectUrl = !empty($redirect_college) ? 'departments.php?college=' . urlencode($redirect_college) : 'departments.php';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Filter departments by college
if (!empty($filter_college)) {
    $departments = array_values(array_filter($departments, function($d) use ($filter_college) {
        return stripos($d['college'], $filter_college) !== false;
    }));
}

// Fetch assigned Department Administrators grouped by department name
$dept_admins_by_dept = [];
if ($pdo) {
    try {
        $daStmt = $pdo->query("SELECT id, name, email, assigned_department, status FROM users WHERE role = 'department_admin'");
        while ($row = $daStmt->fetch()) {
            if (!empty($row['assigned_department'])) {
                $dept_admins_by_dept[$row['assigned_department']][] = $row;
            }
        }
    } catch (Exception $e) {
        $dept_admins_by_dept = [];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Dedicated College Banner (When Filtered by College) -->
<?php if (!empty($filter_college)): ?>
    <div style="margin-bottom: 1.5rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <?php 
            $colLogo = (!empty($selected_college['logo_path']) && file_exists(__DIR__ . '/../' . $selected_college['logo_path']))
                ? '../' . $selected_college['logo_path']
                : '../assets/images/iscp_logo.png';
            ?>
            <img src="<?= h($colLogo) ?>" alt="<?= h($filter_college) ?>" style="width: 52px; height: 52px; object-fit: contain; border-radius: 8px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 3px;">
            <div>
                <div style="font-size: 0.8rem; font-weight: 700; color: #0284c7; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="fa-solid fa-graduation-cap"></i> College Department Overview
                </div>
                <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0.2rem 0 0.1rem 0;">
                    <?= h($filter_college) ?>
                </h2>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Showing affiliated academic departments and assigned administrators.
                </div>
            </div>
        </div>
        <div>
            <a href="colleges.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to Colleges
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-network-wired" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title"><?= !empty($filter_college) ? h($filter_college) . ' Departments' : 'All Academic Departments' ?></h2>
            <span class="card-count-badge"><?= count($departments) ?> departments</span>
        </div>
        <?php if (is_super_admin()): ?>
            <div class="card-header-right">
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addDepartmentModal')">
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
                <p class="empty-state-desc"><?= !empty($filter_college) ? 'There are currently no departments under ' . h($filter_college) . '.' : 'Academic departments linked to colleges will be listed here.' ?></p>
                <?php if (is_super_admin()): ?>
                    <div style="margin-top: 1rem;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addDepartmentModal')">
                            <i class="fa-solid fa-plus"></i> Register First Department
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;">Logo</th>
                            <th style="width: 90px;">Code</th>
                            <th>Department Name</th>
                            <?php if (empty($filter_college)): ?>
                                <th>Affiliated College</th>
                            <?php endif; ?>
                            <th>Department Chairperson</th>
                            <th>Assigned Administrators</th>
                            <th>Status</th>
                            <?php if (is_super_admin()): ?>
                                <th style="text-align: right; width: 230px;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dep): ?>
                            <?php
                            $depLogo = (!empty($dep['logo_path']) && file_exists(__DIR__ . '/../' . $dep['logo_path']))
                                ? '../' . $dep['logo_path']
                                : '../assets/images/iscp_logo.png';
                            $depStatus = $dep['status'] ?? 'active';
                            $assignedAdmins = $dept_admins_by_dept[$dep['name']] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= h($depLogo) ?>" alt="<?= h($dep['name']) ?>" style="width: 36px; height: 36px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; padding: 2px;">
                                </td>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800;"><?= h($dep['code']) ?></span></td>
                                <td><div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;"><?= h($dep['name']) ?></div></td>
                                <?php if (empty($filter_college)): ?>
                                    <td><span style="color:#0284c7; font-weight:600;"><?= h($dep['college']) ?></span></td>
                                <?php endif; ?>
                                <td><?= h($dep['chairperson'] ?? $dep['chair'] ?? 'Not Assigned') ?></td>
                                <td>
                                    <?php if (!empty($assignedAdmins)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                            <?php foreach ($assignedAdmins as $adm): ?>
                                                <div style="font-size: 0.82rem; font-weight: 600; color: #334155;">
                                                    <i class="fa-solid fa-user-check" style="color: #10b981; font-size: 0.75rem;"></i>
                                                    <?= h($adm['name']) ?>
                                                    <?php if ($adm['status'] !== 'active'): ?>
                                                        <span style="font-size: 0.7rem; color: #b45309;">(<?= h($adm['status']) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: #94a3b8; font-style: italic;">No admin assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($depStatus === 'active'): ?>
                                        <span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (is_super_admin()): ?>
                                    <td style="text-align: right;">
                                        <div class="action-buttons" style="justify-content: flex-end;">
                                            <!-- Edit Department -->
                                            <button type="button" class="btn btn-outline btn-sm" onclick="openEditDepartmentModal(<?= h(json_encode($dep)) ?>)" title="Edit department">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </button>

                                            <!-- Toggle Status (Activate / Deactivate) -->
                                            <form method="POST" action="departments.php" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= (int)$dep['id'] ?>">
                                                <input type="hidden" name="redirect_college" value="<?= h($filter_college) ?>">
                                                <?php if ($depStatus === 'active'): ?>
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Deactivate department" onclick="return confirm('Deactivate this department?');">
                                                        <i class="fa-solid fa-ban"></i> Deactivate
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-success btn-sm" title="Activate department">
                                                        <i class="fa-solid fa-check"></i> Activate
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Delete Department -->
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="openDeleteDepartmentModal(<?= (int)$dep['id'] ?>, <?= h(json_encode($dep['name'])) ?>)" title="Delete department">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal-overlay" id="addDepartmentModal">
    <div class="modal-dialog">
        <form method="POST" action="departments.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="redirect_college" value="<?= h($filter_college) ?>">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-network-wired"></i> Register Academic Department</h3>
                <button type="button" class="modal-close" onclick="closeModal('addDepartmentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Department Code (e.g. CS, IT, CE, ME) <span class="required">*</span></label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. CS" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Department Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Department of Computer Science" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Affiliated College <span class="required">*</span></label>
                    <?php if (!empty($filter_college)): ?>
                        <input type="text" name="college" class="form-control" value="<?= h($filter_college) ?>" readonly style="background:#f8fafc; font-weight:600;">
                    <?php elseif (empty($colleges)): ?>
                        <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                            <i class="fa-solid fa-triangle-exclamation"></i> No colleges found. <a href="colleges.php" style="text-decoration: underline; font-weight: 700;">Register a college first</a>.
                        </div>
                        <input type="text" name="college" class="form-control" placeholder="e.g. College of Computer Studies" required>
                    <?php else: ?>
                        <select name="college" class="form-control" required>
                            <option value="">-- Select Affiliated College --</option>
                            <?php foreach ($colleges as $col): ?>
                                <option value="<?= h($col['name']) ?>"><?= h($col['name']) ?> (<?= h($col['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Department Chairperson (Optional)</label>
                    <input type="text" name="chairperson" class="form-control" placeholder="e.g. Prof. Alan Turing">
                </div>
                <div class="form-group">
                    <label class="form-label">Department Logo (Optional)</label>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-hint" style="color: #64748b; font-size: 0.8rem; margin-top: 0.25rem;">
                        Max 2MB (PNG, JPG, WEBP). Displayed beside announcements on the public board.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addDepartmentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal-overlay" id="editDepartmentModal">
    <div class="modal-dialog">
        <form method="POST" action="departments.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_dep_id">
            <input type="hidden" name="redirect_college" value="<?= h($filter_college) ?>">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Academic Department</h3>
                <button type="button" class="modal-close" onclick="closeModal('editDepartmentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Department Code <span class="required">*</span></label>
                    <input type="text" name="code" id="edit_dep_code" class="form-control" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Department Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_dep_name" class="form-control" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Affiliated College <span class="required">*</span></label>
                    <select name="college" id="edit_dep_college" class="form-control" required>
                        <option value="">-- Select Affiliated College --</option>
                        <?php foreach ($colleges as $col): ?>
                            <option value="<?= h($col['name']) ?>"><?= h($col['name']) ?> (<?= h($col['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department Chairperson</label>
                    <input type="text" name="chairperson" id="edit_dep_chairperson" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Update Department Logo (Optional)</label>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-hint" style="color: #64748b; font-size: 0.8rem; margin-top: 0.25rem;">
                        Leave blank to keep existing logo. Max 2MB.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editDepartmentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Department Modal -->
<div class="modal-overlay" id="deleteDepartmentModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="departments.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_dep_id">
            <input type="hidden" name="redirect_college" value="<?= h($filter_college) ?>">
            <div class="modal-header">
                <h3 class="modal-title" style="color: #dc2626;"><i class="fa-solid fa-triangle-exclamation"></i> Delete Department</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteDepartmentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 0.75rem;">Are you sure you want to delete this academic department?</p>
                <div id="delete_dep_display" style="padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fee2e2; border-radius: var(--radius-md); font-weight: 700; color: #991b1b; margin-bottom: 1rem;"></div>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Deleting this department will unlink any Department Administrators assigned to it.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteDepartmentModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete Department</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditDepartmentModal(dep) {
    document.getElementById('edit_dep_id').value = dep.id;
    document.getElementById('edit_dep_code').value = dep.code || '';
    document.getElementById('edit_dep_name').value = dep.name || '';
    document.getElementById('edit_dep_college').value = dep.college || '';
    document.getElementById('edit_dep_chairperson').value = dep.chairperson || dep.chair || '';
    openModal('editDepartmentModal');
}

function openDeleteDepartmentModal(id, name) {
    document.getElementById('delete_dep_id').value = id;
    document.getElementById('delete_dep_display').textContent = name;
    openModal('deleteDepartmentModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
