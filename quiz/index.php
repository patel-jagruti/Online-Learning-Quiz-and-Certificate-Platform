<?php
/**
 * Public quiz catalogue.
 */
require_once __DIR__ . '/../config/config.php';

$db = getDB();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($search !== '') {
    $where   .= ' AND (q.title LIKE ? OR q.description LIKE ? OR c.title LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM quizzes q
    LEFT JOIN courses c ON c.id = q.course_id
    {$where}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT q.*, c.title AS course_title, c.slug AS course_slug,
           (SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id) AS question_count
    FROM quizzes q
    LEFT JOIN courses c ON c.id = q.course_id
    {$where}
    ORDER BY q.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$quizzes = $stmt->fetchAll();

$pageTitle = 'Quizzes';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-warning-subtle text-warning px-3 py-2 mb-2">Practice & Test</span>
            <h1 class="display-5 fw-bold">Available Quizzes</h1>
            <p class="text-muted lead">Test your knowledge and track your progress</p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0"
                                   placeholder="Search quizzes..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($quizzes)): ?>
        <div class="text-center py-5">
            <i class="fas fa-clipboard-question fa-4x text-muted mb-3 opacity-50"></i>
            <h4 class="text-muted">No quizzes available yet</h4>
            <p class="text-muted">Check back soon or browse our courses.</p>
            <a href="<?= APP_URL ?>/courses.php" class="btn btn-primary">Browse Courses</a>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($quizzes as $quiz): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-warning-subtle text-warning">
                                <i class="fas fa-clipboard-question me-1"></i>
                                <?= (int)$quiz['question_count'] ?> questions
                            </span>
                            <?php if ((int)$quiz['time_limit_minutes'] > 0): ?>
                            <small class="text-muted"><i class="fas fa-clock me-1"></i><?= (int)$quiz['time_limit_minutes'] ?> min</small>
                            <?php endif; ?>
                        </div>
                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($quiz['title']) ?></h5>
                        <p class="text-muted small flex-grow-1">
                            <?= htmlspecialchars(truncate($quiz['description'] ?? 'Test your knowledge with this quiz.', 110)) ?>
                        </p>
                        <?php if (!empty($quiz['course_title'])): ?>
                        <div class="small text-muted mb-2">
                            <i class="fas fa-book me-1"></i><?= htmlspecialchars($quiz['course_title']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center small text-muted mb-3">
                            <span>Pass: <?= (int)$quiz['pass_score'] ?>%</span>
                            <span><?= (int)$quiz['max_attempts'] ?> attempts</span>
                        </div>
                        <?php if (isLoggedIn() && hasRole('student')): ?>
                            <a href="<?= APP_URL ?>/student/quiz.php?id=<?= (int)$quiz['id'] ?>" class="btn btn-warning fw-semibold">
                                <i class="fas fa-play me-1"></i>Take Quiz
                            </a>
                        <?php else: ?>
                            <a href="<?= APP_URL ?>/auth/login.php?next=<?= urlencode('/student/quiz.php?id=' . (int)$quiz['id']) ?>"
                               class="btn btn-outline-primary">
                                <i class="fas fa-right-to-bracket me-1"></i>Log in to Take Quiz
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-5">
            <?= paginationLinks($total, $page, $perPage, APP_URL . '/quiz/index.php?search=' . urlencode($search)) ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
