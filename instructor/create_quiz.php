<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');

$instructorId = (int)$_SESSION['user_id'];
$db = getDB();

$errors = [];
$form = [
    'title' => '', 'description' => '', 'course_id' => 0,
    'time_limit_minutes' => '', 'pass_score' => 60, 'max_attempts' => 3,
    'shuffle_questions' => 0, 'show_results' => 1,
];

$myCourses = $db->prepare("SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title");
$myCourses->execute([$instructorId]);
$myCourses = $myCourses->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $form['title']       = trim($_POST['title'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['course_id']   = (int)($_POST['course_id'] ?? 0);
    $form['time_limit_minutes'] = trim($_POST['time_limit_minutes'] ?? '');
    $form['pass_score']  = max(0, min(100, (int)($_POST['pass_score'] ?? 60)));
    $form['max_attempts'] = max(1, (int)($_POST['max_attempts'] ?? 3));
    $form['shuffle_questions'] = isset($_POST['shuffle_questions']) ? 1 : 0;
    $form['show_results'] = isset($_POST['show_results']) ? 1 : 0;

    $questions = $_POST['questions'] ?? [];

    if ($form['title'] === '') {
        $errors[] = 'Quiz title is required.';
    }

    $courseId = null;
    if ($form['course_id'] <= 0) {
        $errors[] = 'Please select a course for this quiz.';
    } else {
        $own = $db->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
        $own->execute([$form['course_id'], $instructorId]);
        if (!$own->fetch()) {
            $errors[] = 'Invalid course selected.';
        } else {
            $courseId = $form['course_id'];
        }
    }

    $validQuestions = [];
    $number = 0;
    foreach ($questions as $q) {
        $text = trim($q['text'] ?? '');
        if ($text === '') {
            continue;
        }
        $number++;
        $type = in_array($q['type'] ?? '', ['mcq', 'true_false', 'short_answer'], true) ? $q['type'] : 'mcq';

        if ($type === 'mcq') {
            $filled = array_filter(array_map('trim', $q['options'] ?? []), fn($v) => $v !== '');
            $correct = (int)($q['correct'] ?? -1);
            $correctText = trim((string)($q['options'][$correct] ?? ''));
            if (count($filled) < 2) {
                $errors[] = "Question {$number}: provide at least two options.";
            } elseif ($correctText === '') {
                $errors[] = "Question {$number}: select a valid correct option.";
            }
        } elseif ($type === 'short_answer' && trim($q['answer'] ?? '') === '') {
            $errors[] = "Question {$number}: provide the expected answer.";
        }

        $validQuestions[] = [
            'text'    => $text,
            'type'    => $type,
            'points'  => max(1, (int)($q['points'] ?? 1)),
            'correct' => $q['correct'] ?? null,
            'options' => $q['options'] ?? [],
            'answer'  => trim($q['answer'] ?? ''),
        ];
    }

    if (empty($validQuestions)) {
        $errors[] = 'Add at least one question.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $ins = $db->prepare("
                INSERT INTO quizzes
                    (course_id, title, description, time_limit_minutes, pass_score,
                     max_attempts, shuffle_questions, show_results, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                $courseId,
                $form['title'],
                $form['description'] ?: null,
                $form['time_limit_minutes'] !== '' ? max(1, (int)$form['time_limit_minutes']) : null,
                $form['pass_score'],
                $form['max_attempts'],
                $form['shuffle_questions'],
                $form['show_results'],
            ]);
            $quizId = (int)$db->lastInsertId();

            $insQ = $db->prepare("
                INSERT INTO questions (quiz_id, question_text, type, points, sort_order, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insO = $db->prepare("
                INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)
            ");

            foreach ($validQuestions as $i => $q) {
                $insQ->execute([$quizId, $q['text'], $q['type'], $q['points'], $i + 1]);
                $questionId = (int)$db->lastInsertId();

                if ($q['type'] === 'mcq') {
                    $correct = (int)($q['correct'] ?? -1);
                    foreach ($q['options'] as $oi => $optionText) {
                        $optionText = trim((string)$optionText);
                        if ($optionText === '') {
                            continue;
                        }
                        $insO->execute([$questionId, $optionText, ((int)$oi === $correct ? 1 : 0)]);
                    }
                } elseif ($q['type'] === 'true_false') {
                    $correct = (string)($q['correct'] ?? '0');
                    $insO->execute([$questionId, 'True', $correct === '0' ? 1 : 0]);
                    $insO->execute([$questionId, 'False', $correct === '1' ? 1 : 0]);
                } else {
                    if ($q['answer'] !== '') {
                        $insO->execute([$questionId, $q['answer'], 1]);
                    }
                }
            }

            $db->commit();
            flash('success', 'Quiz created successfully with ' . count($validQuestions) . ' question(s).');
            redirect(APP_URL . '/instructor/quizzes.php');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[create_quiz] ' . $e->getMessage());
            $errors[] = 'Could not save the quiz. Please try again.';
        }
    }
}

$pageTitle = 'Create Quiz';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1" style="margin-left:240px; padding:2rem;">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= APP_URL ?>/instructor/quizzes.php" class="btn btn-sm btn-outline-secondary me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0">Create Quiz</h4>
                <p class="text-muted mb-0">Build a quiz and add questions</p>
            </div>
        </div>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <?php if (empty($myCourses)): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span><i class="fas fa-info-circle me-2"></i>You need to create a course before adding quizzes.</span>
            <a href="<?= APP_URL ?>/instructor/create_course.php" class="btn btn-sm btn-warning fw-semibold">Create Course</a>
        </div>
        <?php endif; ?>

        <form method="POST" id="quizForm">
            <?= csrfField() ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Quiz Details</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?= htmlspecialchars($form['title']) ?>" placeholder="e.g. Algebra Basics Quiz">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Course</label>
                            <select name="course_id" class="form-select" required>
                                <option value="0">— Select Course —</option>
                                <?php foreach ($myCourses as $co): ?>
                                <option value="<?= (int)$co['id'] ?>" <?= (int)$form['course_id'] === (int)$co['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($co['title']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="What is this quiz about?"><?= htmlspecialchars($form['description']) ?></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Time Limit (min)</label>
                            <input type="number" name="time_limit_minutes" class="form-control" min="1"
                                   value="<?= htmlspecialchars((string)$form['time_limit_minutes']) ?>" placeholder="No limit">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Pass Score (%)</label>
                            <input type="number" name="pass_score" class="form-control" min="0" max="100" value="<?= (int)$form['pass_score'] ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Max Attempts</label>
                            <input type="number" name="max_attempts" class="form-control" min="1" value="<?= (int)$form['max_attempts'] ?>">
                        </div>
                        <div class="col-md-3 d-flex flex-column justify-content-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffle_questions" value="1" <?= $form['shuffle_questions'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="shuffle_questions">Shuffle questions</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_results" id="show_results" value="1" <?= $form['show_results'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="show_results">Show results</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Questions</h6>
                    <span class="badge bg-primary" id="questionCountBadge">0 questions</span>
                </div>
                <div class="card-body">
                    <div id="questionsContainer"></div>
                    <button type="button" class="btn btn-outline-primary" onclick="addQuestion()">
                        <i class="fas fa-plus me-2"></i>Add Question
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= APP_URL ?>/instructor/quizzes.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="fas fa-save me-2"></i>Save Quiz
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let qIndex = 0;
const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

function addQuestion() {
    const idx = qIndex++;
    const container = document.getElementById('questionsContainer');
    const div = document.createElement('div');
    div.className = 'card border mb-3 question-block';
    div.dataset.index = idx;
    div.innerHTML = `
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <span class="fw-semibold small">Question <span class="q-num"></span></span>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Question Text <span class="text-danger">*</span></label>
                <textarea name="questions[${idx}][text]" class="form-control" rows="2" placeholder="Enter the question..." required></textarea>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Type</label>
                    <select name="questions[${idx}][type]" class="form-select" onchange="changeType(this, ${idx})">
                        <option value="mcq">Multiple Choice</option>
                        <option value="true_false">True / False</option>
                        <option value="short_answer">Short Answer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Points</label>
                    <input type="number" name="questions[${idx}][points]" class="form-control" min="1" value="1">
                </div>
            </div>
            <div id="options_${idx}">${mcqOptionsHtml(idx)}</div>
        </div>`;
    container.appendChild(div);
    renumberQuestions();
}

function mcqOptionsHtml(idx) {
    let html = '<label class="form-label small fw-semibold">Options <span class="text-muted">(select the correct one)</span></label>';
    for (let i = 0; i < 4; i++) {
        html += `
        <div class="input-group mb-2">
            <span class="input-group-text">
                <input type="radio" name="questions[${idx}][correct]" value="${i}" ${i === 0 ? 'checked' : ''}>
            </span>
            <span class="input-group-text fw-bold">${OPTION_LETTERS[i]}</span>
            <input type="text" name="questions[${idx}][options][${i}]" class="form-control" placeholder="Option ${OPTION_LETTERS[i]}">
        </div>`;
    }
    return html;
}

function trueFalseHtml(idx) {
    return `
    <label class="form-label small fw-semibold">Correct Answer</label>
    <div class="d-flex gap-3">
        <label class="d-flex align-items-center gap-2">
            <input type="radio" name="questions[${idx}][correct]" value="0" checked> True
        </label>
        <label class="d-flex align-items-center gap-2">
            <input type="radio" name="questions[${idx}][correct]" value="1"> False
        </label>
    </div>`;
}

function shortAnswerHtml(idx) {
    return `
    <label class="form-label small fw-semibold">Expected Answer</label>
    <input type="text" name="questions[${idx}][answer]" class="form-control"
           placeholder="Answer is matched case-insensitively">
    <div class="form-text">Students type this answer. Matching is case-insensitive.</div>`;
}

function changeType(select, idx) {
    const wrap = document.getElementById('options_' + idx);
    if (select.value === 'mcq') wrap.innerHTML = mcqOptionsHtml(idx);
    else if (select.value === 'true_false') wrap.innerHTML = trueFalseHtml(idx);
    else wrap.innerHTML = shortAnswerHtml(idx);
}

function removeQuestion(btn) {
    btn.closest('.question-block').remove();
    renumberQuestions();
}

function renumberQuestions() {
    const blocks = document.querySelectorAll('.question-block');
    blocks.forEach((b, i) => {
        b.querySelector('.q-num').textContent = i + 1;
    });
    document.getElementById('questionCountBadge').textContent = blocks.length + ' question' + (blocks.length === 1 ? '' : 's');
}

document.addEventListener('DOMContentLoaded', () => addQuestion());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
