<?php
/**
 * QuizMaster LMS - Responsive Navigation Bar
 */
$unreadCount = 0;
if (isLoggedIn()) {
    $notifRow = dbRow(
        'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
        [(int)$_SESSION['user_id']]
    );
    $unreadCount = (int)($notifRow['cnt'] ?? 0);
    $notifications = dbRows(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6',
        [(int)$_SESSION['user_id']]
    );
    $authUser = currentUser();
}

$dashboardUrl = match ($_SESSION['user_role'] ?? '') {
    'admin'      => APP_URL . '/admin/dashboard.php',
    'instructor' => APP_URL . '/instructor/dashboard.php',
    default      => APP_URL . '/student/dashboard.php',
};

$currentPath = $_SERVER['PHP_SELF'] ?? '';
function navActive(string $segment): string {
    global $currentPath;
    return str_contains($currentPath, $segment) ? 'active' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm" id="mainNav">
  <div class="container-fluid px-4">

    <!-- Brand -->
    <a class="navbar-brand fw-bold fs-5 d-flex align-items-center gap-2" href="<?= APP_URL ?>/">
      <span class="brand-icon">🎓</span>
      <span class="brand-text"><?= APP_NAME ?></span>
    </a>

    <!-- Toggler -->
    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#navbarMain"
            aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">

      <!-- Left Links -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= navActive('/index') ?>" href="<?= APP_URL ?>/">
            <i class="fa fa-home me-1"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= navActive('/courses') ?>" href="<?= APP_URL ?>/courses/">
            <i class="fa fa-book-open me-1"></i>Courses
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= navActive('/quiz') ?>" href="<?= APP_URL ?>/quiz/">
            <i class="fa fa-clipboard-question me-1"></i>Quizzes
          </a>
        </li>

        <?php if (isLoggedIn() && hasRole(['admin', 'instructor'])): ?>
        <!-- Admin / Instructor extras -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= navActive('/admin') || navActive('/instructor') ? 'active' : '' ?>"
             href="#" id="panelDropdown" role="button"
             data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa fa-layer-group me-1"></i>Panel
          </a>
          <ul class="dropdown-menu dropdown-menu-dark shadow" aria-labelledby="panelDropdown">
            <?php if (hasRole('admin')): ?>
            <li>
              <a class="dropdown-item" href="<?= APP_URL ?>/admin/dashboard.php">
                <i class="fa fa-shield-halved me-2 text-warning"></i>Admin Dashboard
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= APP_URL ?>/admin/users.php">
                <i class="fa fa-users me-2"></i>Manage Users
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= APP_URL ?>/admin/courses.php">
                <i class="fa fa-graduation-cap me-2"></i>Manage Courses
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= APP_URL ?>/admin/reports.php">
                <i class="fa fa-chart-bar me-2"></i>Reports
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <?php if (hasRole(['admin','instructor'])): ?>
            <li>
              <a class="dropdown-item" href="<?= APP_URL ?>/instructor/dashboard.php">
                <i class="fa fa-chalkboard-teacher me-2 text-info"></i>Instructor Panel
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= APP_URL ?>/instructor/create_course.php">
                <i class="fa fa-plus-circle me-2"></i>New Course
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= APP_URL ?>/instructor/create_quiz.php">
                <i class="fa fa-plus-circle me-2"></i>New Quiz
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Right side -->
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-1">

        <?php if (isLoggedIn()): ?>

          <!-- Notifications -->
          <li class="nav-item dropdown">
            <a class="nav-link position-relative px-3" href="#"
               id="notifDropdown" role="button"
               data-bs-toggle="dropdown" aria-expanded="false"
               title="Notifications">
              <i class="fa fa-bell fs-5"></i>
              <?php if ($unreadCount > 0): ?>
              <span class="position-absolute top-0 start-75 translate-middle badge rounded-pill bg-danger">
                <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
              </span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow notif-dropdown"
                aria-labelledby="notifDropdown" style="width:320px;max-height:380px;overflow-y:auto">
              <li class="px-3 py-2 d-flex justify-content-between align-items-center border-bottom border-secondary">
                <span class="fw-semibold">Notifications</span>
                <?php if ($unreadCount > 0): ?>
                <a href="<?= APP_URL ?>/student/notifications.php?mark_all=1"
                   class="small text-info text-decoration-none">Mark all read</a>
                <?php endif; ?>
              </li>
              <?php if (empty($notifications)): ?>
              <li class="px-3 py-3 text-center text-muted small">No notifications</li>
              <?php else: ?>
              <?php foreach ($notifications as $n): ?>
              <li>
                <a class="dropdown-item py-2 d-flex gap-2 align-items-start <?= $n['is_read'] ? 'opacity-75' : 'fw-semibold' ?>"
                   href="<?= APP_URL ?>/student/notifications.php?read=<?= $n['id'] ?>">
                  <span class="notif-icon mt-1 text-<?= $n['type'] === 'success' ? 'success' : ($n['type'] === 'warning' ? 'warning' : 'info') ?>">
                    <i class="fa fa-circle-dot fa-xs"></i>
                  </span>
                  <span>
                    <span class="d-block small"><?= truncate(sanitize($n['message']), 60) ?></span>
                    <span class="text-muted" style="font-size:.7rem"><?= timeAgo($n['created_at']) ?></span>
                  </span>
                </a>
              </li>
              <?php endforeach; ?>
              <li><hr class="dropdown-divider my-0"></li>
              <li>
                <a class="dropdown-item text-center small py-2"
                   href="<?= APP_URL ?>/student/notifications.php">View all</a>
              </li>
              <?php endif; ?>
            </ul>
          </li>

          <!-- User Dropdown -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 px-2"
               href="#" id="userDropdown" role="button"
               data-bs-toggle="dropdown" aria-expanded="false">
              <img src="<?= avatarUrl($authUser ?? []) ?>"
                   alt="avatar" class="rounded-circle border border-secondary"
                   width="32" height="32" style="object-fit:cover">
              <span class="d-none d-lg-inline text-truncate" style="max-width:120px">
                <?= sanitize($authUser['name'] ?? 'User') ?>
              </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow"
                aria-labelledby="userDropdown">
              <li class="px-3 py-2 border-bottom border-secondary">
                <small class="text-muted">Signed in as</small>
                <div class="fw-semibold"><?= sanitize($authUser['name'] ?? '') ?></div>
                <small class="text-info"><?= ucfirst($authUser['role'] ?? '') ?></small>
              </li>
              <li>
                <a class="dropdown-item" href="<?= $dashboardUrl ?>">
                  <i class="fa fa-gauge me-2"></i>Dashboard
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="<?= APP_URL ?>/student/profile.php">
                  <i class="fa fa-user-circle me-2"></i>My Profile
                </a>
              </li>
              <?php if (($authUser['role'] ?? '') === 'student'): ?>
              <li>
                <a class="dropdown-item" href="<?= APP_URL ?>/student/my_courses.php">
                  <i class="fa fa-book me-2"></i>My Courses
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="<?= APP_URL ?>/student/certificates.php">
                  <i class="fa fa-certificate me-2"></i>Certificates
                </a>
              </li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php">
                  <i class="fa fa-right-from-bracket me-2"></i>Log Out
                </a>
              </li>
            </ul>
          </li>

        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= APP_URL ?>/auth/login.php">
              <i class="fa fa-right-to-bracket me-1"></i>Login
            </a>
          </li>
          <li class="nav-item">
            <a class="btn btn-primary btn-sm px-3 ms-1" href="<?= APP_URL ?>/auth/register.php">
              <i class="fa fa-user-plus me-1"></i>Register
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div><!-- /.collapse -->
  </div><!-- /.container-fluid -->
</nav>
