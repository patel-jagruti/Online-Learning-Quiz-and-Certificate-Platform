<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? 'student';
$attemptId = (int)($_GET['attempt'] ?? 0);

/**
 * Render an attempt detail view with per-question breakdown.
 */
if ($attemptId > 0) {
    $stmt = $db->prepare("
        SELECT qa.*, u.name AS student_name, u.email AS student_email,
               q.title AS quiz_title, q.pass_score, q.show_results,
               c.id AS course_id, c.title AS course_title, c.instructor_id
        FROM quiz_attempts qa
        JOIN users u ON u.id = qa.student_id
        JOIN quizzes q ON q.id = qa.quiz_id
        LEFT JOIN courses c ON c.id = q.course_id
        WHERE qa.id = ?
        LIMIT 1
    ");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        flash('error', 'Attempt not found.');
        redirect(APP_URL . '/student/result.php');
    }

    $canView = ((int)$attempt['student_id'] === $userId)
        || $role === 'admin'
        || ($role === 'instructor' && (int)$attempt['instructor_id'] === $userId);

    if (!$canView) {
        flash('error', 'You do not have permission to view this result.');
        redirect(APP_URL . '/student/result.php');
    }

    $answers = dbRows("
        SELECT aa.*, q.question_text, q.type, q.points, q.sort_order,
               o.option_text AS selected_text,
               (SELECT GROUP_CONCAT(o2.option_text SEPARATOR ' | ')
                  FROM options o2 WHERE o2.question_id = q.id AND o2.is_correct = 1) AS correct_text
        FROM attempt_answers aa
        JOIN questions q ON q.id = aa.question_id
        LEFT JOIN options o ON o.id = aa.selected_option_id
        WHERE aa.attempt_id = ?
        ORDER BY q.sort_order, q.id
    ", [$attemptId]);

    $passed = $attempt['score'] !== null && (float)$attempt['score'] >= (int)$attempt['pass_score'];
    $timeTaken = (int)$attempt['time_taken_seconds'];
    $timeText  = $timeTaken > 0
        ? sprintf('%dm %02ds', intdiv($timeTaken, 60), $timeTaken % 60)
        : '—';

    $pageTitle = 'Quiz Result — ' . $attempt['quiz_title'];
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a href="<?= APP_URL ?>/student/result.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                    <?php if ($attempt['course_id']): ?>
                    <a href="<?= APP_URL ?>/student/course_view.php?id=<?= (int)$attempt['course_id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-book me-1"></i>Course
                    </a>
                    <?php endif; ?>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                    <div class="card-body p-4 p-lg-5 text-center <?= $attempt['status'] === 'completed' ? ($passed ? 'bg-success-subtle' : 'bg-danger-subtle') : 'bg-light' ?>">
                        <?php if ($attempt['status'] === 'completed'): ?>
                            <div class="mb-2">
                                <i class="fas <?= $passed ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?> fa-4x"></i>
                            </div>
                            <h3 class="fw-bold mb-1"><?= $passed ? 'Congratulations, you passed!' : 'Keep practicing!' ?></h3>
                            <p class="text-muted mb-3"><?= htmlspecialchars($attempt['quiz_title']) ?></p>
                            <div class="display-4 fw-bold <?= $passed ? 'text-success' : 'text-danger' ?>">
                                <?= number_format((float)$attempt['score'], 1) ?>%
                            </div>
                            <p class="text-muted small mb-0">Passing score: <?= (int)$attempt['pass_score'] ?>%</p>
                        <?php else: ?>
                            <h3 class="fw-bold">Attempt <?= htmlspecialchars($attempt['status']) ?></h3>
                            <p class="text-muted mb-0">This attempt has not been completed.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="row text-center small">
                            <div class="col-4 border-end">
                                <div class="text-muted">Student</div>
                                <div class="fw-semibold"><?= htmlspecialchars($attempt['student_name']) ?></div>
                            </div>
                            <div class="col-4 border-end">
                                <div class="text-muted">Time Taken</div>
                                <div class="fw-semibold"><?= $timeText ?></div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted">Submitted</div>
                                <div class="fw-semibold"><?= $attempt['submitted_at'] ? formatDate($attempt['submitted_at'], 'd M Y H:i') : '—' ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mb-3">Answer Review</h5>
                <?php if (empty($answers)): ?>
                    <div class="alert alert-info">No answer details were recorded for this attempt.</div>
                <?php else: ?>
                    <?php foreach ($answers as $i => $a):
                        $given = $a['text_answer'] !== null && $a['text_answer'] !== ''
                            ? $a['text_answer']
                            : ($a['selected_text'] ?? 'No answer');
                    ?>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-semibold mb-0">
                                    <span class="badge bg-secondary me-2"><?= $i + 1 ?></span>
                                    <?= htmlspecialchars($a['question_text']) ?>
                                </h6>
                                <span class="badge <?= $a['is_correct'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $a['is_correct'] ? 'Correct' : 'Incorrect' ?>
                                </span>
                            </div>
                            <div class="small mb-1">
                                <span class="text-muted">Your answer:</span>
                                <span class="<?= $a['is_correct'] ? 'text-success' : 'text-danger' ?> fw-semibold">
                                    <?= htmlspecialchars($given) ?>
                                </span>
                            </div>
                            <?php if (!$a['is_correct']): ?>
                            <div class="small">
                                <span class="text-muted">Correct answer:</span>
                                <span class="text-success fw-semibold"><?= htmlspecialchars($a['correct_text'] ?? '—') ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

/**
 * Attempt history for the current student.
 */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$total = (int)(dbRow("
    SELECT COUNT(*) AS c FROM quiz_attempts
    WHERE student_id = ? AND status IN ('completed','abandoned')
", [$userId])['c'] ?? 0);

$attempts = dbRows("
    SELECT qa.*, q.title AS quiz_title, q.pass_score, c.title AS course_title
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    LEFT JOIN courses c ON c.id = q.course_id
    WHERE qa.student_id = ? AND qa.status IN ('completed','abandoned')
    ORDER BY qa.started_at DESC
    LIMIT {$perPage} OFFSET {$offset}
", [$userId]);

$pageTitle = 'My Results';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-data me-2 text-primary"></i>My Results</h4>
            <small class="text-muted">Review your quiz performance</small>
        </div>
        <a href="<?= APP_URL ?>/student/dashboard.php" class="btn btn-sm btn-outline-secondary">Dashboard</a>
    </div>

    <?php if (empty($attempts)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-clipboard-list fa-3x text-muted opacity-50 mb-3"></i>
            <h6 class="text-muted">No results yet</h6>
            <p class="text-muted small mb-3">Take a quiz to see your results here.</p>
            <a href="<?= APP_URL ?>/student/courses.php" class="btn btn-primary btn-sm">Browse Courses</a>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Quiz</th>
                        <th class="text-center">Score</th>
                        <th class="text-center">Result</th>
                        <th>Date</th>
                        <th class="text-end pe-4">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a):
                        $passed = $a['status'] === 'completed' && $a['score'] !== null && (float)$a['score'] >= (int)$a['pass_score'];
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold"><?= htmlspecialchars($a['quiz_title']) ?></div>
                            <?php if (!empty($a['course_title'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($a['course_title']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-semibold"><?= $a['score'] !== null ? number_format((float)$a['score'], 1) . '%' : '—' ?></td>
                        <td class="text-center">
                            <?php if ($a['status'] !== 'completed'): ?>
                                <?= statusBadge($a['status']) ?>
                            <?php elseif ($passed): ?>
                                <span class="badge bg-success-subtle text-success">Passed</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger">Failed</span>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= formatDate($a['started_at'], 'd M Y H:i') ?></small></td>
                        <td class="text-end pe-4">
                            <a href="<?= APP_URL ?>/student/result.php?attempt=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="mt-4">
        <?= paginationLinks($total, $page, $perPage, APP_URL . '/student/result.php') ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
