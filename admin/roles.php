<?php
/**
 * Roles & Permissions Matrix Page
 * Follows PDF Section 5: Exact Role and Permission Matrix implementation.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_specific_role([ROLE_SUPER_ADMIN, 'admin']);

$currentUser = current_user();
$page_title = 'Roles & Permissions';
$page_subtitle = 'Institutional Role-Based Access Control (RBAC) permission matrix and authority boundaries.';
$activeNav = 'roles.php';

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-shield-halved" style="color: var(--primary); font-size: 1.25rem;"></i>
            <h2 class="card-title">Role and Permission Matrix</h2>
        </div>
        <div class="card-header-right">
            <a href="users.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-users-gear"></i> Manage User Role Assignments
            </a>
        </div>
    </div>

    <div class="card-body">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.88rem; color: #334155; line-height: 1.6;">
            <strong><i class="fa-solid fa-lock"></i> Backend Security Enforcement:</strong> All permissions listed below are strictly validated on the server on every create, edit, delete, publish, and view operation. Browser client values are never trusted.
        </div>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Feature / Authority</th>
                        <th style="text-align: center;">Super Admin</th>
                        <th style="text-align: center;">University Admin</th>
                        <th style="text-align: center;">Office Admin</th>
                        <th style="text-align: center;">College Admin</th>
                        <th style="text-align: center;">Dept Admin</th>
                        <th style="text-align: center;">Student</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Manage Users & Accounts</strong></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                    </tr>
                    <tr>
                        <td><strong>Manage Offices & Colleges</strong></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                    </tr>
                    <tr>
                        <td><strong>Create Announcements</strong></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                    </tr>
                    <tr>
                        <td><strong>Edit within Scope</strong></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                    </tr>
                    <tr>
                        <td><strong>View Announcements</strong></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">All</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">All</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">All*</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">All*</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">All*</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#fef3c7; color:#d97706;">Relevant</span></td>
                    </tr>
                    <tr>
                        <td><strong>Manage Categories</strong></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                    </tr>
                    <tr>
                        <td><strong>View Analytics</strong></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">All</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">University</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">Office</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">College</span></td>
                        <td style="text-align: center;"><span class="badge" style="background:#e0f2fe; color:#0369a1;">Dept.</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                    </tr>
                    <tr>
                        <td><strong>Manage Permissions</strong></td>
                        <td style="text-align: center;"><span class="badge badge-status-approved"><i class="fa-solid fa-check"></i> Yes</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                        <td style="text-align: center;"><span class="badge badge-status-rejected"><i class="fa-solid fa-xmark"></i> No</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.85rem;">
            * <em>All*</em> means the user may view portal announcements, but editing and management remain restricted to their designated scope.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
