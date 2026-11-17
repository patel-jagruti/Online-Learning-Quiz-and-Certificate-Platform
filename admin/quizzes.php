<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$pdo = getDB();

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['quiz_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM quizzes WHERE id=?")->execute([$id]);
            flash('success', 'Quiz deleted.');
        }
        redirect(APP_URL . '/admin/quizzes.php');
    }
}

// Filter by course
$courseF = (int)($_GET['course'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($courseF) {
    $where .= " AND q.course_id=?";
    $params[] = $courseF;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM quizzes q $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare("
    SELECT q.id, q.title, q.time_limit_minutes, q.pass_score, q.created_at,
           c.title AS course_title,
           COUNT(DISTINCT qs.id) AS question_count,
           COUNT(DISTINCT qa.id) AS attempt_count,
           COALESCE(ROUND(AVG(qa.score),1), 0) AS avg_score
    FROM quizzes q
    LEFT JOIN courses c ON q.course_id = c.id
    LEFT JOIN questions qs ON qs.quiz_id = q.id
    LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.status='completed'
    $where
    GROUP BY q.id
    ORDER BY q.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$quizzes = $stmt->fetchAll();

$allCourses = $pdo->query("SELECT id, title FROM courses ORDER BY title")->fetchAll();

$pageTitle = 'Quiz Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main flex-grow-1 p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-question-circle me-2 text-warning"></i>Quiz Management</h4>
                <small class="text-muted"><?= number_format($totalCount) ?> total quizzes</small>
            </div>
        </div>

        <?php $msg = flash('success'); if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Filter by Course</label>
                        <select name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($allCourses as $co): ?>
                            <option value="<?= $co['id'] ?>" <?= $courseF==$co['id']?'selected':'' ?>><?= htmlspecialchars($co['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= APP_URL ?>/admin/quizzes.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i>Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quizzes Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Title</th>
                                <th>Course</th>
                                <th class="text-center">Questions</th>
                                <th class="text-center">Attempts</th>
                                <th class="text-center">Avg Score</th>
                                <th class="text-center">Pass %</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $q): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars(truncate($q['title'], 35)) ?></div>
                                    <?php if ($q['time_limit_minutes']): ?>
                                    <small class="text-muted"><i class="fas fa-clock me-1"></i><?= $q['time_limit_minutes'] ?> min</small>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($q['course_title'] ?? 'Standalone') ?></small></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $q['question_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $q['attempt_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php $avg = (float)$q['avg_score']; ?>
                                    <span class="badge bg-<?= $avg >= $q['pass_score'] ? 'success' : 'danger' ?>">
                                        <?= $q['attempt_count'] > 0 ? $avg . '%' : 'N/A' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted"><?= $q['pass_score'] ?>%</small>
                                </td>
                                <td><small class="text-muted"><?= formatDate($q['created_at'], 'M d, Y') ?></small></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($q['attempt_count'] > 0): ?>
                                        <a href="<?= APP_URL ?>/admin/reports.php?quiz=<?= $q['id'] ?>" class="btn btn-outline-info" title="View Attempts">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this quiz and all its data?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                                            <button class="btn btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($quizzes)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No quizzes found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p===$page?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&course=<?= $courseF ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
