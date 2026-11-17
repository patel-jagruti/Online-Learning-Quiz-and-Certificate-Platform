<?php
// Student Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-student d-flex flex-column p-3 bg-dark text-white" style="min-height:100vh;width:250px;min-width:220px;">
    <a href="<?= APP_URL ?>/student/dashboard.php" class="d-flex align-items-center mb-4 text-white text-decoration-none">
        <i class="bi bi-mortarboard-fill fs-3 me-2 text-warning"></i>
        <span class="fs-5 fw-bold">QuizMaster</span>
    </a>
    <hr class="text-secondary">
    <?php
    $student = getUser($_SESSION['user_id']);
    ?>
    <div class="d-flex align-items-center mb-3">
        <?php if (!empty($student['avatar'])): ?>
            <img src="<?= APP_URL ?>/uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
        <?php else: ?>
            <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;font-weight:700;font-size:1.1rem;color:#222;">
                <?= strtoupper(substr($student['name'],0,1)) ?>
            </div>
        <?php endif; ?>
        <div>
            <div class="fw-semibold" style="font-size:.95rem;"><?= htmlspecialchars($student['name']) ?></div>
            <div class="text-warning" style="font-size:.78rem;">Student</div>
        </div>
    </div>
    <hr class="text-secondary">
    <ul class="nav nav-pills flex-column mb-auto gap-1">
        <li class="nav-item">
            <a href="<?= APP_URL ?>/student/dashboard.php" class="nav-link text-white <?= $current_page=='dashboard.php'?'active bg-warning text-dark fw-bold':'' ?>">
                <i class="bi bi-house-door me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= APP_URL ?>/student/courses.php" class="nav-link text-white <?= $current_page=='courses.php'?'active bg-warning text-dark fw-bold':'' ?>">
                <i class="bi bi-grid me-2"></i> Browse Courses
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= APP_URL ?>/student/courses.php?filter=enrolled" class="nav-link text-white <?= (isset($_GET['filter'])&&$_GET['filter']=='enrolled')?'active bg-warning text-dark fw-bold':'' ?>">
                <i class="bi bi-journals me-2"></i> My Courses
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= APP_URL ?>/student/result.php" class="nav-link text-white <?= $current_page=='result.php'&&!isset($_GET['attempt'])?'active bg-warning text-dark fw-bold':'' ?>">
                <i class="bi bi-clipboard-data me-2"></i> My Results
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= APP_URL ?>/student/profile.php?tab=certificates" class="nav-link text-white <?= ($current_page=='profile.php'&&(isset($_GET['tab'])&&$_GET['tab']=='certificates'))?'active bg-warning text-dark fw-bold':'' ?>">
                <i class="bi bi-award me-2"></i> My Certificates
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= APP_URL ?>/student/profile.php" class="nav-link text-white <?= ($current_page=='profile.php'&&!isset($_GET['tab']))?'active bg-warning text-dark fw-bold':'' ?>">
                <i class="bi bi-person-circle me-2"></i> Profile
            </a>
        </li>
    </ul>
    <hr class="text-secondary">
    <a href="<?= APP_URL ?>/auth/logout.php" class="nav-link text-danger d-flex align-items-center">
        <i class="bi bi-box-arrow-right me-2"></i> Logout
    </a>
</div>
