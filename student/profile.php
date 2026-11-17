<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
$db = getDB();
$user = getUser($userId);

if (!$user) {
    redirect(APP_URL . '/auth/logout.php');
}

$tab = in_array($_GET['tab'] ?? '', ['profile', 'password', 'certificates'], true)
    ? $_GET['tab']
    : 'profile';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrfValid()) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    if ($_POST['action'] === 'profile' && empty($errors)) {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $bio   = trim($_POST['bio'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $dup = dbRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($dup) {
                $errors[] = 'That email address is already in use.';
            }
        }

        $avatar = $user['avatar'];
        if (!empty($_FILES['avatar']['name'])) {
            $uploaded = uploadFile($_FILES['avatar'], UPLOAD_PATH . '/avatars/');
            if ($uploaded === false) {
                $errors[] = 'Avatar upload failed. Use a JPG, PNG, GIF or WebP under 5MB.';
            } else {
                if (!empty($user['avatar'])) {
                    @unlink(UPLOAD_PATH . '/avatars/' . $user['avatar']);
                }
                $avatar = $uploaded;
            }
        }

        if (empty($errors)) {
            dbExec(
                "UPDATE users SET name = ?, email = ?, bio = ?, avatar = ? WHERE id = ?",
                [$name, $email, $bio ?: null, $avatar, $userId]
            );
            $_SESSION['user_name']   = $name;
            $_SESSION['user_avatar'] = $avatar;
            flash('success', 'Profile updated successfully.');
            redirect(APP_URL . '/student/profile.php?tab=profile');
        }
        $tab = 'profile';
    }

    if ($_POST['action'] === 'password' && empty($errors)) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (empty($errors)) {
            dbExec("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $userId]);
            flash('success', 'Password changed successfully.');
            redirect(APP_URL . '/student/profile.php?tab=password');
        }
        $tab = 'password';
    }
}

$user = getUser($userId);
$certificates = [];
if ($tab === 'certificates' && hasRole('student')) {
    $certificates = dbRows("
        SELECT cert.*, c.title AS course_title, c.slug AS course_slug
        FROM certificates cert
        JOIN courses c ON c.id = cert.course_id
        WHERE cert.student_id = ?
        ORDER BY cert.issued_at DESC
    ", [$userId]);
}

$dashboardUrl = match ($_SESSION['user_role'] ?? 'student') {
    'admin'      => APP_URL . '/admin/dashboard.php',
    'instructor' => APP_URL . '/instructor/dashboard.php',
    default      => APP_URL . '/student/dashboard.php',
};

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= UPLOAD_URL ?>/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                         class="rounded-circle mx-auto mb-3" width="100" height="100" style="object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold mx-auto mb-3"
                         style="width:100px;height:100px;font-size:2.2rem;">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h5>
                <span class="badge bg-primary-subtle text-primary text-capitalize mb-2"><?= htmlspecialchars($user['role']) ?></span>
                <p class="text-muted small mb-0"><?= htmlspecialchars($user['email']) ?></p>
                <hr>
                <div class="nav flex-column nav-pills text-start gap-1">
                    <a class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>" href="?tab=profile">
                        <i class="fas fa-user me-2"></i>Profile
                    </a>
                    <a class="nav-link <?= $tab === 'password' ? 'active' : '' ?>" href="?tab=password">
                        <i class="fas fa-lock me-2"></i>Password
                    </a>
                    <?php if (hasRole('student')): ?>
                    <a class="nav-link <?= $tab === 'certificates' ? 'active' : '' ?>" href="?tab=certificates">
                        <i class="fas fa-award me-2"></i>Certificates
                    </a>
                    <?php endif; ?>
                </div>
                <hr>
                <a href="<?= $dashboardUrl ?>" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
            </div>
        </div>

        <div class="col-lg-9">
            <?php showFlash(); ?>
            <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>

            <?php if ($tab === 'profile'): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold">Profile Information</h5></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($user['name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Bio</label>
                                <textarea name="bio" class="form-control" rows="4" placeholder="Tell us a little about yourself"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Profile Picture</label>
                                <input type="file" name="avatar" class="form-control" accept="image/*">
                                <div class="form-text">JPG, PNG, GIF or WebP — max 5MB.</div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php elseif ($tab === 'password'): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold">Change Password</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="password">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">New Password</label>
                                <input type="password" name="new_password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-lock me-1"></i>Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold">My Certificates</h5></div>
                <div class="card-body p-0">
                    <?php if (empty($certificates)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-award fa-3x text-muted opacity-50 mb-3"></i>
                        <h6 class="text-muted">No certificates yet</h6>
                        <p class="text-muted small mb-3">Complete a course and pass its quizzes to earn one.</p>
                        <a href="<?= APP_URL ?>/student/courses.php?filter=enrolled" class="btn btn-sm btn-primary">My Courses</a>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($certificates as $cert): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($cert['course_title']) ?></div>
                                    <small class="text-muted">Issued <?= formatDate($cert['issued_at']) ?> &middot; <?= htmlspecialchars($cert['cert_code']) ?></small>
                                </div>
                            </div>
                            <a href="<?= APP_URL ?>/student/certificate.php?id=<?= (int)$cert['id'] ?>" class="btn btn-sm btn-warning fw-semibold">
                                <i class="fas fa-download me-1"></i>View
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
