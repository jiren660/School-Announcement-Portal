<?php
/**
 * Pending Announcement Requests Page
 * Review queue with View, Approve (confirmation modal), and Reject (reason modal).
 */

define('APP_INIT', true);
require_once __DIR__ . '/includes/auth.php';
require_admin();

$page_title = 'Pending Announcement Requests';
$page_subtitle = 'Review, approve, or reject announcement submissions submitted by announcers.';
$activeNav = 'pending_requests.php';

$pdo = get_db();
$currentUser = current_user();

// Handle POST actions: Approve or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header('Location: pending_requests.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        set_flash('danger', 'Invalid announcement ID provided.');
        header('Location: pending_requests.php');
        exit;
    }

    if ($action === 'approve') {
        try {
            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET status = 'approved',
                    reviewed_by = ?,
                    reviewed_at = ?,
                    rejection_reason = NULL,
                    updated_at = ?
                WHERE id = ? AND status = 'pending'
            ");
            $now = date('Y-m-d H:i:s');
            $stmt->execute([$currentUser['id'], $now, $now, $id]);

            if ($stmt->rowCount() > 0) {
                set_flash('success', 'Announcement #' . $id . ' has been approved and published to the public portal.');
            } else {
                set_flash('warning', 'Announcement #' . $id . ' was not found or is no longer pending.');
            }
        } catch (PDOException $e) {
            set_flash('danger', 'Database error approving announcement: ' . $e->getMessage());
        }
        header('Location: pending_requests.php');
        exit;

    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');

        if (empty($reason)) {
            set_flash('danger', 'Rejection reason is required. Please explain why the announcement was rejected.');
            header('Location: pending_requests.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET status = 'rejected',
                    rejection_reason = ?,
                    reviewed_by = ?,
                    reviewed_at = ?,
                    updated_at = ?
                WHERE id = ? AND status = 'pending'
            ");
            $now = date('Y-m-d H:i:s');
            $stmt->execute([$reason, $currentUser['id'], $now, $now, $id]);

            if ($stmt->rowCount() > 0) {
                set_flash('info', 'Announcement #' . $id . ' has been rejected. Rejection reason has been recorded.');
            } else {
                set_flash('warning', 'Announcement #' . $id . ' was not found or is no longer pending.');
            }
        } catch (PDOException $e) {
            set_flash('danger', 'Database error rejecting announcement: ' . $e->getMessage());
        }
        header('Location: pending_requests.php');
        exit;
    }
}

// Fetch pending announcements
$pending_list = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT a.*, u.name AS author_name, u.email AS author_email
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.status = 'pending'
            ORDER BY a.created_at ASC
        ");
        $pending_list = $stmt->fetchAll();

        // Attach images
        $imgStmt = $pdo->query("SELECT announcement_id, image_path FROM announcement_images ORDER BY id ASC");
        $all_images = [];
        foreach ($imgStmt->fetchAll() as $im) {
            $all_images[$im['announcement_id']][] = $im['image_path'];
        }

        foreach ($pending_list as &$pAnn) {
            $pAnn['images'] = $all_images[$pAnn['id']] ?? (!empty($pAnn['image_path']) ? [$pAnn['image_path']] : []);
        }
        unset($pAnn);

    } catch (PDOException $e) {
        set_flash('danger', 'Error loading pending announcements: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--warning); font-size: 1.25rem;"></i>
            <h2 class="card-title">Pending Announcement Requests</h2>
            <span class="card-count-badge" style="background: var(--warning-light); color: #b45309;">
                <?= count($pending_list) ?> pending
            </span>
        </div>
        <div class="card-header-right">
            <span style="font-size: 0.85rem; color: var(--text-muted);">
                Admins must review and approve or reject submissions before publication.
            </span>
        </div>
    </div>

    <div class="card-body">
        <?php if (empty($pending_list)): ?>
            <div class="empty-state">
                <div class="empty-state-icon" style="color: var(--success);">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <h3 class="empty-state-title">All Caught Up!</h3>
                <p class="empty-state-desc">There are no pending announcement requests awaiting administrative review at this time.</p>
                <div style="margin-top: 1.25rem;">
                    <a href="view_announcements.php" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-bullhorn"></i> View Approved Announcements
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Announcement Title</th>
                            <th>Category</th>
                            <th>Author (Announcer)</th>
                            <th>Submitted Date</th>
                            <th style="text-align: right; min-width: 220px;">Review Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_list as $ann): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                        <?= h($ann['title']) ?>
                                    </div>
                                    <?php if (!empty($ann['is_urgent'])): ?>
                                        <span class="badge" style="background: #fee2e2; color: #b91c1c; font-size: 0.68rem; margin-top: 0.2rem;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Flagged as Urgent
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="category-pill"><?= h($ann['category'] ?? 'General') ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= h($ann['author_name'] ?? 'Announcer') ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?= h($ann['author_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span title="<?= h(format_datetime($ann['created_at'])) ?>">
                                        <?= h(time_ago($ann['created_at'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <!-- View Detail Button -->
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openViewModal(<?= h(json_encode($ann)) ?>)">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>

                                        <!-- Approve Button (Opens Confirmation Modal) -->
                                        <button type="button" class="btn btn-success btn-sm" onclick="openApproveModal(<?= (int)$ann['id'] ?>, <?= h(json_encode($ann['title'])) ?>)">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>

                                        <!-- Reject Button (Opens Reason Modal) -->
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal(<?= (int)$ann['id'] ?>, <?= h(json_encode($ann['title'])) ?>)">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 1. View Announcement Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Review Announcement Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem;">
                <span class="category-pill" id="viewModalCat">Category</span>
                <span class="badge badge-status-pending"><i class="fa-solid fa-clock"></i> Pending Review</span>
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: auto;" id="viewModalDate"></span>
            </div>

            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem;" id="viewModalTitle"></h2>

            <!-- Instagram-Style Carousel Container -->
            <div id="viewModalCarouselContainer"></div>

            <div style="background: #f8fafc; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                <div><strong>Submitted By:</strong> <span id="viewModalAuthor"></span></div>
            </div>

            <div class="form-group">
                <label class="form-label">Announcement Content</label>
                <div style="background: #ffffff; border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.95rem; line-height: 1.65; white-space: pre-wrap;" id="viewModalContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('viewModal')">Close</button>
            <button type="button" class="btn btn-success" id="viewModalApproveBtn">
                <i class="fa-solid fa-check"></i> Approve
            </button>
            <button type="button" class="btn btn-danger" id="viewModalRejectBtn">
                <i class="fa-solid fa-xmark"></i> Reject
            </button>
        </div>
    </div>
</div>

<!-- 2. Approve Confirmation Modal -->
<div class="modal-overlay" id="approveModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <form method="POST" action="pending_requests.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" id="approveId">

            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--success);">
                    <i class="fa-solid fa-circle-check"></i> Confirm Approval
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('approveModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 1rem;">
                    Are you sure you want to approve and publish the following announcement?
                </p>
                <div style="padding: 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); font-weight: 600; color: #166534;" id="approveTitleDisplay">
                </div>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 1rem;">
                    <i class="fa-solid fa-info-circle"></i> Once approved, this announcement will become immediately visible to students and faculty on the public bulletin board.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-check"></i> Yes, Approve & Publish
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Reject Announcement Modal (with reason) -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-dialog" style="max-width: 520px;">
        <form method="POST" action="pending_requests.php" id="rejectForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="rejectId">

            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger);">
                    <i class="fa-solid fa-triangle-exclamation"></i> Reject Announcement
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 0.75rem;">
                    Rejecting announcement:
                </p>
                <div style="padding: 0.75rem 1rem; background: #fff1f2; border: 1px solid #fecdd3; border-radius: var(--radius-md); font-weight: 600; color: #9f1239; margin-bottom: 1.25rem;" id="rejectTitleDisplay">
                </div>

                <div class="form-group">
                    <label for="rejection_reason" class="form-label">
                        Rejection Reason <span class="required">*</span>
                    </label>
                    <textarea 
                        name="rejection_reason" 
                        id="rejection_reason" 
                        class="form-textarea" 
                        rows="4" 
                        placeholder="Please provide clear and specific feedback on why this announcement cannot be approved (e.g. guidelines violation, inappropriate content, missing details)..." 
                        required></textarea>
                    <div class="form-hint">
                        This reason is recorded and displayed to the announcer in the Rejected Announcements log.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-ban"></i> Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentAnnData = null;

function openViewModal(ann) {
    currentAnnData = ann;
    document.getElementById('viewModalTitle').textContent = ann.title || '';
    document.getElementById('viewModalCat').textContent = ann.category || 'General';
    document.getElementById('viewModalDate').textContent = 'Submitted: ' + (ann.created_at || '');
    document.getElementById('viewModalAuthor').textContent = (ann.author_name || 'Announcer') + (ann.author_email ? ' (' + ann.author_email + ')' : '');
    document.getElementById('viewModalContent').textContent = ann.content || ann.summary || '';

    // Render Instagram-Style Carousel
    window.renderInstagramCarousel(ann.images || [], 'viewModalCarouselContainer', '../');

    // Wire up footer buttons inside view modal
    document.getElementById('viewModalApproveBtn').onclick = function () {
        closeModal('viewModal');
        openApproveModal(ann.id, ann.title);
    };

    document.getElementById('viewModalRejectBtn').onclick = function () {
        closeModal('viewModal');
        openRejectModal(ann.id, ann.title);
    };

    openModal('viewModal');
}

function openApproveModal(id, title) {
    document.getElementById('approveId').value = id;
    document.getElementById('approveTitleDisplay').textContent = title;
    openModal('approveModal');
}

function openRejectModal(id, title) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectTitleDisplay').textContent = title;
    document.getElementById('rejection_reason').value = '';
    openModal('rejectModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

