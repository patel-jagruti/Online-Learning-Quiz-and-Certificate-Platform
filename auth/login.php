<?php
require_once __DIR__.'/../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'student';
    if ($role === 'admin') redirect(APP_URL . '/admin/dashboard.php');
    elseif ($role === 'instructor') redirect(APP_URL . '/instructor/dashboard.php');
    else redirect(APP_URL . '/student/dashboard.php');
}

$error = '';
$success = '';
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    $emailVal = $email;

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_name']   = $user['name'];
                $_SESSION['user_role']   = $user['role'];
                $_SESSION['user_avatar'] = $user['avatar'] ?? '';

                // Remember Me cookie (30 days)
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', false, true);
                    // Store token in DB (optional: extend schema)
                }

                flash('login_success', 'Welcome back, ' . $user['name'] . '!');

                // Role-based redirect
                switch ($user['role']) {
                    case 'admin':
                        redirect(APP_URL . '/admin/dashboard.php');
                    case 'instructor':
                        redirect(APP_URL . '/instructor/dashboard.php');
                    default:
                        redirect(APP_URL . '/student/dashboard.php');
                }
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'A system error occurred. Please try again later.';
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= APP_NAME ?></title>
    <link href="<?= ASSET_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .auth-card { border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .auth-logo { width: 72px; height: 72px; background: linear-gradient(135deg,#667eea,#764ba2);
                     border-radius: 50%; display:flex; align-items:center; justify-content:center;
                     margin: 0 auto 1rem; box-shadow: 0 8px 25px rgba(102,126,234,0.5); }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102,126,234,.25); }
        .btn-login { background: linear-gradient(135deg, #667eea, #764ba2); border: none;
                     padding: 12px; font-size: 1rem; font-weight: 600; letter-spacing: 0.5px; }
        .btn-login:hover { opacity: 0.92; transform: translateY(-1px); }
        .divider { position:relative; text-align:center; margin: 1.5rem 0; }
        .divider::before { content:''; position:absolute; top:50%; left:0; right:0; height:1px; background:#dee2e6; }
        .divider span { background:#fff; padding: 0 12px; position:relative; color:#6c757d; font-size:.875rem; }
        .input-group-text { background: #f8f9fa; border-right: none; }
        .form-control { border-left: none; }
        .input-group .form-control:focus { border-color: #667eea; }
        .input-group:focus-within .input-group-text { border-color: #667eea; }
        .password-toggle { cursor: pointer; background:#f8f9fa; border-left: none; }
    </style>
</head>
<body class="d-flex align-items-center py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-9 col-md-7 col-lg-5 col-xl-4">

            <!-- Back to Home -->
            <div class="text-center mb-3">
                <a href="<?= APP_URL ?>/" class="text-white text-decoration-none opacity-75">
                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
            </div>

            <div class="auth-card card border-0 p-4 p-sm-5">
                <!-- Logo -->
                <div class="text-center mb-4">
                    <div class="auth-logo">
                        <i class="fas fa-graduation-cap fa-2x text-white"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Welcome Back!</h3>
                    <p class="text-muted small">Sign in to continue learning</p>
                </div>

                <!-- Flash / Error -->
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php $msg = flash('register_success'); if ($msg): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 py-2">
                    <i class="fas fa-check-circle"></i><span><?= htmlspecialchars($msg) ?></span>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="" novalidate>
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($emailVal) ?>"
                                   placeholder="you@example.com" required autofocus>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label fw-semibold mb-0">Password</label>
                            <a href="<?= APP_URL ?>/auth/forgot_password.php" class="text-decoration-none small text-primary">
                                Forgot Password?
                            </a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter your password" required>
                            <button type="button" class="btn password-toggle input-group-text border-start-0"
                                    onclick="togglePassword('password', this)">
                                <i class="fas fa-eye text-muted"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label text-muted small" for="remember">Remember me for 30 days</label>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-login btn-primary w-100 text-white mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>

                <div class="divider"><span>New to QuizMaster?</span></div>

                <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-user-plus me-2"></i>Create Free Account
                </a>
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
    const field = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
