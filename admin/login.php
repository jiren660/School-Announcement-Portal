<?php
/**
 * Admin Login Redirect / Handler
 * Routes to unified login or redirects if already authenticated.
 */

session_start();
if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    if (in_array($role, ['admin', 'super_admin', 'university_admin', 'office_admin', 'college_admin', 'department_admin'], true)) {
        header('Location: index.php');
        exit;
    } elseif ($role === 'student') {
        header('Location: ../student/index.php');
        exit;
    } else {
        header('Location: ../announcer/index.php');
        exit;
    }
}

// Redirect to root Unified Login
header('Location: ../login.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
