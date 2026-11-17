<?php
require_once __DIR__ . '/../config/config.php';
requireRole('student');
redirect(APP_URL . '/student/courses.php?filter=enrolled');
