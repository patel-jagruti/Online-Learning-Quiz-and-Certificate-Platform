<?php
require_once __DIR__.'/../config/config.php';

if (isLoggedIn()) redirect(APP_URL . '/student/dashboard.php');

$error   = '';
$success = '';

// Ensure password_resets table exists
try {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if user exists (always show success message to prevent email enumeration)
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Delete old tokens for this email
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                // Generate secure token
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expiresAt]);

                // In production, send email here. For now, show reset link.
                $resetUrl = APP_URL . '/auth/reset_password.php?token=' . $token;
                // Store reset URL in session for demo purposes
                $_SESSION['demo_reset_url'] = $resetUrl;
            }

            // Always show success (security: don't reveal if email exists)
            $success = "If <strong>" . htmlspecialchars($email) . "</strong> is registered, you'll receive a password reset link shortly. Please check your inbox (and spam folder).";

        } catch (Exception $e) {
            $error = 'A system error occurred. Please try again.';
        }
    }
}

$demoUrl = $_SESSION['demo_reset_url'] ?? '';
unset($_SESSION['demo_reset_url']);

$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – <?= APP_NAME ?></title>
    <link href="<?= ASSET_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; }
        .auth-card { border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
        .auth-logo { width:72px; height:72px; background:linear-gradient(135deg,#f093fb,#f5576c);
                     border-radius:50%; display:flex; align-items:center; justify-content:center;
                     margin:0 auto 1rem; box-shadow:0 8px 25px rgba(240,147,251,.4); }
        .form-control:focus { border-color:#f5576c; box-shadow:0 0 0 .2rem rgba(245,87,108,.25); }
        .btn-reset { background:linear-gradient(135deg,#f093fb,#f5576c); border:none;
                     padding:12px; font-size:1rem; font-weight:600; color:#fff; }
        .btn-reset:hover { opacity:.92; transform:translateY(-1px); color:#fff; }
        .input-group-text { background:#f8f9fa; border-right:none; }
        .form-control { border-left:none; }
        .input-group:focus-within .input-group-text { border-color:#f5576c; }
    </style>
</head>
<body class="d-flex align-items-center py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-9 col-md-7 col-lg-5 col-xl-4">

            <div class="text-center mb-3">
                <a href="<?= APP_URL ?>/auth/login.php" class="text-white text-decoration-none opacity-75">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>

            <div class="auth-card card border-0 p-4 p-sm-5">
                <div class="text-center mb-4">
                    <div class="auth-logo">
                        <i class="fas fa-key fa-2x text-white"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Forgot Password?</h3>
                    <p class="text-muted small">No worries! Enter your email and we'll send you reset instructions.</p>
                </div>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="alert alert-success py-3 text-center">
                    <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                    <p class="mb-0"><?= $success ?></p>
                </div>
                <?php if (!empty($demoUrl)): ?>
                <div class="alert alert-info py-2 small">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Demo only</strong> – In production an email would be sent. Reset link:<br>
                    <a href="<?= htmlspecialchars($demoUrl) ?>" class="text-break"><?= htmlspecialchars($demoUrl) ?></a>
                </div>
                <?php endif; ?>
                <?php else: ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="you@example.com" required autofocus
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-reset w-100 mb-3">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>
                </form>

                <?php endif; ?>

                <p class="text-center text-muted small mb-0">
                    Remember your password?
                    <a href="<?= APP_URL ?>/auth/login.php" class="text-decoration-none fw-semibold">Sign In</a>
                </p>
            </div>

            <p class="text-center text-white opacity-75 mt-4 small">
                &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
            </p>
        </div>
    </div>
</div>
<script src="<?= ASSET_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
