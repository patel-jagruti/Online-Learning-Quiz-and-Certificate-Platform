<?php
require_once __DIR__.'/../config/config.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Handle enrollment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course_id'])) {
    $courseId = (int)$_POST['enroll_course_id'];
    if (!isEnrolled($userId, $courseId)) {
        $stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id, enrolled_at) VALUES (?,?,NOW())");
        $stmt->execute([$userId, $courseId]);
        flash('success', 'Successfully enrolled in the course!');
    }
    redirect(APP_URL.'/student/course_view.php?id='.$courseId);
}

// Filters
$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$filter = $_GET['filter'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

// Categories
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Build query
$where = ["c.status = 'published'"];
$params = [];

if ($filter === 'enrolled') {
    $where[] = "e.student_id = ?";
    $params[] = $userId;
}
if ($search !== '') {
    $where[] = "(c.title LIKE ? OR c.description LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId > 0) {
    $where[] = "c.category_id = ?";
    $params[] = $categoryId;
}

$whereSQL = $where ? 'WHERE '.implode(' AND ', $where) : '';

$joinType = ($filter === 'enrolled') ? 'INNER' : 'LEFT';
$baseSQL = "FROM courses c
    JOIN users u ON u.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    $joinType JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    $whereSQL";

$countParams = array_merge([$userId], $params);
$countStmt = $db->prepare("SELECT COUNT(DISTINCT c.id) $baseSQL");
$countStmt->execute($countParams);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);

$dataSQL = "SELECT DISTINCT c.*, u.name as instructor_name, cat.name as category_name, cat.icon as cat_icon,
    e.progress_percent, e.enrolled_at,
    (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) as lesson_count
    $baseSQL ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset";
$dataStmt = $db->prepare($dataSQL);
$dataStmt->execute($countParams);
$courses = $dataStmt->fetchAll();

$pageTitle = $filter === 'enrolled' ? 'My Courses' : 'Browse Courses';
require_once __DIR__.'/../includes/header.php';
?>

<div class="d-flex" style="min-height:100vh;background:#f4f6fb;">
    <?php require_once __DIR__.'/includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0"><?= $filter === 'enrolled' ? '<i class="bi bi-journals me-2 text-primary"></i>My Courses' : '<i class="bi bi-grid me-2 text-primary"></i>Browse Courses' ?></h3>
                <p class="text-muted mb-0"><?= $total ?> course<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
        </div>

        <?php $msg = flash('success'); if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Search + Category Filter -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <?php if ($filter): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Search Courses</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search by title, description, instructor..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                    </div>
                </form>
                <!-- Category Tabs -->
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="?<?= $filter ? 'filter='.$filter.'&' : '' ?>search=<?= urlencode($search) ?>" class="btn btn-sm <?= !$categoryId ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?<?= $filter ? 'filter='.$filter.'&' : '' ?>search=<?= urlencode($search) ?>&category=<?= $cat['id'] ?>"
                           class="btn btn-sm <?= $categoryId == $cat['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <?= htmlspecialchars($cat['icon'] ?? '') ?> <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Courses Grid -->
        <?php if (empty($courses)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                <h5>No courses found</h5>
                <p>Try adjusting your search or browse all courses.</p>
                <a href="<?= APP_URL ?>/student/courses.php" class="btn btn-primary">View All Courses</a>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($courses as $course): ?>
            <div class="col-sm-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden" style="transition:.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                    <?php if ($course['thumbnail']): ?>
                        <img src="<?= APP_URL ?>/uploads/thumbnails/<?= htmlspecialchars($course['thumbnail']) ?>" class="card-img-top" style="height:170px;object-fit:cover;">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="height:170px;">
                            <i class="bi bi-book text-primary" style="font-size:3.5rem;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <?php if ($course['category_name']): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($course['category_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!is_null($course['progress_percent'])): ?>
                                <span class="badge bg-success bg-opacity-10 text-success">Enrolled</span>
                            <?php endif; ?>
                        </div>
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($course['title']) ?></h6>
                        <div class="text-muted small mb-2"><i class="bi bi-person me-1"></i><?= htmlspecialchars($course['instructor_name']) ?></div>
                        <div class="text-muted small mb-3"><i class="bi bi-collection-play me-1"></i><?= $course['lesson_count'] ?> lesson<?= $course['lesson_count'] !== 1 ? 's' : '' ?></div>

                        <?php if (!is_null($course['progress_percent'])): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Progress</span>
                                    <span class="fw-semibold"><?= $course['progress_percent'] ?>%</span>
                                </div>
                                <div class="progress" style="height:5px;">
                                    <div class="progress-bar bg-success" style="width:<?= $course['progress_percent'] ?>%"></div>
                                </div>
                            </div>
                            <a href="<?= APP_URL ?>/student/course_view.php?id=<?= $course['id'] ?>" class="btn btn-success mt-auto">
                                <i class="bi bi-play-circle me-1"></i>Continue Learning
                            </a>
                        <?php else: ?>
                            <form method="POST" class="mt-auto">
                                <input type="hidden" name="enroll_course_id" value="<?= $course['id'] ?>">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-circle me-1"></i>Enroll Now
                                    <?php if ($course['price'] > 0): ?>
                                        &mdash; $<?= number_format($course['price'], 2) ?>
                                    <?php else: ?>
                                        (Free)
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <li class="page-item <?= $i==$page?'active':'' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
