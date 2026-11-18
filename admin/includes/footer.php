<?php
/**
 * Admin Layout Footer
 */
if (!defined('APP_INIT')) {
    exit;
}
?>
        </main>

        <!-- Admin Global Footer -->
        <footer class="admin-footer">
            <div class="footer-left">
                &copy; <?= date('Y') ?> International State College of the Philippines. School Announcement System.
            </div>
            <div class="footer-right">
                <span class="db-badge">Database: <?= strtoupper(get_db_driver()) ?></span>
                <span class="security-status"><i class="fa-solid fa-shield-halved"></i> Role: Admin</span>
            </div>
        </footer>
    </div>
</div>

<!-- Admin JavaScript Bundle -->
<script src="../assets/js/admin.js"></script>
</body>
</html>

