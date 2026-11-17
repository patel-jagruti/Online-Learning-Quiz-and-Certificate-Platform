<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$pdo = getDB();

// --- CSV Export handlers (must run before any output) ---
if (isset($_GET['export'])) {
    $exportType = sanitize($_GET['export']);
    $dateFrom   = sanitize($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
    $dateTo     = sanitize($_GET['date_to']   ?? date('Y-m-d'));

    header('Content-Type: text/csv');

    if ($exportType === 'students') {
        header('Content-Disposition: attachment; filename="top_students_' . date('Ymd') . '.csv"');
        $rows = $pdo->prepare("
            SELECT u.name, u.email,
                   COUNT(DISTINCT qa.id) AS quizzes_taken,
                   ROUND(AVG(qa.score),2) AS avg_score,
                   COUNT(DISTINCT CASE WHEN qa.score >= qz.pass_score THEN qa.id END) AS passed
            FROM users u
            JOIN quiz_attempts qa ON qa.student_id = u.id AND qa.status='completed'
            JOIN quizzes qz ON qa.quiz_id = qz.id
            WHERE DATE(qa.submitted_at) BETWEEN ? AND ?
            GROUP BY u.id HAVING quizzes_taken > 0
            ORDER BY avg_score DESC LIMIT 50
        ");
        $rows->execute([$dateFrom, $dateTo]);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name','Email','Quizzes Taken','Avg Score','Passed']);
        foreach ($rows->fetchAll() as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }

    if ($exportType === 'courses') {
        header('Content-Disposition: attachment; filename="popular_courses_' . date('Ymd') . '.csv"');
        $rows = $pdo->prepare("
            SELECT c.title, u.name AS instructor, cat.name AS category,
                   COUNT(e.id) AS enrollments, c.status
            FROM courses c
            LEFT JOIN users u ON c.instructor_id=u.id
            LEFT JOIN categories cat ON c.category_id=cat.id
            LEFT JOIN enrollments e ON e.course_id=c.id
            WHERE DATE(e.enrolled_at) BETWEEN ? AND ? OR e.id IS NULL
            GROUP BY c.id ORDER BY enrollments DESC LIMIT 50
        ");
        $rows->execute([$dateFrom, $dateTo]);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Title','Instructor','Category','Enrollments','Status']);
        foreach ($rows->fetchAll() as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }

    if ($exportType === 'quizzes') {
        header('Content-Disposition: attachment; filename="quiz_pass_rates_' . date('Ymd') . '.csv"');
        $rows = $pdo->prepare("
            SELECT q.title,
                   COUNT(qa.id) AS attempts,
                   ROUND(AVG(qa.score),2) AS avg_score,
                   ROUND(COUNT(CASE WHEN qa.score >= q.pass_score THEN 1 END)*100.0/NULLIF(COUNT(qa.id),0),1) AS pass_rate
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed'
            WHERE DATE(qa.submitted_at) BETWEEN ? AND ? OR qa.id IS NULL
            GROUP BY q.id ORDER BY pass_rate DESC
        ");
        $rows->execute([$dateFrom, $dateTo]);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Quiz Title','Attempts','Avg Score','Pass Rate %']);
        foreach ($rows->fetchAll() as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }
}

// --- Date range ---
$dateFrom = sanitize($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo   = sanitize($_GET['date_to']   ?? date('Y-m-d'));

// Top students
$topStudents = $pdo->prepare("
    SELECT u.id, u.name, u.email,
           COUNT(DISTINCT qa.id) AS quizzes_taken,
           ROUND(AVG(qa.score),1) AS avg_score,
           COUNT(DISTINCT CASE WHEN qa.score >= qz.pass_score THEN qa.id END) AS passed
    FROM users u
    JOIN quiz_attempts qa ON qa.student_id = u.id AND qa.status='completed'
    JOIN quizzes qz ON qa.quiz_id = qz.id
    WHERE DATE(qa.submitted_at) BETWEEN ? AND ?
    GROUP BY u.id HAVING quizzes_taken > 0
    ORDER BY avg_score DESC
    LIMIT 15
");
$topStudents->execute([$dateFrom, $dateTo]);
$topStudents = $topStudents->fetchAll();

// Most popular courses
$popularCourses = $pdo->prepare("
    SELECT c.id, c.title, c.status,
           u.name AS instructor,
           cat.name AS category,
           COUNT(e.id) AS enrollments,
           ROUND(AVG(e.progress_percent),0) AS avg_progress
    FROM courses c
    LEFT JOIN users u ON c.instructor_id=u.id
    LEFT JOIN categories cat ON c.category_id=cat.id
    LEFT JOIN enrollments e ON e.course_id=c.id AND DATE(e.enrolled_at) BETWEEN ? AND ?
    GROUP BY c.id
    HAVING enrollments > 0
    ORDER BY enrollments DESC
    LIMIT 15
");
$popularCourses->execute([$dateFrom, $dateTo]);
$popularCourses = $popularCourses->fetchAll();

// Quiz pass rates
$quizPassRates = $pdo->prepare("
    SELECT q.id, q.title, q.pass_score,
           COUNT(qa.id) AS attempts,
           ROUND(AVG(qa.score),1) AS avg_score,
           ROUND(COUNT(CASE WHEN qa.score >= q.pass_score THEN 1 END)*100.0/NULLIF(COUNT(qa.id),0),1) AS pass_rate
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.id AND qa.status='completed' AND DATE(qa.submitted_at) BETWEEN ? AND ?
    GROUP BY q.id
    HAVING attempts > 0
    ORDER BY pass_rate DESC
    LIMIT 15
");
$quizPassRates->execute([$dateFrom, $dateTo]);
$quizPassRates = $quizPassRates->fetchAll();

// Chart: quiz attempts over time (last 14 days)
$attemptsOverTime = $pdo->prepare("
    SELECT DATE(submitted_at) AS day, COUNT(*) AS cnt
    FROM quiz_attempts
    WHERE status='completed' AND submitted_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY day ORDER BY day
");
$attemptsOverTime->execute();
$attemptsOverTime = $attemptsOverTime->fetchAll();

$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main flex-grow-1 p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-bar me-2 text-danger"></i>Reports & Analytics</h4>
                <small class="text-muted">Date range: <?= htmlspecialchars($dateFrom) ?> to <?= htmlspecialchars($dateTo) ?></small>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i>From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i>To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Apply</button>
                    </div>
                    <div class="col-md-2">
                        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline-secondary w-100"><i class="fas fa-sync me-1"></i>Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Chart: Attempts over time -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-line me-2 text-primary"></i>Quiz Attempts Over Time (Last 14 Days)</h6>
            </div>
            <div class="card-body">
                <canvas id="attemptsChart" height="80"></canvas>
            </div>
        </div>

        <!-- Top Students -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-trophy me-2 text-warning"></i>Top Performing Students</h6>
                <a href="?export=students&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th class="text-center">Quizzes Taken</th>
                                <th class="text-center">Avg Score</th>
                                <th class="text-center">Passed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topStudents as $i => $s): ?>
                            <tr>
                                <td>
                                    <?php if ($i===0): ?><span class="badge bg-warning text-dark">🥇 1</span>
                                    <?php elseif($i===1): ?><span class="badge bg-secondary">🥈 2</span>
                                    <?php elseif($i===2): ?><span class="badge bg-danger text-white">🥉 3</span>
                                    <?php else: ?><span class="text-muted"><?= $i+1 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($s['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($s['email']) ?></small>
                                </td>
                                <td class="text-center"><span class="badge bg-info"><?= $s['quizzes_taken'] ?></span></td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center gap-2 justify-content-center">
                                        <div class="progress flex-grow-1" style="height:8px;max-width:80px;">
                                            <div class="progress-bar bg-success" style="width:<?= min(100,$s['avg_score']) ?>%"></div>
                                        </div>
                                        <span class="fw-bold text-success"><?= $s['avg_score'] ?>%</span>
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge bg-success"><?= $s['passed'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topStudents)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No data for selected period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Most Popular Courses -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-fire me-2 text-danger"></i>Most Popular Courses</h6>
                <a href="?export=courses&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course</th>
                                <th>Instructor</th>
                                <th>Category</th>
                                <th class="text-center">Enrollments</th>
                                <th class="text-center">Avg Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularCourses as $co): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars(truncate($co['title'],40)) ?></td>
                                <td><small><?= htmlspecialchars($co['instructor'] ?? 'N/A') ?></small></td>
                                <td><span class="badge bg-light text-dark"><?= htmlspecialchars($co['category'] ?? 'N/A') ?></span></td>
                                <td class="text-center"><span class="badge bg-primary rounded-pill"><?= $co['enrollments'] ?></span></td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center gap-2 justify-content-center">
                                        <div class="progress flex-grow-1" style="height:8px;max-width:80px;">
                                            <div class="progress-bar" style="width:<?= $co['avg_progress'] ?>%"></div>
                                        </div>
                                        <small><?= $co['avg_progress'] ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <?php $sb = ['draft'=>'secondary','published'=>'success','archived'=>'warning'][$co['status']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $sb ?>"><?= ucfirst($co['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($popularCourses)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No enrollment data for selected period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quiz Pass Rate -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-check-double me-2 text-success"></i>Quiz Pass Rates</h6>
                <a href="?export=quizzes&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quiz</th>
                                <th class="text-center">Attempts</th>
                                <th class="text-center">Avg Score</th>
                                <th class="text-center">Pass Threshold</th>
                                <th class="text-center">Pass Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizPassRates as $qr): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars(truncate($qr['title'],40)) ?></td>
                                <td class="text-center"><span class="badge bg-secondary"><?= $qr['attempts'] ?></span></td>
                                <td class="text-center"><span class="badge bg-info"><?= $qr['avg_score'] ?>%</span></td>
                                <td class="text-center"><small class="text-muted"><?= $qr['pass_score'] ?>%</small></td>
                                <td class="text-center">
                                    <?php $pr = (float)$qr['pass_rate']; ?>
                                    <span class="badge bg-<?= $pr >= 70 ? 'success' : ($pr >= 40 ? 'warning' : 'danger') ?>">
                                        <?= $pr ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($quizPassRates)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No quiz data for selected period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="<?= ASSET_URL ?>/vendor/chartjs/chart.umd.min.js"></script>
<script>
const aLabels = <?= json_encode(array_column($attemptsOverTime, 'day')) ?>;
const aData   = <?= json_encode(array_column($attemptsOverTime, 'cnt')) ?>;
new Chart(document.getElementById('attemptsChart'), {
    type: 'line',
    data: {
        labels: aLabels.length ? aLabels : ['No Data'],
        datasets: [{
            label: 'Quiz Attempts',
            data: aData.length ? aData : [0],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102,126,234,0.15)',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#667eea',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
