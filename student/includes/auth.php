<?php
/**
 * Student Authentication & Session Helper
 * Enforces view-only access. Blocks administrative actions.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Ensures the student session is valid and loads live affiliation details.
 */
function current_student(): array {
    $id = (int)($_SESSION['user_id'] ?? 0);
    $student = [
        'id' => $id,
        'name' => $_SESSION['user_name'] ?? 'Student User',
        'email' => $_SESSION['user_email'] ?? 'student@iscp.edu.ph',
        'role' => 'student',
        'enrolled_college' => $_SESSION['assigned_college'] ?? null,
        'enrolled_department' => $_SESSION['assigned_department'] ?? null,
        'student_id' => null
    ];

    if ($id > 0) {
        $pdo = get_db();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT name, email, role, assigned_college, assigned_department, student_id FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $u = $stmt->fetch();
                if ($u) {
                    $student['name'] = $u['name'];
                    $student['email'] = $u['email'];
                    $student['enrolled_college'] = $u['assigned_college'];
                    $student['enrolled_department'] = $u['assigned_department'];
                    $student['student_id'] = $u['student_id'];
                    $_SESSION['assigned_college'] = $u['assigned_college'];
                    $_SESSION['assigned_department'] = $u['assigned_department'];
                }
            } catch (Exception $e) {}
        }
    }

    return $student;
}

function require_student(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function h(?string $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

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

function format_datetime(?string $datetime): string {
    if (empty($datetime)) return 'N/A';
    $timestamp = strtotime($datetime);
    return $timestamp ? date('M j, Y g:i A', $timestamp) : 'N/A';
}
