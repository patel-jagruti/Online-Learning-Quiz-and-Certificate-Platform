<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = $_SESSION['user_id'];
$db = getDB();

$errors = [];
$step   = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$courseId = null;

// Categories for dropdown
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// --- POST handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);

    if ($step === 1) {
        // Basic info
        $title       = trim($_POST['title'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $status      = in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'draft';
        $price       = (float)($_POST['price'] ?? 0);

        if (!$title) $errors[] = 'Title is required.';
        if (!$slug)  $slug = generateSlug($title);

        // Check slug uniqueness
        $slugCheck = $db->prepare("SELECT id FROM courses WHERE slug = ? AND instructor_id != ?");
        $slugCheck->execute([$slug, $instructorId]);
        if ($slugCheck->fetch()) {
            $slug = $slug . '-' . substr(uniqid(), -4);
        }

        if (empty($errors)) {
            $ins = $db->prepare("INSERT INTO courses (title, slug, description, instructor_id, category_id, status, price, created_at, updated_at)
                                  VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
            $ins->execute([$title, $slug, $description, $instructorId, $categoryId ?: null, $status, $price]);
            $courseId = $db->lastInsertId();
            // Move to step 2
            header('Location: ' . APP_URL . '/instructor/create_course.php?step=2&course_id=' . $courseId);
            exit;
        }
    }

    if ($step === 2) {
        $courseId = (int)($_POST['course_id'] ?? 0);
        // Thumbnail upload
        if (!empty($_FILES['thumbnail']['name'])) {
            $uploaded = uploadFile($_FILES['thumbnail'], UPLOAD_PATH . '/thumbnails/');
            if ($uploaded) {
                $db->prepare("UPDATE courses SET thumbnail = ?, updated_at = NOW() WHERE id = ? AND instructor_id = ?")
                   ->execute([$uploaded, $courseId, $instructorId]);
            } else {
                $errors[] = 'Thumbnail upload failed. Allowed: jpg, png, jpeg, gif.';
            }
        }
        if (empty($errors)) {
            header('Location: ' . APP_URL . '/instructor/create_course.php?step=3&course_id=' . $courseId);
            exit;
        }
    }

    if ($step === 3) {
        $courseId   = (int)($_POST['course_id'] ?? 0);
        $titles     = $_POST['lesson_title']    ?? [];
        $contents   = $_POST['lesson_content']  ?? [];
        $videoUrls  = $_POST['lesson_video']    ?? [];
        $durations  = $_POST['lesson_duration'] ?? [];

        // Delete old lessons and re-insert
        $db->prepare("DELETE FROM lessons WHERE course_id = ?")->execute([$courseId]);

        foreach ($titles as $i => $lt) {
            $lt = trim($lt);
            if (!$lt) continue;
            $db->prepare("INSERT INTO lessons (course_id, title, content, video_url, duration_minutes, sort_order, created_at)
                          VALUES (?,?,?,?,?,?,NOW())")
               ->execute([
                   $courseId,
                   $lt,
                   trim($contents[$i] ?? ''),
                   trim($videoUrls[$i] ?? ''),
                   (int)($durations[$i] ?? 0),
                   $i + 1
               ]);
        }
        flash('success', 'Course created successfully with lessons!');
        redirect(APP_URL . '/instructor/courses.php');
    }
}

// Step 2/3: load existing course data
$courseData = null;
if (isset($_GET['course_id'])) {
    $courseId = (int)$_GET['course_id'];
    $cStmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    $cStmt->execute([$courseId, $instructorId]);
    $courseData = $cStmt->fetch();
    if (!$courseData) {
        flash('error', 'Course not found.');
        redirect(APP_URL . '/instructor/courses.php');
    }
}

$pageTitle = 'Create Course';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1" style="margin-left:240px; padding:2rem;">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= APP_URL ?>/instructor/courses.php" class="btn btn-sm btn-outline-secondary me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0">Create New Course</h4>
                <p class="text-muted mb-0">Follow the steps to publish your course</p>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="d-flex align-items-center gap-2 mb-4">
            <?php
            $stepLabels = ['Basic Info', 'Thumbnail', 'Lessons'];
            foreach ($stepLabels as $si => $sl):
                $sNum = $si + 1;
                $active  = $sNum === $step;
                $done    = $sNum < $step;
            ?>
            <div class="d-flex align-items-center <?= $si > 0 ? 'ms-1' : '' ?>">
                <?php if ($si > 0): ?>
                    <div style="width:30px;height:2px;background:<?= $done ? '#0d6efd' : '#dee2e6' ?>;"></div>
                <?php endif; ?>
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                         style="width:32px;height:32px;background:<?= $active ? '#0d6efd' : ($done ? '#198754' : '#dee2e6') ?>;color:<?= ($active||$done) ? '#fff' : '#6c757d' ?>;">
                        <?= $done ? '<i class="fas fa-check" style="font-size:.7rem;"></i>' : $sNum ?>
                    </div>
                    <span class="small fw-semibold <?= $active ? 'text-primary' : ($done ? 'text-success' : 'text-muted') ?>"><?= $sl ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <!-- Step 1 -->
        <?php if ($step === 1): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Step 1: Basic Information</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="step" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Course Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="courseTitle" class="form-control" placeholder="e.g. Introduction to Python" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted">/courses/</span>
                            <input type="text" name="slug" id="courseSlug" class="form-control" placeholder="auto-generated" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
                        </div>
                        <div class="form-text">Leave blank to auto-generate from title.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Describe your course..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= (($_POST['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= (($_POST['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
                                <option value="archived" <?= (($_POST['status'] ?? '') === 'archived') ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price ($)</label>
                            <input type="number" name="price" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">
                            Next: Thumbnail <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Step 2 -->
        <?php elseif ($step === 2 && $courseData): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Step 2: Course Thumbnail</h6></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Thumbnail Image</label>
                        <input type="file" name="thumbnail" id="thumbnailInput" class="form-control" accept=".jpg,.jpeg,.png,.gif" onchange="previewThumbnail(this)">
                        <div class="form-text">Recommended: 1280×720px (16:9). Max 2MB. JPG/PNG/GIF.</div>
                    </div>
                    <div id="thumbnailPreview" class="mb-3" style="display:none;">
                        <label class="form-label fw-semibold">Preview</label><br>
                        <img id="previewImg" src="" alt="Preview" class="rounded" style="max-width:320px;max-height:180px;object-fit:cover;border:2px solid #dee2e6;">
                    </div>

                    <?php if (!empty($courseData['thumbnail'])): ?>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Current thumbnail:</div>
                        <img src="<?= UPLOAD_URL ?>/thumbnails/<?= htmlspecialchars($courseData['thumbnail']) ?>" alt="Thumbnail" class="rounded" style="max-width:200px;max-height:120px;object-fit:cover;">
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between">
                        <a href="<?= APP_URL ?>/instructor/create_course.php?step=3&course_id=<?= $courseId ?>" class="btn btn-outline-secondary">
                            Skip this step
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            Next: Lessons <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Step 3 -->
        <?php elseif ($step === 3 && $courseData): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Step 3: Lessons Manager</h6>
                <span class="badge bg-primary" id="lessonCount">0 lessons</span>
            </div>
            <div class="card-body">
                <form method="POST" id="lessonsForm">
                    <input type="hidden" name="step" value="3">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">

                    <div id="lessonsContainer" class="mb-3">
                        <!-- Lessons added dynamically -->
                    </div>

                    <button type="button" class="btn btn-outline-primary mb-4" onclick="addLesson()">
                        <i class="fas fa-plus me-2"></i>Add Lesson
                    </button>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-check me-2"></i>Save & Finish
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Step 1: Slug auto-generation
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('courseTitle');
    const slugInput  = document.getElementById('courseSlug');
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            if (!slugInput.dataset.manual) {
                slugInput.value = titleInput.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/[\s_]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        });
        slugInput.addEventListener('input', function() {
            slugInput.dataset.manual = '1';
        });
    }
    updateLessonCount();
});

// Step 2: Thumbnail preview
function previewThumbnail(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('thumbnailPreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Step 3: Lessons
let lessonIndex = 0;

function addLesson(data = {}) {
    const idx = lessonIndex++;
    const container = document.getElementById('lessonsContainer');
    const div = document.createElement('div');
    div.className = 'lesson-card card border mb-2';
    div.dataset.index = idx;
    div.innerHTML = `
        <div class="card-header d-flex align-items-center gap-2 py-2 bg-light">
            <span class="drag-handle text-muted me-2" style="cursor:grab;"><i class="fas fa-grip-vertical"></i></span>
            <span class="fw-semibold small">Lesson ${idx+1}</span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removeLesson(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="card-body row g-2">
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                <input type="text" name="lesson_title[]" class="form-control form-control-sm" placeholder="Lesson title" value="${escHtml(data.title||'')}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Duration (min)</label>
                <input type="number" name="lesson_duration[]" class="form-control form-control-sm" min="0" value="${escHtml(String(data.duration_minutes||0))}">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-info w-100" onclick="toggleContent(this)">
                    <i class="fas fa-chevron-down"></i> Content
                </button>
            </div>
            <div class="col-12 lesson-content-wrap" style="display:none;">
                <label class="form-label small fw-semibold">Content</label>
                <textarea name="lesson_content[]" class="form-control form-control-sm" rows="3" placeholder="Lesson content...">${escHtml(data.content||'')}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Video URL</label>
                <input type="url" name="lesson_video[]" class="form-control form-control-sm video-url-input" placeholder="https://youtube.com/..." value="${escHtml(data.video_url||'')}" onchange="previewVideo(this)">
                <div class="video-preview mt-2"></div>
            </div>
        </div>`;
    container.appendChild(div);
    updateLessonCount();
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function removeLesson(btn) {
    btn.closest('.lesson-card').remove();
    updateLessonCount();
}

function toggleContent(btn) {
    const wrap = btn.closest('.card-body').querySelector('.lesson-content-wrap');
    wrap.style.display = wrap.style.display === 'none' ? '' : 'none';
}

function updateLessonCount() {
    const count = document.querySelectorAll('.lesson-card').length;
    const el = document.getElementById('lessonCount');
    if (el) el.textContent = count + ' lesson' + (count !== 1 ? 's' : '');
}

function previewVideo(input) {
    const url = input.value;
    const preview = input.nextElementSibling;
    const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
    if (ytMatch) {
        preview.innerHTML = `<iframe width="280" height="158" src="https://www.youtube.com/embed/${ytMatch[1]}" frameborder="0" allowfullscreen class="rounded"></iframe>`;
    } else {
        preview.innerHTML = '';
    }
}

// Sortable via drag (simple re-ordering)
(function initDnD() {
    let dragSrc = null;
    document.addEventListener('dragstart', function(e) {
        const card = e.target.closest('.lesson-card');
        if (card) { dragSrc = card; card.style.opacity = '.4'; }
    });
    document.addEventListener('dragend', function(e) {
        const card = e.target.closest('.lesson-card');
        if (card) card.style.opacity = '1';
    });
    document.addEventListener('dragover', function(e) {
        e.preventDefault();
        const card = e.target.closest('.lesson-card');
        if (card && dragSrc && card !== dragSrc) {
            const container = document.getElementById('lessonsContainer');
            const cards = [...container.querySelectorAll('.lesson-card')];
            const srcIdx = cards.indexOf(dragSrc);
            const tgtIdx = cards.indexOf(card);
            if (srcIdx > tgtIdx) container.insertBefore(dragSrc, card);
            else container.insertBefore(dragSrc, card.nextSibling);
        }
    });
    // Make lesson cards draggable
    document.addEventListener('mousedown', function(e) {
        const card = e.target.closest('.lesson-card');
        if (card) card.setAttribute('draggable', 'true');
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
