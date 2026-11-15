<?php
/**
 * Authentication & Security Helper for Admin Module
 * Provides role-based access control, session validation,
 * CSRF protection, and utility helpers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Ensures the user is logged in as an ADMIN.
 * If not authenticated, redirects to login page.
 * If authenticated but not an admin, denies access with a 403 response.
 */
function require_admin(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        $current_url = $_SERVER['REQUEST_URI'] ?? 'index.php';
        $_SESSION['intended_url'] = $current_url;
        header('Location: login.php');
        exit;
    }

    // Role-based access control check
    if (!in_array($_SESSION['user_role'], ['admin', 'super_admin'], true)) {
        http_response_code(403);
        render_access_denied();
        exit;
    }

    // Verify user is still active in the database
    $pdo = get_db();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id, status, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active' || !in_array($user['role'], ['admin', 'super_admin'], true)) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            header('Location: login.php?err=deactivated');
            exit;
        }
    }
}

/**
 * Returns current authenticated user array
 */
function current_user(): array {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'guest',
    ];
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
 * Count helper for sidebar badges
 */
function get_pending_count(): int {
    $pdo = get_db();
    if (!$pdo) return 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'pending'");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 403 Forbidden Error Page
 */
function render_access_denied(): void {
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
            .icon {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
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
            <p>You do not have administrative privileges to access this area. This portal requires an active <strong>Admin</strong> account.</p>
            <a href="login.php" class="btn">Return to Login</a>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Returns user-friendly title for roles
 */
function format_role_label(?string $role, ?string $college = null): string {
    switch ($role) {
        case 'super_admin':
        case 'admin':
            return 'Super Administrator';
        case 'university_admin':
            return 'University Administrator';
        case 'office_admin':
            return 'Office Administrator';
        case 'college_admin':
            return !empty($college) ? "College Administrator ($college)" : 'College Administrator';
        case 'announcer':
            return 'Faculty / Announcer';
        default:
            return ucwords(str_replace('_', ' ', (string)$role));
    }
}

