<?php
/**
 * Unified Login Portal
 * For Administrators, Teachers, Faculty, and Staff (Announcers)
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/auth.php';

// If already logged in, redirect to respective portal
if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    if (in_array($_SESSION['user_role'], ['admin', 'super_admin'], true)) {
        header('Location: admin/index.php');
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
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid school email address.';
    } elseif (!$pdo) {
        $error = 'Database service is currently unreachable. Please check connection.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $stmt->execute([$email]);
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

                    // Role-based redirection
                    if (in_array($user['role'], ['admin', 'super_admin'], true)) {
                        $redirect = $_SESSION['intended_url'] ?? 'admin/index.php';
                        unset($_SESSION['intended_url']);
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        // University, Office, College, Dept Admins & Announcers
                        $redirect = $_SESSION['intended_url'] ?? 'announcer/index.php';
                        unset($_SESSION['intended_url']);
                        header('Location: ' . $redirect);
                        exit;
                    }
                }
            } else {
                $error = 'Invalid email address or password.';
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
    <title>Staff & Admin Sign In - School Announcement System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card-container {
            width: 100%;
            max-width: 460px;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo-circle {
            width: 58px;
            height: 58px;
            background: var(--primary-gradient);
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: #ffffff;
            margin-bottom: 0.75rem;
            box-shadow: 0 8px 16px rgba(2, 132, 199, 0.35);
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }
        .login-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        .role-badges-banner {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 0.75rem;
        }
        .role-badge-pill {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-full);
            background: #e0f2fe;
            color: #0369a1;
        }
        .input-icon-wrapper {
            position: relative;
        }
        .input-icon-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.95rem;
        }
        .input-icon-wrapper input {
            padding-left: 2.75rem;
        }
        .btn-login-submit {
            width: 100%;
            padding: 0.75rem;
            font-size: 0.98rem;
            margin-top: 0.5rem;
        }
        .demo-credentials-box {
            margin-top: 1.75rem;
            padding: 1rem;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            color: #475569;
        }
        .demo-credentials-box code {
            background: #e2e8f0;
            padding: 0.15rem 0.35rem;
            border-radius: 4px;
            color: #0f172a;
            font-weight: 600;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        .back-link a:hover {
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="login-card-container">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo-circle">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h1 class="login-title">Portal Sign In</h1>
            <p class="login-subtitle">International State College of the Philippines</p>
            <div class="role-badges-banner">
                <span class="role-badge-pill"><i class="fa-solid fa-shield-halved"></i> Administrator</span>
                <span class="role-badge-pill"><i class="fa-solid fa-chalkboard-user"></i> Teachers & Staff</span>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem; font-size: 0.85rem; padding: 0.75rem 1rem;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div class="alert-text"><?= h($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($info)): ?>
            <div class="alert alert-info" style="margin-bottom: 1.25rem; font-size: 0.85rem; padding: 0.75rem 1rem;">
                <i class="fa-solid fa-circle-info"></i>
                <div class="alert-text"><?= h($info) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email" class="form-label">School Email</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" class="form-input" placeholder="e.g. admin@iscp.edu.ph or teacher@iscp.edu.ph" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-login-submit">
                <i class="fa-solid fa-right-to-bracket"></i>
                Sign In to Portal
            </button>
        </form>

        <div class="demo-credentials-box">
            <div style="font-weight: 700; margin-bottom: 0.35rem;"><i class="fa-solid fa-key"></i> Available Demo Accounts:</div>
            <div style="margin-bottom: 0.25rem;">
                👑 <strong>Admin:</strong> <code>admin@iscp.edu.ph</code> / <code>AdminPass123!</code>
            </div>
            <div>
                📢 <strong>Announcer / Teacher:</strong> <code>announcer@iscp.edu.ph</code> / <code>AnnouncerPass123!</code>
            </div>
        </div>
    </div>

    <div class="back-link">
        <a href="index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Public Announcement Board
        </a>
    </div>
</div>

</body>
</html>

