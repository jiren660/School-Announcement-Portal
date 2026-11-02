<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Redirect if already logged in
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$is_connected = is_db_connected();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = get_db();
        $authenticated = false;
        $admin_user = null;

        if ($is_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $authenticated = true;
                    $admin_user = $user;
                }
            } catch (PDOException $e) {
                $error = 'Database error during authentication: ' . $e->getMessage();
            }
        }

        // Fallback for development / offline testing (admin / password123)
        if (!$authenticated && $username === 'admin' && $password === 'password123') {
            $authenticated = true;
            $admin_user = [
                'id' => 1,
                'username' => 'admin',
                'full_name' => 'Demo Administrator',
                'role' => 'Super Admin'
            ];
        }

        if ($authenticated) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin_user['id'];
            $_SESSION['admin_username'] = $admin_user['username'];
            $_SESSION['admin_name'] = $admin_user['full_name'];
            $_SESSION['admin_role'] = $admin_user['role'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid credentials. Default is: admin / password123';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - School Announcement Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 160px);
            padding: 2rem;
        }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            max-width: 440px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(16px);
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: var(--transition);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="nav-container">
            <div class="brand-wrapper">
                <div class="brand-icon">🎓</div>
                <div class="brand-text">
                    <h1>Apex State University</h1>
                    <p>Administration Portal</p>
                </div>
            </div>
            <a href="../index.php" class="btn btn-secondary">← Back to Feed</a>
        </div>
    </header>

    <div class="login-wrapper">
        <div class="login-card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔐</div>
                <h2 style="font-size: 1.5rem; font-weight: 700;">Admin Sign In</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Access the announcement management dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1.25rem; font-size: 0.85rem;">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="e.g. admin" required autofocus value="admin">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required value="password123">
                </div>

                <div style="margin-top: 1.75rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem;">
                        Sign In to Dashboard →
                    </button>
                </div>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); text-align: center; font-size: 0.75rem; color: var(--text-dim);">
                Default credentials: <strong>admin</strong> / <strong>password123</strong>
            </div>
        </div>
    </div>

</body>
</html>
