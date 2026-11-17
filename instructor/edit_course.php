<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = $_SESSION['user_id'];
$db = getDB();

$courseId = (int)($_GET['id'] ?? $_POST['course_id'] ?? 0);
if (!$courseId) { flash('error', 'No course specified.'); redirect(APP_URL . '/instructor/courses.php'); }

// Verify ownership
$cStmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$cStmt->execute([$courseId, $instructorId]);
$courseData = $cStmt->fetch();
if (!$courseData) { flash('error', 'Course not found.'); redirect(APP_URL . '/instructor/courses.php'); }

$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$lessons    = $db->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order ASC");
$lessons->execute([$courseId]);
$lessons = $lessons->fetchAll();

$errors = [];
$step   = (int)($_POST['step'] ?? $_GET['step'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);

    if ($step === 1) {
        $title       = trim($_POST['title'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $status      = in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'draft';
        $price       = (float)($_POST['price'] ?? 0);

        if (!$title) $errors[] = 'Title is required.';
        if (!$slug)  $slug = generateSlug($title);

        // Check slug uniqueness (exclude current course)
        $slugCheck = $db->prepare("SELECT id FROM courses WHERE slug = ? AND id != ?");
        $slugCheck->execute([$slug, $courseId]);
        if ($slugCheck->fetch()) $slug = $slug . '-' . substr(uniqid(), -4);

        if (empty($errors)) {
            $db->prepare("UPDATE courses SET title=?,slug=?,description=?,category_id=?,status=?,price=?,updated_at=NOW() WHERE id=?")
               ->execute([$title,$slug,$description,$categoryId ?: null,$status,$price,$courseId]);
            header('Location: ' . APP_URL . '/instructor/edit_course.php?id=' . $courseId . '&step=2');
            exit;
        }
    }

    if ($step === 2) {
        if (!empty($_FILES['thumbnail']['name'])) {
            $uploaded = uploadFile($_FILES['thumbnail'], UPLOAD_PATH . '/thumbnails/');
            if ($uploaded) {
                // Remove old thumbnail
                if (!empty($courseData['thumbnail'])) {
                    @unlink(UPLOAD_PATH . '/thumbnails/' . $courseData['thumbnail']);
                }
                $db->prepare("UPDATE courses SET thumbnail=?,updated_at=NOW() WHERE id=?")->execute([$uploaded,$courseId]);
            } else {
                $errors[] = 'Thumbnail upload failed.';
            }
        }
        if (empty($errors)) {
            header('Location: ' . APP_URL . '/instructor/edit_course.php?id=' . $courseId . '&step=3');
            exit;
        }
    }

    if ($step === 3) {
        $titles    = $_POST['lesson_title']    ?? [];
        $contents  = $_POST['lesson_content']  ?? [];
        $videoUrls = $_POST['lesson_video']    ?? [];
        $durations = $_POST['lesson_duration'] ?? [];
        $lessonIds = $_POST['lesson_id']       ?? [];

        // Delete removed lessons
        $keepIds = array_filter(array_map('intval', $lessonIds));
        if (!empty($keepIds)) {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            $db->prepare("DELETE FROM lessons WHERE course_id = ? AND id NOT IN ($placeholders)")
               ->execute(array_merge([$courseId], $keepIds));
        } else {
            $db->prepare("DELETE FROM lessons WHERE course_id = ?")->execute([$courseId]);
        }

        foreach ($titles as $i => $lt) {
            $lt = trim($lt);
            if (!$lt) continue;
            $lid = (int)($lessonIds[$i] ?? 0);
            if ($lid) {
                $db->prepare("UPDATE lessons SET title=?,content=?,video_url=?,duration_minutes=?,sort_order=? WHERE id=? AND course_id=?")
                   ->execute([$lt, trim($contents[$i]??''), trim($videoUrls[$i]??''), (int)($durations[$i]??0), $i+1, $lid, $courseId]);
            } else {
                $db->prepare("INSERT INTO lessons (course_id,title,content,video_url,duration_minutes,sort_order,created_at) VALUES (?,?,?,?,?,?,NOW())")
                   ->execute([$courseId, $lt, trim($contents[$i]??''), trim($videoUrls[$i]??''), (int)($durations[$i]??0), $i+1]);
            }
        }
        flash('success', 'Course updated successfully!');
        redirect(APP_URL . '/instructor/courses.php');
    }
}

// Reload course data for display
$cStmt->execute([$courseId, $instructorId]);
$courseData = $cStmt->fetch();
$lessons = $db->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order ASC");
$lessons->execute([$courseId]);
$lessons = $lessons->fetchAll();

$pageTitle = 'Edit Course: ' . $courseData['title'];
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
                <h4 class="fw-bold mb-0">Edit Course</h4>
                <p class="text-muted mb-0"><?= htmlspecialchars($courseData['title']) ?></p>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="d-flex align-items-center gap-2 mb-4">
            <?php
            $stepLabels = ['Basic Info','Thumbnail','Lessons'];
            foreach ($stepLabels as $si => $sl):
                $sNum = $si + 1; $active = $sNum === $step; $done = $sNum < $step;
            ?>
            <div class="d-flex align-items-center <?= $si > 0 ? 'ms-1' : '' ?>">
                <?php if ($si > 0): ?><div style="width:30px;height:2px;background:<?= $done ? '#0d6efd':'#dee2e6'?>;"></div><?php endif; ?>
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                         style="width:32px;height:32px;background:<?=$active?'#0d6efd':($done?'#198754':'#dee2e6')?>;color:<?=($active||$done)?'#fff':'#6c757d'?>;">
                        <?=$done?'<i class="fas fa-check" style="font-size:.7rem;"></i>':$sNum?>
                    </div>
                    <a href="<?= APP_URL ?>/instructor/edit_course.php?id=<?= $courseId ?>&step=<?= $sNum ?>" class="small fw-semibold text-decoration-none <?=$active?'text-primary':($done?'text-success':'text-muted')?>"><?=$sl?></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <!-- Step 1 -->
        <?php if ($step === 1): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Step 1: Basic Information</h6></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="step" value="1">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Course Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="courseTitle" class="form-control" value="<?= htmlspecialchars($courseData['title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted">/courses/</span>
                            <input type="text" name="slug" id="courseSlug" class="form-control" value="<?= htmlspecialchars($courseData['slug']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($courseData['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">— Select —</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $courseData['category_id'] == $cat['id'] ? 'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['draft','published','archived'] as $s): ?>
                                <option value="<?=$s?>" <?=$courseData['status']===$s?'selected':''?>><?=ucfirst($s)?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price ($)</label>
                            <input type="number" name="price" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($courseData['price']) ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">Next: Thumbnail <i class="fas fa-arrow-right ms-2"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Step 2 -->
        <?php elseif ($step === 2): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Step 2: Course Thumbnail</h6></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <?php if (!empty($courseData['thumbnail'])): ?>
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Current thumbnail:</div>
                        <img src="<?= UPLOAD_URL ?>/thumbnails/<?= htmlspecialchars($courseData['thumbnail']) ?>" class="rounded img-thumbnail" style="max-width:280px;">
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload New Thumbnail</label>
                        <input type="file" name="thumbnail" class="form-control" accept=".jpg,.jpeg,.png,.gif" onchange="previewThumb(this)">
                    </div>
                    <div id="thumbPreview" style="display:none;" class="mb-3">
                        <img id="thumbPreviewImg" src="" class="rounded" style="max-width:280px;">
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="?id=<?=$courseId?>&step=3" class="btn btn-outline-secondary">Skip</a>
                        <button type="submit" class="btn btn-primary px-4">Next: Lessons <i class="fas fa-arrow-right ms-2"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Step 3 -->
        <?php elseif ($step === 3): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Step 3: Lessons Manager</h6>
                <span class="badge bg-primary" id="lessonCount">0 lessons</span>
            </div>
            <div class="card-body">
                <form method="POST" id="lessonsForm">
                    <input type="hidden" name="step" value="3">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <div id="lessonsContainer" class="mb-3"></div>
                    <button type="button" class="btn btn-outline-primary mb-4" onclick="addLesson()">
                        <i class="fas fa-plus me-2"></i>Add Lesson
                    </button>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('courseTitle');
    const slugInput  = document.getElementById('courseSlug');
    if (titleInput && slugInput) {
        slugInput.dataset.manual = '1';
        titleInput.addEventListener('input', function() {
            if (!slugInput.dataset.manualEdit) {
                slugInput.value = titleInput.value.toLowerCase().trim().replace(/[^\w\s-]/g,'').replace(/[\s_]+/g,'-').replace(/^-+|-+$/g,'');
            }
        });
        slugInput.addEventListener('focus', () => slugInput.dataset.manualEdit = '1');
    }

    // Preload existing lessons
    const existingLessons = <?= json_encode($lessons) ?>;
    existingLessons.forEach(l => addLesson(l));
    updateLessonCount();
});

function previewThumb(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('thumbPreviewImg').src = e.target.result;
            document.getElementById('thumbPreview').style.display = '';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

let lessonIndex = 0;
function addLesson(data = {}) {
    const idx = lessonIndex++;
    const container = document.getElementById('lessonsContainer');
    const div = document.createElement('div');
    div.className = 'lesson-card card border mb-2';
    div.setAttribute('draggable', 'true');
    div.innerHTML = `
        <div class="card-header d-flex align-items-center gap-2 py-2 bg-light">
            <span class="text-muted" style="cursor:grab;"><i class="fas fa-grip-vertical"></i></span>
            <input type="hidden" name="lesson_id[]" value="${data.id||''}">
            <span class="fw-semibold small">Lesson ${idx+1}: ${escHtml(data.title||'New Lesson')}</span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="this.closest('.lesson-card').remove();updateLessonCount()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="card-body row g-2">
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                <input type="text" name="lesson_title[]" class="form-control form-control-sm" value="${escHtml(data.title||'')}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Duration (min)</label>
                <input type="number" name="lesson_duration[]" class="form-control form-control-sm" min="0" value="${data.duration_minutes||0}">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Content</label>
                <textarea name="lesson_content[]" class="form-control form-control-sm" rows="2">${escHtml(data.content||'')}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Video URL</label>
                <input type="url" name="lesson_video[]" class="form-control form-control-sm" placeholder="https://youtube.com/..." value="${escHtml(data.video_url||'')}" onchange="previewVideo(this)">
                <div class="video-preview mt-1"></div>
            </div>
        </div>`;
    container.appendChild(div);
    updateLessonCount();
}
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function updateLessonCount() {
    const c = document.querySelectorAll('.lesson-card').length;
    const el = document.getElementById('lessonCount');
    if (el) el.textContent = c + ' lesson' + (c!==1?'s':'');
}
function previewVideo(input) {
    const url = input.value;
    const preview = input.nextElementSibling;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
    preview.innerHTML = m ? `<iframe width="280" height="158" src="https://www.youtube.com/embed/${m[1]}" frameborder="0" allowfullscreen class="rounded"></iframe>` : '';
}

// Drag to reorder
let dragSrc = null;
document.addEventListener('dragstart', e => { dragSrc = e.target.closest('.lesson-card'); if(dragSrc) dragSrc.style.opacity='.4'; });
document.addEventListener('dragend', e => { const c=e.target.closest('.lesson-card'); if(c) c.style.opacity='1'; });
document.addEventListener('dragover', e => {
    e.preventDefault();
    const target = e.target.closest('.lesson-card');
    if (target && dragSrc && target !== dragSrc) {
        const cont = document.getElementById('lessonsContainer');
        const cards = [...cont.querySelectorAll('.lesson-card')];
        cont.insertBefore(dragSrc, cards.indexOf(dragSrc) > cards.indexOf(target) ? target : target.nextSibling);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
