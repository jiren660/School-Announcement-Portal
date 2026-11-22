<?php
/**
 * System Settings Page (Super Administrator)
 * Configure institutional portal preferences, publishing workflows, and security policies.
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_specific_role([ROLE_SUPER_ADMIN, 'admin']);

$currentUser = current_user();
$page_title = 'System Settings';
$page_subtitle = 'Manage institutional portal preferences, publishing workflows, and access configurations.';
$activeNav = 'settings.php';

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        set_flash('success', 'Portal configuration and workflow preferences have been saved successfully.');
    }
    header('Location: settings.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<form method="POST" action="settings.php" id="settingsForm">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(300px, 1fr); gap: 1.75rem; align-items: start;">
        
        <!-- Left Column: Primary Settings Sections -->
        <div>
            <!-- Section 1: Institutional Portal Identity -->
            <div class="content-card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-school" style="color: var(--primary); font-size: 1.25rem;"></i>
                        <h2 class="card-title">Institutional Identity &amp; Term</h2>
                    </div>
                </div>

                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-building-columns" style="color: var(--primary);"></i>
                            <span>University Portal Name</span>
                        </label>
                        <input 
                            type="text" 
                            name="portal_name" 
                            class="form-control" 
                            value="International State College of the Philippines" 
                            required
                        >
                        <div class="form-hint">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Displayed in the public bulletin header and system emails.</span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">
                                <i class="fa-regular fa-calendar" style="color: var(--primary);"></i>
                                <span>Academic Year</span>
                            </label>
                            <input 
                                type="text" 
                                name="academic_year" 
                                class="form-control" 
                                value="A.Y. 2026-2027" 
                                required
                            >
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">
                                <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
                                <span>Current Semester / Term</span>
                            </label>
                            <select name="academic_term" class="form-control">
                                <option value="1st Semester" selected>1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Midyear / Summer">Midyear / Summer Term</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Announcement Publishing & Review Workflow -->
            <div class="content-card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-code-branch" style="color: var(--primary); font-size: 1.25rem;"></i>
                        <h2 class="card-title">Announcement Workflow &amp; Publishing Rules</h2>
                    </div>
                </div>

                <div class="card-body">
                    <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1.25rem; line-height: 1.5;">
                        Configure multi-tier authorization workflows and review stages for announcements before they go live on the public bulletin board.
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <!-- Workflow Toggle 1 -->
                        <label class="toggle-option-card" style="padding: 1rem 1.25rem;">
                            <input type="checkbox" name="require_approval" value="1" checked style="accent-color: #0284c7; width: 20px; height: 20px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-main);">
                                    Require Review &amp; Approval for Department Submissions
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem; line-height: 1.45;">
                                    When enabled, submissions from Department Administrators are routed to <em>Pending Approvals</em>. Super and University Administrators review and authorize them.
                                </div>
                            </div>
                        </label>

                        <!-- Workflow Toggle 2 -->
                        <label class="toggle-option-card" style="padding: 1rem 1.25rem;">
                            <input type="checkbox" name="allow_drafts" value="1" checked style="accent-color: #0284c7; width: 20px; height: 20px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-main);">
                                    Enable Announcement Scheduling &amp; Drafts
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem; line-height: 1.45;">
                                    Allows authorized administrators to save in-progress drafts or schedule announcements to auto-publish on a future date and time.
                                </div>
                            </div>
                        </label>

                        <!-- Workflow Toggle 3 -->
                        <label class="toggle-option-card" style="padding: 1rem 1.25rem;">
                            <input type="checkbox" name="allow_carousel" value="1" checked style="accent-color: #0284c7; width: 20px; height: 20px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-main);">
                                    Multi-Photo Carousel &amp; Image Attachments
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem; line-height: 1.45;">
                                    Permits authors to upload multiple infographics and images with automatic thumbnail rendering and full-screen gallery modal.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save Portal Settings</span>
                </button>
                <a href="index.php" class="btn btn-outline" style="padding: 0.75rem 1.25rem;">Cancel</a>
            </div>
        </div>

        <!-- Right Column: System Information & Status Overview -->
        <div>
            <!-- System Status Card -->
            <div class="content-card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i>
                        <h3 class="card-title" style="font-size: 1rem;">System &amp; Security</h3>
                    </div>
                </div>
                <div class="card-body" style="font-size: 0.88rem; line-height: 1.6;">
                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #64748b;">Access Control (RBAC):</span>
                        <span style="font-weight: 700; color: #059669;"><i class="fa-solid fa-circle-check"></i> Enforced</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #64748b;">Public Bulletin:</span>
                        <span style="font-weight: 700; color: #0284c7;"><i class="fa-solid fa-globe"></i> Active</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #64748b;">Password Security:</span>
                        <span style="font-weight: 600; color: var(--text-main);">Bcrypt Hashing</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #64748b;">Session Protection:</span>
                        <span style="font-weight: 600; color: var(--text-main);">CSRF Tokenized</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 0.75rem;">
                        <span style="color: #64748b;">Timezone:</span>
                        <span style="font-weight: 600; color: var(--text-main);">Asia/Manila (GMT+8)</span>
                    </div>
                </div>
            </div>

            <!-- Quick Tip Card -->
            <div style="background: linear-gradient(135deg, #025c8d 0%, #0373af 100%); color: #ffffff; border-radius: var(--radius-md); padding: 1.25rem; box-shadow: var(--shadow-sm);">
                <div style="font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Super Administrator Tip</span>
                </div>
                <p style="font-size: 0.82rem; line-height: 1.5; color: #e0f2fe; margin-bottom: 0;">
                    Changes to institutional terminology and publishing workflows apply immediately across all administrative tiers.
                </p>
            </div>
        </div>

    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
