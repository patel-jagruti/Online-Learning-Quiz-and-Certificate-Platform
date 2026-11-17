<?php
/**
 * Public course catalogue.
 */
require_once __DIR__ . '/config/config.php';

$db = getDB();

$search     = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;
$offset     = ($page - 1) * $perPage;

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$where  = ["c.status = 'published'"];
$params = [];

if ($search !== '') {
    $where[]  = "(c.title LIKE ? OR c.description LIKE ? OR u.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($categoryId > 0) {
    $where[]  = "c.category_id = ?";
    $params[] = $categoryId;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM courses c
    JOIN users u ON u.id = c.instructor_id
    {$whereSQL}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT c.*, u.name AS instructor_name, cat.name AS category_name, cat.icon AS category_icon,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrolled_count,
           (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
    FROM courses c
    JOIN users u ON u.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    {$whereSQL}
    ORDER BY c.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$courses = $stmt->fetchAll();

$pageTitle = 'Browse Courses';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 mb-2">Course Catalogue</span>
            <h1 class="display-5 fw-bold">Find Your Next Course</h1>
            <p class="text-muted lead">Learn from expert instructors and earn certificates</p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0"
                                   placeholder="Search courses or instructors..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?= APP_URL ?>/courses.php" class="btn btn-sm <?= !$categoryId ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= APP_URL ?>/courses.php?category=<?= (int)$cat['id'] ?>"
               class="btn btn-sm <?= $categoryId === (int)$cat['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($courses)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-3 opacity-50"></i>
            <h4 class="text-muted">No courses found</h4>
            <p class="text-muted">Try a different search term or category.</p>
            <a href="<?= APP_URL ?>/courses.php" class="btn btn-primary">View All Courses</a>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($courses as $course): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden course-card">
                    <?php if (!empty($course['thumbnail'])): ?>
                        <img src="<?= UPLOAD_URL ?>/thumbnails/<?= htmlspecialchars($course['thumbnail']) ?>"
                             class="card-img-top" alt="<?= htmlspecialchars($course['title']) ?>"
                             style="height:180px;object-fit:cover;">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center"
                             style="height:180px;background:linear-gradient(135deg,#667eea,#764ba2);">
                            <i class="fas fa-graduation-cap fa-3x text-white opacity-75"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <?php if (!empty($course['category_name'])): ?>
                            <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($course['category_name']) ?></span>
                            <?php endif; ?>
                            <?php if ((float)$course['price'] <= 0): ?>
                            <span class="badge bg-success-subtle text-success">Free</span>
                            <?php endif; ?>
                        </div>
                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($course['title']) ?></h5>
                        <p class="text-muted small flex-grow-1"><?= htmlspecialchars(truncate($course['description'] ?? '', 110)) ?></p>
                        <div class="d-flex align-items-center justify-content-between small text-muted mb-3">
                            <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($course['instructor_name']) ?></span>
                            <span><i class="fas fa-users me-1"></i><?= number_format((int)$course['enrolled_count']) ?></span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-primary">
                                <?= (float)$course['price'] > 0 ? getSetting('currency_symbol', '$') . number_format((float)$course['price'], 2) : 'Free' ?>
                            </span>
                            <a href="<?= APP_URL ?>/courses/view.php?slug=<?= urlencode($course['slug']) ?>" class="btn btn-sm btn-primary">
                                View Course <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-5">
            <?= paginationLinks($total, $page, $perPage, APP_URL . '/courses.php?search=' . urlencode($search) . '&category=' . $categoryId) ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
