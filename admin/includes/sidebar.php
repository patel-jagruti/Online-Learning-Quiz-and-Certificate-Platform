<?php
// admin/includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminAvatar = $_SESSION['user_avatar'] ?? '';
?>
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="fas fa-graduation-cap me-2"></i>
            <span>Admin Panel</span>
        </div>
    </div>

    <div class="sidebar-admin-info">
        <div class="admin-avatar">
            <?php if ($adminAvatar && file_exists(UPLOAD_PATH . '/avatars/' . $adminAvatar)): ?>
                <img src="<?= UPLOAD_URL ?>/avatars/<?= htmlspecialchars($adminAvatar) ?>" alt="Avatar" class="rounded-circle" width="45" height="45" style="object-fit:cover;">
            <?php else: ?>
                <div class="avatar-placeholder">
                    <i class="fas fa-user-shield"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="admin-info">
            <div class="admin-name"><?= htmlspecialchars($adminName) ?></div>
            <div class="admin-role"><span class="badge bg-danger">Administrator</span></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'courses.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/courses.php">
                    <i class="fas fa-book-open"></i>
                    <span>Courses</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'quizzes.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/quizzes.php">
                    <i class="fas fa-question-circle"></i>
                    <span>Quizzes</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item mt-3 border-top pt-3">
                <a class="nav-link text-danger" href="<?= APP_URL ?>/auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</div>

<style>
.sidebar {
    width: 260px;
    height: calc(100vh - var(--admin-nav-h, 64px));
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    color: #fff;
    position: fixed;
    top: var(--admin-nav-h, 64px);
    left: 0;
    z-index: 1000;
    overflow-y: auto;
    transition: all 0.3s ease;
    box-shadow: 4px 0 15px rgba(0,0,0,0.3);
}
.admin-body > main.main-content { padding-top: 0; padding-bottom: 0; }
.admin-body .site-footer,
.admin-body .back-to-top { display: none; }
.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar-brand {
    font-size: 1.3rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
}
.sidebar-brand i { color: #e94560; }
.sidebar-admin-info {
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.05);
}
.avatar-placeholder {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e94560, #0f3460);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
}
.admin-name { font-weight: 600; font-size: 0.9rem; }
.admin-role { margin-top: 2px; }
.sidebar-nav { padding: 15px 10px; }
.sidebar-nav .nav-link {
    color: rgba(255,255,255,0.75);
    padding: 10px 15px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.9rem;
    transition: all 0.2s;
    margin-bottom: 3px;
}
.sidebar-nav .nav-link:hover {
    color: #fff;
    background: rgba(255,255,255,0.1);
    transform: translateX(3px);
}
.sidebar-nav .nav-link.active {
    color: #fff;
    background: linear-gradient(135deg, #e94560, #c62a47);
    box-shadow: 0 4px 15px rgba(233,69,96,0.4);
}
.sidebar-nav .nav-link i { width: 20px; text-align: center; font-size: 1rem; }
.admin-main {
    margin-left: 260px;
    width: calc(100% - 260px);
    min-height: calc(100vh - var(--admin-nav-h, 64px));
    background: #f0f2f5;
}
.admin-main > .row,
.admin-main > .card,
.admin-main > .alert {
    max-width: 100%;
}
@media (max-width: 991.98px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.show { transform: translateX(0); }
    .admin-main { margin-left: 0; width: 100%; }
}
</style>

<script>
(function () {
    function syncAdminNavHeight() {
        var nav = document.getElementById('mainNav');
        if (nav) {
            document.documentElement.style.setProperty('--admin-nav-h', nav.offsetHeight + 'px');
        }
    }
    syncAdminNavHeight();
    window.addEventListener('resize', syncAdminNavHeight);
})();
</script>
