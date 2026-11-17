<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    flash('error', 'No course specified.');
    redirect(APP_URL . '/instructor/courses.php');
}

$own = dbRow(
    "SELECT id FROM courses WHERE id = ? AND instructor_id = ?",
    [$courseId, (int)$_SESSION['user_id']]
);
if (!$own) {
    flash('error', 'Course not found.');
    redirect(APP_URL . '/instructor/courses.php');
}

redirect(APP_URL . '/instructor/edit_course.php?id=' . $courseId . '&step=3');
