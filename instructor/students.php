<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = (int)$_SESSION['user_id'];
$db = getDB();

$search   = trim($_GET['search'] ?? '');
$courseId = (int)($_GET['course'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$myCourses = dbRows("SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title", [$instructorId]);

$where  = 'WHERE c.instructor_id = ?';
$params = [$instructorId];

if ($courseId > 0) {
    $where   .= ' AND c.id = ?';
    $params[] = $courseId;
}
if ($search !== '') {
    $where   .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$countStmt = $db->prepare("
    SELECT COUNT(DISTINCT u.id)
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    JOIN courses c ON c.id = e.course_id
    {$where}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.avatar,
           COUNT(DISTINCT e.course_id) AS courses_count,
           ROUND(AVG(e.progress_percent), 0) AS avg_progress,
           MAX(e.enrolled_at) AS last_enrolled
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    JOIN courses c ON c.id = e.course_id
    {$where}
    GROUP BY u.id, u.name, u.email, u.avatar
    ORDER BY last_enrolled DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$students = $stmt->fetchAll();

$pageTitle = 'My Students';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1" style="margin-left:240px; padding:2rem;">
        <div class="mb-4">
            <h4 class="fw-bold mb-0">My Students</h4>
            <p class="text-muted mb-0">Students enrolled across your courses</p>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name or email"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Course</label>
                        <select name="course" class="form-select">
                            <option value="0">All courses</option>
                            <?php foreach ($myCourses as $co): ?>
                            <option value="<?= (int)$co['id'] ?>" <?= $courseId === (int)$co['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($co['title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1">Filter</button>
                        <a href="<?= APP_URL ?>/instructor/students.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Students</h6>
                <span class="badge bg-primary"><?= number_format($total) ?> total</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($students)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted opacity-50 mb-3"></i>
                    <p class="text-muted mb-0">No students found.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Student</th>
                                <th class="text-center">Courses</th>
                                <th style="min-width:160px;">Avg. Progress</th>
                                <th>Last Enrolled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($s['avatar']) && file_exists(UPLOAD_PATH . '/avatars/' . $s['avatar'])): ?>
                                            <img src="<?= UPLOAD_URL ?>/avatars/<?= htmlspecialchars($s['avatar']) ?>"
                                                 class="rounded-circle" width="36" height="36" style="object-fit:cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                                 style="width:36px;height:36px;"><?= strtoupper(substr($s['name'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($s['name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($s['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge bg-secondary-subtle text-secondary"><?= (int)$s['courses_count'] ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:8px;">
                                            <div class="progress-bar bg-success" style="width:<?= (int)$s['avg_progress'] ?>%"></div>
                                        </div>
                                        <small class="fw-semibold"><?= (int)$s['avg_progress'] ?>%</small>
                                    </div>
                                </td>
                                <td><small class="text-muted"><?= formatDate($s['last_enrolled']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($total > $perPage): ?>
            <div class="card-footer bg-white py-3">
                <?= paginationLinks($total, $page, $perPage, APP_URL . '/instructor/students.php?search=' . urlencode($search) . '&course=' . $courseId) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
