<?php
/**
 * Student Registration Page
 * International State College of the Philippines
 * 
 * Replicates the exact visual layout and design system of login.php:
 * - Full background imagery with university-blue overlay
 * - Modern card container with top gradient accent bar
 * - Circular logo branding with subtle hover animation
 * - Unified input groups with leading icons and focus-ring states
 * - Password visibility toggles
 * - Dynamic department cascading dropdown based on selected college
 * - Direct navigation back to the public announcement board
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/auth.php';

$is_success_view = (isset($_GET['success']) && $_GET['success'] === '1' && !empty($_SESSION['registration_success']));
$regInfo = $_SESSION['registration_success'] ?? [];

// If already logged in, redirect to respective portal (unless viewing registration success)
if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role']) && !$is_success_view) {
    if ($_SESSION['user_role'] === 'student') {
        header('Location: student/index.php');
        exit;
    } else {
        header('Location: admin/index.php');
        exit;
    }
}

$pdo = get_db();
$error = '';
$form_data = [
    'name' => '',
    'email' => '',
    'student_id' => '',
    'college' => '',
    'department' => ''
];

// Fetch active colleges and departments for registration dropdowns
$colleges = [];
$departments = [];

if ($pdo) {
    try {
        $colleges = $pdo->query("SELECT id, code, name FROM colleges ORDER BY name ASC")->fetchAll();
        $departments = $pdo->query("SELECT id, code, name, college FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {
        $colleges = [];
        $departments = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session validation failed. Please try submitting again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $college = trim($_POST['college'] ?? '');
        $department = trim($_POST['department'] ?? '');

        $form_data = [
            'name' => $name,
            'email' => $email,
            'student_id' => $student_id,
            'college' => $college,
            'department' => $department
        ];

        // Validation Rules
        if (empty($name) || strlen($name) < 2) {
            $error = 'Please enter your complete full name (at least 2 characters).';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid school email address.';
        } elseif (!str_ends_with(strtolower($email), '@iscp.edu.ph')) {
            $error = 'Registration requires a valid university-issued school email address ending in @iscp.edu.ph.';
        } elseif (empty($student_id) || strlen($student_id) < 4) {
            $error = 'Please enter a valid School ID Number / ID Code (e.g. 2024-10023).';
        } elseif (empty($college)) {
            $error = 'Please select your affiliated College.';
        } elseif (empty($department)) {
            $error = 'Please select your academic Department.';
        } elseif (strlen($password) < 6) {
            $error = 'Your password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Password and confirmation password do not match.';
        } elseif (!$pdo) {
            $error = 'Database service is currently unreachable. Please try again shortly.';
        } else {
            try {
                // Verify Department belongs to Selected College
                $deptCheck = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND college = ?");
                $deptCheck->execute([$department, $college]);
                if (!$deptCheck->fetch()) {
                    $error = 'The selected department does not belong to the chosen college. Please re-select.';
                } else {
                    // Check duplicate email
                    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
                    $emailCheck->execute([$email]);
                    if ($emailCheck->fetch()) {
                        $error = 'An account with school email "' . h($email) . '" is already registered.';
                    } else {
                        // Check duplicate student_id
                        $idCheck = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
                        $idCheck->execute([$student_id]);
                        if ($idCheck->fetch()) {
                            $error = 'An account with School ID Number "' . h($student_id) . '" is already registered.';
                        } else {
                            // Register student account
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $insertStmt = $pdo->prepare("
                                INSERT INTO users (name, email, password_hash, role, assigned_college, assigned_department, student_id, status, created_at)
                                VALUES (?, ?, ?, 'student', ?, ?, ?, 'active', NOW())
                            ");
                            $insertStmt->execute([$name, $email, $password_hash, $college, $department, $student_id]);

                            $newUserId = (int)$pdo->lastInsertId();

                            // Log registration in activity_logs
                            try {
                                $logStmt = $pdo->prepare("
                                    INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
                                    VALUES (?, 'student_register', ?, ?, NOW())
                                ");
                                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                                $logStmt->execute([$newUserId, 'Student registered: ' . $name . ' (' . $student_id . ')', $ip]);
                            } catch (Exception $e) {}

                            // Automatically log in the registered student into active session
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $newUserId;
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_role'] = 'student';
                            $_SESSION['assigned_college'] = $college;
                            $_SESSION['assigned_department'] = $department;
                            $_SESSION['registration_success'] = [
                                'name' => $name,
                                'email' => $email,
                                'college' => $college,
                                'department' => $department,
                                'student_id' => $student_id
                            ];

                            header('Location: register.php?success=1');
                            exit;
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error during registration: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - International State College of the Philippines</title>
    
    <!-- Professional Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --primary-navy: #0f172a;
            --primary-light: #e0f2fe;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-full: 9999px;
            --shadow-card: 0 20px 40px -10px rgba(15, 23, 42, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.4);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 4.5rem 1.25rem 2.5rem;
            position: relative;
            background-color: #0b1329;
            overflow-x: hidden;
        }

        /* 
         * Background Image Layer with Subtle Blue Tint Overlay (Identical to login.php)
         */
        .portal-bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: url('assets/images/iscp_school.jpg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            opacity: 0.35;
            filter: saturate(1.1);
        }

        .portal-bg-overlay {
            position: fixed;
            inset: 0;
            z-index: 1;
            background: linear-gradient(
                135deg, 
                rgba(11, 25, 44, 0.90) 0%, 
                rgba(15, 39, 74, 0.85) 45%, 
                rgba(3, 105, 161, 0.78) 100%
            );
            backdrop-filter: blur(2px);
        }

        /* Container Wrapper - Centered, matching login.php layout */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            margin: auto 0;
            animation: fadeInCard 0.45s ease-out;
        }

        @keyframes fadeInCard {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Primary Card Container */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-lg);
            padding: clamp(1.85rem, 5vw, 2.35rem) clamp(1.5rem, 4vw, 2.15rem);
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.85);
            position: relative;
            overflow: hidden;
        }

        /* Top Accent University Bar */
        .login-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0284c7, #38bdf8, #0369a1);
            z-index: 2;
        }

        /* Brand & Logo Section */
        .login-brand {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        /* University Logo Styling - Circular border */
        .login-logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #bae6fd;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.14);
            padding: 4px;
            transition: var(--transition);
        }

        .login-logo-container:hover {
            border-color: #0284c7;
            transform: scale(1.03);
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.22);
        }

        .university-logo-img {
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 50%;
            display: block;
        }

        .login-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--primary-navy);
            letter-spacing: -0.025em;
            line-height: 1.25;
        }

        .login-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
            font-weight: 500;
        }

        /* Alert Callouts */
        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.86rem;
            line-height: 1.45;
            margin-bottom: 1.35rem;
            animation: fadeInCard 0.25s ease;
        }

        .login-alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .login-alert i {
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        /* Form Group Styles */
        .form-group {
            margin-bottom: 1.25rem;
            width: 100%;
            min-width: 0;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.45rem;
        }

        .form-label .required {
            color: #dc2626;
        }

        /* Unified Input Group with Prefix/Suffix indicator */
        .input-group {
            display: flex;
            align-items: stretch;
            position: relative;
            width: 100%;
            min-width: 0;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            background: #ffffff;
            transition: var(--transition);
            overflow: hidden;
        }

        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px rgba(2, 132, 199, 0.15);
        }

        .input-group-addon {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.95rem;
            color: var(--text-muted);
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .input-group input, .input-group select {
            flex: 1;
            width: 100%;
            min-width: 0;
            border: none;
            outline: none;
            padding: 0.75rem 0.95rem;
            font-family: inherit;
            font-size: 0.92rem;
            color: var(--text-main);
            background: transparent;
        }

        .input-group input::placeholder {
            color: #94a3b8;
            font-size: 0.88rem;
        }

        .input-group select {
            cursor: pointer;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }

        /* Password Visibility Toggle Button */
        .btn-password-toggle {
            background: transparent;
            border: none;
            outline: none;
            color: #64748b;
            padding: 0 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .btn-password-toggle:hover {
            color: var(--primary);
        }

        .form-hint {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
        }

        /* Submit Button */
        .btn-login-submit {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            padding: 0.85rem 1.25rem;
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 0.98rem;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: none;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(2, 132, 199, 0.32);
            transition: var(--transition);
            margin-top: 0.75rem;
        }

        .btn-login-submit:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            box-shadow: 0 10px 22px rgba(2, 132, 199, 0.42);
            transform: translateY(-1px);
        }

        .btn-login-submit:active {
            transform: translateY(1px);
            box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);
        }

        /* Back to Public Announcement Board Link (Upper Left) */
        .top-back-link {
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 50;
        }

        .top-back-link a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            transition: var(--transition);
            padding: 0.55rem 1.05rem;
            border-radius: var(--radius-full);
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        }

        .top-back-link a:hover {
            color: #ffffff;
            background: rgba(2, 132, 199, 0.85);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.35);
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            body {
                padding: 4.5rem 1rem 2rem;
            }
            .login-card {
                padding: 1.75rem 1.25rem;
            }
            .login-title {
                font-size: 1.35rem;
            }
            .login-logo-container {
                width: 76px;
                height: 76px;
            }
        }
    </style>
</head>
<body>

    <!-- Back to Public Announcement Board (Upper Left) -->
    <div class="top-back-link">
        <a href="index.php" title="Back to Public Announcement Board">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Public Announcement Board</span>
        </a>
    </div>

    <!-- Subtle Blue Overlay and Muted Background Image -->
    <div class="portal-bg-layer" aria-hidden="true"></div>
    <div class="portal-bg-overlay" aria-hidden="true"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Brand & Official University Logo Header -->
            <div class="login-brand">
                <div class="login-logo-container" title="International State College of the Philippines">
                    <img 
                        src="assets/images/iscp_logo.png" 
                        alt="International State College of the Philippines Official Logo" 
                        class="university-logo-img"
                    >
                </div>
                <h1 class="login-title">Student Registration</h1>
                <p class="login-subtitle">International State College of the Philippines</p>
            </div>

            <!-- Feedback Alert -->
            <?php if (!empty($error)): ?>
                <div class="login-alert login-alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><?= h($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($is_success_view): ?>
                <div style="text-align: center; padding: 0.5rem 0 0.5rem;">
                    <div style="width: 76px; height: 76px; border-radius: 50%; background: #ecfdf5; border: 2px solid #a7f3d0; color: #059669; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2.25rem; box-shadow: 0 10px 25px rgba(5, 150, 105, 0.18);">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <h2 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; margin-bottom: 0.4rem;">Registration Successful!</h2>
                    <p style="font-size: 0.92rem; color: #475569; margin-bottom: 1.5rem; line-height: 1.5;">
                        Welcome to the portal, <strong><?= h($regInfo['name'] ?? 'Student') ?></strong>! Your account has been registered and activated.
                    </p>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 1.1rem 1.25rem; margin-bottom: 1.75rem; text-align: left;">
                        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.65rem; font-size: 0.88rem; color: #334155;">
                            <i class="fa-solid fa-graduation-cap" style="color: var(--primary); font-size: 1rem; width: 18px;"></i>
                            <span>College: <strong><?= h($regInfo['college'] ?? '') ?></strong></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.65rem; font-size: 0.88rem; color: #334155;">
                            <i class="fa-solid fa-sitemap" style="color: #0d9488; font-size: 1rem; width: 18px;"></i>
                            <span>Department: <strong><?= h($regInfo['department'] ?? '') ?></strong></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.88rem; color: #64748b;">
                            <i class="fa-solid fa-id-card" style="color: #6366f1; font-size: 1rem; width: 18px;"></i>
                            <span>Student ID: <strong><?= h($regInfo['student_id'] ?? '') ?></strong></span>
                        </div>
                    </div>

                    <a href="student/index.php" class="btn-login-submit" style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; text-decoration: none; padding: 1.05rem 1.25rem; font-size: 0.95rem; font-weight: 700; line-height: 1.35; text-align: center; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(2, 132, 199, 0.4);">
                        <i class="fa-solid fa-bullhorn" style="font-size: 1.15rem;"></i>
                        <span>Click here to view announcements from your colleges and departments</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 1.25rem; font-size: 0.85rem;">
                        <a href="index.php" style="color: #64748b; text-decoration: none; font-weight: 600;">
                            <i class="fa-solid fa-arrow-left"></i> Public Bulletin
                        </a>
                        <span style="color: #cbd5e1;">&bull;</span>
                        <a href="login.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                            Sign In to Portal
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Registration Form -->
                <form method="POST" action="register.php" class="login-form" novalidate>
                    <?= csrf_field() ?>

                <!-- Full Name -->
                <div class="form-group">
                    <label for="name" class="form-label">Full Name <span class="required">*</span></label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            autofocus 
                            autocomplete="name"
                            placeholder="e.g. Maria Clara Santos"
                            value="<?= h($form_data['name']) ?>"
                        >
                    </div>
                </div>

                <!-- School Email Address -->
                <div class="form-group">
                    <label for="email" class="form-label">School Email <span class="required">*</span></label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-at"></i>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required 
                            autocomplete="email"
                            placeholder="student@iscp.edu.ph"
                            value="<?= h($form_data['email']) ?>"
                        >
                    </div>
                    <div class="form-hint">Must end with @iscp.edu.ph</div>
                </div>

                <!-- School ID Number -->
                <div class="form-group">
                    <label for="student_id" class="form-label">School ID Number <span class="required">*</span></label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-id-card"></i>
                        </div>
                        <input 
                            type="text" 
                            id="student_id" 
                            name="student_id" 
                            required 
                            placeholder="e.g. 2024-10023"
                            value="<?= h($form_data['student_id']) ?>"
                        >
                    </div>
                </div>

                <!-- College Selection -->
                <div class="form-group">
                    <label for="college" class="form-label">College <span class="required">*</span></label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-building-columns"></i>
                        </div>
                        <select id="college" name="college" required onchange="filterDepartments()">
                            <option value="">-- Choose College --</option>
                            <?php foreach ($colleges as $col): ?>
                                <option value="<?= h($col['name']) ?>" <?= $form_data['college'] === $col['name'] ? 'selected' : '' ?>>
                                    <?= h($col['name']) ?> (<?= h($col['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Department Selection -->
                <div class="form-group">
                    <label for="department" class="form-label">Department <span class="required">*</span></label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-network-wired"></i>
                        </div>
                        <select id="department" name="department" required>
                            <option value="">-- Select College First --</option>
                        </select>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="new-password"
                            placeholder="Min. 6 characters" 
                            minlength="6"
                        >
                        <button 
                            type="button" 
                            class="btn-password-toggle" 
                            id="togglePasswordBtn" 
                            aria-label="Toggle password visibility"
                            title="Show/Hide password"
                        >
                            <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="required">*</span></label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required 
                            autocomplete="new-password"
                            placeholder="Repeat password" 
                            minlength="6"
                        >
                        <button 
                            type="button" 
                            class="btn-password-toggle" 
                            id="toggleConfirmPasswordBtn" 
                            aria-label="Toggle confirm password visibility"
                            title="Show/Hide confirm password"
                        >
                            <i class="fa-solid fa-eye" id="toggleConfirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login-submit">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Create Student Account</span>
                </button>

                <!-- Sign In Link -->
                <div style="margin-top: 1.35rem; text-align: center; font-size: 0.88rem; color: #64748b;">
                    Already have an account? <a href="login.php" style="color: #0284c7; font-weight: 700; text-decoration: underline;">Sign In to Portal</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Interactive Scripts: Dynamic Department Filtering & Password Visibility Toggles -->
    <script>
        // Department data mapped from server
        const allDepartments = <?= json_encode($departments) ?>;
        const previousDept = <?= json_encode($form_data['department']) ?>;

        function filterDepartments() {
            const collegeSelect = document.getElementById('college');
            const deptSelect = document.getElementById('department');
            const selectedCollege = collegeSelect.value;

            deptSelect.innerHTML = '';

            if (!selectedCollege) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = '-- Select College First --';
                deptSelect.appendChild(opt);
                return;
            }

            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = '-- Choose Department --';
            deptSelect.appendChild(defaultOpt);

            const filtered = allDepartments.filter(d => d.college.trim().toLowerCase() === selectedCollege.trim().toLowerCase());

            if (filtered.length === 0) {
                const emptyOpt = document.createElement('option');
                emptyOpt.value = '';
                emptyOpt.textContent = 'No departments available for this college';
                deptSelect.appendChild(emptyOpt);
                return;
            }

            filtered.forEach(dep => {
                const opt = document.createElement('option');
                opt.value = dep.name;
                opt.textContent = dep.name + (dep.code ? ' (' + dep.code + ')' : '');
                if (dep.name === previousDept) {
                    opt.selected = true;
                }
                deptSelect.appendChild(opt);
            });
        }

        // Setup password toggles
        function setupToggle(btnId, inputId, iconId) {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (btn && input && icon) {
                btn.addEventListener('click', function () {
                    const isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');
                    icon.classList.toggle('fa-eye', !isPassword);
                    icon.classList.toggle('fa-eye-slash', isPassword);
                    input.focus();
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            filterDepartments();
            setupToggle('togglePasswordBtn', 'password', 'togglePasswordIcon');
            setupToggle('toggleConfirmPasswordBtn', 'confirm_password', 'toggleConfirmPasswordIcon');
        });
    </script>
</body>
</html>
