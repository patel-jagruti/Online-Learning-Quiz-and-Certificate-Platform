<?php
/**
 * QuizMaster LMS - Helper Functions
 * Production-quality utility library
 */

// ============================================================
// INPUT / OUTPUT HELPERS
// ============================================================

/**
 * Sanitize a scalar value for safe output / DB use.
 */
function sanitize(mixed $input): string
{
    if (is_array($input)) {
        return '';
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * HTTP redirect and exit.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ============================================================
// SESSION / AUTH
// ============================================================

/**
 * Check if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check whether the logged-in user has the given role.
 * Accepts a single role string or an array of roles.
 */
function hasRole(string|array $role): bool
{
    if (!isLoggedIn()) {
        return false;
    }
    $roles = is_array($role) ? $role : [$role];
    return in_array($_SESSION['user_role'] ?? '', $roles, true);
}

/**
 * Redirect to login if not authenticated.
 */
function requireLogin(string $redirectTo = '/quiz/auth/login.php'): void
{
    if (!isLoggedIn()) {
        flash('error', 'Please log in to access that page.');
        redirect(APP_URL . '/auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

/**
 * Require a specific role; redirect otherwise.
 */
function requireRole(string|array $role, string $redirectTo = ''): void
{
    requireLogin();
    if (!hasRole($role)) {
        flash('error', 'You do not have permission to access that page.');
        if ($redirectTo) {
            redirect($redirectTo);
        }
        $home = match ($_SESSION['user_role'] ?? '') {
            'admin'      => APP_URL . '/admin/dashboard.php',
            'instructor' => APP_URL . '/instructor/dashboard.php',
            default      => APP_URL . '/student/dashboard.php',
        };
        redirect($home);
    }
}

// ============================================================
// USER
// ============================================================

/**
 * Fetch a user record by ID.
 */
function getUser(int $id): array|false
{
    return dbRow('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
}

/**
 * Fetch the currently logged-in user's full record.
 */
function currentUser(): array|false
{
    if (!isLoggedIn()) {
        return false;
    }
    return getUser((int)$_SESSION['user_id']);
}

// ============================================================
// DATE / TIME
// ============================================================

/**
 * Format a datetime string.
 */
function formatDate(string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception) {
        return $date;
    }
}

/**
 * Human-readable "time ago" string.
 */
function timeAgo(string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    try {
        $diff = (new DateTime())->diff(new DateTime($datetime));
    } catch (Exception) {
        return $datetime;
    }

    if ($diff->y > 0) return $diff->y . ' year' .   ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' .  ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' .    ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' .   ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// ============================================================
// STRING HELPERS
// ============================================================

/**
 * Generate a URL-friendly slug from a string.
 */
function generateSlug(string $str): string
{
    $str = mb_strtolower(trim($str), 'UTF-8');
    $str = preg_replace('/[^a-z0-9\s\-_]/', '', $str);
    $str = preg_replace('/[\s\-_]+/', '-', $str);
    return trim($str, '-');
}

/**
 * Truncate a string to $length characters, appending ellipsis.
 */
function truncate(string $str, int $length = 100): string
{
    $str = strip_tags($str);
    if (mb_strlen($str, 'UTF-8') <= $length) {
        return $str;
    }
    return mb_substr($str, 0, $length, 'UTF-8') . '…';
}

/**
 * Generate a unique 12-character uppercase alphanumeric certificate code.
 */
function generateCertCode(): string
{
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(9)), 0, 12));
        $exists = dbRow('SELECT id FROM certificates WHERE cert_code = ? LIMIT 1', [$code]);
    } while ($exists);
    return $code;
}

// ============================================================
// FILE UPLOAD
// ============================================================

/**
 * Handle a file upload from $_FILES[$file].
 * Returns the saved filename on success, false on failure.
 *
 * @param array  $file    Single entry from $_FILES
 * @param string $dir     Absolute destination directory
 * @param array  $allowed Permitted extensions (lowercase)
 * @param int    $maxMB   Max file size in megabytes
 */
function uploadFile(
    array  $file,
    string $dir,
    array  $allowed = ['jpg', 'png', 'jpeg', 'gif', 'webp'],
    int    $maxMB   = 5
): string|false {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return false;
    }

    if ($file['size'] > $maxMB * 1024 * 1024) {
        return false;
    }

    // Basic MIME check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $safeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $safeMimes, true)) {
        return false;
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = uniqid('', true) . '_' . time() . '.' . $ext;
    $dest     = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }

    return $filename;
}

// ============================================================
// NOTIFICATIONS
// ============================================================

/**
 * Insert a notification row for a user.
 */
function sendNotification(int $userId, string $message, string $type = 'info'): bool
{
    try {
        dbExec(
            'INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)',
            [$userId, $message, $type]
        );
        return true;
    } catch (PDOException) {
        return false;
    }
}

// ============================================================
// SETTINGS
// ============================================================

/**
 * Return all site settings as key => value array (cached in session).
 */
function getSettings(): array
{
    if (isset($_SESSION['_settings'])) {
        return $_SESSION['_settings'];
    }
    $rows = dbRows('SELECT key_name, value FROM settings');
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['key_name']] = $row['value'];
    }
    $_SESSION['_settings'] = $settings;
    return $settings;
}

/**
 * Get a single setting value by key.
 */
function getSetting(string $key, mixed $default = null): mixed
{
    return getSettings()[$key] ?? $default;
}

// ============================================================
// FLASH MESSAGES
// ============================================================

/**
 * Set or get a flash message.
 * flash('key', 'message') — sets it
 * flash('key')            — gets & clears it; returns null if none
 */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/**
 * Render all pending flash messages as Bootstrap alerts.
 */
function showFlash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $map = [
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];
    foreach ($_SESSION['flash'] as $key => $msg) {
        $cls = $map[$key] ?? 'info';
        $safeMsg = htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        echo <<<HTML
<div class="alert alert-{$cls} alert-dismissible fade show" role="alert">
  {$safeMsg}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
HTML;
    }
    unset($_SESSION['flash']);
}

// ============================================================
// PROGRESS & STATS
// ============================================================

/**
 * Calculate a student's lesson completion % for a course.
 * Returns 0-100 integer.
 */
function calculateProgress(int $studentId, int $courseId): int
{
    $total = dbRow(
        'SELECT COUNT(*) AS cnt FROM lessons WHERE course_id = ?',
        [$courseId]
    );
    if (!$total || (int)$total['cnt'] === 0) {
        return 0;
    }

    $done = dbRow(
        'SELECT COUNT(*) AS cnt
           FROM lesson_progress lp
           JOIN lessons l ON l.id = lp.lesson_id
          WHERE lp.student_id = ? AND l.course_id = ?',
        [$studentId, $courseId]
    );

    $pct = (int)round(((int)($done['cnt'] ?? 0) / (int)$total['cnt']) * 100);

    // Persist to enrollments table
    dbExec(
        'UPDATE enrollments SET progress_percent = ? WHERE student_id = ? AND course_id = ?',
        [$pct, $studentId, $courseId]
    );

    return $pct;
}

/**
 * Aggregate stats for a user.
 * Returns ['courses_enrolled', 'quizzes_taken', 'avg_score', 'certificates']
 */
function getUserStats(int $userId): array
{
    $enrolled = dbRow(
        'SELECT COUNT(*) AS cnt FROM enrollments WHERE student_id = ?',
        [$userId]
    );
    $quizStats = dbRow(
        'SELECT COUNT(*) AS cnt, AVG(score) AS avg
           FROM quiz_attempts
          WHERE student_id = ? AND status = "completed"',
        [$userId]
    );
    $certs = dbRow(
        'SELECT COUNT(*) AS cnt FROM certificates WHERE student_id = ?',
        [$userId]
    );

    return [
        'courses_enrolled' => (int)($enrolled['cnt'] ?? 0),
        'quizzes_taken'    => (int)($quizStats['cnt'] ?? 0),
        'avg_score'        => round((float)($quizStats['avg'] ?? 0), 1),
        'certificates'     => (int)($certs['cnt'] ?? 0),
    ];
}

/**
 * Aggregate stats for a quiz.
 * Returns ['total_attempts', 'avg_score', 'pass_rate']
 */
function getQuizStats(int $quizId): array
{
    $quiz = dbRow('SELECT pass_score FROM quizzes WHERE id = ? LIMIT 1', [$quizId]);
    $passScore = $quiz ? (int)$quiz['pass_score'] : 60;

    $stats = dbRow(
        'SELECT COUNT(*) AS total, AVG(score) AS avg_score
           FROM quiz_attempts
          WHERE quiz_id = ? AND status = "completed"',
        [$quizId]
    );

    $passed = dbRow(
        'SELECT COUNT(*) AS cnt
           FROM quiz_attempts
          WHERE quiz_id = ? AND status = "completed" AND score >= ?',
        [$quizId, $passScore]
    );

    $total    = (int)($stats['total'] ?? 0);
    $passRate = $total > 0
        ? round(((int)($passed['cnt'] ?? 0) / $total) * 100, 1)
        : 0;

    return [
        'total_attempts' => $total,
        'avg_score'      => round((float)($stats['avg_score'] ?? 0), 1),
        'pass_rate'      => $passRate,
    ];
}

/**
 * Check if a student is enrolled in a course.
 */
function isEnrolled(int $studentId, int $courseId): bool
{
    $row = dbRow(
        'SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1',
        [$studentId, $courseId]
    );
    return (bool)$row;
}

/**
 * Count quiz attempts for a student.
 */
function getAttemptCount(int $studentId, int $quizId): int
{
    $row = dbRow(
        'SELECT COUNT(*) AS cnt
           FROM quiz_attempts
          WHERE student_id = ? AND quiz_id = ? AND status IN ("completed","abandoned")',
        [$studentId, $quizId]
    );
    return (int)($row['cnt'] ?? 0);
}

// ============================================================
// PAGINATION
// ============================================================

/**
 * Calculate pagination offset.
 */
function paginationOffset(int $page, int $perPage = ITEMS_PER_PAGE): int
{
    return max(0, ($page - 1)) * $perPage;
}

/**
 * Render Bootstrap pagination links.
 */
function paginationLinks(int $total, int $page, int $perPage, string $url): string
{
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }
    $sep   = str_contains($url, '?') ? '&' : '?';
    $html  = '<nav><ul class="pagination justify-content-center flex-wrap">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $page ? ' active' : '';
        $html  .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"{$url}{$sep}page={$i}\">{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}

// ============================================================
// MISCELLANEOUS
// ============================================================

/**
 * Return avatar URL for a user, falling back to a Gravatar.
 */
function avatarUrl(array $user): string
{
    if (!empty($user['avatar'])) {
        return UPLOAD_URL . '/avatars/' . $user['avatar'];
    }
    $hash = md5(strtolower(trim($user['email'] ?? '')));
    return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=80";
}

/**
 * Return a Bootstrap badge HTML for a status string.
 */
function statusBadge(string $status): string
{
    $map = [
        'draft'       => 'secondary',
        'published'   => 'success',
        'archived'    => 'dark',
        'in_progress' => 'warning',
        'completed'   => 'success',
        'abandoned'   => 'danger',
        'active'      => 'success',
        'inactive'    => 'secondary',
    ];
    $cls  = $map[$status] ?? 'secondary';
    $text = ucfirst(str_replace('_', ' ', $status));
    return "<span class=\"badge bg-{$cls}\">{$text}</span>";
}

/**
 * CSRF token: generate once per session, return it.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 */
function csrfValid(): bool
{
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals($_SESSION['csrf_token'] ?? '', $submitted);
}

/**
 * Hidden CSRF input field HTML.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}
