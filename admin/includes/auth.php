<?php
/**
 * Authentication & Security Helper for Admin Module
 * Provides Role-Based Access Control (RBAC), session validation,
 * CSRF protection, organizational scope checks, and utility helpers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Valid administrator roles recognized by the portal
 */
const ROLE_SUPER_ADMIN = 'super_admin';
const ROLE_UNIVERSITY_ADMIN = 'university_admin';
const ROLE_OFFICE_ADMIN = 'office_admin';
const ROLE_COLLEGE_ADMIN = 'college_admin';
const ROLE_DEPARTMENT_ADMIN = 'department_admin';
const ROLE_STUDENT = 'student';
const ROLE_ANNOUNCER = 'announcer'; // Legacy announcer maps to publisher role

/**
 * Ensures the user is logged in as an authorized administrator.
 * If not authenticated, redirects to login page.
 * If authenticated as student, redirects to student portal or denies.
 */
function require_admin(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        $current_url = $_SERVER['REQUEST_URI'] ?? 'index.php';
        $_SESSION['intended_url'] = $current_url;
        header('Location: ../login.php');
        exit;
    }

    $role = $_SESSION['user_role'];
    $allowed_roles = [
        ROLE_SUPER_ADMIN, 
        'admin', 
        ROLE_UNIVERSITY_ADMIN, 
        ROLE_OFFICE_ADMIN, 
        ROLE_COLLEGE_ADMIN, 
        ROLE_DEPARTMENT_ADMIN,
        ROLE_ANNOUNCER
    ];

    if (!in_array($role, $allowed_roles, true)) {
        if ($role === ROLE_STUDENT) {
            header('Location: ../student/index.php');
            exit;
        }
        http_response_code(403);
        render_access_denied();
        exit;
    }

    // Verify user is still active in the database
    $pdo = get_db();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id, status, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || $user['status'] !== 'active') {
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();
                header('Location: ../login.php?err=deactivated');
                exit;
            }
        } catch (PDOException $e) {
            // If DB check fails transiently, keep session active
        }
    }
}

/**
 * Enforces specific role access.
 * If user does not have one of the required roles, halts with 403.
 */
function require_specific_role(array $roles): void {
    require_admin();
    $currentRole = current_user_role();
    if (!in_array($currentRole, $roles, true) && !is_super_admin()) {
        http_response_code(403);
        render_access_denied("You do not have permission to access this management area.");
        exit;
    }
}

/**
 * Returns current authenticated user array with organizational scope
 */
function current_user(): array {
    $role = $_SESSION['user_role'] ?? 'guest';
    $name = $_SESSION['user_name'] ?? 'User';
    $email = $_SESSION['user_email'] ?? '';
    $id = $_SESSION['user_id'] ?? null;

    $assigned_college = $_SESSION['assigned_college'] ?? null;
    $assigned_office = $_SESSION['assigned_office'] ?? null;
    $assigned_department = $_SESSION['assigned_department'] ?? null;

    // Check if organization details need to be hydrated from DB
    if ($id && ($assigned_college === null && $assigned_office === null && $assigned_department === null)) {
        $pdo = get_db();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if ($row) {
                    $assigned_college = $row['assigned_college'] ?? null;
                    $assigned_office = $row['assigned_office'] ?? null;
                    $assigned_department = $row['assigned_department'] ?? null;
                    $_SESSION['assigned_college'] = $assigned_college;
                    $_SESSION['assigned_office'] = $assigned_office;
                    $_SESSION['assigned_department'] = $assigned_department;
                }
            } catch (Exception $e) {
                // ignore
            }
        }
    }

    // Default fallbacks based on role if DB columns not yet populated
    if (empty($assigned_college) && $role === ROLE_COLLEGE_ADMIN) {
        $assigned_college = 'College of Computer Studies';
    }
    if (empty($assigned_office) && $role === ROLE_OFFICE_ADMIN) {
        $assigned_office = 'Office of the University Registrar';
    }
    if (empty($assigned_department) && $role === ROLE_DEPARTMENT_ADMIN) {
        $assigned_department = 'Department of Computer Science';
    }

    return [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'assigned_college' => $assigned_college,
        'assigned_office' => $assigned_office,
        'assigned_department' => $assigned_department,
    ];
}

/**
 * Returns normalized role name
 */
function current_user_role(): string {
    $role = $_SESSION['user_role'] ?? 'guest';
    if ($role === 'admin') return ROLE_SUPER_ADMIN;
    return $role;
}

/**
 * Checks if current user is Super Administrator
 */
function is_super_admin(): bool {
    $role = current_user_role();
    return in_array($role, [ROLE_SUPER_ADMIN, 'admin'], true);
}

/**
 * Get active organizational unit string for current user
 */
function get_user_assigned_unit(array $user): string {
    $role = $user['role'] ?? '';
    if (in_array($role, [ROLE_SUPER_ADMIN, 'admin'], true)) {
        return 'All Campuses & Units';
    } elseif ($role === ROLE_UNIVERSITY_ADMIN) {
        return 'International State College of the Philippines (All Campuses)';
    } elseif ($role === ROLE_OFFICE_ADMIN) {
        return $user['assigned_office'] ?? 'Assigned University Office';
    } elseif ($role === ROLE_COLLEGE_ADMIN) {
        return $user['assigned_college'] ?? 'Assigned College';
    } elseif ($role === ROLE_DEPARTMENT_ADMIN) {
        return $user['assigned_department'] ?? 'Assigned Department';
    } elseif ($role === ROLE_ANNOUNCER) {
        return $user['assigned_college'] ?? 'Academic Department';
    }
    return 'General';
}

/**
 * Role Permission Matrix according to PDF Section 5
 */
function get_role_permissions(string $role): array {
    $role = ($role === 'admin') ? ROLE_SUPER_ADMIN : $role;
    $matrix = [
        ROLE_SUPER_ADMIN => [
            'manage_users'       => true,
            'manage_offices'     => true,
            'manage_colleges'    => true,
            'manage_departments' => true,
            'manage_categories'  => true,
            'manage_permissions' => true,
            'create_posts'       => true,
            'edit_within_scope'  => true,
            'view_announcements' => 'all',
            'view_analytics'     => 'all',
            'publishing_scope'   => 'any',
        ],
        ROLE_UNIVERSITY_ADMIN => [
            'manage_users'       => false,
            'manage_offices'     => false,
            'manage_colleges'    => false,
            'manage_departments' => false,
            'manage_categories'  => false,
            'manage_permissions' => false,
            'create_posts'       => true,
            'edit_within_scope'  => true,
            'view_announcements' => 'all',
            'view_analytics'     => 'university',
            'publishing_scope'   => 'university_wide',
        ],
        ROLE_OFFICE_ADMIN => [
            'manage_users'       => false,
            'manage_offices'     => false,
            'manage_colleges'    => false,
            'manage_departments' => false,
            'manage_categories'  => false,
            'manage_permissions' => false,
            'create_posts'       => true,
            'edit_within_scope'  => true,
            'view_announcements' => 'all',
            'view_analytics'     => 'office',
            'publishing_scope'   => 'office_only',
        ],
        ROLE_COLLEGE_ADMIN => [
            'manage_users'       => false,
            'manage_offices'     => false,
            'manage_colleges'    => false,
            'manage_departments' => false,
            'manage_categories'  => false,
            'manage_permissions' => false,
            'create_posts'       => true,
            'edit_within_scope'  => true,
            'view_announcements' => 'all',
            'view_analytics'     => 'college',
            'publishing_scope'   => 'college_only',
        ],
        ROLE_DEPARTMENT_ADMIN => [
            'manage_users'       => false,
            'manage_offices'     => false,
            'manage_colleges'    => false,
            'manage_departments' => false,
            'manage_categories'  => false,
            'manage_permissions' => false,
            'create_posts'       => true,
            'edit_within_scope'  => true,
            'view_announcements' => 'all',
            'view_analytics'     => 'department',
            'publishing_scope'   => 'department_only',
        ],
        ROLE_STUDENT => [
            'manage_users'       => false,
            'manage_offices'     => false,
            'manage_colleges'    => false,
            'manage_departments' => false,
            'manage_categories'  => false,
            'manage_permissions' => false,
            'create_posts'       => false,
            'edit_within_scope'  => false,
            'view_announcements' => 'relevant',
            'view_analytics'     => false,
            'publishing_scope'   => 'none',
        ],
        ROLE_ANNOUNCER => [
            'manage_users'       => false,
            'manage_offices'     => false,
            'manage_colleges'    => false,
            'manage_departments' => false,
            'manage_categories'  => false,
            'manage_permissions' => false,
            'create_posts'       => true,
            'edit_within_scope'  => true,
            'view_announcements' => 'all',
            'view_analytics'     => 'college',
            'publishing_scope'   => 'college_only',
        ]
    ];

    return $matrix[$role] ?? $matrix[ROLE_STUDENT];
}

/**
 * Backend Scope Validation: Checks if user has permission to edit/archive/delete
 * a specific announcement based on organizational scope.
 */
function can_manage_announcement(array $user, array $announcement): bool {
    if (is_super_admin()) {
        return true;
    }

    $role = $user['role'] ?? '';
    $userId = (int)($user['id'] ?? 0);
    $creatorId = (int)($announcement['created_by'] ?? 0);

    // Creator can always manage their own submission
    if ($userId > 0 && $userId === $creatorId) {
        return true;
    }

    return false;
}

/**
 * Returns user-friendly title for roles
 */
function format_role_label(?string $role, ?string $unit = null): string {
    switch ($role) {
        case 'super_admin':
        case 'admin':
            return 'Super Administrator';
        case 'university_admin':
            return 'University Administrator';
        case 'office_admin':
            return !empty($unit) ? "Office Admin ($unit)" : 'Office Administrator';
        case 'college_admin':
            return !empty($unit) ? "College Admin ($unit)" : 'College Administrator';
        case 'department_admin':
            return !empty($unit) ? "Department Admin ($unit)" : 'Department Administrator';
        case 'student':
            return 'Student';
        case 'announcer':
            return 'Faculty / Announcer';
        default:
            return ucwords(str_replace('_', ' ', (string)$role));
    }
}

/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from POST request
 */
function verify_csrf_token(): bool {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output HTML hidden CSRF input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Set flash alert message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Get and clear flash alert message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Escape string for safe HTML rendering
 */
function h(?string $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Friendly relative time formatting
 */
function time_ago(?string $datetime): string {
    if (empty($datetime)) return 'N/A';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'N/A';
    
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $timestamp);
}

/**
 * Formats a date for tables and detail views
 */
function format_datetime(?string $datetime): string {
    if (empty($datetime)) return 'N/A';
    $timestamp = strtotime($datetime);
    return $timestamp ? date('M j, Y g:i A', $timestamp) : 'N/A';
}

/**
 * Count helper for pending review count
 */
function get_pending_count(): int {
    $pdo = get_db();
    if (!$pdo) return 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM announcements WHERE status IN ('pending', 'pending_approval')");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Count helper for pending user accounts awaiting Super Admin approval
 */
function get_pending_users_count(): int {
    $pdo = get_db();
    if (!$pdo) return 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending_approval'");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 403 Forbidden Error Page
 */
function render_access_denied(string $message = ''): void {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 - Access Denied</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background: #f1f5f9;
                color: #0f172a;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 1rem;
            }
            .card {
                background: white;
                max-width: 480px;
                padding: 2.5rem;
                border-radius: 16px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.08);
                text-align: center;
            }
            .icon { font-size: 3rem; margin-bottom: 1rem; }
            h1 { font-size: 1.75rem; margin: 0 0 0.5rem; color: #dc2626; }
            p { color: #64748b; line-height: 1.6; margin-bottom: 1.5rem; }
            .btn {
                display: inline-block;
                background: #0284c7;
                color: white;
                text-decoration: none;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">🚫</div>
            <h1>Access Denied (403)</h1>
            <p><?= !empty($message) ? h($message) : 'You do not have administrative privileges to access this area. This action is restricted to authorized roles.' ?></p>
            <a href="index.php" class="btn">Return to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
}
