<?php
require_once __DIR__.'/../config/config.php';

if (isLoggedIn()) redirect(APP_URL . '/student/dashboard.php');

$error   = '';
$success = '';
$token   = sanitize($_GET['token'] ?? $_POST['token'] ?? '');
$validToken = null;

// Validate token
if (!empty($token)) {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("
            SELECT pr.*, u.id AS user_id, u.name AS user_name
            FROM password_resets pr
            JOIN users u ON pr.email = u.email
            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $validToken = $stmt->fetch();
    } catch (Exception $e) {
        $error = 'A system error occurred. Please try again.';
    }
}

if (!$validToken && empty($error)) {
    $error = 'This password reset link is invalid or has expired. Please <a href="' . APP_URL . '/auth/forgot_password.php">request a new one</a>.';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $formErrors = [];
    if (strlen($password) < 8) {
        $formErrors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $formErrors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $formErrors[] = 'Password must contain at least one number.';
    }
    if ($password !== $confirm) {
        $formErrors[] = 'Passwords do not match.';
    }

    if (empty($formErrors)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Update user password
            $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$hash, $validToken['user_id']]);

            // Mark token as used
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
                ->execute([$token]);

            $success = true;
        } catch (Exception $e) {
            $error = 'Failed to update password. Please try again.';
        }
    } else {
        $error = implode(' ', $formErrors);
    }
}

$pageTitle = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – <?= APP_NAME ?></title>
    <link href="<?= ASSET_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); min-height: 100vh; }
        .auth-card { border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
        .auth-logo { width:72px; height:72px; background:linear-gradient(135deg,#4facfe,#00f2fe);
                     border-radius:50%; display:flex; align-items:center; justify-content:center;
                     margin:0 auto 1rem; box-shadow:0 8px 25px rgba(79,172,254,.4); }
        .form-control:focus { border-color:#4facfe; box-shadow:0 0 0 .2rem rgba(79,172,254,.25); }
        .btn-reset { background:linear-gradient(135deg,#4facfe,#00f2fe); border:none;
                     padding:12px; font-size:1rem; font-weight:600; color:#fff; }
        .btn-reset:hover { opacity:.92; transform:translateY(-1px); color:#fff; }
        .input-group-text { background:#f8f9fa; border-right:none; }
        .form-control { border-left:none; }
        .input-group:focus-within .input-group-text { border-color:#4facfe; }
        .password-toggle { cursor:pointer; background:#f8f9fa; border-left:none; }
        .strength-bar { height:5px; border-radius:3px; transition:.3s; }
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
                        <i class="fas fa-shield-alt fa-2x text-white"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Reset Password</h3>
                    <p class="text-muted small">
                        <?= $validToken ? 'Hi <strong>' . htmlspecialchars($validToken['user_name']) . '</strong>, choose a new secure password.' : 'Password Reset' ?>
                    </p>
                </div>

                <?php if ($success): ?>
                <div class="text-center py-3">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-4x text-success"></i>
                    </div>
                    <h5 class="fw-bold text-success mb-2">Password Updated!</h5>
                    <p class="text-muted">Your password has been reset successfully. You can now log in with your new password.</p>
                    <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-primary w-100 mt-2">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                </div>

                <?php elseif (!empty($error) && !$validToken): ?>
                <div class="alert alert-danger text-center py-3">
                    <i class="fas fa-times-circle fa-2x mb-2 d-block"></i>
                    <?= $error ?>
                </div>
                <a href="<?= APP_URL ?>/auth/forgot_password.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-redo me-2"></i>Request New Reset Link
                </a>

                <?php else: ?>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <!-- New Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Min 8 chars, 1 uppercase, 1 number"
                                   required oninput="checkStrength(this.value)">
                            <button type="button" class="btn password-toggle input-group-text border-start-0"
                                    onclick="togglePassword('password', this)">
                                <i class="fas fa-eye text-muted"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="d-flex gap-1 mb-1">
                                <div class="strength-bar flex-fill bg-secondary" id="s1"></div>
                                <div class="strength-bar flex-fill bg-secondary" id="s2"></div>
                                <div class="strength-bar flex-fill bg-secondary" id="s3"></div>
                                <div class="strength-bar flex-fill bg-secondary" id="s4"></div>
                            </div>
                            <small class="text-muted" id="strengthText">Password strength</small>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control" id="confirm_password"
                                   name="confirm_password" placeholder="Repeat your new password" required>
                            <button type="button" class="btn password-toggle input-group-text border-start-0"
                                    onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye text-muted"></i>
                            </button>
                        </div>
                        <div id="matchMsg" class="small mt-1"></div>
                    </div>

                    <button type="submit" class="btn btn-reset w-100">
                        <i class="fas fa-save me-2"></i>Update Password
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <p class="text-center text-white opacity-75 mt-4 small">
                &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
            </p>
        </div>
    </div>
</div>
<script src="<?= ASSET_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(fieldId, btn) {
    const f = document.getElementById(fieldId);
    const i = btn.querySelector('i');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.classList.toggle('fa-eye'); i.classList.toggle('fa-eye-slash');
}
function checkStrength(val) {
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const colors = ['bg-danger','bg-warning','bg-info','bg-success'];
    const labels = ['Weak','Fair','Good','Strong'];
    for (let i = 1; i <= 4; i++) {
        document.getElementById('s'+i).className = 'strength-bar flex-fill '+(i<=score?colors[score-1]:'bg-secondary');
    }
    const txt = document.getElementById('strengthText');
    txt.textContent = val.length ? 'Strength: '+(labels[score-1]||'Very Weak') : 'Password strength';
    txt.className = 'small '+(score>=3?'text-success':score>=2?'text-warning':'text-danger');
}
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const pw  = document.getElementById('password').value;
    const el  = document.getElementById('matchMsg');
    if (!this.value) { el.textContent = ''; return; }
    el.innerHTML = this.value === pw
        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Passwords match</span>'
        : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Passwords do not match</span>';
});
</script>
</body>
</html>
