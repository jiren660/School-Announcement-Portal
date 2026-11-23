<?php
/**
 * Admin Layout Footer
 */
if (!defined('APP_INIT')) {
    exit;
}
?>
        </main>
    </div>
</div>

<!-- Sign Out Confirmation Modal -->
<div class="modal-overlay" id="signOutModal">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 0.5rem; color: #dc2626;">
                <i class="fa-solid fa-right-from-bracket"></i> Confirm Sign Out
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('signOutModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem 1.25rem; text-align: center;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </div>
            <h4 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                Are you sure you want to sign out?
            </h4>
            <p style="font-size: 0.88rem; color: #64748b; line-height: 1.5; margin-bottom: 0;">
                Your administrative session will be securely ended. Any unsaved changes in progress will be lost.
            </p>
        </div>
        <div class="modal-footer" style="display: flex; gap: 0.75rem; justify-content: flex-end; background: #f8fafc; padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0;">
            <button type="button" class="btn btn-outline" onclick="closeModal('signOutModal')">
                Cancel
            </button>
            <a href="logout.php" class="btn btn-danger" style="display: inline-flex; align-items: center; gap: 0.45rem;">
                <i class="fa-solid fa-right-from-bracket"></i> Yes, Sign Out
            </a>
        </div>
    </div>
</div>

<!-- Inline Fallback for Core Modals -->
<script>
window.openModal = window.openModal || function(id) {
    var m = document.getElementById(id);
    if (m) {
        var drop = document.getElementById('profileMenuDropdown');
        if (drop) drop.classList.remove('show');
        m.classList.add('open');
    }
};
window.closeModal = window.closeModal || function(id) {
    var m = typeof id === 'string' ? document.getElementById(id) : (id ? id.closest('.modal-overlay') : null);
    if (m) m.classList.remove('open');
};
</script>

<!-- Admin JavaScript Bundle -->
<script src="../assets/js/admin.js"></script>
</body>
</html>

