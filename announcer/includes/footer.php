<?php
/**
 * Announcer Layout Footer
 */
if (!defined('APP_INIT')) {
    exit;
}
?>
        </main>

        <!-- Announcer Global Footer -->
        <footer class="admin-footer">
            <div class="footer-left">
                &copy; <?= date('Y') ?> International State College of the Philippines. Announcement Submission Portal.
            </div>
            <div class="footer-right">
                <span class="db-badge">Role: <?= h(format_role_label(current_user()['role'] ?? 'announcer')) ?></span>
            </div>
        </footer>
    </div>
</div>

<!-- JavaScript Bundle -->
<script src="../assets/js/admin.js"></script>
</body>
</html>

