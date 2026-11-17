<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = (int)$_SESSION['user_id'];
$db = getDB();

$quizId = (int)($_GET['quiz'] ?? 0);
$search = trim($_GET['search'] ?? '');
$status = in_array($_GET['status'] ?? '', ['completed', 'in_progress', 'abandoned'], true) ? $_GET['status'] : '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$myQuizzes = dbRows("
    SELECT q.id, q.title, c.title AS course_title
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    WHERE c.instructor_id = ?
    ORDER BY q.title
", [$instructorId]);

$where  = "WHERE c.instructor_id = ?";
$params = [$instructorId];

if ($quizId > 0) {
    $where   .= ' AND q.id = ?';
    $params[] = $quizId;
}
if ($status !== '') {
    $where   .= ' AND qa.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where   .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM quiz_attempts qa
    JOIN users u ON u.id = qa.student_id
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN courses c ON c.id = q.course_id
    {$where}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$summaryStmt = $db->prepare("
    SELECT COUNT(*) AS attempts,
           COALESCE(AVG(CASE WHEN qa.status = 'completed' THEN qa.score END), 0) AS avg_score,
           COALESCE(SUM(CASE WHEN qa.status = 'completed' AND qa.score >= q.pass_score THEN 1 ELSE 0 END), 0) AS passed,
           COALESCE(SUM(CASE WHEN qa.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed
    FROM quiz_attempts qa
    JOIN users u ON u.id = qa.student_id
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN courses c ON c.id = q.course_id
    {$where}
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$stmt = $db->prepare("
    SELECT qa.*, u.name AS student_name, u.email AS student_email,
           q.title AS quiz_title, q.pass_score, c.title AS course_title
    FROM quiz_attempts qa
    JOIN users u ON u.id = qa.student_id
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN courses c ON c.id = q.course_id
    {$where}
    ORDER BY qa.started_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$attempts = $stmt->fetchAll();

$passRate = (int)($summary['completed'] ?? 0) > 0
    ? round(((int)$summary['passed'] / (int)$summary['completed']) * 100, 1)
    : 0;

$pageTitle = 'Quiz Results';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1" style="margin-left:240px; padding:2rem;">
        <div class="mb-4">
            <h4 class="fw-bold mb-0">Quiz Results</h4>
            <p class="text-muted mb-0">Review student attempts across your quizzes</p>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Total Attempts</div>
                    <div class="fs-3 fw-bold"><?= number_format((int)$summary['attempts']) ?></div>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Completed</div>
                    <div class="fs-3 fw-bold"><?= number_format((int)$summary['completed']) ?></div>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Average Score</div>
                    <div class="fs-3 fw-bold text-primary"><?= round((float)$summary['avg_score'], 1) ?>%</div>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Pass Rate</div>
                    <div class="fs-3 fw-bold text-success"><?= $passRate ?>%</div>
                </div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Search Student</label>
                        <input type="text" name="search" class="form-control" placeholder="Name or email"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Quiz</label>
                        <select name="quiz" class="form-select">
                            <option value="0">All quizzes</option>
                            <?php foreach ($myQuizzes as $q): ?>
                            <option value="<?= (int)$q['id'] ?>" <?= $quizId === (int)$q['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($q['title']) ?> — <?= htmlspecialchars($q['course_title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <?php foreach (['completed' => 'Completed', 'in_progress' => 'In Progress', 'abandoned' => 'Abandoned'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1">Filter</button>
                        <a href="<?= APP_URL ?>/instructor/results.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($attempts)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-bar fa-3x text-muted opacity-50 mb-3"></i>
                    <p class="text-muted mb-0">No quiz attempts found.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Student</th>
                                <th>Quiz</th>
                                <th class="text-center">Score</th>
                                <th class="text-center">Result</th>
                                <th class="text-center">Status</th>
                                <th>Date</th>
                                <th class="text-end pe-4">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $a): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= htmlspecialchars($a['student_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($a['student_email']) ?></small>
                                </td>
                                <td>
                                    <div class="small"><?= htmlspecialchars($a['quiz_title']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($a['course_title']) ?></small>
                                </td>
                                <td class="text-center fw-semibold">
                                    <?= $a['score'] !== null ? number_format((float)$a['score'], 1) . '%' : '—' ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['status'] === 'completed'): ?>
                                        <?php if ((float)$a['score'] >= (int)$a['pass_score']): ?>
                                            <span class="badge bg-success-subtle text-success">Passed</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Failed</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= statusBadge($a['status']) ?></td>
                                <td><small class="text-muted"><?= formatDate($a['started_at'], 'd M Y H:i') ?></small></td>
                                <td class="text-end pe-4">
                                    <a href="<?= APP_URL ?>/student/result.php?attempt=<?= (int)$a['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($total > $perPage): ?>
            <div class="card-footer bg-white py-3">
                <?= paginationLinks($total, $page, $perPage, APP_URL . '/instructor/results.php?search=' . urlencode($search) . '&quiz=' . $quizId . '&status=' . urlencode($status)) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
