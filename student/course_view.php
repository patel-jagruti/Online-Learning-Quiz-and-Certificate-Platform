<?php
require_once __DIR__.'/../config/config.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];
$courseId = (int)($_GET['id'] ?? 0);

if (!$courseId) redirect(APP_URL.'/student/courses.php');

// Fetch course
$stmt = $db->prepare("
    SELECT c.*, u.name as instructor_name, u.bio as instructor_bio, u.avatar as instructor_avatar,
           cat.name as category_name
    FROM courses c
    JOIN users u ON u.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE c.id = ? AND c.status = 'published'
");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) { flash('error','Course not found.'); redirect(APP_URL.'/student/courses.php'); }

// Check enrollment
if (!isEnrolled($userId, $courseId)) {
    flash('error','You need to enroll in this course first.');
    redirect(APP_URL.'/student/courses.php');
}

// Enrollment record
$enrollStmt = $db->prepare("SELECT * FROM enrollments WHERE student_id=? AND course_id=?");
$enrollStmt->execute([$userId, $courseId]);
$enrollment = $enrollStmt->fetch();

// Lessons
$lessonsStmt = $db->prepare("
    SELECT l.*,
           (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.lesson_id=l.id AND lp.student_id=?) as is_completed
    FROM lessons l
    WHERE l.course_id = ?
    ORDER BY l.sort_order, l.id
");
$lessonsStmt->execute([$userId, $courseId]);
$lessons = $lessonsStmt->fetchAll();
$totalLessons = count($lessons);
$completedLessons = array_sum(array_column($lessons, 'is_completed'));

// Quizzes for this course
$quizzesStmt = $db->prepare("
    SELECT q.*,
           (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=? AND qa.status='completed') as attempts_done,
           (SELECT score FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=? AND qa.status='completed' ORDER BY qa.submitted_at DESC LIMIT 1) as best_score
    FROM quizzes q
    WHERE q.course_id = ?
    ORDER BY q.created_at
");
$quizzesStmt->execute([$userId, $userId, $courseId]);
$quizzes = $quizzesStmt->fetchAll();

// Certificate
$certStmt = $db->prepare("SELECT * FROM certificates WHERE student_id=? AND course_id=?");
$certStmt->execute([$userId, $courseId]);
$certificate = $certStmt->fetch();

// Has the student passed at least one quiz in this course?
$passedQuizCount = (int)(dbRow("
    SELECT COUNT(*) AS c
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    WHERE qa.student_id = ? AND q.course_id = ?
      AND qa.status = 'completed' AND qa.score >= q.pass_score
", [$userId, $courseId])['c'] ?? 0);

$pageTitle = htmlspecialchars($course['title']);
require_once __DIR__.'/../includes/header.php';

// Convert YouTube URL to embed
function getYouTubeEmbed($url) {
    if (empty($url)) return '';
    preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
    if (isset($m[1])) return 'https://www.youtube.com/embed/'.$m[1];
    return $url;
}
?>

<div class="d-flex" style="min-height:100vh;background:#f4f6fb;">
    <?php require_once __DIR__.'/includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <!-- Breadcrumb -->
        <nav class="mb-3"><ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/student/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/student/courses.php?filter=enrolled">My Courses</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($course['title']) ?></li>
        </ol></nav>

        <?php $msg = flash('success'); if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <!-- Course Header -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="row g-0">
                <?php if ($course['thumbnail']): ?>
                <div class="col-md-4">
                    <img src="<?= APP_URL ?>/uploads/thumbnails/<?= htmlspecialchars($course['thumbnail']) ?>" class="img-fluid h-100" style="object-fit:cover;max-height:280px;">
                </div>
                <?php endif; ?>
                <div class="<?= $course['thumbnail'] ? 'col-md-8' : 'col-12' ?>">
                    <div class="card-body p-4">
                        <?php if ($course['category_name']): ?>
                            <span class="badge bg-primary mb-2"><?= htmlspecialchars($course['category_name']) ?></span>
                        <?php endif; ?>
                        <h2 class="fw-bold mb-2"><?= htmlspecialchars($course['title']) ?></h2>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <?php if ($course['instructor_avatar']): ?>
                                <img src="<?= APP_URL ?>/uploads/avatars/<?= htmlspecialchars($course['instructor_avatar']) ?>" class="rounded-circle" width="32" height="32" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px;font-size:.85rem;"><?= strtoupper(substr($course['instructor_name'],0,1)) ?></div>
                            <?php endif; ?>
                            <span class="text-muted"><?= htmlspecialchars($course['instructor_name']) ?></span>
                        </div>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($course['description'])) ?></p>

                        <!-- Progress -->
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">Your Progress</span>
                            <span><?= $completedLessons ?>/<?= $totalLessons ?> lessons &mdash; <?= $enrollment['progress_percent'] ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height:10px;">
                            <div class="progress-bar bg-success" style="width:<?= $enrollment['progress_percent'] ?>%"></div>
                        </div>

                        <?php if ($certificate): ?>
                        <a href="<?= APP_URL ?>/student/certificate.php?course_id=<?= $courseId ?>" class="btn btn-warning fw-semibold">
                            <i class="bi bi-award me-1"></i> Download Certificate
                        </a>
                        <?php elseif ($enrollment['progress_percent'] >= 100 && $passedQuizCount > 0): ?>
                        <a href="<?= APP_URL ?>/student/certificate.php?course_id=<?= $courseId ?>" class="btn btn-warning fw-semibold">
                            <i class="bi bi-award me-1"></i> Claim Certificate
                        </a>
                        <?php elseif ($enrollment['progress_percent'] >= 100 && !empty($quizzes)): ?>
                        <div class="alert alert-success py-2 mb-2">
                            <i class="bi bi-check-circle me-1"></i> Course complete! Pass the quiz to earn your certificate.
                        </div>
                        <a href="<?= APP_URL ?>/student/quiz.php?id=<?= (int)$quizzes[0]['id'] ?>" class="btn btn-warning fw-semibold">
                            <i class="bi bi-patch-question me-1"></i> Take the Quiz
                        </a>
                        <?php elseif ($enrollment['progress_percent'] >= 100): ?>
                        <div class="alert alert-info py-2 mb-0">Pass at least one quiz to earn your certificate.</div>
                        <?php else: ?>
                        <div class="alert alert-light border py-2 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i> Complete all lessons and pass a quiz to unlock your certificate.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Lessons Accordion -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-collection-play me-2 text-primary"></i>Lessons</h5>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($lessons)): ?>
                            <p class="text-muted text-center py-3">No lessons available yet.</p>
                        <?php else: ?>
                        <div class="accordion" id="lessonsAccordion">
                            <?php foreach ($lessons as $i => $lesson): ?>
                            <div class="accordion-item border mb-2 rounded-3 overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#lesson<?= $lesson['id'] ?>">
                                        <span class="me-3">
                                            <?php if ($lesson['is_completed']): ?>
                                                <i class="bi bi-check-circle-fill text-success"></i>
                                            <?php else: ?>
                                                <i class="bi bi-circle text-muted"></i>
                                            <?php endif; ?>
                                        </span>
                                        <?= htmlspecialchars($lesson['title']) ?>
                                        <?php if ($lesson['duration_minutes']): ?>
                                            <span class="ms-auto me-3 badge bg-light text-muted small"><?= $lesson['duration_minutes'] ?> min</span>
                                        <?php endif; ?>
                                    </button>
                                </h2>
                                <div id="lesson<?= $lesson['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>">
                                    <div class="accordion-body">
                                        <?php if ($lesson['video_url']): ?>
                                        <div class="ratio ratio-16x9 mb-3 rounded-3 overflow-hidden">
                                            <iframe src="<?= getYouTubeEmbed($lesson['video_url']) ?>" allowfullscreen loading="lazy"></iframe>
                                        </div>
                                        <?php endif; ?>
                                        <div class="lesson-content mb-3"><?= $lesson['content'] ?></div>
                                        <?php if (!$lesson['is_completed']): ?>
                                        <button class="btn btn-success btn-sm mark-complete-btn" data-lesson-id="<?= $lesson['id'] ?>" data-course-id="<?= $courseId ?>">
                                            <i class="bi bi-check2-circle me-1"></i>Mark as Complete
                                        </button>
                                        <?php else: ?>
                                        <span class="badge bg-success py-2 px-3"><i class="bi bi-check-circle me-1"></i>Completed</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quizzes & Info Sidebar -->
            <div class="col-lg-4">
                <!-- Quizzes -->
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-patch-question me-2 text-warning"></i>Course Quizzes</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($quizzes)): ?>
                            <p class="text-muted small text-center">No quizzes for this course.</p>
                        <?php else: ?>
                        <?php foreach ($quizzes as $q): ?>
                        <div class="border rounded-3 p-3 mb-2">
                            <div class="fw-semibold mb-1"><?= htmlspecialchars($q['title']) ?></div>
                            <div class="text-muted small mb-2">
                                <i class="bi bi-clock me-1"></i><?= $q['time_limit_minutes'] ? $q['time_limit_minutes'].' min' : 'No limit' ?>
                                &bull; Pass: <?= $q['pass_score'] ?>%
                                &bull; <?= $q['attempts_done'] ?>/<?= $q['max_attempts'] ?> attempts
                            </div>
                            <?php if (!is_null($q['best_score'])): ?>
                                <div class="mb-2">
                                    <span class="badge <?= $q['best_score'] >= $q['pass_score'] ? 'bg-success' : 'bg-danger' ?>">
                                        Best: <?= number_format($q['best_score'],1) ?>%
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($q['attempts_done'] < $q['max_attempts']): ?>
                                <a href="<?= APP_URL ?>/student/quiz.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-warning w-100">
                                    <i class="bi bi-play-fill me-1"></i><?= $q['attempts_done'] > 0 ? 'Retake Quiz' : 'Take Quiz' ?>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary w-100" disabled>Max Attempts Reached</button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Stats -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-3">Course Info</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Total Lessons</span><strong><?= $totalLessons ?></strong></li>
                            <li class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Completed</span><strong><?= $completedLessons ?></strong></li>
                            <li class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Quizzes</span><strong><?= count($quizzes) ?></strong></li>
                            <li class="d-flex justify-content-between py-1"><span class="text-muted">Enrolled Since</span><strong><?= date('M d, Y', strtotime($enrollment['enrolled_at'])) ?></strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.mark-complete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const lessonId = this.dataset.lessonId;
        const courseId = this.dataset.courseId;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Marking...';

        fetch('<?= APP_URL ?>/student/ajax/mark_lesson.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lesson_id=${lessonId}&course_id=${courseId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.closest('.accordion-body').querySelector('.mark-complete-btn')?.remove();
                // Replace button with badge
                const badge = document.createElement('span');
                badge.className = 'badge bg-success py-2 px-3';
                badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Completed';
                btn.replaceWith(badge);
                // Update progress
                const icon = btn.closest('.accordion-item').querySelector('.accordion-button i');
                if (icon) { icon.className = 'bi bi-check-circle-fill text-success'; }
                // Show toast
                showToast('Lesson marked as complete! Progress: ' + data.progress + '%');
            }
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Mark as Complete'; });
    });
});

function showToast(msg) {
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success shadow';
    toast.style.zIndex = 9999;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
