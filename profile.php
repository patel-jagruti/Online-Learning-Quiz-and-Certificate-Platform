<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
redirect(APP_URL . '/student/profile.php');
