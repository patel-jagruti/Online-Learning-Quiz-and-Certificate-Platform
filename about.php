<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'About Us';
require_once __DIR__ . '/includes/header.php';

$stats = [
    'courses'  => (int)dbRow("SELECT COUNT(*) AS c FROM courses WHERE status = 'published'")['c'],
    'students' => (int)dbRow("SELECT COUNT(*) AS c FROM users WHERE role = 'student'")['c'],
    'quizzes'  => (int)dbRow("SELECT COUNT(*) AS c FROM quizzes")['c'],
];
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 mb-3">About <?= APP_NAME ?></span>
                <h1 class="display-5 fw-bold mb-3">Learning Made Simple, Fun &amp; Effective</h1>
                <p class="lead text-muted">
                    <?= APP_NAME ?> is a modern learning management system that combines
                    structured courses with interactive quizzes, progress tracking and
                    shareable certificates.
                </p>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 text-center h-100 p-4">
                    <div class="mb-3"><i class="fas fa-book-open fa-3x text-primary"></i></div>
                    <h4 class="fw-bold"><?= number_format($stats['courses']) ?> Courses</h4>
                    <p class="text-muted mb-0">Expert-created lessons across a wide range of subjects.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 text-center h-100 p-4">
                    <div class="mb-3"><i class="fas fa-users fa-3x text-success"></i></div>
                    <h4 class="fw-bold"><?= number_format($stats['students']) ?> Students</h4>
                    <p class="text-muted mb-0">Learners advancing their skills every single day.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 text-center h-100 p-4">
                    <div class="mb-3"><i class="fas fa-question-circle fa-3x text-warning"></i></div>
                    <h4 class="fw-bold"><?= number_format($stats['quizzes']) ?> Quizzes</h4>
                    <p class="text-muted mb-0">Interactive assessments that reinforce what you learn.</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="p-4 bg-light rounded-4 h-100">
                    <h5 class="fw-bold"><i class="fas fa-bullseye text-primary me-2"></i>Our Mission</h5>
                    <p class="text-muted mb-0">Make high-quality education accessible to everyone, everywhere.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-light rounded-4 h-100">
                    <h5 class="fw-bold"><i class="fas fa-lightbulb text-warning me-2"></i>Our Vision</h5>
                    <p class="text-muted mb-0">A world where learning is engaging, measurable and rewarding.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-light rounded-4 h-100">
                    <h5 class="fw-bold"><i class="fas fa-heart text-danger me-2"></i>Our Values</h5>
                    <p class="text-muted mb-0">Curiosity, quality, accessibility and continuous improvement.</p>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <a href="<?= APP_URL ?>/courses.php" class="btn btn-primary btn-lg px-5">Start Learning Today</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
