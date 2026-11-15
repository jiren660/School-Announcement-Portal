<?php
/**
 * Admin Login Redirect / Handler
 * Routes to unified login or redirects if already authenticated.
 */

session_start();
if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: index.php');
        exit;
    } elseif ($_SESSION['user_role'] === 'announcer') {
        header('Location: ../announcer/index.php');
        exit;
    }
}

// Redirect to root Unified Login
header('Location: ../login.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
