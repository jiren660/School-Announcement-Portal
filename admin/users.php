<?php
/**
 * User Management Page
 * Handles creating announcer accounts, editing users,
 * activating/deactivating status, and resetting passwords.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_specific_role([ROLE_SUPER_ADMIN, 'admin', ROLE_COLLEGE_ADMIN]);

$currentUser = current_user();
$currentRole = current_user_role();
$page_title = ($currentRole === ROLE_COLLEGE_ADMIN) ? 'Department Users Management' : 'User Management';
$page_subtitle = ($currentRole === ROLE_COLLEGE_ADMIN) 
    ? 'Manage Department Administrators under ' . h($currentUser['assigned_college']) . '.'
    : 'Manage announcer accounts, administrative access, and credentials.';
$activeNav = 'users.php';

// ====================================================================
// Handle POST Requests (Add, Edit, Toggle Status, Reset Password)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed (CSRF token expired or invalid).');
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $allowed_roles = ['super_admin', 'university_admin', 'office_admin', 'college_admin', 'department_admin', 'student', 'admin', 'announcer'];

    // 1. ADD USER
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'college_admin');
        
        $assigned_office = ($role === 'office_admin') ? trim($_POST['assigned_office'] ?? '') : null;
        $assigned_college = ($role === 'college_admin') ? trim($_POST['assigned_college'] ?? '') : null;
        $assigned_department = ($role === 'department_admin') ? trim($_POST['assigned_department'] ?? '') : null;
        $status = 'active'; // Default to active upon creation

        // Allowed roles when creating accounts through this form
        $allowed_add_roles = ['university_admin', 'office_admin', 'college_admin', 'department_admin'];

        // Special Scoping for College Administrator:
        if ($currentRole === ROLE_COLLEGE_ADMIN) {
            $role = 'department_admin';
            $assigned_college = $currentUser['assigned_college'] ?? '';
            $status = 'pending_approval'; // Newly created department users start as pending_approval

            // Verify department belongs to this college
            if ($pdo) {
                $vStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND college = ?");
                $vStmt->execute([$assigned_department, $assigned_college]);
                if (!$vStmt->fetch()) {
                    set_flash('danger', 'The selected department does not belong to your authorized college.');
                    header('Location: users.php');
                    exit;
                }
            }
        }

        // Validation
        if (empty($name) || strlen($name) < 2) {
            set_flash('danger', 'User full name is required and must be at least 2 characters.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'A valid email address is required.');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters long.');
        } elseif (!in_array($role, $allowed_add_roles, true)) {
            set_flash('danger', 'Invalid administrator role selected.');
        } elseif ($role === 'office_admin' && empty($assigned_office)) {
            set_flash('danger', 'Please select the specific Office for the Office Administrator.');
        } elseif ($role === 'college_admin' && empty($assigned_college)) {
            set_flash('danger', 'Please select the specific College for the College Administrator.');
        } elseif ($role === 'department_admin' && empty($assigned_department)) {
            set_flash('danger', 'Please select the specific Department for the Department Administrator.');
        } else {
            try {
                // Check email uniqueness
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    set_flash('danger', 'An account with email "' . h($email) . '" already exists.');
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("
                        INSERT INTO users (name, email, password_hash, role, assigned_college, assigned_office, assigned_department, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$name, $email, $password_hash, $role, $assigned_college, $assigned_office, $assigned_department, $status, date('Y-m-d H:i:s')]);
                    $unitLabel = $assigned_office ?: ($assigned_college ?: $assigned_department);
                    
                    if ($status === 'pending_approval') {
                        set_flash('success', 'Department Administrator account for "' . h($name) . '" has been created and submitted for Super Administrator approval.');
                    } else {
                        set_flash('success', 'User account for "' . h($name) . '" (' . format_role_label($role, $unitLabel) . ') has been created successfully.');
                    }
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Database error creating user: ' . $e->getMessage());
            }
        }
        header('Location: users.php');
        exit;
    }

    // 2. EDIT USER
    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'college_admin');
        $assigned_office = ($role === 'office_admin') ? trim($_POST['assigned_office'] ?? '') : null;
        $assigned_college = ($role === 'college_admin') ? trim($_POST['assigned_college'] ?? '') : null;
        $assigned_department = ($role === 'department_admin') ? trim($_POST['assigned_department'] ?? '') : null;
        $status = trim($_POST['status'] ?? 'active');

        if ($id <= 0) {
            set_flash('danger', 'Invalid user ID.');
        } elseif (empty($name) || strlen($name) < 2) {
            set_flash('danger', 'User full name is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'A valid email address is required.');
        } elseif (!in_array($role, $allowed_roles, true)) {
            set_flash('danger', 'Invalid role selected.');
        } elseif ($role === 'office_admin' && empty($assigned_office)) {
            set_flash('danger', 'Please select the specific Office for the Office Administrator.');
        } elseif ($role === 'college_admin' && empty($assigned_college)) {
            set_flash('danger', 'Please select the specific College for the College Administrator.');
        } elseif ($role === 'department_admin' && empty($assigned_department)) {
            set_flash('danger', 'Please select the specific Department for the Department Administrator.');
        } elseif (!in_array($status, ['active', 'inactive'], true)) {
            set_flash('danger', 'Invalid status selected.');
        } else {
            // Safety check: Cannot demote or deactivate self
            if ($id === (int)$currentUser['id'] && (!in_array($role, ['admin', 'super_admin'], true) || $status !== 'active')) {
                set_flash('danger', 'You cannot deactivate or remove admin permissions from your own current session.');
            } else {
                try {
                    // Check email collision
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?");
                    $checkStmt->execute([$email, $id]);
                    if ($checkStmt->fetch()) {
                        set_flash('danger', 'The email address "' . h($email) . '" is already registered to another user.');
                    } else {
                        $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, assigned_college = ?, assigned_office = ?, assigned_department = ?, status = ? WHERE id = ?");
                        $upd->execute([$name, $email, $role, $assigned_college, $assigned_office, $assigned_department, $status, $id]);

                        if ($id === (int)$currentUser['id']) {
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_role'] = $role;
                        }
                        set_flash('success', 'User account "' . h($name) . '" updated successfully.');
                    }
                } catch (PDOException $e) {
                    set_flash('danger', 'Database error updating user: ' . $e->getMessage());
                }
            }
        }
        header('Location: users.php');
        exit;
    }

    // 3. TOGGLE ACTIVATE / DEACTIVATE STATUS
    elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            set_flash('danger', 'Invalid user ID.');
        } elseif ($id === (int)$currentUser['id']) {
            set_flash('danger', 'You cannot deactivate your own administrator account.');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT name, status FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();

                if ($user) {
                    $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
                    $upd = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $upd->execute([$newStatus, $id]);

                    $statusLabel = ($newStatus === 'active') ? 'activated' : 'deactivated';
                    set_flash('success', 'User account "' . h($user['name']) . '" has been ' . $statusLabel . '.');
                } else {
                    set_flash('danger', 'User account not found.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Database error toggling user status: ' . $e->getMessage());
            }
        }
        header('Location: users.php');
        exit;
    }

    // 4. RESET PASSWORD
    elseif ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($id <= 0) {
            set_flash('danger', 'Invalid user ID.');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'New password must be at least 6 characters long.');
        } elseif ($password !== $confirm) {
            set_flash('danger', 'New password and confirmation password do not match.');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();

                if ($user) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $upd->execute([$new_hash, $id]);

                    set_flash('success', 'Password for "' . h($user['name']) . '" has been successfully reset.');
                } else {
                    set_flash('danger', 'User account not found.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Database error resetting password: ' . $e->getMessage());
            }
        }
        header('Location: users.php');
        exit;
    }

    // 5. DELETE USER (Allowed only for deactivated accounts, cannot delete self or super admin)
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            set_flash('danger', 'Invalid user ID.');
        } elseif ($id === (int)$currentUser['id']) {
            set_flash('danger', 'You cannot delete your own account.');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT name, role, status FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();

                if (!$user) {
                    set_flash('danger', 'User account not found.');
                } elseif (in_array($user['role'], ['super_admin', 'admin'], true)) {
                    set_flash('danger', 'Super Administrator accounts cannot be deleted.');
                } elseif ($user['status'] !== 'inactive') {
                    set_flash('danger', 'Only deactivated (inactive) accounts can be deleted. Please deactivate the account first.');
                } else {
                    $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $del->execute([$id]);
                    set_flash('success', 'User account "' . h($user['name']) . '" has been permanently deleted.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Database error deleting user: ' . $e->getMessage());
            }
        }
        header('Location: users.php');
        exit;
    }

    // 6. APPROVE USER REGISTRATION (Super Admin only)
    elseif ($action === 'approve_user') {
        if (!is_super_admin()) {
            set_flash('danger', 'Only Super Administrators can approve user accounts.');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending_approval'");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    set_flash('success', 'Department user registration has been approved and activated.');
                } else {
                    set_flash('warning', 'User account not found or already processed.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Database error approving user: ' . $e->getMessage());
            }
        }
        header('Location: users.php');
        exit;
    }

    // 7. REJECT USER REGISTRATION (Super Admin only)
    elseif ($action === 'reject_user') {
        if (!is_super_admin()) {
            set_flash('danger', 'Only Super Administrators can reject user accounts.');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND status = 'pending_approval'");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    set_flash('info', 'Department user registration has been rejected.');
                } else {
                    set_flash('warning', 'User account not found or already processed.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Database error rejecting user: ' . $e->getMessage());
            }
        }
        header('Location: users.php');
        exit;
    }
}

// Fetch users list with optional search and filters
$search = trim($_GET['q'] ?? '');
$filter_role = trim($_GET['role'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$users_list = [];

$registered_offices = [];
$registered_colleges = [];
$registered_departments = [];

if ($pdo) {
    try {
        $where = [];
        $params = [];

        // Scoping: If College Admin, show only department users under their assigned college
        if ($currentRole === ROLE_COLLEGE_ADMIN) {
            $myCollege = $currentUser['assigned_college'] ?? '';
            $where[] = "assigned_college = ?";
            $where[] = "role = 'department_admin'";
            $params[] = $myCollege;
        } else {
            // Exclude Super Admin accounts and the logged-in administrator from the managed directory list
            $where[] = "role NOT IN ('super_admin', 'admin')";
            $where[] = "id != ?";
            $params[] = (int)$currentUser['id'];
        }

        if (!empty($search)) {
            $where[] = "(name LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filter_role)) {
            $where[] = "role = ?";
            $params[] = $filter_role;
        }

        if (!empty($filter_status)) {
            $where[] = "status = ?";
            $params[] = $filter_status;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT id, name, email, role, assigned_college, assigned_office, assigned_department, status, created_at FROM users {$whereClause} ORDER BY (status = 'pending_approval') DESC, role ASC, name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users_list = $stmt->fetchAll();

        // Fetch dynamic units registered by Super Admin in Office, College, and Department Management
        $registered_offices = $pdo->query("SELECT id, code, name FROM offices ORDER BY name ASC")->fetchAll();
        $registered_colleges = $pdo->query("SELECT id, code, name FROM colleges ORDER BY name ASC")->fetchAll();
        
        if ($currentRole === ROLE_COLLEGE_ADMIN) {
            $dStmt = $pdo->prepare("SELECT id, code, name, college FROM departments WHERE college = ? ORDER BY name ASC");
            $dStmt->execute([$currentUser['assigned_college'] ?? '']);
            $registered_departments = $dStmt->fetchAll();
        } else {
            $registered_departments = $pdo->query("SELECT id, code, name, college FROM departments ORDER BY name ASC")->fetchAll();
        }

    } catch (PDOException $e) {
        set_flash('danger', 'Error retrieving users: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-users" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">User Accounts Directory</h2>
            <span class="card-count-badge"><?= count($users_list) ?> users</span>
        </div>
        <div class="card-header-right">
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddUserModal()">
                <i class="fa-solid fa-user-plus"></i> Add Announcer / User
            </button>
        </div>
    </div>

    <div class="card-body">
        <!-- Search & Filter Controls -->
        <form method="GET" action="users.php" class="toolbar-bar">
            <div class="toolbar-filters">
                <div class="search-input-group">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" class="form-control-search" placeholder="Search user name or email..." value="<?= h($search) ?>">
                </div>

                <select name="role" class="form-select-filter" onchange="this.form.submit()">
                    <option value="">All Managed Roles</option>
                    <option value="university_admin" <?= $filter_role === 'university_admin' ? 'selected' : '' ?>>University Administrator</option>
                    <option value="office_admin" <?= $filter_role === 'office_admin' ? 'selected' : '' ?>>Office Administrator</option>
                    <option value="college_admin" <?= $filter_role === 'college_admin' ? 'selected' : '' ?>>College Administrator</option>
                    <option value="department_admin" <?= $filter_role === 'department_admin' ? 'selected' : '' ?>>Department Administrator</option>
                </select>

                <select name="status" class="form-select-filter" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <?php if (!empty($search) || !empty($filter_role) || !empty($filter_status)): ?>
                    <a href="users.php" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($users_list)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <h3 class="empty-state-title">No user accounts found</h3>
                <p class="empty-state-desc">No accounts matched your filters. Click "Add Announcer" to create one.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th style="text-align: right; min-width: 250px;">Account Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $u): ?>
                            <tr>
                                <td>
                                    <?php
                                    $roleKey = $u['role'];
                                    $avatarBg = '#0284c7';
                                    $roleIcon = 'fa-bullhorn';
                                    $badgeStyle = 'background: #e0f2fe; color: #0369a1;';

                                    if ($roleKey === 'super_admin' || $roleKey === 'admin') {
                                        $avatarBg = '#4338ca';
                                        $roleIcon = 'fa-shield-halved';
                                        $badgeStyle = 'background: #ede9fe; color: #5b21b6;';
                                    } elseif ($roleKey === 'university_admin') {
                                        $avatarBg = '#0284c7';
                                        $roleIcon = 'fa-building-columns';
                                        $badgeStyle = 'background: #e0f2fe; color: #0369a1;';
                                    } elseif ($roleKey === 'office_admin') {
                                        $avatarBg = '#0d9488';
                                        $roleIcon = 'fa-briefcase';
                                        $badgeStyle = 'background: #ccfbf1; color: #0f766e;';
                                    } elseif ($roleKey === 'college_admin') {
                                        $avatarBg = '#d97706';
                                        $roleIcon = 'fa-graduation-cap';
                                        $badgeStyle = 'background: #fef3c7; color: #b45309;';
                                    }
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="avatar-circle" style="background: <?= $avatarBg ?>;">
                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.92rem;">
                                                <?= h($u['name']) ?>
                                                <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                                                    <span style="font-size: 0.7rem; color: #0284c7; font-weight: 700;">(You)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 0.88rem; color: var(--text-muted); font-family: monospace;">
                                        <?= h($u['email']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="<?= $badgeStyle ?> font-weight: 600; font-size: 0.78rem;">
                                        <i class="fa-solid <?= $roleIcon ?>"></i>
                                        <?= h(format_role_label($u['role'])) ?>
                                    </span>
                                    <?php if ($u['role'] === 'office_admin' && !empty($u['assigned_office'])): ?>
                                        <div style="font-size: 0.74rem; color: #0284c7; font-weight: 600; margin-top: 0.25rem;">
                                            <i class="fa-solid fa-building-columns"></i> <?= h($u['assigned_office']) ?>
                                        </div>
                                    <?php elseif ($u['role'] === 'college_admin' && !empty($u['assigned_college'])): ?>
                                        <div style="font-size: 0.74rem; color: #b45309; font-weight: 600; margin-top: 0.25rem;">
                                            <i class="fa-solid fa-graduation-cap"></i> <?= h($u['assigned_college']) ?>
                                        </div>
                                    <?php elseif ($u['role'] === 'department_admin' && !empty($u['assigned_department'])): ?>
                                        <div style="font-size: 0.74rem; color: #7e22ce; font-weight: 600; margin-top: 0.25rem;">
                                            <i class="fa-solid fa-network-wired"></i> <?= h($u['assigned_department']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge badge-status-active">
                                            <i class="fa-solid fa-circle-check"></i> Active
                                        </span>
                                    <?php elseif ($u['status'] === 'pending_approval'): ?>
                                        <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a;">
                                            <i class="fa-solid fa-clock"></i> Pending Approval
                                        </span>
                                    <?php elseif ($u['status'] === 'rejected'): ?>
                                        <span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                            <i class="fa-solid fa-circle-xmark"></i> Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-status-inactive">
                                            <i class="fa-solid fa-circle-xmark"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span title="<?= h(format_datetime($u['created_at'])) ?>">
                                        <?= h(time_ago($u['created_at'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <?php if ($u['status'] === 'pending_approval' && is_super_admin()): ?>
                                            <!-- Super Admin Quick Approve / Reject for Pending Users -->
                                            <form method="POST" action="users.php" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve_user">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm" title="Approve Registration">
                                                    <i class="fa-solid fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="users.php" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject_user">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Reject Registration">
                                                    <i class="fa-solid fa-xmark"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Edit User Button -->
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openEditModal(<?= h(json_encode($u)) ?>)" title="Edit account details">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>

                                        <!-- Reset Password Button -->
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openResetPasswordModal(<?= (int)$u['id'] ?>, <?= h(json_encode($u['name'])) ?>)" title="Reset user password">
                                            <i class="fa-solid fa-key"></i> Reset Pass
                                        </button>

                                        <!-- Activate / Deactivate Toggle Button -->
                                        <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                            <?php if ($u['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="openToggleStatusModal(<?= (int)$u['id'] ?>, <?= h(json_encode($u['name'])) ?>, 'deactivate')" title="Deactivate user">
                                                    <i class="fa-solid fa-user-slash"></i> Deactivate
                                                </button>
                                            <?php elseif ($u['status'] === 'inactive'): ?>
                                                <button type="button" class="btn btn-success btn-sm" onclick="openToggleStatusModal(<?= (int)$u['id'] ?>, <?= h(json_encode($u['name'])) ?>, 'activate')" title="Activate user">
                                                    <i class="fa-solid fa-user-check"></i> Activate
                                                </button>
                                                <!-- Delete button: only available when account is deactivated -->
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="openDeleteUserModal(<?= (int)$u['id'] ?>, <?= h(json_encode($u['name'])) ?>)" title="Permanently delete deactivated user">
                                                    <i class="fa-solid fa-trash"></i> Delete
                                                </button>
                                            <?php elseif ($u['status'] === 'rejected'): ?>
                                                <!-- Delete button for rejected registration -->
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="openDeleteUserModal(<?= (int)$u['id'] ?>, <?= h(json_encode($u['name'])) ?>)" title="Permanently delete rejected registration">
                                                    <i class="fa-solid fa-trash"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
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

<!-- ====================================================================
     MODAL 1: ADD USER (Admins create announcer accounts)
     ==================================================================== -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-dialog">
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> Add Announcer / User
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
            </div>

            <div class="modal-body">
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Admins create announcer accounts directly. Announcers can submit announcements which will await admin review.
                </p>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="add_name" class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" id="add_name" name="name" class="form-input" placeholder="e.g. Prof. Juan Dela Cruz" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="add_email" class="form-label">School Email Address <span class="required">*</span></label>
                    <input type="email" id="add_email" name="email" class="form-input" placeholder="e.g. juan.delacruz@iscp.edu.ph" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="add_password" class="form-label">Initial Password <span class="required">*</span></label>
                    <input type="password" id="add_password" name="password" class="form-input" placeholder="Minimum 6 characters" minlength="6" required>
                    <div class="form-hint">Password will be securely hashed with bcrypt.</div>
                </div>

                <?php if ($currentRole === ROLE_COLLEGE_ADMIN): ?>
                    <input type="hidden" name="role" value="department_admin">
                    <input type="hidden" name="assigned_college" value="<?= h($currentUser['assigned_college']) ?>">
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Authorized College</label>
                        <input type="text" class="form-input" value="<?= h($currentUser['assigned_college']) ?>" readonly style="background: #f8fafc; font-weight: 600;">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="add_assigned_department" class="form-label">Assigned Department <span class="required">*</span></label>
                        <?php if (empty($registered_departments)): ?>
                            <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                                <i class="fa-solid fa-triangle-exclamation"></i> No departments registered under <?= h($currentUser['assigned_college']) ?>. Please coordinate with the Super Administrator.
                            </div>
                        <?php else: ?>
                            <select id="add_assigned_department" name="assigned_department" class="form-select" required>
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($registered_departments as $dep): ?>
                                    <option value="<?= h($dep['name']) ?>"><?= h($dep['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint" style="color: #b45309;">
                                <i class="fa-solid fa-clock"></i> New department administrator accounts will be submitted for Super Administrator approval before they can log in.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="add_role" class="form-label">Account Role <span class="required">*</span></label>
                        <select id="add_role" name="role" class="form-select" onchange="toggleUnitFields('add')" required>
                            <option value="university_admin" selected>University Administrator</option>
                            <option value="office_admin">Office Administrator</option>
                            <option value="college_admin">College Administrator</option>
                            <option value="department_admin">Department Administrator</option>
                        </select>
                    </div>

                    <!-- Specific Office Field (shown ONLY when Office Administrator is selected) -->
                    <div class="form-group" id="add_office_wrapper" style="margin-top: 1rem; margin-bottom: 0; display: none;">
                        <label for="add_assigned_office" class="form-label">Select Assigned Office <span class="required">*</span></label>
                        <?php if (empty($registered_offices)): ?>
                            <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                                <i class="fa-solid fa-triangle-exclamation"></i> No offices registered yet. <a href="offices.php" target="_blank" style="text-decoration: underline; font-weight: 700;">Add an office in Office Management first</a>.
                            </div>
                        <?php else: ?>
                            <select id="add_assigned_office" name="assigned_office" class="form-select">
                                <option value="">-- Choose University Office --</option>
                                <?php foreach ($registered_offices as $off): ?>
                                    <option value="<?= h($off['name']) ?>"><?= h($off['name']) ?> (<?= h($off['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">Announcements submitted by this user will be tagged to this office.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Specific College Field (shown ONLY when College Administrator is selected) -->
                    <div class="form-group" id="add_college_wrapper" style="margin-top: 1rem; margin-bottom: 0; display: none;">
                        <label for="add_assigned_college" class="form-label">Select Assigned College <span class="required">*</span></label>
                        <?php if (empty($registered_colleges)): ?>
                            <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                                <i class="fa-solid fa-triangle-exclamation"></i> No colleges registered yet. <a href="colleges.php" target="_blank" style="text-decoration: underline; font-weight: 700;">Add a college in College Management first</a>.
                            </div>
                        <?php else: ?>
                            <select id="add_assigned_college" name="assigned_college" class="form-select">
                                <option value="">-- Choose College --</option>
                                <?php foreach ($registered_colleges as $col): ?>
                                    <option value="<?= h($col['name']) ?>"><?= h($col['name']) ?> (<?= h($col['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">Announcements submitted by this user will be tagged to this college.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Specific Department Field (shown ONLY when Department Administrator is selected) -->
                    <div class="form-group" id="add_department_wrapper" style="margin-top: 1rem; margin-bottom: 0; display: none;">
                        <label for="add_assigned_department" class="form-label">Select Assigned Department <span class="required">*</span></label>
                        <?php if (empty($registered_departments)): ?>
                            <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                                <i class="fa-solid fa-triangle-exclamation"></i> No departments registered yet. <a href="departments.php" target="_blank" style="text-decoration: underline; font-weight: 700;">Add a department in Department Management first</a>.
                            </div>
                        <?php else: ?>
                            <select id="add_assigned_department" name="assigned_department" class="form-select">
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($registered_departments as $dep): ?>
                                    <option value="<?= h($dep['name']) ?>"><?= h($dep['name']) ?> (<?= h($dep['college']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">Announcements submitted by this user will be tagged to this academic department.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL 2: EDIT USER
     ==================================================================== -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-dialog">
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Edit User Account
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="edit_name" class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" id="edit_name" name="name" class="form-input" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="edit_email" class="form-label">School Email Address <span class="required">*</span></label>
                    <input type="email" id="edit_email" name="email" class="form-input" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_role" class="form-label">Role <span class="required">*</span></label>
                        <select id="edit_role" name="role" class="form-select" onchange="toggleUnitFields('edit')" required>
                            <option value="super_admin">Super Administrator (Full Admin Access)</option>
                            <option value="university_admin">University Administrator</option>
                            <option value="office_admin">Office Administrator</option>
                            <option value="college_admin">College Administrator</option>
                            <option value="department_admin">Department Administrator</option>
                            <option value="student">Student (View-Only Portal)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_status" class="form-label">Status <span class="required">*</span></label>
                        <select id="edit_status" name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Specific Office Field (shown for Office Administrator) -->
                <div class="form-group" id="edit_office_wrapper" style="margin-top: 1rem; margin-bottom: 0; display: none;">
                    <label for="edit_assigned_office" class="form-label">Assigned University Office <span class="required">*</span></label>
                    <?php if (empty($registered_offices)): ?>
                        <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                            <i class="fa-solid fa-triangle-exclamation"></i> No offices registered yet. <a href="offices.php" target="_blank" style="text-decoration: underline; font-weight: 700;">Add an office in Office Management first</a>.
                        </div>
                    <?php else: ?>
                        <select id="edit_assigned_office" name="assigned_office" class="form-select">
                            <option value="">-- Select Specific Office --</option>
                            <?php foreach ($registered_offices as $off): ?>
                                <option value="<?= h($off['name']) ?>"><?= h($off['name']) ?> (<?= h($off['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Specific College Field (shown for College Administrator) -->
                <div class="form-group" id="edit_college_wrapper" style="margin-top: 1rem; margin-bottom: 0; display: none;">
                    <label for="edit_assigned_college" class="form-label">Assigned College <span class="required">*</span></label>
                    <?php if (empty($registered_colleges)): ?>
                        <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                            <i class="fa-solid fa-triangle-exclamation"></i> No colleges registered yet. <a href="colleges.php" target="_blank" style="text-decoration: underline; font-weight: 700;">Add a college in College Management first</a>.
                        </div>
                    <?php else: ?>
                        <select id="edit_assigned_college" name="assigned_college" class="form-select">
                            <option value="">-- Select Specific College --</option>
                            <?php foreach ($registered_colleges as $col): ?>
                                <option value="<?= h($col['name']) ?>"><?= h($col['name']) ?> (<?= h($col['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Specific Department Field (shown for Department Administrator) -->
                <div class="form-group" id="edit_department_wrapper" style="margin-top: 1rem; margin-bottom: 0; display: none;">
                    <label for="edit_assigned_department" class="form-label">Assigned Department <span class="required">*</span></label>
                    <?php if (empty($registered_departments)): ?>
                        <div class="alert alert-warning" style="font-size: 0.85rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; border-radius: var(--radius-md);">
                            <i class="fa-solid fa-triangle-exclamation"></i> No departments registered yet. <a href="departments.php" target="_blank" style="text-decoration: underline; font-weight: 700;">Add a department in Department Management first</a>.
                        </div>
                    <?php else: ?>
                        <select id="edit_assigned_department" name="assigned_department" class="form-select">
                            <option value="">-- Select Specific Department --</option>
                            <?php foreach ($registered_departments as $dep): ?>
                                <option value="<?= h($dep['name']) ?>"><?= h($dep['name']) ?> (<?= h($dep['college']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL 3: TOGGLE STATUS (ACTIVATE / DEACTIVATE)
     ==================================================================== -->
<div class="modal-overlay" id="toggleStatusModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" id="toggle_id">

            <div class="modal-header">
                <h3 class="modal-title" id="toggleModalTitle">Confirm Status Change</h3>
                <button type="button" class="modal-close" onclick="closeModal('toggleStatusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="toggleModalPrompt" style="margin-bottom: 0.75rem;"></p>
                <div style="padding: 0.85rem 1rem; border-radius: var(--radius-md); font-weight: 700;" id="toggleUserNameDisplay"></div>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 1rem;" id="toggleModalNote"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('toggleStatusModal')">Cancel</button>
                <button type="submit" class="btn" id="toggleSubmitBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL 4: RESET PASSWORD
     ==================================================================== -->
<div class="modal-overlay" id="resetPasswordModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="reset_id">

            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa-solid fa-key" style="color: var(--warning);"></i> Reset User Password
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.88rem; margin-bottom: 0.75rem;">
                    Resetting password for: <strong id="resetUserNameDisplay"></strong>
                </p>

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-input" placeholder="Minimum 6 characters" minlength="6" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Re-type new password" minlength="6" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-shield-halved"></i> Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================================
     MODAL 5: DELETE USER (FOR DEACTIVATED ACCOUNTS)
     ==================================================================== -->
<div class="modal-overlay" id="deleteUserModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_user_id">

            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger);">
                    <i class="fa-solid fa-trash"></i> Delete User Account
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 0.75rem;">Are you sure you want to permanently delete this deactivated account?</p>
                <div style="padding: 0.85rem 1rem; border-radius: var(--radius-md); background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; font-weight: 700;" id="deleteUserNameDisplay"></div>
                <p style="font-size: 0.82rem; color: #dc2626; margin-top: 1rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> This action is permanent and cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteUserModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-trash"></i> Delete Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamically show or hide the Office, College, or Department fields based on selected role
function toggleUnitFields(mode) {
    const roleEl = document.getElementById(mode + '_role');
    const officeWrapper = document.getElementById(mode + '_office_wrapper');
    const collegeWrapper = document.getElementById(mode + '_college_wrapper');
    const deptWrapper = document.getElementById(mode + '_department_wrapper');

    const officeSelect = document.getElementById(mode + '_assigned_office');
    const collegeSelect = document.getElementById(mode + '_assigned_college');
    const deptSelect = document.getElementById(mode + '_assigned_department');

    if (!roleEl) return;
    const role = roleEl.value;

    if (officeWrapper) officeWrapper.style.display = (role === 'office_admin') ? 'block' : 'none';
    if (collegeWrapper) collegeWrapper.style.display = (role === 'college_admin') ? 'block' : 'none';
    if (deptWrapper) deptWrapper.style.display = (role === 'department_admin') ? 'block' : 'none';

    if (officeSelect) {
        officeSelect.required = (role === 'office_admin');
        if (role !== 'office_admin') officeSelect.value = '';
    }
    if (collegeSelect) {
        collegeSelect.required = (role === 'college_admin');
        if (role !== 'college_admin') collegeSelect.value = '';
    }
    if (deptSelect) {
        deptSelect.required = (role === 'department_admin');
        if (role !== 'department_admin') deptSelect.value = '';
    }
}

// Backward compatibility alias
function toggleCollegeField(mode) {
    toggleUnitFields(mode);
}

function openAddUserModal() {
    const roleEl = document.getElementById('add_role');
    if (roleEl) {
        roleEl.value = 'university_admin';
        toggleUnitFields('add');
    }
    openModal('addUserModal');
}

function openEditModal(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    
    if (document.getElementById('edit_assigned_office')) {
        document.getElementById('edit_assigned_office').value = user.assigned_office || '';
    }
    if (document.getElementById('edit_assigned_college')) {
        document.getElementById('edit_assigned_college').value = user.assigned_college || '';
    }
    if (document.getElementById('edit_assigned_department')) {
        document.getElementById('edit_assigned_department').value = user.assigned_department || '';
    }
    
    toggleUnitFields('edit');
    openModal('editUserModal');
}

function openToggleStatusModal(id, name, action) {
    document.getElementById('toggle_id').value = id;
    const nameBox = document.getElementById('toggleUserNameDisplay');
    const submitBtn = document.getElementById('toggleSubmitBtn');
    nameBox.textContent = name;

    if (action === 'deactivate') {
        document.getElementById('toggleModalTitle').textContent = 'Deactivate User Account';
        document.getElementById('toggleModalPrompt').textContent = 'Are you sure you want to deactivate this account?';
        document.getElementById('toggleModalNote').textContent = 'Deactivated users will not be able to log in or submit announcements.';
        nameBox.style.background = '#fff1f2';
        nameBox.style.color = '#9f1239';
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="fa-solid fa-user-slash"></i> Deactivate Account';
    } else {
        document.getElementById('toggleModalTitle').textContent = 'Activate User Account';
        document.getElementById('toggleModalPrompt').textContent = 'Are you sure you want to reactivate this account?';
        document.getElementById('toggleModalNote').textContent = 'Reactivating will immediately restore the user\'s access to the portal.';
        nameBox.style.background = '#f0fdf4';
        nameBox.style.color = '#166534';
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fa-solid fa-user-check"></i> Activate Account';
    }

    openModal('toggleStatusModal');
}

function openResetPasswordModal(id, name) {
    document.getElementById('reset_id').value = id;
    document.getElementById('resetUserNameDisplay').textContent = name;
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    openModal('resetPasswordModal');
}

function openDeleteUserModal(id, name) {
    document.getElementById('delete_user_id').value = id;
    document.getElementById('deleteUserNameDisplay').textContent = name;
    openModal('deleteUserModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

