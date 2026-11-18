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
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (!$is_connected || !$pdo) {
        $error = 'The database is unavailable. Admin sign-in requires a database account.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin_user = $stmt->fetch();

            if ($admin_user && password_verify($password, $admin_user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin_user['id'];
                $_SESSION['admin_username'] = $admin_user['username'];
                $_SESSION['admin_name'] = $admin_user['full_name'];
                $_SESSION['admin_role'] = $admin_user['role'];

                header('Location: index.php');
                exit;
            }

            $error = 'Invalid username or password.';
        } catch (PDOException $e) {
            error_log('Admin authentication failed: ' . $e->getMessage());
            $error = 'Unable to authenticate with the database. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - International State College of the Philippines</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-image:
                linear-gradient(145deg, rgba(239, 247, 255, 0.48), rgba(7, 85, 184, 0.66)),
                url("../assets/images/iscp_school.jpg");
            background-position: center;
            background-size: cover;
            background-attachment: fixed;
        }
        .navbar {
            background: linear-gradient(105deg, #063b82, #0755b8 55%, #3184e8);
            border-bottom-color: rgba(255, 255, 255, 0.2);
        }
        .back-to-feed {
            background: transparent;
            border: 0;
            color: #fff;
            box-shadow: none;
        }
        .back-to-feed:hover {
            background: transparent;
            color: #dbeafe;
            transform: translateY(-2px);
        }
        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
            padding: 2rem;
        }
        .login-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(232, 242, 255, 0.96));
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 24px 70px rgba(0, 31, 82, 0.38), 0 0 0 6px rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(18px);
        }
        .login-logo {
            display: block;
            width: 82px;
            height: 82px;
            object-fit: contain;
            margin: 0 auto 0.75rem;
            border-radius: 50%;
            box-shadow: 0 8px 22px rgba(7, 85, 184, 0.2);
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
            background: #fff;
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
                <img class="brand-logo" src="../assets/images/iscp_logo.png" alt="International State College of the Philippines logo">
                <div class="brand-text">
                    <h1>International State College of the Philippines</h1>
                    <p>Administration Portal</p>
                </div>
            </div>
            <a href="../index.php" class="btn back-to-feed">←     Back to Feed</a>
        </div>
    </header>

    <div class="login-wrapper">
        <div class="login-card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <img class="login-logo" src="../assets/images/iscp_logo.png" alt="International State College of the Philippines logo">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #0755b8;">Admin Sign In</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Access the announcement management dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
            <div style="background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; padding: 0.75rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1.25rem; font-size: 0.85rem;">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <div style="margin-top: 1.75rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem;">
                        Sign In to Dashboard 
                    </button>
                </div>
            </form>

        </div>
    </div>

</body>
</html>
