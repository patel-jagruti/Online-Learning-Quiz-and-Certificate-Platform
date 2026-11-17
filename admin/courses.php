<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');

$pdo = getDB();

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!csrfValid()) {
            flash('error', 'Invalid security token. Please try again.');
            redirect(APP_URL . '/admin/courses.php');
        }

        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $instructorId = (int)($_POST['instructor_id'] ?? 0);
        $categoryId   = (int)($_POST['category_id'] ?? 0);
        $status       = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_POST['status'] : 'draft';
        $price        = max(0, (float)($_POST['price'] ?? 0));

        $instructor = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('instructor','admin') AND is_active = 1");
        $instructor->execute([$instructorId]);
        $category = $categoryId > 0
            ? $pdo->prepare("SELECT id FROM categories WHERE id = ?")
            : null;
        if ($category) {
            $category->execute([$categoryId]);
        }

        if ($title === '') {
            flash('error', 'Course title is required.');
        } elseif (!$instructor->fetch()) {
            flash('error', 'Please select a valid instructor.');
        } elseif ($category && !$category->fetch()) {
            flash('error', 'Please select a valid category.');
        } else {
            $slug = generateSlug($title);
            $baseSlug = $slug;
            $n = 1;
            do {
                $dup = $pdo->prepare("SELECT id FROM courses WHERE slug = ?");
                $dup->execute([$slug]);
                if (!$dup->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . (++$n);
            } while ($n < 100);

            $pdo->prepare("
                INSERT INTO courses (title, slug, description, instructor_id, category_id, status, price, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $title,
                $slug,
                $description ?: null,
                $instructorId,
                $categoryId ?: null,
                $status,
                $price,
            ]);

            flash('success', 'Course created successfully.');
        }
        redirect(APP_URL . '/admin/courses.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['course_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);
            flash('success', 'Course deleted successfully.');
        }
        redirect(APP_URL . '/admin/courses.php');
    }

    if ($action === 'status') {
        $id     = (int)($_POST['course_id'] ?? 0);
        $status = sanitize($_POST['status'] ?? '');
        if ($id && in_array($status, ['draft','published','archived'])) {
            $pdo->prepare("UPDATE courses SET status=? WHERE id=?")->execute([$status, $id]);
            flash('success', 'Course status updated.');
        }
        redirect(APP_URL . '/admin/courses.php');
    }
}

// --- Filters ---
$statusF   = sanitize($_GET['status'] ?? '');
$categoryF = (int)($_GET['category'] ?? 0);
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($statusF && in_array($statusF, ['draft','published','archived'])) {
    $where .= " AND c.status=?";
    $params[] = $statusF;
}
if ($categoryF) {
    $where .= " AND c.category_id=?";
    $params[] = $categoryF;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM courses c $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.status, c.created_at,
           u.name AS instructor,
           cat.name AS category,
           COUNT(DISTINCT e.id) AS enrollment_count
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN enrollments e ON e.course_id = c.id
    $where
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$courses = $stmt->fetchAll();

$allCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$allInstructors = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role IN ('instructor','admin') AND is_active = 1
    ORDER BY name
")->fetchAll();

$pageTitle = 'Course Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="admin-main flex-grow-1 p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-book-open me-2 text-success"></i>Course Management</h4>
                <small class="text-muted"><?= number_format($totalCount) ?> total courses</small>
            </div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                <i class="fas fa-plus me-1"></i>Add Course
            </button>
        </div>

        <?php $msg = flash('success'); if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php $err = flash('error'); if ($err): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="draft"     <?= $statusF==='draft'     ?'selected':'' ?>>Draft</option>
                            <option value="published" <?= $statusF==='published' ?'selected':'' ?>>Published</option>
                            <option value="archived"  <?= $statusF==='archived'  ?'selected':'' ?>>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryF==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="<?= APP_URL ?>/admin/courses.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i>Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Courses Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Title</th>
                                <th>Instructor</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th class="text-center">Enrollments</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars(truncate($c['title'], 40)) ?></td>
                                <td><small><?= htmlspecialchars($c['instructor'] ?? 'N/A') ?></small></td>
                                <td><span class="badge bg-light text-dark"><?= htmlspecialchars($c['category'] ?? 'N/A') ?></span></td>
                                <td>
                                    <?php
                                    $sb = ['draft'=>'secondary','published'=>'success','archived'=>'warning'][$c['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $sb ?>"><?= ucfirst($c['status']) ?></span>
                                </td>
                                <td class="text-center"><span class="badge bg-primary rounded-pill"><?= $c['enrollment_count'] ?></span></td>
                                <td><small class="text-muted"><?= formatDate($c['created_at'], 'M d, Y') ?></small></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Status change -->
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle btn-sm" data-bs-toggle="dropdown">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php foreach (['draft','published','archived'] as $st): ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="status">
                                                        <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                                        <input type="hidden" name="status" value="<?= $st ?>">
                                                        <button class="dropdown-item <?= $c['status']===$st?'active':'' ?>" type="submit">
                                                            <?= ucfirst($st) ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this course permanently?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                            <button class="btn btn-outline-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($courses)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No courses found.</td></tr>
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
                            <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($statusF) ?>&category=<?= $categoryF ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addCourseModalLabel">
                        <i class="fas fa-plus-circle me-2 text-success"></i>Add New Course
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Course Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="adminCourseTitle" class="form-control" required
                                   placeholder="e.g. Introduction to Data Science">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                      placeholder="What will students learn in this course?"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Instructor <span class="text-danger">*</span></label>
                            <select name="instructor_id" class="form-select" required>
                                <option value="">Select instructor</option>
                                <?php foreach ($allInstructors as $ins): ?>
                                <option value="<?= (int)$ins['id'] ?>">
                                    <?= htmlspecialchars($ins['name']) ?> (<?= htmlspecialchars($ins['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="0">No category</option>
                                <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= htmlspecialchars(getSetting('currency_symbol', '$')) ?></span>
                                <input type="number" name="price" class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Create Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
