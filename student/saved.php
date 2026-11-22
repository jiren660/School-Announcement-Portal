<?php
/**
 * Student Saved / Bookmarked Announcements
 * Follows PDF Section 2.F & 4.F: Student Corner.
 */

define('STUDENT_INIT', true);
require_once __DIR__ . '/includes/auth.php';

$currentStudent = current_student();
$page_title = 'Saved Announcements';
$page_subtitle = 'Your bookmarked announcements, timetables, and campus guidelines.';

$pdo = get_db();

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-bookmark" style="color: #f59e0b; font-size: 1.25rem;"></i>
            <h2 class="card-title">Bookmarked Announcements</h2>
        </div>
        <div class="card-header-right">
            <button type="button" class="btn btn-outline btn-sm" onclick="clearAllSaved()">
                <i class="fa-solid fa-trash"></i> Clear All Saved
            </button>
        </div>
    </div>

    <div class="card-body">
        <div id="savedListContainer"></div>
    </div>
</div>

<script>
function renderSavedList() {
    const container = document.getElementById('savedListContainer');
    let saved = [];
    try {
        saved = JSON.parse(localStorage.getItem('iscp_saved_posts') || '[]');
    } catch(e) {}

    if (saved.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-bookmark"></i></div>
                <h3 class="empty-state-title">No saved announcements</h3>
                <p class="empty-state-desc">You can bookmark announcements from the feed by clicking the "Save" button on any card.</p>
                <div style="margin-top: 1rem;">
                    <a href="index.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-left"></i> Browse Announcement Feed</a>
                </div>
            </div>
        `;
        return;
    }

    let html = `<div style="display: flex; flex-direction: column; gap: 1rem;">`;
    saved.forEach(item => {
        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border: 1px solid var(--card-border); border-radius: var(--radius-md); background: #ffffff;">
                <div>
                    <h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">${escapeHtml(item.title)}</h3>
                    <div style="font-size: 0.78rem; color: #64748b;">Saved: ${new Date(item.savedAt).toLocaleDateString()}</div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="index.php#post-${item.id}" class="btn btn-primary btn-sm">View in Feed</a>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSaved(${item.id})"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        `;
    });
    html += `</div>`;
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function removeSaved(id) {
    let saved = JSON.parse(localStorage.getItem('iscp_saved_posts') || '[]');
    saved = saved.filter(p => p.id !== id);
    localStorage.setItem('iscp_saved_posts', JSON.stringify(saved));
    renderSavedList();
}

function clearAllSaved() {
    if (confirm('Are you sure you want to clear all bookmarked announcements?')) {
        localStorage.removeItem('iscp_saved_posts');
        renderSavedList();
    }
}

document.addEventListener('DOMContentLoaded', renderSavedList);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
