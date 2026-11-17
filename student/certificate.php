<?php
require_once __DIR__ . '/../config/config.php';
requireRole('student');

$userId = (int)$_SESSION['user_id'];
$db = getDB();

$certId   = (int)($_GET['id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);

$certificate = null;

if ($certId > 0) {
    $certificate = dbRow("SELECT * FROM certificates WHERE id = ? AND student_id = ?", [$certId, $userId]);
    if ($certificate) {
        $courseId = (int)$certificate['course_id'];
    }
} elseif ($courseId > 0) {
    $certificate = dbRow("SELECT * FROM certificates WHERE course_id = ? AND student_id = ?", [$courseId, $userId]);

    if (!$certificate && isEnrolled($userId, $courseId)) {
        $progress = calculateProgress($userId, $courseId);
        $passedStmt = $db->prepare("
            SELECT COUNT(*) FROM quiz_attempts qa
            JOIN quizzes q ON q.id = qa.quiz_id
            WHERE qa.student_id = ? AND q.course_id = ?
              AND qa.status = 'completed' AND qa.score >= q.pass_score
        ");
        $passedStmt->execute([$userId, $courseId]);
        $passedAny = (int)$passedStmt->fetchColumn() > 0;

        if ($progress >= 100 && $passedAny) {
            $code = generateCertCode();
            dbExec(
                "INSERT INTO certificates (student_id, course_id, cert_code, issued_at) VALUES (?, ?, ?, NOW())",
                [$userId, $courseId, $code]
            );
            $certificate = dbRow("SELECT * FROM certificates WHERE course_id = ? AND student_id = ?", [$courseId, $userId]);
            sendNotification($userId, 'Your certificate is ready!', 'success');
        } else {
            flash('error', 'Complete all lessons and pass a quiz to earn this certificate.');
            redirect(APP_URL . '/student/course_view.php?id=' . $courseId);
        }
    }
}

if (!$certificate || $courseId <= 0) {
    flash('error', 'Certificate not found.');
    redirect(APP_URL . '/student/profile.php?tab=certificates');
}

$course = dbRow("
    SELECT c.*, u.name AS instructor_name
    FROM courses c
    JOIN users u ON u.id = c.instructor_id
    WHERE c.id = ?
", [$courseId]);

if (!$course) {
    flash('error', 'Course not found.');
    redirect(APP_URL . '/student/profile.php?tab=certificates');
}

$student = getUser($userId);
$pageTitle = 'Certificate — ' . $course['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
    .navbar, footer, .no-print, .alert { display: none !important; }
    body { background: #fff !important; }
    .certificate-wrap { box-shadow: none !important; }
}
.certificate-wrap {
    max-width: 960px;
    margin: 0 auto;
    border: 14px solid #f0b429;
    outline: 3px solid #1a2b4a;
    outline-offset: -30px;
    background: #fff;
    position: relative;
}
.certificate-inner { padding: 4rem 3rem; text-align: center; }
.certificate-seal {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg,#f0b429,#e09f00);
    color: #1a2b4a;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem; margin: 0 auto 1rem;
}
</style>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <a href="<?= APP_URL ?>/student/profile.php?tab=certificates" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back to Certificates
        </a>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/student/course_view.php?id=<?= $courseId ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-book me-1"></i>Course
            </a>
            <button type="button" class="btn btn-warning btn-sm fw-semibold" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print / Save PDF
            </button>
        </div>
    </div>

    <div class="certificate-wrap shadow-lg rounded-3">
        <div class="certificate-inner">
            <div class="text-uppercase fw-bold mb-1" style="letter-spacing:4px;color:#1a2b4a;">
                <?= htmlspecialchars(getSetting('site_name', APP_NAME)) ?>
            </div>
            <div class="text-muted small mb-4">Certificate of Completion</div>

            <div class="certificate-seal"><i class="fas fa-award"></i></div>

            <p class="text-muted mb-2">This is to certify that</p>
            <h1 class="fw-bold mb-3" style="font-family:Georgia,serif;color:#1a2b4a;">
                <?= htmlspecialchars($student['name']) ?>
            </h1>
            <p class="text-muted mb-2">has successfully completed the course</p>
            <h3 class="fw-bold mb-4" style="color:#7c5c00;"><?= htmlspecialchars($course['title']) ?></h3>

            <p class="text-muted mb-0">
                Issued on <?= formatDate($certificate['issued_at'], 'd F Y') ?>
            </p>

            <div class="row mt-5 pt-3 align-items-end">
                <div class="col-4 text-center">
                    <div class="border-top pt-2 mx-3 small text-muted">Instructor</div>
                    <div class="fw-semibold small"><?= htmlspecialchars($course['instructor_name']) ?></div>
                </div>
                <div class="col-4 text-center">
                    <div class="fw-semibold small text-uppercase" style="color:#1a2b4a;letter-spacing:2px;">
                        <?= htmlspecialchars(getSetting('site_name', APP_NAME)) ?>
                    </div>
                    <div class="border-top pt-2 mx-3 small text-muted">Platform</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-2 mx-3 small text-muted">Certificate ID</div>
                    <div class="fw-semibold small"><?= htmlspecialchars($certificate['cert_code']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-center text-muted small mt-4 mb-0">
        Verify this certificate using ID <strong><?= htmlspecialchars($certificate['cert_code']) ?></strong>.
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
