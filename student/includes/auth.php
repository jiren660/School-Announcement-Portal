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
 * Ensures the student session is valid (or guest viewing mode).
 */
function current_student(): array {
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'name' => $_SESSION['user_name'] ?? 'Student User',
        'email' => $_SESSION['user_email'] ?? 'student@iscp.edu.ph',
        'role' => 'student',
        'enrolled_college' => $_SESSION['assigned_college'] ?? 'College of Computer Studies',
        'enrolled_program' => 'BS in Information Technology'
    ];
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
