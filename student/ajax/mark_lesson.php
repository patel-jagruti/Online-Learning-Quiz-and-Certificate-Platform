<?php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

if (!hasRole('student')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only students can track lesson progress.']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$lessonId  = (int)($_POST['lesson_id'] ?? 0);
$courseId  = (int)($_POST['course_id'] ?? 0);

if ($lessonId <= 0 || $courseId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$db = getDB();

$lesson = dbRow("SELECT id FROM lessons WHERE id = ? AND course_id = ?", [$lessonId, $courseId]);
if (!$lesson) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Lesson not found.']);
    exit;
}

if (!isEnrolled($studentId, $courseId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not enrolled in this course.']);
    exit;
}

try {
    dbExec(
        "INSERT IGNORE INTO lesson_progress (student_id, lesson_id, completed_at) VALUES (?, ?, NOW())",
        [$studentId, $lessonId]
    );

    $progress = calculateProgress($studentId, $courseId);

    $total = (int)(dbRow("SELECT COUNT(*) AS c FROM lessons WHERE course_id = ?", [$courseId])['c'] ?? 0);
    $completed = (int)(dbRow("
        SELECT COUNT(*) AS c
        FROM lesson_progress lp
        JOIN lessons l ON l.id = lp.lesson_id
        WHERE lp.student_id = ? AND l.course_id = ?
    ", [$studentId, $courseId])['c'] ?? 0);

    echo json_encode([
        'success'   => true,
        'progress'  => $progress,
        'completed' => $completed,
        'total'     => $total,
    ]);
} catch (Throwable $e) {
    error_log('[mark_lesson] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save progress.']);
}
