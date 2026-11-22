<?php
/**
 * Student Profile Page
 * Follows PDF Section 2.F: Student account information.
 */

define('STUDENT_INIT', true);
require_once __DIR__ . '/includes/auth.php';

$currentStudent = current_student();
$page_title = 'Student Profile';
$page_subtitle = 'Your enrolled academic information and notification preferences.';

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 680px; margin: 0 auto;">
    <div class="content-card">
        <div class="card-header">
            <div class="card-header-left">
                <i class="fa-solid fa-user-graduate" style="color: var(--primary); font-size: 1.25rem;"></i>
                <h2 class="card-title">Academic Profile</h2>
            </div>
        </div>

        <div class="card-body">
            <div style="text-align: center; margin-bottom: 1.75rem;">
                <div class="avatar-circle" style="width: 72px; height: 72px; font-size: 1.8rem; margin: 0 auto 1rem; background: linear-gradient(135deg, #0284c7, #38bdf8);">
                    S
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a;"><?= h($currentStudent['name']) ?></h3>
                <p style="font-size: 0.88rem; color: #64748b;"><?= h($currentStudent['email']) ?></p>
                <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; margin-top:0.4rem;">
                    Enrolled Student
                </span>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1.25rem;">
                <div class="form-group">
                    <label class="form-label">Affiliated College</label>
                    <input type="text" class="form-control" value="<?= h($currentStudent['enrolled_college']) ?>" readonly style="background:#ffffff;">
                </div>

                <div class="form-group">
                    <label class="form-label">Degree Program</label>
                    <input type="text" class="form-control" value="<?= h($currentStudent['enrolled_program']) ?>" readonly style="background:#ffffff;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Portal Access Level</label>
                    <input type="text" class="form-control" value="Student (View-Only & Bookmark)" readonly style="background:#ffffff;">
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
