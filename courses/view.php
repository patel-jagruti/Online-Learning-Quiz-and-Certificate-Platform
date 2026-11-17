<?php
/**
 * Public course detail page with free enrollment.
 */
require_once __DIR__ . '/../config/config.php';

$db   = getDB();
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    redirect(APP_URL . '/courses.php');
}

$stmt = $db->prepare("
    SELECT c.*, u.name AS instructor_name, u.bio AS instructor_bio, u.avatar AS instructor_avatar,
           cat.name AS category_name
    FROM courses c
    JOIN users u ON u.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE c.slug = ? AND c.status = 'published'
    LIMIT 1
");
$stmt->execute([$slug]);
$course = $stmt->fetch();

if (!$course) {
    flash('error', 'Course not found.');
    redirect(APP_URL . '/courses.php');
}

$courseId = (int)$course['id'];

$lessons = $db->prepare("SELECT id, title, duration_minutes, sort_order FROM lessons WHERE course_id = ? ORDER BY sort_order, id");
$lessons->execute([$courseId]);
$lessons = $lessons->fetchAll();

$quizCount = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE course_id = ?");
$quizCount->execute([$courseId]);
$quizCount = (int)$quizCount->fetchColumn();

$enrollStmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
$enrollStmt->execute([$courseId]);
$enrolledCount = (int)$enrollStmt->fetchColumn();

$isEnrolled = isLoggedIn() && isEnrolled((int)$_SESSION['user_id'], $courseId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    if (!isLoggedIn()) {
        flash('error', 'Please log in to enroll in this course.');
        redirect(APP_URL . '/auth/login.php?next=' . urlencode('/courses/view.php?slug=' . $course['slug']));
    }
    if (!hasRole('student')) {
        flash('error', 'Only students can enroll in courses.');
        redirect(APP_URL . '/courses/view.php?slug=' . urlencode($course['slug']));
    }
    if (!isEnrolled((int)$_SESSION['user_id'], $courseId)) {
        dbExec(
            "INSERT INTO enrollments (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())",
            [(int)$_SESSION['user_id'], $courseId]
        );
        sendNotification((int)$_SESSION['user_id'], 'You enrolled in "' . $course['title'] . '".', 'success');
        flash('success', 'Successfully enrolled! Start learning now.');
    }
    redirect(APP_URL . '/student/course_view.php?id=' . $courseId);
}

$pageTitle = $course['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/courses.php">Courses</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($course['title']) ?></li>
            </ol>
        </nav>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="row g-0">
                <div class="col-lg-5">
                    <?php if (!empty($course['thumbnail'])): ?>
                        <img src="<?= UPLOAD_URL ?>/thumbnails/<?= htmlspecialchars($course['thumbnail']) ?>"
                             alt="<?= htmlspecialchars($course['title']) ?>"
                             class="w-100 h-100" style="object-fit:cover;min-height:260px;">
                    <?php else: ?>
                        <div class="h-100 d-flex align-items-center justify-content-center"
                             style="min-height:260px;background:linear-gradient(135deg,#667eea,#764ba2);">
                            <i class="fas fa-graduation-cap fa-4x text-white opacity-75"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-7">
                    <div class="card-body p-4 p-lg-5">
                        <?php if (!empty($course['category_name'])): ?>
                        <span class="badge bg-primary mb-2"><?= htmlspecialchars($course['category_name']) ?></span>
                        <?php endif; ?>
                        <h1 class="fw-bold mb-3"><?= htmlspecialchars($course['title']) ?></h1>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($course['description'] ?? '')) ?></p>

                        <div class="d-flex flex-wrap gap-4 small text-muted my-3">
                            <span><i class="fas fa-user-tie me-1"></i><?= htmlspecialchars($course['instructor_name']) ?></span>
                            <span><i class="fas fa-play-circle me-1"></i><?= count($lessons) ?> lessons</span>
                            <span><i class="fas fa-question-circle me-1"></i><?= $quizCount ?> quizzes</span>
                            <span><i class="fas fa-award me-1"></i>Certificate on completion</span>
                            <span><i class="fas fa-users me-1"></i><?= number_format($enrolledCount) ?> enrolled</span>
                        </div>

                        <h3 class="fw-bold text-primary mb-3">
                            <?= (float)$course['price'] > 0 ? getSetting('currency_symbol', '$') . number_format((float)$course['price'], 2) : 'Free' ?>
                        </h3>

                        <?php if ($isEnrolled): ?>
                            <a href="<?= APP_URL ?>/student/course_view.php?id=<?= $courseId ?>" class="btn btn-success btn-lg px-4">
                                <i class="fas fa-play me-2"></i>Continue Learning
                            </a>
                        <?php elseif (isLoggedIn() && hasRole('student')): ?>
                            <form method="POST">
                                <button type="submit" name="enroll" value="1" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-plus-circle me-2"></i>Enroll Now
                                </button>
                            </form>
                        <?php elseif (isLoggedIn()): ?>
                            <a href="<?= match ($_SESSION['user_role'] ?? '') {
                                'admin'      => APP_URL . '/admin/dashboard.php',
                                'instructor' => APP_URL . '/instructor/dashboard.php',
                                default      => APP_URL . '/student/dashboard.php',
                            } ?>" class="btn btn-outline-primary btn-lg px-4">
                                Go to Dashboard
                            </a>
                        <?php else: ?>
                            <a href="<?= APP_URL ?>/auth/login.php?next=<?= urlencode('/courses/view.php?slug=' . $course['slug']) ?>"
                               class="btn btn-primary btn-lg px-4">
                                <i class="fas fa-right-to-bracket me-2"></i>Log in to Enroll
                            </a>
                            <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-outline-primary btn-lg px-4 ms-2">
                                Register Free
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-list-ol me-2 text-primary"></i>Course Curriculum</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($lessons)): ?>
                            <p class="text-muted text-center py-4 mb-0">Lessons will be published soon.</p>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($lessons as $i => $lesson): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3">
                                <span>
                                    <span class="badge bg-light text-muted me-2"><?= $i + 1 ?></span>
                                    <?= htmlspecialchars($lesson['title']) ?>
                                </span>
                                <?php if ((int)$lesson['duration_minutes'] > 0): ?>
                                <small class="text-muted"><i class="fas fa-clock me-1"></i><?= (int)$lesson['duration_minutes'] ?> min</small>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Your Instructor</h6>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <?php if (!empty($course['instructor_avatar'])): ?>
                                <img src="<?= UPLOAD_URL ?>/avatars/<?= htmlspecialchars($course['instructor_avatar']) ?>"
                                     class="rounded-circle" width="56" height="56" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                     style="width:56px;height:56px;font-size:1.3rem;">
                                    <?= strtoupper(substr($course['instructor_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($course['instructor_name']) ?></div>
                                <small class="text-muted">Instructor</small>
                            </div>
                        </div>
                        <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($course['instructor_bio'] ?? 'No biography provided yet.')) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
