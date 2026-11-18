<?php
/**
 * Announcer Authentication & Security Helper
 * For Teachers, Faculty, and Staff
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Ensures the user is logged in as an ANNOUNCER (or ADMIN).
 */
function require_announcer(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        $current_url = $_SERVER['REQUEST_URI'] ?? 'index.php';
        $_SESSION['intended_url'] = $current_url;
        header('Location: ../login.php');
        exit;
    }

    // Allow any authorized administrator role or announcer
    $allowed_roles = ['announcer', 'super_admin', 'admin', 'university_admin', 'office_admin', 'college_admin', 'department_admin'];
    if (!in_array($_SESSION['user_role'], $allowed_roles, true)) {
        header('Location: ../login.php?err=forbidden');
        exit;
    }

    // Verify user is still active in the database
    $pdo = get_db();
    if ($pdo) {
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
        'type' => $type,
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

if (!function_exists('format_role_label')) {
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
}

