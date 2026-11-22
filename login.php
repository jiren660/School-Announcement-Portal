<?php
/**
 * Unified School & Admin Sign In Portal
 * International State College of the Philippines
 * 
 * Supports Super Admin, University Admin, Office Admin, College Admin,
 * Department Admin, Faculty/Announcers, and Students.
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/auth.php';

// If already logged in, redirect to respective role portal
if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    if (in_array($role, ['admin', 'super_admin', 'university_admin', 'office_admin', 'college_admin', 'department_admin'], true)) {
        header('Location: admin/index.php');
        exit;
    } elseif ($role === 'student') {
        header('Location: student/index.php');
        exit;
    } else {
        header('Location: announcer/index.php');
        exit;
    }
}

$error = '';
$info = '';

if (isset($_GET['err'])) {
    if ($_GET['err'] === 'deactivated') {
        $error = 'Your account has been deactivated. Please contact the administrator.';
    } elseif ($_GET['err'] === 'forbidden') {
        $error = 'You do not have permission to access that area.';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $info = 'You have been successfully signed out.';
}

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['email'] ?? $_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login_input) || empty($password)) {
        $error = 'Please enter both your username or school email and password.';
    } elseif (!$pdo) {
        $error = 'Database service is currently unreachable. Please verify that MariaDB is running.';
    } else {
        try {
            // Support both direct email and username format (e.g. "admin" or "admin@iscp.edu.ph")
            $check_email = $login_input;
            $fallback_email = strpos($login_input, '@') === false ? $login_input . '@iscp.edu.ph' : $login_input;

            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE LOWER(email) = LOWER(?) OR LOWER(email) = LOWER(?) 
                LIMIT 1
            ");
            $stmt->execute([$check_email, $fallback_email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account is deactivated. Please coordinate with the School Administration.';
                } else {
                    // Valid credentials: Start authenticated session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['assigned_college'] = $user['assigned_college'] ?? null;
                    $_SESSION['assigned_office'] = $user['assigned_office'] ?? null;
                    $_SESSION['assigned_department'] = $user['assigned_department'] ?? null;

                    // Role-based redirection according to RBAC architecture
                    $admin_roles = ['admin', 'super_admin', 'university_admin', 'office_admin', 'college_admin', 'department_admin'];
                    
                    if (in_array($user['role'], $admin_roles, true)) {
                        $redirect = $_SESSION['intended_url'] ?? 'admin/index.php';
                        unset($_SESSION['intended_url']);
                        header('Location: ' . $redirect);
                        exit;
                    } elseif ($user['role'] === 'student') {
                        $redirect = $_SESSION['intended_url'] ?? 'student/index.php';
                        unset($_SESSION['intended_url']);
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        // Announcers / Faculty
                        $redirect = $_SESSION['intended_url'] ?? 'announcer/index.php';
                        unset($_SESSION['intended_url']);
                        header('Location: ' . $redirect);
                        exit;
                    }
                }
            } else {
                $error = 'Invalid username/email address or password.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An authentication error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator & Staff Sign In - International State College of the Philippines</title>
    
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            background-color: #0b1329;
            overflow-x: hidden;
        }

        /* 
         * Background Image Layer with Subtle Blue Tint Overlay
         * Uses assets/images/iscp_school.jpg with reduced opacity
         * and a rich university-themed blue gradient overlay
         */
        .portal-bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: url('assets/images/iscp_school.jpg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            opacity: 0.35; /* Reduced visual intensity */
            filter: saturate(1.1);
        }

        .portal-bg-overlay {
            position: fixed;
            inset: 0;
            z-index: 1;
            /* Subtle university-themed blue gradient tint */
            background: linear-gradient(
                135deg, 
                rgba(11, 25, 44, 0.90) 0%, 
                rgba(15, 39, 74, 0.85) 45%, 
                rgba(3, 105, 161, 0.78) 100%
            );
            backdrop-filter: blur(2px);
        }

        /* Login Container Wrapper */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 415px;
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

        /* Primary Login Card Container */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-lg);
            padding: clamp(1.85rem, 5vw, 2.35rem) clamp(1.5rem, 4vw, 2rem);
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.85);
            position: relative;
            overflow: hidden; /* Cleanly clips top accent bar to the card's rounded corners */
        }

        /* Top Accent University Bar - Perfectly flush with the top border curve */
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
            margin-bottom: 2rem;
        }

        /* University Logo Styling - Circular border */
        .login-logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.15rem;
            width: 94px;
            height: 94px;
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
            margin-bottom: 1.5rem;
            animation: fadeInCard 0.25s ease;
        }

        .login-alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .login-alert-info {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }

        .login-alert i {
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        /* Form Group Styles */
        .form-group {
            margin-bottom: 1.35rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.45rem;
        }

        /* Email / Username Input Group with Prefix/Suffix indicator */
        .input-group {
            display: flex;
            align-items: stretch;
            position: relative;
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

        .input-group input {
            flex: 1;
            border: none;
            outline: none;
            padding: 0.75rem 0.95rem;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--text-main);
            background: transparent;
            min-width: 0;
        }

        .input-group input::placeholder {
            color: #94a3b8;
            font-size: 0.88rem;
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
        }

        .btn-password-toggle:hover {
            color: var(--primary);
        }

        /* Submit Button */
        .btn-login-submit {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            padding: 0.82rem 1.25rem;
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
            margin-top: 0.5rem;
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
                padding: 1rem;
            }
            .login-card {
                padding: 1.75rem 1.25rem;
            }
            .login-title {
                font-size: 1.35rem;
            }
            .login-logo-container {
                width: 80px;
                height: 80px;
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
                <h1 class="login-title">Portal Sign In</h1>
                <p class="login-subtitle">International State College of the Philippines</p>
            </div>

            <!-- Feedback Alerts -->
            <?php if (!empty($error)): ?>
                <div class="login-alert login-alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><?= h($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($info)): ?>
                <div class="login-alert login-alert-info" role="status">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><?= h($info) ?></div>
                </div>
            <?php endif; ?>

            <!-- Sign In Form -->
            <form method="POST" action="login.php" class="login-form" novalidate>
                <?= csrf_field() ?>

                <!-- Username / Email Input -->
                <div class="form-group">
                    <label for="email" class="form-label">Username or School Email</label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-at"></i>
                        </div>
                        <input 
                            type="text" 
                            id="email" 
                            name="email" 
                            required 
                            autofocus 
                            autocomplete="username"
                            placeholder="example@iscp.edu.ph"
                            value="<?= h($_POST['email'] ?? '') ?>"
                        >
                    </div>
                </div>

                <!-- Password Input with Masked Characters & Visibility Toggle -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <div class="input-group-addon" aria-hidden="true">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            placeholder="Enter your account password"
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

                <!-- Submit Button -->
                <button type="submit" class="btn-login-submit">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Sign In to Portal</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Interactive Password Visibility Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (toggleBtn && passwordInput && toggleIcon) {
                toggleBtn.addEventListener('click', function () {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    toggleIcon.classList.toggle('fa-eye', !isPassword);
                    toggleIcon.classList.toggle('fa-eye-slash', isPassword);
                    passwordInput.focus();
                });
            }
        });
    </script>
</body>
</html>
