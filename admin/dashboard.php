<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$pdo = getDB();

// Stats
$totalUsers       = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalCourses     = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalQuizzes     = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalEnrollments = $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();

// Recent Users (last 10)
$recentUsers = $pdo->query("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Recent Enrollments (last 10)
$recentEnrollments = $pdo->query("
    SELECT e.id, u.name AS student, c.title AS course, e.progress_percent, e.enrolled_at
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY e.enrolled_at DESC LIMIT 10
")->fetchAll();

// Chart data: users per month (last 6 months)
$usersPerMonth = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month, COUNT(*) AS cnt
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
")->fetchAll();

// Chart data: enrollments by category
$enrollByCategory = $pdo->query("
    SELECT cat.name AS category, COUNT(e.id) AS cnt
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN categories cat ON c.category_id = cat.id
    GROUP BY cat.id, cat.name
")->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main flex-grow-1 p-4">

        <!-- Top bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-tachometer-alt me-2 text-danger"></i>Dashboard</h4>
                <small class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>!</small>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-user-plus me-1"></i>Add User</a>
                <a href="<?= APP_URL ?>/admin/courses.php" class="btn btn-sm btn-outline-success"><i class="fas fa-plus me-1"></i>New Course</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm stat-card bg-gradient-primary text-white h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon"><i class="fas fa-users fa-2x"></i></div>
                        <div>
                            <div class="stat-number"><?= number_format($totalUsers) ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                        <a href="<?= APP_URL ?>/admin/users.php" class="text-white-50 small"><i class="fas fa-arrow-right me-1"></i>Manage Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm stat-card bg-gradient-success text-white h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon"><i class="fas fa-book-open fa-2x"></i></div>
                        <div>
                            <div class="stat-number"><?= number_format($totalCourses) ?></div>
                            <div class="stat-label">Total Courses</div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                        <a href="<?= APP_URL ?>/admin/courses.php" class="text-white-50 small"><i class="fas fa-arrow-right me-1"></i>Manage Courses</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm stat-card bg-gradient-warning text-white h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon"><i class="fas fa-question-circle fa-2x"></i></div>
                        <div>
                            <div class="stat-number"><?= number_format($totalQuizzes) ?></div>
                            <div class="stat-label">Total Quizzes</div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                        <a href="<?= APP_URL ?>/admin/quizzes.php" class="text-white-50 small"><i class="fas fa-arrow-right me-1"></i>Manage Quizzes</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm stat-card bg-gradient-danger text-white h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon"><i class="fas fa-user-graduate fa-2x"></i></div>
                        <div>
                            <div class="stat-number"><?= number_format($totalEnrollments) ?></div>
                            <div class="stat-label">Total Enrollments</div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                        <a href="<?= APP_URL ?>/admin/reports.php" class="text-white-50 small"><i class="fas fa-arrow-right me-1"></i>View Reports</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Users Registered Per Month</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="usersChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-success"></i>Enrollments by Category</h6>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Data Tables -->
        <div class="row g-4 mb-4">
            <!-- Recent Users -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2 text-primary"></i>Recent Users</h6>
                        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $roleBadge = ['admin'=>'danger','instructor'=>'warning','student'=>'info'];
                                            $rc = $roleBadge[$u['role']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $rc ?>"><?= ucfirst($u['role']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($u['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= formatDate($u['created_at'], 'M d, Y') ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentUsers)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No users yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-user-graduate me-2 text-success"></i>Recent Enrollments</h6>
                        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-sm btn-outline-success">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Progress</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEnrollments as $e): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($e['student']) ?></td>
                                        <td><small><?= htmlspecialchars(truncate($e['course'], 30)) ?></small></td>
                                        <td>
                                            <div class="progress" style="height:6px; min-width:70px;">
                                                <div class="progress-bar bg-success" style="width:<?= $e['progress_percent'] ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= $e['progress_percent'] ?>%</small>
                                        </td>
                                        <td><small><?= formatDate($e['enrolled_at'], 'M d') ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentEnrollments)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No enrollments yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-users d-block fa-2x mb-2"></i>Manage Users
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="<?= APP_URL ?>/admin/courses.php" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-book-open d-block fa-2x mb-2"></i>Manage Courses
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="<?= APP_URL ?>/admin/quizzes.php" class="btn btn-outline-warning w-100 py-3">
                            <i class="fas fa-question-circle d-block fa-2x mb-2"></i>Manage Quizzes
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="<?= APP_URL ?>/admin/reports.php" class="btn btn-outline-danger w-100 py-3">
                            <i class="fas fa-chart-line d-block fa-2x mb-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.bg-gradient-danger  { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-card { border-radius: 15px !important; overflow: hidden; }
.stat-icon { opacity: 0.8; }
.stat-number { font-size: 2rem; font-weight: 700; line-height: 1; }
.stat-label  { font-size: 0.85rem; opacity: 0.9; }
</style>

<script src="<?= ASSET_URL ?>/vendor/chartjs/chart.umd.min.js"></script>
<script>
// Users per month bar chart
const uLabels = <?= json_encode(array_column($usersPerMonth, 'month')) ?>;
const uData   = <?= json_encode(array_column($usersPerMonth, 'cnt')) ?>;
new Chart(document.getElementById('usersChart'), {
    type: 'bar',
    data: {
        labels: uLabels.length ? uLabels : ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [{
            label: 'New Users',
            data: uData.length ? uData : [0,0,0,0,0,0],
            backgroundColor: 'rgba(102,126,234,0.7)',
            borderColor: '#667eea',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Enrollments by category pie chart
const cLabels = <?= json_encode(array_column($enrollByCategory, 'category')) ?>;
const cData   = <?= json_encode(array_column($enrollByCategory, 'cnt')) ?>;
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: cLabels.length ? cLabels : ['No Data'],
        datasets: [{
            data: cData.length ? cData : [1],
            backgroundColor: ['#667eea','#11998e','#f5576c','#f093fb','#4facfe','#ffd200'],
            borderWidth: 2,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
