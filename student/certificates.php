<?php
require_once __DIR__ . '/../config/config.php';
requireRole('student');
redirect(APP_URL . '/student/profile.php?tab=certificates');
