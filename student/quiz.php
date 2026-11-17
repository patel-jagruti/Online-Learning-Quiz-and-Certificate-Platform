<?php
require_once __DIR__.'/../config/config.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];
$quizId = (int)($_GET['id'] ?? 0);

if (!$quizId) redirect(APP_URL.'/student/courses.php');

// Fetch quiz
$stmt = $db->prepare("SELECT q.*, c.id as course_id, c.title as course_title FROM quizzes q LEFT JOIN courses c ON c.id = q.course_id WHERE q.id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) { flash('error','Quiz not found.'); redirect(APP_URL.'/student/courses.php'); }

// Check enrollment if course-based quiz
if ($quiz['course_id']) {
    if (!isEnrolled($userId, $quiz['course_id'])) {
        flash('error','You must be enrolled in this course to take this quiz.');
        redirect(APP_URL.'/student/course_view.php?id='.$quiz['course_id']);
    }
}

// Check max attempts
$attemptCount = getAttemptCount($userId, $quizId);
if ($attemptCount >= $quiz['max_attempts']) {
    flash('error','You have reached the maximum number of attempts for this quiz.');
    redirect(APP_URL.'/student/'.($quiz['course_id'] ? 'course_view.php?id='.$quiz['course_id'] : 'dashboard.php'));
}

// Create new attempt on page load (only once — check for existing in_progress)
$existingStmt = $db->prepare("SELECT * FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND status='in_progress' ORDER BY started_at DESC LIMIT 1");
$existingStmt->execute([$quizId, $userId]);
$attempt = $existingStmt->fetch();
if (!$attempt) {
    $insertStmt = $db->prepare("INSERT INTO quiz_attempts (quiz_id, student_id, started_at, status) VALUES (?,?,NOW(),'in_progress')");
    $insertStmt->execute([$quizId, $userId]);
    $attemptId = $db->lastInsertId();
    $attempt = ['id' => $attemptId, 'started_at' => date('Y-m-d H:i:s')];
}

// Fetch questions
$questionsStmt = $db->prepare("
    SELECT q.*, GROUP_CONCAT(o.id,'::',o.option_text ORDER BY o.id SEPARATOR '||') as options_raw
    FROM questions q
    LEFT JOIN options o ON o.question_id = q.id
    WHERE q.quiz_id = ?
    GROUP BY q.id
    ORDER BY ".($quiz['shuffle_questions'] ? 'RAND()' : 'q.sort_order, q.id')."
");
$questionsStmt->execute([$quizId]);
$questions = $questionsStmt->fetchAll();

// Parse options
foreach ($questions as &$q) {
    $q['options'] = [];
    if ($q['options_raw']) {
        foreach (explode('||', $q['options_raw']) as $opt) {
            [$oid, $otxt] = explode('::', $opt, 2);
            $q['options'][] = ['id' => $oid, 'text' => $otxt];
        }
    }
}
unset($q);

$totalQ = count($questions);
$timeLimitSeconds = ($quiz['time_limit_minutes'] ?? 0) * 60;
// Calculate remaining time
$startedAt = strtotime($attempt['started_at'] ?? date('Y-m-d H:i:s'));
$elapsed = time() - $startedAt;
$remainingSeconds = $timeLimitSeconds > 0 ? max(0, $timeLimitSeconds - $elapsed) : 0;

$pageTitle = 'Quiz: '.htmlspecialchars($quiz['title']);
require_once __DIR__.'/../includes/header.php';
?>

<style>
body { background: #f4f6fb; }
.quiz-wrapper { max-width: 860px; margin: 0 auto; padding: 2rem 1rem; }
.timer-circle {
    width: 90px; height: 90px; border-radius: 50%;
    background: conic-gradient(#f59e0b var(--pct,100%), #e5e7eb 0);
    display: flex; align-items: center; justify-content: center;
    position: relative; box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.timer-circle::after {
    content: ''; position: absolute; inset: 10px;
    background: white; border-radius: 50%;
}
.timer-text { position: relative; z-index: 1; font-weight: 700; font-size: 1rem; line-height: 1; text-align: center; }
.question-card { display: none; }
.question-card.active { display: block; }
.option-label { cursor: pointer; border: 2px solid #e5e7eb; border-radius: .75rem; padding: .75rem 1rem; display: block; margin-bottom:.5rem; transition:.15s; }
.option-label:hover { border-color: #6366f1; background: #eef2ff; }
input[type=radio]:checked + .option-label,
input[type=radio]:checked ~ .option-label { border-color: #4f46e5; background: #eef2ff; }
.progress-dots { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 1rem; }
.progress-dot { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #d1d5db; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 600; transition: .15s; }
.progress-dot.answered { background: #4f46e5; border-color: #4f46e5; color: white; }
.progress-dot.current { border-color: #f59e0b; }
</style>

<div class="quiz-wrapper">
    <!-- Quiz Header -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3 p-4">
            <div>
                <div class="text-muted small mb-1"><i class="bi bi-patch-question me-1"></i><?= htmlspecialchars($quiz['course_title'] ?? 'Standalone Quiz') ?></div>
                <h3 class="fw-bold mb-1"><?= htmlspecialchars($quiz['title']) ?></h3>
                <?php if ($quiz['description']): ?>
                    <p class="text-muted mb-0 small"><?= htmlspecialchars($quiz['description']) ?></p>
                <?php endif; ?>
                <div class="mt-2 d-flex gap-3 flex-wrap small text-muted">
                    <span><i class="bi bi-question-circle me-1"></i><?= $totalQ ?> Questions</span>
                    <span><i class="bi bi-trophy me-1"></i>Pass: <?= $quiz['pass_score'] ?>%</span>
                    <?php if ($quiz['time_limit_minutes']): ?>
                    <span><i class="bi bi-clock me-1"></i><?= $quiz['time_limit_minutes'] ?> min</span>
                    <?php endif; ?>
                    <span><i class="bi bi-arrow-repeat me-1"></i>Attempt <?= $attemptCount + 1 ?>/<?= $quiz['max_attempts'] ?></span>
                </div>
            </div>
            <?php if ($timeLimitSeconds > 0): ?>
            <div class="text-center">
                <div class="timer-circle" id="timerCircle">
                    <div class="timer-text" id="timerText">--:--</div>
                </div>
                <div class="text-muted small mt-1">Time Left</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <form id="quizForm" data-attempt-id="<?= $attempt['id'] ?>" data-quiz-id="<?= $quizId ?>">
        <input type="hidden" name="attempt_id" value="<?= $attempt['id'] ?>">
        <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

        <!-- Progress Dots -->
        <div class="progress-dots" id="progressDots">
            <?php foreach ($questions as $i => $q): ?>
            <div class="progress-dot <?= $i===0?'current':'' ?>" id="dot<?= $i ?>" onclick="goToQuestion(<?= $i ?>)"><?= $i+1 ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Question Counter -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="fw-semibold text-muted" id="questionCounter">Question 1 of <?= $totalQ ?></span>
            <div class="progress flex-grow-1 mx-3" style="height:6px;">
                <div class="progress-bar bg-primary" id="questionProgress" style="width:<?= $totalQ ? round(1/$totalQ*100) : 0 ?>%"></div>
            </div>
        </div>

        <!-- Questions -->
        <?php foreach ($questions as $i => $q): ?>
        <div class="question-card card border-0 shadow-sm rounded-4 mb-3 <?= $i===0?'active':'' ?>" id="qcard<?= $i ?>">
            <div class="card-body p-4">
                <div class="d-flex gap-2 mb-3">
                    <span class="badge bg-primary"><?= $i+1 ?></span>
                    <span class="badge bg-light text-dark"><?= $q['points'] ?> pt<?= $q['points']!=1?'s':'' ?></span>
                    <span class="badge bg-info text-dark"><?= ucfirst(str_replace('_',' ',$q['type'])) ?></span>
                </div>
                <h5 class="fw-semibold mb-4"><?= htmlspecialchars($q['question_text']) ?></h5>

                <?php if ($q['type'] === 'mcq'): ?>
                    <?php foreach ($q['options'] as $opt): ?>
                    <div>
                        <input type="radio" name="answers[<?= $q['id'] ?>]" id="opt<?= $opt['id'] ?>" value="<?= $opt['id'] ?>" class="visually-hidden answer-radio" data-q="<?= $i ?>">
                        <label for="opt<?= $opt['id'] ?>" class="option-label"><?= htmlspecialchars($opt['text']) ?></label>
                    </div>
                    <?php endforeach; ?>

                <?php elseif ($q['type'] === 'true_false'): ?>
                    <div>
                        <input type="radio" name="answers[<?= $q['id'] ?>]" id="tf_true<?= $q['id'] ?>" value="true" class="visually-hidden answer-radio" data-q="<?= $i ?>">
                        <label for="tf_true<?= $q['id'] ?>" class="option-label"><i class="bi bi-check-circle me-2 text-success"></i>True</label>
                    </div>
                    <div>
                        <input type="radio" name="answers[<?= $q['id'] ?>]" id="tf_false<?= $q['id'] ?>" value="false" class="visually-hidden answer-radio" data-q="<?= $i ?>">
                        <label for="tf_false<?= $q['id'] ?>" class="option-label"><i class="bi bi-x-circle me-2 text-danger"></i>False</label>
                    </div>

                <?php elseif ($q['type'] === 'short_answer'): ?>
                    <textarea name="answers[<?= $q['id'] ?>]" class="form-control answer-text" rows="4" placeholder="Type your answer here..." data-q="<?= $i ?>"></textarea>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Navigation Buttons -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <button type="button" class="btn btn-outline-secondary px-4" id="prevBtn" onclick="navigate(-1)" disabled>
                <i class="bi bi-chevron-left me-1"></i>Previous
            </button>
            <button type="button" class="btn btn-warning fw-semibold px-4" id="submitBtn" onclick="confirmSubmit()" style="display:none;">
                <i class="bi bi-send-check me-1"></i>Submit Quiz
            </button>
            <button type="button" class="btn btn-primary px-4" id="nextBtn" onclick="navigate(1)">
                Next <i class="bi bi-chevron-right ms-1"></i>
            </button>
        </div>
    </form>
</div>

<!-- Submit Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Submit Quiz?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-send-check text-warning d-block mb-3" style="font-size:3rem;"></i>
                <p class="mb-1">You are about to submit your quiz.</p>
                <p class="text-muted small" id="unansweredNote"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2">
                <button class="btn btn-secondary px-4" data-bs-dismiss="modal">Review Answers</button>
                <button class="btn btn-warning fw-semibold px-4" onclick="submitQuiz()">
                    <i class="bi bi-send-check me-1"></i>Yes, Submit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const TOTAL = <?= $totalQ ?>;
const TIME_LIMIT = <?= $remainingSeconds ?>;
const ATTEMPT_ID = <?= $attempt['id'] ?>;
const QUIZ_ID = <?= $quizId ?>;
const SUBMIT_URL = '<?= APP_URL ?>/student/ajax/submit_quiz.php';
const STORAGE_KEY = 'quiz_answers_' + QUIZ_ID + '_' + ATTEMPT_ID;

let current = 0;
let answered = new Set();
let timerInterval = null;
let remaining = TIME_LIMIT;

// Restore from localStorage
const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
Object.entries(saved).forEach(([qid, val]) => {
    const radio = document.querySelector(`input[name="answers[${qid}]"][value="${val}"]`);
    if (radio) { radio.checked = true; }
    const textarea = document.querySelector(`textarea[name="answers[${qid}]"]`);
    if (textarea) textarea.value = val;
});

function goToQuestion(idx) {
    document.getElementById('qcard' + current)?.classList.remove('active');
    document.getElementById('dot' + current)?.classList.remove('current');
    current = idx;
    document.getElementById('qcard' + current)?.classList.add('active');
    document.getElementById('dot' + current)?.classList.add('current');
    document.getElementById('questionCounter').textContent = `Question ${current+1} of ${TOTAL}`;
    document.getElementById('questionProgress').style.width = ((current+1)/TOTAL*100) + '%';
    document.getElementById('prevBtn').disabled = current === 0;
    document.getElementById('nextBtn').style.display = current === TOTAL-1 ? 'none' : '';
    document.getElementById('submitBtn').style.display = current === TOTAL-1 ? '' : 'none';
}

function navigate(dir) { goToQuestion(Math.max(0, Math.min(TOTAL-1, current+dir))); }

// Track answers
document.querySelectorAll('.answer-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        const qIdx = parseInt(this.dataset.q);
        answered.add(qIdx);
        document.getElementById('dot' + qIdx)?.classList.add('answered');
        saveToLocal();
    });
});
document.querySelectorAll('.answer-text').forEach(ta => {
    ta.addEventListener('input', function() {
        const qIdx = parseInt(this.dataset.q);
        if (this.value.trim()) { answered.add(qIdx); document.getElementById('dot'+qIdx)?.classList.add('answered'); }
        else { answered.delete(qIdx); document.getElementById('dot'+qIdx)?.classList.remove('answered'); }
        saveToLocal();
    });
});

function saveToLocal() {
    const data = {};
    document.querySelectorAll('.answer-radio:checked').forEach(r => {
        const name = r.name.match(/\[(\d+)\]/)?.[1];
        if (name) data[name] = r.value;
    });
    document.querySelectorAll('.answer-text').forEach(ta => {
        const name = ta.name.match(/\[(\d+)\]/)?.[1];
        if (name && ta.value.trim()) data[name] = ta.value;
    });
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
}
setInterval(saveToLocal, 30000);

// Restore answered dots from localStorage
Object.keys(saved).forEach((qid, idx) => {
    // find question index
    const q = document.querySelector(`[name="answers[${qid}]"]`) || document.querySelector(`textarea[name="answers[${qid}]"]`);
    if (q) {
        const qIdx = parseInt(q.dataset.q);
        if (!isNaN(qIdx)) { answered.add(qIdx); document.getElementById('dot'+qIdx)?.classList.add('answered'); }
    }
});

function confirmSubmit() {
    const unanswered = TOTAL - answered.size;
    const note = document.getElementById('unansweredNote');
    note.textContent = unanswered > 0 ? `${unanswered} question(s) are unanswered.` : 'All questions answered!';
    new bootstrap.Modal(document.getElementById('submitModal')).show();
}

function collectAnswers() {
    const data = {};
    document.querySelectorAll('.answer-radio:checked').forEach(r => {
        const name = r.name.match(/\[(\d+)\]/)?.[1];
        if (name) data[name] = r.value;
    });
    document.querySelectorAll('.answer-text').forEach(ta => {
        const name = ta.name.match(/\[(\d+)\]/)?.[1];
        if (name) data[name] = ta.value;
    });
    return data;
}

function submitQuiz() {
    const answers = collectAnswers();
    const btn = document.querySelector('#submitModal .btn-warning');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...';

    fetch(SUBMIT_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ attempt_id: ATTEMPT_ID, quiz_id: QUIZ_ID, answers })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            localStorage.removeItem(STORAGE_KEY);
            window.location.href = data.redirect_url;
        } else {
            alert('Error: ' + (data.message || 'Could not submit. Please try again.'));
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-check me-1"></i>Yes, Submit';
        }
    })
    .catch(() => {
        alert('Network error. Please check your connection and try again.');
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-check me-1"></i>Yes, Submit';
    });
}

// Timer
<?php if ($timeLimitSeconds > 0): ?>
function updateTimer() {
    if (remaining <= 0) {
        clearInterval(timerInterval);
        // Auto-submit
        const answers = collectAnswers();
        fetch(SUBMIT_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ attempt_id: ATTEMPT_ID, quiz_id: QUIZ_ID, answers })
        }).then(r => r.json()).then(data => {
            localStorage.removeItem(STORAGE_KEY);
            if (data.redirect_url) window.location.href = data.redirect_url;
        });
        return;
    }
    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    document.getElementById('timerText').textContent = `${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
    const pct = (remaining / <?= $timeLimitSeconds ?>) * 100;
    document.getElementById('timerCircle').style.setProperty('--pct', pct + '%');
    if (remaining <= 60) document.getElementById('timerText').style.color = '#ef4444';
    remaining--;
}
updateTimer();
timerInterval = setInterval(updateTimer, 1000);
<?php endif; ?>

// Init
goToQuestion(0);
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
