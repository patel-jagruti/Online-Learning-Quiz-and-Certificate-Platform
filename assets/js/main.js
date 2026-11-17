/**
 * QuizMaster LMS — Main JavaScript
 * Features: quiz timer, localStorage autosave, toasts,
 *           form validation, back-to-top, misc UX helpers.
 */

'use strict';

/* ============================================================
   DOCUMENT READY
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  initBackToTop();
  initTooltips();
  initAutoSlug();
  initFormValidation();
  initConfirmDialogs();
  initCharCounter();
  initSearchFilter();
  initQuizPageIfPresent();
  initAvatarPreview();
  initThumbnailPreview();
});

/* ============================================================
   BACK TO TOP
   ============================================================ */
function initBackToTop() {
  const btn = document.getElementById('backToTop');
  if (!btn) return;
  window.addEventListener('scroll', () => {
    btn.classList.toggle('visible', window.scrollY > 300);
  }, { passive: true });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

/* ============================================================
   BOOTSTRAP TOOLTIPS
   ============================================================ */
function initTooltips() {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el, { trigger: 'hover' });
  });
}

/* ============================================================
   TOAST NOTIFICATIONS
   ============================================================ */
/**
 * Show a Bootstrap toast notification.
 * @param {string} message
 * @param {'success'|'danger'|'warning'|'info'} type
 * @param {number} delay  Auto-hide delay in ms (0 = no auto-hide)
 */
function showToast(message, type = 'info', delay = 4000) {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
  }

  const icons = {
    success: 'fa-circle-check',
    danger:  'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info',
  };
  const icon = icons[type] || icons.info;

  const id   = 'toast_' + Date.now();
  const html = `
    <div id="${id}" class="toast align-items-center border-0 bg-${type} text-white" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="fa ${icon}"></i>
          <span>${escapeHtml(message)}</span>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>`;
  container.insertAdjacentHTML('beforeend', html);

  const toastEl = document.getElementById(id);
  const toast   = new bootstrap.Toast(toastEl, { autohide: delay > 0, delay });
  toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
  toast.show();
}

/* ============================================================
   AUTO-SLUG
   ============================================================ */
function initAutoSlug() {
  const titleInput = document.getElementById('title');
  const slugInput  = document.getElementById('slug');
  if (!titleInput || !slugInput) return;

  titleInput.addEventListener('input', () => {
    if (slugInput.dataset.manual) return;
    slugInput.value = generateSlug(titleInput.value);
  });
  slugInput.addEventListener('input', () => {
    slugInput.dataset.manual = '1';
  });
  slugInput.addEventListener('blur', () => {
    slugInput.value = generateSlug(slugInput.value);
  });
}

function generateSlug(str) {
  return str.toLowerCase().trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/* ============================================================
   FORM VALIDATION (Bootstrap 5 native)
   ============================================================ */
function initFormValidation() {
  document.querySelectorAll('form.needs-validation').forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) firstInvalid.focus();
      }
      form.classList.add('was-validated');
    }, false);
  });
}

/* ============================================================
   CONFIRM DIALOGS
   ============================================================ */
function initConfirmDialogs() {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      const msg = el.dataset.confirm || 'Are you sure?';
      if (!confirm(msg)) e.preventDefault();
    });
  });
}

/* ============================================================
   CHARACTER COUNTER
   ============================================================ */
function initCharCounter() {
  document.querySelectorAll('[data-maxlength]').forEach(el => {
    const max     = parseInt(el.dataset.maxlength, 10);
    const counter = document.createElement('small');
    counter.className = 'form-text text-muted char-counter';
    el.after(counter);
    const update = () => {
      const left = max - el.value.length;
      counter.textContent = `${left} character${left !== 1 ? 's' : ''} remaining`;
      counter.classList.toggle('text-danger', left < 20);
    };
    el.addEventListener('input', update);
    update();
  });
}

/* ============================================================
   CLIENT-SIDE SEARCH FILTER
   ============================================================ */
function initSearchFilter() {
  const input = document.getElementById('clientSearch');
  if (!input) return;
  const target = input.dataset.target || '.searchable-row';
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    document.querySelectorAll(target).forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/* ============================================================
   AVATAR / THUMBNAIL PREVIEW
   ============================================================ */
function initAvatarPreview() {
  const inp = document.getElementById('avatarInput');
  const img = document.getElementById('avatarPreview');
  if (!inp || !img) return;
  inp.addEventListener('change', () => {
    const f = inp.files[0];
    if (f) img.src = URL.createObjectURL(f);
  });
}

function initThumbnailPreview() {
  const inp = document.getElementById('thumbnailInput');
  const img = document.getElementById('thumbnailPreview');
  if (!inp || !img) return;
  inp.addEventListener('change', () => {
    const f = inp.files[0];
    if (f) {
      img.src = URL.createObjectURL(f);
      img.style.display = 'block';
    }
  });
}

/* ============================================================
   QUIZ ENGINE
   ============================================================ */
function initQuizPageIfPresent() {
  const quizForm = document.getElementById('quizForm');
  if (!quizForm) return;

  const quizId    = quizForm.dataset.quizId;
  const attemptId = quizForm.dataset.attemptId;
  const timeLimitSec = parseInt(quizForm.dataset.timeLimit || '0', 10);
  const storageKey   = `quiz_${quizId}_attempt_${attemptId}`;

  // ── Autosave answers ────────────────────────────────────────
  restoreAnswers(storageKey);
  quizForm.addEventListener('change', () => saveAnswers(storageKey, quizForm));

  // ── Timer ───────────────────────────────────────────────────
  if (timeLimitSec > 0) {
    initTimer(timeLimitSec, storageKey, quizForm);
  }

  // ── Before-unload warning ───────────────────────────────────
  let submitted = false;
  quizForm.addEventListener('submit', () => {
    submitted = true;
    clearAnswers(storageKey);
  });
  window.addEventListener('beforeunload', e => {
    if (!submitted) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
}

/* ── Autosave helpers ──────────────────────────────────────── */
function saveAnswers(key, form) {
  const data = {};
  new FormData(form).forEach((val, name) => { data[name] = val; });
  try {
    localStorage.setItem(key, JSON.stringify(data));
  } catch (_) {}
}

function restoreAnswers(key) {
  let data;
  try {
    data = JSON.parse(localStorage.getItem(key) || 'null');
  } catch (_) { return; }
  if (!data) return;

  Object.entries(data).forEach(([name, val]) => {
    const els = document.querySelectorAll(`[name="${CSS.escape(name)}"]`);
    els.forEach(el => {
      if (el.type === 'radio' || el.type === 'checkbox') {
        el.checked = el.value === val;
      } else {
        el.value = val;
      }
    });
  });
}

function clearAnswers(key) {
  try { localStorage.removeItem(key); } catch (_) {}
}

/* ── Quiz Timer ────────────────────────────────────────────── */
function initTimer(totalSec, storageKey, form) {
  const timerText = document.getElementById('timerText');
  const timerFill = document.getElementById('timerFill');
  const circumference = 2 * Math.PI * 45; // r=45

  const startedKey = storageKey + '_started';
  let startedAt    = parseInt(sessionStorage.getItem(startedKey) || '0', 10);
  if (!startedAt) {
    startedAt = Date.now();
    sessionStorage.setItem(startedKey, startedAt);
  }

  function tick() {
    const elapsed  = Math.floor((Date.now() - startedAt) / 1000);
    const remaining = Math.max(0, totalSec - elapsed);
    const mm = String(Math.floor(remaining / 60)).padStart(2, '0');
    const ss = String(remaining % 60).padStart(2, '0');

    if (timerText) timerText.textContent = `${mm}:${ss}`;

    if (timerFill) {
      const progress = remaining / totalSec;
      const offset   = circumference * (1 - progress);
      timerFill.style.strokeDashoffset = offset;
      timerFill.classList.toggle('warning', remaining <= totalSec * 0.25 && remaining > totalSec * 0.1);
      timerFill.classList.toggle('danger',  remaining <= totalSec * 0.1);
    }

    if (remaining <= 0) {
      clearInterval(ticker);
      showToast('Time is up! Submitting your quiz…', 'warning', 3000);
      setTimeout(() => {
        clearAnswers(storageKey);
        sessionStorage.removeItem(startedKey);
        form.submit();
      }, 1500);
      return;
    }

    if (remaining === 60) showToast('1 minute remaining!', 'warning');
    if (remaining === 300) showToast('5 minutes remaining!', 'info');
  }

  const ticker = setInterval(tick, 1000);
  tick();
}

/* ============================================================
   DYNAMIC LESSON BUILDER  (instructor/create_course.php)
   ============================================================ */
let lessonIndex = parseInt(document.getElementById('lessonCount')?.value || '0', 10);

function addLesson() {
  const container = document.getElementById('lessonsContainer');
  if (!container) return;
  const idx = lessonIndex++;
  document.getElementById('lessonCount').value = lessonIndex;

  const html = `
  <div class="lesson-item card mb-3 p-3" id="lesson_${idx}">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-semibold text-primary">Lesson ${idx + 1}</span>
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLesson(${idx})">
        <i class="fa fa-trash"></i>
      </button>
    </div>
    <div class="mb-2">
      <input type="text" class="form-control" name="lessons[${idx}][title]"
             placeholder="Lesson title" required>
    </div>
    <div class="mb-2">
      <textarea class="form-control" name="lessons[${idx}][content]" rows="3"
                placeholder="Lesson content / notes"></textarea>
    </div>
    <div class="row g-2">
      <div class="col-md-6">
        <input type="url" class="form-control" name="lessons[${idx}][video_url]"
               placeholder="Video URL (YouTube embed, optional)">
      </div>
      <div class="col-md-3">
        <input type="number" class="form-control" name="lessons[${idx}][duration_minutes]"
               placeholder="Duration (min)" min="0">
      </div>
      <div class="col-md-3">
        <input type="hidden" name="lessons[${idx}][sort_order]" value="${idx}">
        <input type="number" class="form-control" name="lessons[${idx}][sort_order_display]"
               value="${idx + 1}" placeholder="Order" min="1">
      </div>
    </div>
  </div>`;
  container.insertAdjacentHTML('beforeend', html);
}

function removeLesson(idx) {
  document.getElementById(`lesson_${idx}`)?.remove();
}

/* ============================================================
   DYNAMIC QUESTION BUILDER  (instructor/create_quiz.php)
   ============================================================ */
let questionIndex = parseInt(document.getElementById('questionCount')?.value || '0', 10);

function addQuestion() {
  const container = document.getElementById('questionsContainer');
  if (!container) return;
  const idx = questionIndex++;
  document.getElementById('questionCount').value = questionIndex;

  const html = `
  <div class="question-card" id="question_${idx}">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="fw-semibold">Question ${idx + 1}</span>
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(${idx})">
        <i class="fa fa-trash"></i>
      </button>
    </div>
    <div class="mb-3">
      <textarea class="form-control" name="questions[${idx}][text]" rows="2"
                placeholder="Question text" required></textarea>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <select class="form-select" name="questions[${idx}][type]"
                onchange="onQuestionTypeChange(this, ${idx})">
          <option value="mcq">Multiple Choice (MCQ)</option>
          <option value="true_false">True / False</option>
          <option value="short_answer">Short Answer</option>
        </select>
      </div>
      <div class="col-md-3">
        <input type="number" class="form-control" name="questions[${idx}][points]"
               value="1" min="1" placeholder="Points">
      </div>
      <div class="col-md-3">
        <input type="number" class="form-control" name="questions[${idx}][sort_order]"
               value="${idx + 1}" min="1" placeholder="Order">
      </div>
    </div>
    <div id="options_${idx}" class="options-container">
      ${buildMcqOptions(idx)}
    </div>
  </div>`;
  container.insertAdjacentHTML('beforeend', html);
}

function removeQuestion(idx) {
  document.getElementById(`question_${idx}`)?.remove();
}

function onQuestionTypeChange(select, idx) {
  const container = document.getElementById(`options_${idx}`);
  if (!container) return;
  switch (select.value) {
    case 'mcq':          container.innerHTML = buildMcqOptions(idx);       break;
    case 'true_false':   container.innerHTML = buildTrueFalseOptions(idx); break;
    case 'short_answer': container.innerHTML = '<p class="text-muted small">Students will type their answer.</p>'; break;
  }
}

function buildMcqOptions(idx) {
  let html = '<div class="mb-2 small fw-semibold text-muted">Options (mark correct one):</div>';
  for (let i = 0; i < 4; i++) {
    html += `
    <div class="d-flex align-items-center gap-2 mb-2">
      <input type="radio" name="questions[${idx}][correct]" value="${i}" class="form-check-input mt-0" required>
      <input type="text" class="form-control form-control-sm"
             name="questions[${idx}][options][${i}]" placeholder="Option ${i + 1}" required>
    </div>`;
  }
  return html;
}

function buildTrueFalseOptions(idx) {
  return `
  <div class="mb-2 small fw-semibold text-muted">Mark the correct answer:</div>
  <div class="d-flex gap-4">
    <label class="d-flex align-items-center gap-2">
      <input type="radio" name="questions[${idx}][correct]" value="0" required>
      <input type="hidden" name="questions[${idx}][options][0]" value="True">
      <span>✅ True</span>
    </label>
    <label class="d-flex align-items-center gap-2">
      <input type="radio" name="questions[${idx}][correct]" value="1">
      <input type="hidden" name="questions[${idx}][options][1]" value="False">
      <span>❌ False</span>
    </label>
  </div>`;
}

/* ============================================================
   UTILITY
   ============================================================ */
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * Debounce a function.
 */
function debounce(fn, ms = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}

/**
 * Simple fetch wrapper returning JSON.
 */
async function apiFetch(url, opts = {}) {
  const defaults = {
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  };
  try {
    const res  = await fetch(url, { ...defaults, ...opts });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
  } catch (err) {
    showToast(err.message, 'danger');
    throw err;
  }
}
