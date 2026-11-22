<?php
/**
 * Administrator Profile & Settings Page
 * Follows PDF Section 2 (Profile Settings / Office Profile / College Profile / Dept Profile).
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$currentUser = current_user();
$currentRole = current_user_role();
$assignedUnit = get_user_assigned_unit($currentUser);

$page_title = 'Account & Unit Profile';
$page_subtitle = 'Manage administrative credentials, security settings, and organizational unit preferences.';
$activeNav = 'profile.php';

$pdo = get_db();
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_msg = 'Security token invalid.';
    } else {
        $new_name = trim($_POST['name'] ?? '');
        $current_pw = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';

        if (empty($new_name)) {
            $error_msg = 'Name cannot be empty.';
        } elseif (!empty($new_pw) && strlen($new_pw) < 6) {
            $error_msg = 'New password must be at least 6 characters.';
        } else {
            if ($pdo) {
                try {
                    if (!empty($new_pw)) {
                        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, password_hash = ? WHERE id = ?");
                        $stmt->execute([$new_name, $hash, $currentUser['id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                        $stmt->execute([$new_name, $currentUser['id']]);
                    }
                    $_SESSION['user_name'] = $new_name;
                    $currentUser['name'] = $new_name;
                    $success_msg = 'Profile settings successfully updated.';
                } catch (PDOException $e) {
                    $error_msg = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.9fr); gap: 1.5rem; align-items: start;">
    
    <!-- Profile Edit Form -->
    <div class="content-card">
        <div class="card-header">
            <div class="card-header-left">
                <i class="fa-solid fa-user-gear" style="color: var(--primary); font-size: 1.25rem;"></i>
                <h2 class="card-title">Administrative Profile</h2>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.25rem;">
                    <div class="alert-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="alert-text"><?= h($success_msg) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                    <div class="alert-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                    <div class="alert-text"><?= h($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="profile.php">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= h($currentUser['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address (Read-only)</label>
                    <input type="email" class="form-control" value="<?= h($currentUser['email']) ?>" readonly style="background: #f1f5f9; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label class="form-label">Administrative Role</label>
                    <input type="text" class="form-control" value="<?= h(format_role_label($currentRole)) ?>" readonly style="background: #f1f5f9; cursor: not-allowed; font-weight: 600;">
                </div>

                <div class="form-group">
                    <label class="form-label">Assigned Organizational Unit</label>
                    <input type="text" class="form-control" value="<?= h($assignedUnit) ?>" readonly style="background: #f1f5f9; cursor: not-allowed; font-weight: 600;">
                </div>

                <div style="border-top: 1px solid #e2e8f0; margin: 1.5rem 0 1rem; padding-top: 1rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
                        <i class="fa-solid fa-key"></i> Change Password
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters">
                    </div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right: Account Overview Card -->
    <div>
        <div class="content-card">
            <div class="card-header">
                <div class="card-header-left">
                    <i class="fa-solid fa-id-card" style="color: var(--primary);"></i>
                    <h3 class="card-title" style="font-size: 1rem;">Credential Overview</h3>
                </div>
            </div>
            <div class="card-body" style="text-align: center; padding: 1.75rem 1.25rem;">
                <div class="avatar-circle" style="width: 64px; height: 64px; font-size: 1.6rem; margin: 0 auto 1rem; background: var(--primary-gradient);">
                    <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
                </div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a;"><?= h($currentUser['name']) ?></h3>
                <p style="font-size: 0.85rem; color: #64748b;"><?= h($currentUser['email']) ?></p>

                <div style="margin-top: 1rem;">
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700; font-size:0.75rem;">
                        <?= h(format_role_label($currentRole)) ?>
                    </span>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 0.85rem; margin-top: 1.25rem; font-size: 0.8rem; text-align: left;">
                    <div style="margin-bottom: 0.35rem;"><strong>Scope:</strong> <?= h($assignedUnit) ?></div>
                    <div style="margin-bottom: 0.35rem;"><strong>Session Security:</strong> Protected (CSRF Active)</div>
                    <div><strong>Access Level:</strong> Authorized Administrator</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
