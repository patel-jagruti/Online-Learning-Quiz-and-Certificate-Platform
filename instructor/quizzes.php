<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = (int)$_SESSION['user_id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrfValid()) {
        flash('error', 'Invalid security token. Please try again.');
        redirect(APP_URL . '/instructor/quizzes.php');
    }
    $quizId = (int)$_POST['delete_id'];
    $own = $db->prepare("
        SELECT q.id FROM quizzes q
        JOIN courses c ON c.id = q.course_id
        WHERE q.id = ? AND c.instructor_id = ?
    ");
    $own->execute([$quizId, $instructorId]);
    if ($own->fetch()) {
        dbExec("DELETE FROM quizzes WHERE id = ?", [$quizId]);
        flash('success', 'Quiz deleted successfully.');
    } else {
        flash('error', 'Quiz not found or you do not have permission to delete it.');
    }
    redirect(APP_URL . '/instructor/quizzes.php');
}

$quizzes = dbRows("
    SELECT q.*, c.title AS course_title,
           (SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id) AS question_count,
           (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.status = 'completed') AS attempt_count,
           (SELECT COALESCE(AVG(qa.score), 0) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.status = 'completed') AS avg_score
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    WHERE c.instructor_id = ?
    ORDER BY q.created_at DESC
", [$instructorId]);

$pageTitle = 'My Quizzes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1" style="margin-left:240px; padding:2rem;">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-0">My Quizzes</h4>
                <p class="text-muted mb-0">Manage quizzes across your courses</p>
            </div>
            <a href="<?= APP_URL ?>/instructor/create_quiz.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Create Quiz
            </a>
        </div>

        <?php showFlash(); ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($quizzes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-question fa-3x text-muted opacity-50 mb-3"></i>
                    <h5 class="text-muted">No quizzes yet</h5>
                    <p class="text-muted">Create your first quiz to start assessing your students.</p>
                    <a href="<?= APP_URL ?>/instructor/create_quiz.php" class="btn btn-primary btn-sm">Create Quiz</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Quiz</th>
                                <th>Course</th>
                                <th class="text-center">Questions</th>
                                <th class="text-center">Attempts</th>
                                <th class="text-center">Avg. Score</th>
                                <th class="text-center">Pass</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $quiz): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= htmlspecialchars($quiz['title']) ?></div>
                                    <small class="text-muted">
                                        <?php if ((int)$quiz['time_limit_minutes'] > 0): ?>
                                            <i class="fas fa-clock me-1"></i><?= (int)$quiz['time_limit_minutes'] ?> min &middot;
                                        <?php endif; ?>
                                        Max <?= (int)$quiz['max_attempts'] ?> attempts
                                    </small>
                                </td>
                                <td><span class="small"><?= htmlspecialchars($quiz['course_title']) ?></span></td>
                                <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= (int)$quiz['question_count'] ?></span></td>
                                <td class="text-center"><?= (int)$quiz['attempt_count'] ?></td>
                                <td class="text-center">
                                    <?php $avg = round((float)$quiz['avg_score'], 1); ?>
                                    <span class="fw-semibold <?= $avg >= (int)$quiz['pass_score'] ? 'text-success' : 'text-danger' ?>">
                                        <?= $avg ?>%
                                    </span>
                                </td>
                                <td class="text-center"><?= (int)$quiz['pass_score'] ?>%</td>
                                <td class="text-end pe-4">
                                    <a href="<?= APP_URL ?>/instructor/results.php?quiz=<?= (int)$quiz['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="View results">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this quiz and all its questions/attempts?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="delete_id" value="<?= (int)$quiz['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
