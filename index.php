<?php
require_once __DIR__.'/config/config.php';

// Redirect logged-in users to their role dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'student';
    if ($role === 'admin') redirect(APP_URL . '/admin/dashboard.php');
    elseif ($role === 'instructor') redirect(APP_URL . '/instructor/dashboard.php');
    else redirect(APP_URL . '/student/dashboard.php');
}

$pageTitle = 'Welcome';

// Fetch stats from DB
$pdo = getDB();
$stats = ['courses' => 0, 'students' => 0, 'quizzes' => 0, 'certificates' => 0];
try {
    $stats['courses']      = $pdo->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn();
    $stats['students']     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $stats['quizzes']      = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
    $stats['certificates'] = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
} catch (Exception $e) { /* ignore if tables not ready */ }

// Fetch 6 featured published courses
$featuredCourses = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, u.name AS instructor_name, cat.name AS category_name, cat.icon AS category_icon,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrolled_count
        FROM courses c
        LEFT JOIN users u ON c.instructor_id = u.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'published'
        ORDER BY c.created_at DESC
        LIMIT 6
    ");
    $featuredCourses = $stmt->fetchAll();
} catch (Exception $e) { /* ignore */ }

require_once __DIR__.'/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-overlay"></div>
    <div class="container hero-content text-center text-white">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <span class="badge bg-warning text-dark mb-3 px-3 py-2 fs-6">
                    <i class="fas fa-star me-1"></i> #1 Online Learning Platform
                </span>
                <h1 class="display-3 fw-bold mb-4">Welcome to <span class="text-warning">QuizMaster</span> LMS</h1>
                <p class="lead mb-5 opacity-90">
                    Unlock your potential with expert-led courses, interactive quizzes,<br>
                    and earn certificates that matter. Learn at your own pace, anywhere.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="<?= APP_URL ?>/courses.php" class="btn btn-warning btn-lg px-5 shadow">
                        <i class="fas fa-graduation-cap me-2"></i>Browse Courses
                    </a>
                    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-outline-light btn-lg px-5">
                        <i class="fas fa-rocket me-2"></i>Get Started Free
                    </a>
                </div>
                <div class="mt-4 opacity-75">
                    <small><i class="fas fa-check-circle text-success me-1"></i> No credit card required &nbsp;
                    <i class="fas fa-check-circle text-success me-1"></i> Cancel anytime</small>
                </div>
            </div>
        </div>
    </div>
    <div class="hero-wave">
        <svg viewBox="0 0 1440 80" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z" fill="#f8f9fa"/>
        </svg>
    </div>
</section>

<!-- Stats Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3">
                <div class="stat-card card border-0 shadow-sm h-100 py-4">
                    <div class="stat-icon mb-3"><i class="fas fa-book-open fa-2x text-primary"></i></div>
                    <h2 class="display-6 fw-bold text-primary counter"><?= number_format($stats['courses']) ?></h2>
                    <p class="text-muted mb-0">Total Courses</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card card border-0 shadow-sm h-100 py-4">
                    <div class="stat-icon mb-3"><i class="fas fa-users fa-2x text-success"></i></div>
                    <h2 class="display-6 fw-bold text-success counter"><?= number_format($stats['students']) ?></h2>
                    <p class="text-muted mb-0">Students Enrolled</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card card border-0 shadow-sm h-100 py-4">
                    <div class="stat-icon mb-3"><i class="fas fa-question-circle fa-2x text-warning"></i></div>
                    <h2 class="display-6 fw-bold text-warning counter"><?= number_format($stats['quizzes']) ?></h2>
                    <p class="text-muted mb-0">Quizzes Available</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card card border-0 shadow-sm h-100 py-4">
                    <div class="stat-icon mb-3"><i class="fas fa-certificate fa-2x text-danger"></i></div>
                    <h2 class="display-6 fw-bold text-danger counter"><?= number_format($stats['certificates']) ?></h2>
                    <p class="text-muted mb-0">Certificates Issued</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Courses -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 mb-2">Featured Courses</span>
            <h2 class="display-5 fw-bold">Explore Top Courses</h2>
            <p class="text-muted lead">Handpicked courses to kickstart your learning journey</p>
        </div>
        <?php if (empty($featuredCourses)): ?>
        <div class="text-center py-5">
            <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">No courses published yet</h4>
            <p class="text-muted">Be the first to explore when courses go live!</p>
            <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary">Get Notified</a>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($featuredCourses as $course): ?>
            <div class="col-md-6 col-lg-4">
                <div class="course-card card border-0 shadow-sm h-100">
                    <div class="course-thumbnail position-relative overflow-hidden">
                        <?php if (!empty($course['thumbnail'])): ?>
                            <img src="<?= APP_URL ?>/uploads/thumbnails/<?= htmlspecialchars($course['thumbnail']) ?>"
                                 class="card-img-top" alt="<?= htmlspecialchars($course['title']) ?>"
                                 style="height:200px;object-fit:cover;">
                        <?php else: ?>
                            <div class="course-thumb-placeholder d-flex align-items-center justify-content-center"
                                 style="height:200px;background:linear-gradient(135deg,#667eea,#764ba2);">
                                <i class="fas fa-graduation-cap fa-3x text-white opacity-75"></i>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($course['category_name'])): ?>
                        <span class="badge bg-primary position-absolute top-0 start-0 m-2">
                            <?= htmlspecialchars($course['category_icon'] ?? '') ?>
                            <?= htmlspecialchars($course['category_name']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ((float)$course['price'] == 0): ?>
                        <span class="badge bg-success position-absolute top-0 end-0 m-2">FREE</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fw-semibold"><?= htmlspecialchars($course['title']) ?></h5>
                        <p class="card-text text-muted small flex-grow-1">
                            <?= htmlspecialchars(truncate($course['description'] ?? '', 100)) ?>
                        </p>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-xs rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="width:28px;height:28px;font-size:12px;">
                                    <?= strtoupper(substr($course['instructor_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($course['instructor_name'] ?? 'Unknown') ?></small>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-users me-1"></i><?= number_format($course['enrolled_count']) ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-primary fs-5">
                                <?= (float)$course['price'] > 0 ? '₹'.number_format($course['price'], 2) : 'Free' ?>
                            </span>
                            <a href="<?= APP_URL ?>/courses/view.php?slug=<?= urlencode($course['slug']) ?>"
                               class="btn btn-sm btn-primary">Enroll Now</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="<?= APP_URL ?>/courses.php" class="btn btn-outline-primary btn-lg px-5">
                View All Courses <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- How It Works -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-success-subtle text-success px-3 py-2 mb-2">Simple Process</span>
            <h2 class="display-5 fw-bold">How It Works</h2>
            <p class="text-muted lead">Get started in three easy steps</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4 text-center">
                <div class="how-card p-4">
                    <div class="step-circle mx-auto mb-4">
                        <span class="step-number">1</span>
                        <i class="fas fa-user-plus fa-2x text-primary"></i>
                    </div>
                    <h4 class="fw-bold">Register Free</h4>
                    <p class="text-muted">Create your free account in seconds. No payment required to get started.</p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="how-card p-4">
                    <div class="step-circle mx-auto mb-4">
                        <span class="step-number">2</span>
                        <i class="fas fa-search fa-2x text-success"></i>
                    </div>
                    <h4 class="fw-bold">Enroll in Courses</h4>
                    <p class="text-muted">Browse hundreds of courses and enroll in the ones that match your goals.</p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="how-card p-4">
                    <div class="step-circle mx-auto mb-4">
                        <span class="step-number">3</span>
                        <i class="fas fa-trophy fa-2x text-warning"></i>
                    </div>
                    <h4 class="fw-bold">Learn &amp; Get Certified</h4>
                    <p class="text-muted">Complete lessons, pass quizzes, and earn certificates to showcase your skills.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-warning-subtle text-warning px-3 py-2 mb-2">Student Stories</span>
            <h2 class="display-5 fw-bold">What Our Students Say</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="testimonial-card card border-0 shadow-sm h-100 p-4">
                    <div class="d-flex mb-3">
                        <?php for($i=0;$i<5;$i++): ?>
                        <i class="fas fa-star text-warning me-1"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted mb-4">"QuizMaster transformed my learning experience. The interactive quizzes helped me retain information much better than traditional studying."</p>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width:48px;height:48px;">A</div>
                        <div>
                            <h6 class="mb-0 fw-semibold">Arjun Sharma</h6>
                            <small class="text-muted">Computer Science Student</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card card border-0 shadow-sm h-100 p-4">
                    <div class="d-flex mb-3">
                        <?php for($i=0;$i<5;$i++): ?>
                        <i class="fas fa-star text-warning me-1"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted mb-4">"I earned 3 certificates in just 2 months. The platform is intuitive, the courses are comprehensive, and the support team is amazing!"</p>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle bg-success text-white d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width:48px;height:48px;">P</div>
                        <div>
                            <h6 class="mb-0 fw-semibold">Priya Nair</h6>
                            <small class="text-muted">Marketing Professional</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card card border-0 shadow-sm h-100 p-4">
                    <div class="d-flex mb-3">
                        <?php for($i=0;$i<5;$i++): ?>
                        <i class="fas fa-star text-warning me-1"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted mb-4">"As an instructor, the platform makes it so easy to create engaging content. My students love the quiz features and progress tracking!"</p>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle bg-warning text-white d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width:48px;height:48px;">R</div>
                        <div>
                            <h6 class="mb-0 fw-semibold">Ravi Kumar</h6>
                            <small class="text-muted">Instructor &amp; Educator</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer CTA -->
<section class="cta-section py-5 text-white text-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <h2 class="display-5 fw-bold mb-3">Ready to Start Learning?</h2>
                <p class="lead mb-4 opacity-90">Join thousands of students who are already advancing their careers with QuizMaster LMS.</p>
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-warning btn-lg px-5">
                        <i class="fas fa-rocket me-2"></i>Start Free Today
                    </a>
                    <a href="<?= APP_URL ?>/courses.php" class="btn btn-outline-light btn-lg px-5">
                        Browse Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__.'/includes/footer.php'; ?>
