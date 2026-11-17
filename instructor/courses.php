<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = $_SESSION['user_id'];
$db = getDB();

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        // Verify ownership
        $check = $db->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
        $check->execute([$courseId, $instructorId]);
        if ($check->fetch()) {
            // Check enrollments
            $enrolled = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
            $enrolled->execute([$courseId]);
            if ((int)$enrolled->fetchColumn() === 0) {
                // Delete lessons first
                $db->prepare("DELETE FROM lessons WHERE course_id = ?")->execute([$courseId]);
                $db->prepare("DELETE FROM courses WHERE id = ?")->execute([$courseId]);
                flash('success', 'Course deleted successfully.');
            } else {
                flash('error', 'Cannot delete a course with enrolled students.');
            }
        }
        redirect(APP_URL . '/instructor/courses.php');
    }

    if ($action === 'status_change') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'draft';
        $upd = $db->prepare("UPDATE courses SET status = ?, updated_at = NOW() WHERE id = ? AND instructor_id = ?");
        $upd->execute([$newStatus, $courseId, $instructorId]);
        flash('success', 'Course status updated.');
        redirect(APP_URL . '/instructor/courses.php');
    }
}

// Filter
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['draft', 'published', 'archived'];

$sql = "
    SELECT c.*, cat.name AS category_name,
           COUNT(DISTINCT e.id)  AS enrolled_count,
           COUNT(DISTINCT l.id)  AS lessons_count,
           COUNT(DISTINCT q.id)  AS quizzes_count
    FROM courses c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN enrollments e   ON e.course_id = c.id
    LEFT JOIN lessons l       ON l.course_id = c.id
    LEFT JOIN quizzes q       ON q.course_id = c.id
    WHERE c.instructor_id = ?
";
$params = [$instructorId];

if ($statusFilter && in_array($statusFilter, $validStatuses)) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$pageTitle = 'My Courses';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1" style="margin-left:240px; padding:2rem;">

        <!-- Flash messages -->
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">My Courses</h4>
                <p class="text-muted mb-0">Manage all your courses</p>
            </div>
            <a href="<?= APP_URL ?>/instructor/create_course.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create New Course
            </a>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <span class="text-muted small me-2">Filter by status:</span>
                    <a href="<?= APP_URL ?>/instructor/courses.php" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                    <?php foreach ($validStatuses as $s): ?>
                    <a href="<?= APP_URL ?>/instructor/courses.php?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= ucfirst($s) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Students</th>
                                <th>Lessons</th>
                                <th>Quizzes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($courses)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-book-open fa-2x mb-2 d-block"></i>
                                    No courses found. <a href="<?= APP_URL ?>/instructor/create_course.php">Create your first course!</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($course['title']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($course['slug']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($course['category_name'] ?? '—') ?></span>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="status_change">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <select name="status" class="form-select form-select-sm status-select" style="width:120px;" onchange="this.form.submit()">
                                            <?php foreach ($validStatuses as $s): ?>
                                            <option value="<?= $s ?>" <?= $course['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td><i class="fas fa-users text-muted me-1"></i><?= $course['enrolled_count'] ?></td>
                                <td><?= $course['lessons_count'] ?></td>
                                <td><?= $course['quizzes_count'] ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= APP_URL ?>/instructor/edit_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/instructor/lessons.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-info" title="Lessons">
                                            <i class="fas fa-list"></i>
                                        </a>
                                        <?php if ($course['enrolled_count'] == 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete(<?= $course['id'] ?>, '<?= htmlspecialchars(addslashes($course['title'])) ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete: has enrolled students">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteCourseName"></strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="course_id" id="deleteCourseId">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteCourseId').value = id;
    document.getElementById('deleteCourseName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
