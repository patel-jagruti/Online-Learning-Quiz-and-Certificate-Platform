<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = $_SESSION['user_id'];
$db = getDB();

// Stats
$myCoursesCount = $db->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
$myCoursesCount->execute([$instructorId]);
$myCoursesCount = (int)$myCoursesCount->fetchColumn();

$totalStudents = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id)
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.instructor_id = ?
");
$totalStudents->execute([$instructorId]);
$totalStudents = (int)$totalStudents->fetchColumn();

$totalQuizzes = $db->prepare("
    SELECT COUNT(*) FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE c.instructor_id = ?
");
$totalQuizzes->execute([$instructorId]);
$totalQuizzesVal = (int)$totalQuizzes->fetchColumn();

// Standalone quizzes (no course linked)
$standaloneQuizzes = $db->prepare("
    SELECT COUNT(*) FROM quizzes WHERE course_id IS NULL
");
$standaloneQuizzes->execute();
// Use only course-linked count

$avgScore = $db->prepare("
    SELECT COALESCE(AVG(qa.score), 0)
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    LEFT JOIN courses c ON q.course_id = c.id
    WHERE (c.instructor_id = ? OR q.course_id IS NULL)
      AND qa.status = 'completed'
");
$avgScore->execute([$instructorId]);
$avgScoreVal = round((float)$avgScore->fetchColumn(), 1);

// Recent 5 courses
$recentCourses = $db->prepare("
    SELECT c.*, cat.name AS category_name,
           COUNT(DISTINCT e.id) AS enrolled_count,
           COUNT(DISTINCT l.id) AS lessons_count,
           COUNT(DISTINCT q.id) AS quizzes_count
    FROM courses c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN enrollments e ON e.course_id = c.id
    LEFT JOIN lessons l ON l.course_id = c.id
    LEFT JOIN quizzes q ON q.course_id = c.id
    WHERE c.instructor_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 5
");
$recentCourses->execute([$instructorId]);
$recentCourses = $recentCourses->fetchAll();

// Recent activity: enrollments
$recentEnrollments = $db->prepare("
    SELECT u.name AS student_name, c.title AS course_title, e.enrolled_at
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE c.instructor_id = ?
    ORDER BY e.enrolled_at DESC
    LIMIT 5
");
$recentEnrollments->execute([$instructorId]);
$recentEnrollments = $recentEnrollments->fetchAll();

// Recent activity: quiz attempts
$recentAttempts = $db->prepare("
    SELECT u.name AS student_name, q.title AS quiz_title, qa.score, qa.submitted_at, qa.status
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    JOIN quizzes q ON qa.quiz_id = q.id
    LEFT JOIN courses c ON q.course_id = c.id
    WHERE c.instructor_id = ? AND qa.status = 'completed'
    ORDER BY qa.submitted_at DESC
    LIMIT 5
");
$recentAttempts->execute([$instructorId]);
$recentAttempts = $recentAttempts->fetchAll();

$instructor = getUser($instructorId);
$pageTitle = 'Instructor Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1" style="margin-left:240px; padding:2rem;">

        <!-- Welcome Banner -->
        <div class="welcome-banner rounded-3 p-4 mb-4 text-white d-flex align-items-center justify-content-between"
             style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div>
                <h4 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($instructor['name']) ?>! 👋</h4>
                <p class="mb-0 opacity-75">Here's what's happening with your courses today.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/instructor/create_course.php" class="btn btn-warning btn-sm fw-bold">
                    <i class="fas fa-plus me-1"></i>New Course
                </a>
                <a href="<?= APP_URL ?>/instructor/create_quiz.php" class="btn btn-light btn-sm fw-bold">
                    <i class="fas fa-file-alt me-1"></i>New Quiz
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;font-size:1.4rem;">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold"><?= $myCoursesCount ?></div>
                            <div class="text-muted small">My Courses</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;font-size:1.4rem;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold"><?= $totalStudents ?></div>
                            <div class="text-muted small">Total Students</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;font-size:1.4rem;">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold"><?= $totalQuizzesVal ?></div>
                            <div class="text-muted small">Total Quizzes</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;font-size:1.4rem;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold"><?= $avgScoreVal ?>%</div>
                            <div class="text-muted small">Avg Quiz Score</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Courses Table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-book-open me-2 text-primary"></i>Recent Courses</h6>
                <a href="<?= APP_URL ?>/instructor/courses.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Students</th>
                                <th>Lessons</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentCourses)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No courses yet. <a href="<?= APP_URL ?>/instructor/create_course.php">Create one!</a></td></tr>
                        <?php else: ?>
                            <?php foreach ($recentCourses as $course): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($course['title']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($course['slug']) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($course['category_name'] ?? 'None') ?></span></td>
                                <td>
                                    <?php
                                    $statusClass = match($course['status']) {
                                        'published' => 'success',
                                        'draft'     => 'warning',
                                        'archived'  => 'secondary',
                                        default     => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($course['status']) ?></span>
                                </td>
                                <td><i class="fas fa-users text-muted me-1"></i><?= $course['enrolled_count'] ?></td>
                                <td><?= $course['lessons_count'] ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/instructor/edit_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?= APP_URL ?>/instructor/lessons.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-list"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-user-plus me-2 text-success"></i>Recent Enrollments</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentEnrollments)): ?>
                            <div class="text-center text-muted py-4">No enrollments yet.</div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentEnrollments as $enr): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold small"><?= htmlspecialchars($enr['student_name']) ?></div>
                                    <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($enr['course_title']) ?></div>
                                </div>
                                <small class="text-muted"><?= timeAgo($enr['enrolled_at']) ?></small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-clipboard-check me-2 text-info"></i>Recent Quiz Attempts</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentAttempts)): ?>
                            <div class="text-center text-muted py-4">No quiz attempts yet.</div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentAttempts as $att): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold small"><?= htmlspecialchars($att['student_name']) ?></div>
                                    <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($att['quiz_title']) ?></div>
                                </div>
                                <span class="badge <?= $att['score'] >= 60 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= round($att['score'], 1) ?>%
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /main-content -->
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
