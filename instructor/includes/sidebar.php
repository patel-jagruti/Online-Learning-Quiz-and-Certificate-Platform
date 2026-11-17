<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function isSidebarActive(string $file): string {
    global $currentPage;
    return $currentPage === $file ? 'active' : '';
}
?>
<div class="sidebar" id="instructorSidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-graduation-cap text-warning"></i>
            <span>Instructor Panel</span>
        </div>
    </div>

    <?php
    $instructor = getUser($_SESSION['user_id']);
    ?>
    <div class="sidebar-user">
        <div class="sidebar-avatar">
            <?php if (!empty($instructor['avatar']) && file_exists(UPLOAD_PATH . '/avatars/' . $instructor['avatar'])): ?>
                <img src="<?= UPLOAD_URL ?>/avatars/<?= htmlspecialchars($instructor['avatar']) ?>" alt="Avatar" class="rounded-circle" width="40" height="40">
            <?php else: ?>
                <div class="avatar-placeholder rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center" style="width:40px;height:40px;font-weight:700;">
                    <?= strtoupper(substr($instructor['name'] ?? 'I', 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="sidebar-user-info ms-2">
            <div class="sidebar-username fw-bold text-white small"><?= htmlspecialchars($instructor['name'] ?? 'Instructor') ?></div>
            <div class="sidebar-role text-warning" style="font-size:0.72rem;">Instructor</div>
        </div>
    </div>

    <ul class="sidebar-nav list-unstyled mt-2">
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/instructor/dashboard.php" class="sidebar-link <?= isSidebarActive('dashboard.php') ?>">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
        </li>
        <li class="sidebar-heading small text-muted px-3 py-2 text-uppercase">Courses</li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/instructor/courses.php" class="sidebar-link <?= isSidebarActive('courses.php') ?>">
                <i class="fas fa-book-open"></i> <span>My Courses</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/instructor/create_course.php" class="sidebar-link <?= isSidebarActive('create_course.php') ?>">
                <i class="fas fa-plus-circle"></i> <span>Create Course</span>
            </a>
        </li>
        <li class="sidebar-heading small text-muted px-3 py-2 text-uppercase">Quizzes</li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/instructor/quizzes.php" class="sidebar-link <?= isSidebarActive('quizzes.php') ?>">
                <i class="fas fa-question-circle"></i> <span>My Quizzes</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/instructor/create_quiz.php" class="sidebar-link <?= isSidebarActive('create_quiz.php') ?>">
                <i class="fas fa-file-alt"></i> <span>Create Quiz</span>
            </a>
        </li>
        <li class="sidebar-heading small text-muted px-3 py-2 text-uppercase">Students</li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/instructor/students.php" class="sidebar-link <?= isSidebarActive('students.php') ?>">
                <i class="fas fa-users"></i> <span>My Students</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/instructor/results.php" class="sidebar-link <?= isSidebarActive('results.php') ?>">
                <i class="fas fa-chart-bar"></i> <span>Results</span>
            </a>
        </li>
        <li class="sidebar-heading small text-muted px-3 py-2 text-uppercase">Account</li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/profile.php" class="sidebar-link <?= isSidebarActive('profile.php') ?>">
                <i class="fas fa-user-cog"></i> <span>Profile</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= APP_URL ?>/auth/logout.php" class="sidebar-link text-danger">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    width: 240px;
    min-height: 100vh;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    position: fixed;
    top: 0; left: 0;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 15px rgba(0,0,0,.3);
    overflow-y: auto;
}
.sidebar-header {
    padding: 1.2rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
}
.sidebar-user {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
.sidebar-nav .sidebar-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1rem;
    color: rgba(255,255,255,.75);
    text-decoration: none;
    border-radius: 6px;
    margin: 1px 8px;
    font-size: 0.88rem;
    transition: all .2s;
}
.sidebar-nav .sidebar-link:hover,
.sidebar-nav .sidebar-link.active {
    background: rgba(234,179,8,.15);
    color: #fbbf24;
}
.sidebar-nav .sidebar-link i { width: 18px; text-align: center; }
.sidebar-heading { font-size: 0.65rem !important; letter-spacing: .08em; }
</style>
