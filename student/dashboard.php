<?php
require_once __DIR__.'/../config/config.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];
$student = getUser($userId);
$stats = getUserStats($userId);

// My Courses (enrolled, with progress)
$coursesStmt = $db->prepare("
    SELECT c.*, u.name as instructor_name, cat.name as category_name,
           e.progress_percent, e.enrolled_at,
           (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) as lesson_count
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN users u ON u.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE e.student_id = ?
    ORDER BY e.enrolled_at DESC
    LIMIT 6
");
$coursesStmt->execute([$userId]);
$myCourses = $coursesStmt->fetchAll();

// Upcoming quizzes (quizzes in enrolled courses not yet taken or not completed)
$upcomingStmt = $db->prepare("
    SELECT q.*, c.title as course_title,
           (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=? AND qa.status='completed') as attempts_done
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    WHERE q.course_id IS NOT NULL
    HAVING attempts_done < q.max_attempts
    ORDER BY q.created_at DESC
    LIMIT 5
");
$upcomingStmt->execute([$userId, $userId]);
$upcomingQuizzes = $upcomingStmt->fetchAll();

// Recent activity: lesson completions + quiz attempts
$activityStmt = $db->prepare("
    (SELECT 'lesson' as type, l.title as title, lp.completed_at as activity_time, c.title as context
     FROM lesson_progress lp
     JOIN lessons l ON l.id = lp.lesson_id
     JOIN courses c ON c.id = l.course_id
     WHERE lp.student_id = ?)
    UNION
    (SELECT 'quiz' as type, q.title as title, qa.submitted_at as activity_time, q.title as context
     FROM quiz_attempts qa
     JOIN quizzes q ON q.id = qa.quiz_id
     WHERE qa.student_id = ? AND qa.status='completed')
    ORDER BY activity_time DESC
    LIMIT 8
");
$activityStmt->execute([$userId, $userId]);
$recentActivity = $activityStmt->fetchAll();

$pageTitle = 'My Dashboard';
require_once __DIR__.'/../includes/header.php';
?>

<div class="d-flex" style="min-height:100vh;background:#f4f6fb;">
    <?php require_once __DIR__.'/includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <!-- Welcome Banner -->
        <div class="rounded-4 p-4 mb-4 text-white position-relative overflow-hidden" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
            <div class="position-absolute top-0 end-0 opacity-10" style="font-size:10rem;line-height:1;user-select:none;">🎓</div>
            <h2 class="fw-bold mb-1">Hello, <?= htmlspecialchars($student['name']) ?>! 👋</h2>
            <p class="mb-3 opacity-90">Ready to learn? You're making great progress!</p>
            <a href="<?= APP_URL ?>/student/courses.php" class="btn btn-warning fw-semibold px-4">
                <i class="bi bi-search me-1"></i> Explore Courses
            </a>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 bg-primary bg-opacity-10">
                            <i class="bi bi-journals text-primary fs-3"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold text-primary"><?= $stats['courses_enrolled'] ?></div>
                            <div class="text-muted small">Courses Enrolled</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 bg-success bg-opacity-10">
                            <i class="bi bi-clipboard-check text-success fs-3"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold text-success"><?= $stats['quizzes_taken'] ?></div>
                            <div class="text-muted small">Quizzes Taken</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 bg-warning bg-opacity-10">
                            <i class="bi bi-graph-up text-warning fs-3"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold text-warning"><?= number_format($stats['avg_score'],1) ?>%</div>
                            <div class="text-muted small">Avg Score</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 bg-info bg-opacity-10">
                            <i class="bi bi-award text-info fs-3"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold text-info"><?= $stats['certificates'] ?></div>
                            <div class="text-muted small">Certificates Earned</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- My Courses -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-journals me-2 text-primary"></i>My Courses</h5>
                        <a href="<?= APP_URL ?>/student/courses.php?filter=enrolled" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($myCourses)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-journals fs-1 d-block mb-2 opacity-25"></i>
                                You haven't enrolled in any courses yet.
                                <div class="mt-2"><a href="<?= APP_URL ?>/student/courses.php" class="btn btn-primary btn-sm">Browse Courses</a></div>
                            </div>
                        <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($myCourses as $course): ?>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100 hover-shadow" style="transition:.2s;">
                                    <div class="d-flex gap-2 align-items-start">
                                        <?php if ($course['thumbnail']): ?>
                                            <img src="<?= APP_URL ?>/uploads/thumbnails/<?= htmlspecialchars($course['thumbnail']) ?>" class="rounded-2" width="56" height="56" style="object-fit:cover;">
                                        <?php else: ?>
                                            <div class="rounded-2 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                                <i class="bi bi-book text-primary fs-4"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fw-semibold text-truncate"><?= htmlspecialchars($course['title']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($course['instructor_name']) ?></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">Progress</span>
                                            <span class="fw-semibold"><?= $course['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-success" style="width:<?= $course['progress_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <a href="<?= APP_URL ?>/student/course_view.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                                            <?= $course['progress_percent'] > 0 ? 'Continue' : 'Start' ?> Course
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Quizzes -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-warning"></i>Upcoming Quizzes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingQuizzes)): ?>
                            <p class="text-muted text-center py-3">No quizzes pending. Enroll in more courses!</p>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcomingQuizzes as $q): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($q['title']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($q['course_title']) ?> &bull;
                                        <?= $q['time_limit_minutes'] ? $q['time_limit_minutes'].' min' : 'No time limit' ?> &bull;
                                        <?= $q['attempts_done'] ?>/<?= $q['max_attempts'] ?> attempts
                                    </div>
                                </div>
                                <a href="<?= APP_URL ?>/student/quiz.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-warning fw-semibold">
                                    <i class="bi bi-play-fill me-1"></i>Take Quiz
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-activity me-2 text-success"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivity)): ?>
                            <div class="text-center text-muted py-4 px-3">
                                <i class="bi bi-activity fs-1 d-block mb-2 opacity-25"></i>
                                No activity yet. Start a course!
                            </div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentActivity as $act): ?>
                            <li class="list-group-item px-3 py-2 d-flex gap-2 align-items-start">
                                <span class="mt-1">
                                    <?php if ($act['type']==='lesson'): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill"><i class="bi bi-check2"></i></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning rounded-pill"><i class="bi bi-patch-question"></i></span>
                                    <?php endif; ?>
                                </span>
                                <div class="flex-grow-1">
                                    <div class="small fw-semibold text-truncate" style="max-width:160px;"><?= htmlspecialchars($act['title']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        <?= $act['type']==='lesson' ? 'Lesson completed' : 'Quiz taken' ?>
                                        &bull; <?= $act['activity_time'] ? timeAgo($act['activity_time']) : '' ?>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
