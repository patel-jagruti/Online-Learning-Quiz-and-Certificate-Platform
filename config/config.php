<?php
/**
 * QuizMaster LMS - Application Bootstrap
 * Defines application constants, starts the session and loads core libraries.
 */

// ------------------------------------------------------------
// Error reporting (set display_errors to 0 in production)
// ------------------------------------------------------------
define('APP_ENV', 'development');
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');

// ------------------------------------------------------------
// Paths & URLs
// ------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__));

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

$basePathFs = str_replace('\\', '/', BASE_PATH);
$docRoot    = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$subPath    = ($docRoot && str_starts_with($basePathFs, $docRoot))
    ? substr($basePathFs, strlen($docRoot))
    : '';

define('APP_URL', rtrim($scheme . '://' . $host . $subPath, '/'));
define('ASSET_URL', APP_URL . '/assets');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', APP_URL . '/uploads');

// ------------------------------------------------------------
// Application constants
// ------------------------------------------------------------
define('APP_NAME', 'QuizMaster LMS');
define('APP_VERSION', '1.0.0');
define('ITEMS_PER_PAGE', 12);

// ------------------------------------------------------------
// Timezone
// ------------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');

// ------------------------------------------------------------
// Session
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('quizmaster_session');
    session_start();
}

// ------------------------------------------------------------
// Ensure upload directories exist
// ------------------------------------------------------------
foreach (['avatars', 'thumbnails'] as $sub) {
    if (!is_dir(UPLOAD_PATH . '/' . $sub)) {
        @mkdir(UPLOAD_PATH . '/' . $sub, 0755, true);
    }
}

// ------------------------------------------------------------
// Core libraries
// ------------------------------------------------------------
require_once __DIR__ . '/db.php';
require_once BASE_PATH . '/includes/functions.php';
