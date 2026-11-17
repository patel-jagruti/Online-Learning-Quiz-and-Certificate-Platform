<?php
require_once __DIR__.'/../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/student/dashboard.php');
}

$errors  = [];
$success = '';
$formData = ['name' => '', 'email' => '', 'role' => 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = sanitize($_POST['role'] ?? 'student');

    $formData = compact('name', 'email', 'role');

    // Validations
    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!in_array($role, ['student', 'instructor'])) {
        $errors[] = 'Invalid role selected.';
    }

    if (empty($errors)) {
        try {
            $pdo = getDB();

            // Check email uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'This email address is already registered. Please <a href="' . APP_URL . '/auth/login.php">log in</a> instead.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                ");
                $stmt->execute([$name, $email, $hash, $role]);
                $userId = $pdo->lastInsertId();

                // Auto-login
                session_regenerate_id(true);
                $_SESSION['user_id']     = $userId;
                $_SESSION['user_name']   = $name;
                $_SESSION['user_role']   = $role;
                $_SESSION['user_avatar'] = '';

                flash('login_success', 'Welcome to QuizMaster LMS, ' . $name . '! Your account is ready.');
                redirect(APP_URL . '/student/dashboard.php');
            }
        } catch (Exception $e) {
            $errors[] = 'Registration failed due to a system error. Please try again.';
        }
    }
}

$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – <?= APP_NAME ?></title>
    <link href="<?= ASSET_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSET_URL ?>/css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); min-height: 100vh; }
        .auth-card { border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
        .auth-logo { width:72px; height:72px; background:linear-gradient(135deg,#11998e,#38ef7d);
                     border-radius:50%; display:flex; align-items:center; justify-content:center;
                     margin:0 auto 1rem; box-shadow:0 8px 25px rgba(17,153,142,.4); }
        .form-control:focus, .form-select:focus { border-color:#11998e; box-shadow:0 0 0 .2rem rgba(17,153,142,.25); }
        .btn-register { background:linear-gradient(135deg,#11998e,#38ef7d); border:none;
                        padding:12px; font-size:1rem; font-weight:600; color:#fff; }
        .btn-register:hover { opacity:.92; transform:translateY(-1px); color:#fff; }
        .strength-bar { height:5px; border-radius:3px; transition:.3s; }
        .input-group-text { background:#f8f9fa; border-right:none; }
        .form-control, .form-select { border-left:none; }
        .input-group .form-control:focus { border-color:#11998e; }
        .input-group:focus-within .input-group-text { border-color:#11998e; }
        .password-toggle { cursor:pointer; background:#f8f9fa; border-left:none; }
        .role-card { cursor:pointer; border:2px solid #dee2e6; border-radius:12px; padding:1rem;
                     transition:.2s; text-align:center; }
        .role-card:hover, .role-card.selected { border-color:#11998e; background:rgba(17,153,142,.05); }
        .role-card input { display:none; }
    </style>
</head>
<body class="d-flex align-items-center py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

            <div class="text-center mb-3">
                <a href="<?= APP_URL ?>/" class="text-white text-decoration-none opacity-75">
                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
            </div>

            <div class="auth-card card border-0 p-4 p-sm-5">
                <div class="text-center mb-4">
                    <div class="auth-logo">
                        <i class="fas fa-user-plus fa-2x text-white"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Create Your Account</h3>
                    <p class="text-muted small">Join thousands of learners today – it's free!</p>
                </div>

                <!-- Errors -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2">
                    <i class="fas fa-exclamation-triangle me-2"></i><strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <?php foreach ($errors as $err): ?>
                        <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate id="registerForm">
                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= htmlspecialchars($formData['name']) ?>"
                                   placeholder="John Doe" required minlength="2">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($formData['email']) ?>"
                                   placeholder="you@example.com" required>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Min 8 chars, 1 uppercase, 1 number" required
                                   oninput="checkStrength(this.value)">
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
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control" id="confirm_password"
                                   name="confirm_password" placeholder="Repeat your password" required>
                            <button type="button" class="btn password-toggle input-group-text border-start-0"
                                    onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye text-muted"></i>
                            </button>
                        </div>
                        <div id="matchMsg" class="small mt-1"></div>
                    </div>

                    <!-- Role -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">I want to join as</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="role-card <?= $formData['role']==='student' ? 'selected' : '' ?>"
                                       id="role-student" onclick="selectRole('student')">
                                    <input type="radio" name="role" value="student"
                                           <?= $formData['role']==='student' ? 'checked' : '' ?>>
                                    <i class="fas fa-user-graduate fa-2x text-primary mb-2 d-block"></i>
                                    <strong>Student</strong>
                                    <div class="text-muted small">Learn & get certified</div>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="role-card <?= $formData['role']==='instructor' ? 'selected' : '' ?>"
                                       id="role-instructor" onclick="selectRole('instructor')">
                                    <input type="radio" name="role" value="instructor"
                                           <?= $formData['role']==='instructor' ? 'checked' : '' ?>>
                                    <i class="fas fa-chalkboard-teacher fa-2x text-success mb-2 d-block"></i>
                                    <strong>Instructor</strong>
                                    <div class="text-muted small">Teach & share knowledge</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label small text-muted" for="terms">
                                I agree to the <a href="#" class="text-decoration-none">Terms of Service</a>
                                and <a href="#" class="text-decoration-none">Privacy Policy</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-register w-100 mb-3" id="submitBtn">
                        <i class="fas fa-user-plus me-2"></i>Create My Account
                    </button>
                </form>

                <p class="text-center text-muted small mb-0">
                    Already have an account?
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
<script>
function togglePassword(fieldId, btn) {
    const f = document.getElementById(fieldId);
    const i = btn.querySelector('i');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.classList.toggle('fa-eye'); i.classList.toggle('fa-eye-slash');
}
function selectRole(role) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('role-' + role).classList.add('selected');
    document.querySelector('input[name="role"][value="' + role + '"]').checked = true;
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
        const bar = document.getElementById('s' + i);
        bar.className = 'strength-bar flex-fill ' + (i <= score ? colors[score-1] : 'bg-secondary');
    }
    const txt = document.getElementById('strengthText');
    txt.textContent = val.length ? 'Strength: ' + (labels[score-1] || 'Very Weak') : 'Password strength';
    txt.className = 'small ' + (score >= 3 ? 'text-success' : score >= 2 ? 'text-warning' : 'text-danger');
}
document.getElementById('confirm_password').addEventListener('input', function() {
    const pw = document.getElementById('password').value;
    const el = document.getElementById('matchMsg');
    if (!this.value) { el.textContent = ''; return; }
    if (this.value === pw) {
        el.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Passwords match</span>';
    } else {
        el.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Passwords do not match</span>';
    }
});
</script>
</body>
</html>
