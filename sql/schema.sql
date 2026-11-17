-- ============================================================
-- QuizMaster LMS - Full Database Schema
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS quiz_lms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quiz_lms;

-- -------------------------------------------------------
-- users
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)    NOT NULL,
    email           VARCHAR(255)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('admin','instructor','student') NOT NULL DEFAULT 'student',
    avatar          VARCHAR(255)    DEFAULT NULL,
    bio             TEXT            DEFAULT NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email  (email),
    INDEX idx_users_role   (role),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- categories
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    slug        VARCHAR(120)    NOT NULL UNIQUE,
    description TEXT            DEFAULT NULL,
    icon        VARCHAR(100)    DEFAULT 'fa-folder',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- courses
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(255)    NOT NULL,
    slug            VARCHAR(280)    NOT NULL UNIQUE,
    description     TEXT            DEFAULT NULL,
    instructor_id   INT UNSIGNED    NOT NULL,
    category_id     INT UNSIGNED    DEFAULT NULL,
    thumbnail       VARCHAR(255)    DEFAULT NULL,
    status          ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    price           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_courses_category   FOREIGN KEY (category_id)   REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_courses_slug        (slug),
    INDEX idx_courses_instructor  (instructor_id),
    INDEX idx_courses_category    (category_id),
    INDEX idx_courses_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- lessons
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS lessons (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id        INT UNSIGNED    NOT NULL,
    title            VARCHAR(255)    NOT NULL,
    content          LONGTEXT        DEFAULT NULL,
    video_url        VARCHAR(500)    DEFAULT NULL,
    duration_minutes INT UNSIGNED    DEFAULT 0,
    sort_order       INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lessons_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_lessons_course (course_id),
    INDEX idx_lessons_order  (course_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- enrollments
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id       INT UNSIGNED    NOT NULL,
    course_id        INT UNSIGNED    NOT NULL,
    progress_percent INT             NOT NULL DEFAULT 0,
    enrolled_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at     DATETIME        DEFAULT NULL,
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_course  FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    UNIQUE KEY uq_enrollment (student_id, course_id),
    INDEX idx_enrollments_student (student_id),
    INDEX idx_enrollments_course  (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- lesson_progress
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS lesson_progress (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED    NOT NULL,
    lesson_id   INT UNSIGNED    NOT NULL,
    completed_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lp_student FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_lp_lesson  FOREIGN KEY (lesson_id)  REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY uq_lesson_progress (student_id, lesson_id),
    INDEX idx_lp_student (student_id),
    INDEX idx_lp_lesson  (lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- quizzes
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS quizzes (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id            INT UNSIGNED    DEFAULT NULL,
    title                VARCHAR(255)    NOT NULL,
    description          TEXT            DEFAULT NULL,
    time_limit_minutes   INT UNSIGNED    DEFAULT NULL,
    pass_score           INT             NOT NULL DEFAULT 60,
    max_attempts         INT             NOT NULL DEFAULT 3,
    shuffle_questions    TINYINT(1)      NOT NULL DEFAULT 0,
    show_results         TINYINT(1)      NOT NULL DEFAULT 1,
    created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quizzes_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_quizzes_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- questions
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id       INT UNSIGNED    NOT NULL,
    question_text TEXT            NOT NULL,
    type          ENUM('mcq','true_false','short_answer') NOT NULL DEFAULT 'mcq',
    points        INT             NOT NULL DEFAULT 1,
    sort_order    INT             NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_questions_quiz  (quiz_id),
    INDEX idx_questions_order (quiz_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- options
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS options (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id  INT UNSIGNED    NOT NULL,
    option_text  VARCHAR(500)    NOT NULL,
    is_correct   TINYINT(1)      NOT NULL DEFAULT 0,
    CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_options_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- quiz_attempts
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id             INT UNSIGNED    NOT NULL,
    student_id          INT UNSIGNED    NOT NULL,
    score               DECIMAL(5,2)    DEFAULT NULL,
    time_taken_seconds  INT UNSIGNED    DEFAULT NULL,
    started_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at        DATETIME        DEFAULT NULL,
    status              ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
    CONSTRAINT fk_attempts_quiz    FOREIGN KEY (quiz_id)    REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_student FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_attempts_quiz    (quiz_id),
    INDEX idx_attempts_student (student_id),
    INDEX idx_attempts_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- attempt_answers
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS attempt_answers (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id         INT UNSIGNED    NOT NULL,
    question_id        INT UNSIGNED    NOT NULL,
    selected_option_id INT UNSIGNED    DEFAULT NULL,
    text_answer        TEXT            DEFAULT NULL,
    is_correct         TINYINT(1)      DEFAULT NULL,
    CONSTRAINT fk_aa_attempt    FOREIGN KEY (attempt_id)         REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_question   FOREIGN KEY (question_id)        REFERENCES questions(id)     ON DELETE CASCADE,
    CONSTRAINT fk_aa_option     FOREIGN KEY (selected_option_id) REFERENCES options(id)       ON DELETE SET NULL,
    INDEX idx_aa_attempt  (attempt_id),
    INDEX idx_aa_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- certificates
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS certificates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED    NOT NULL,
    course_id   INT UNSIGNED    NOT NULL,
    cert_code   VARCHAR(50)     NOT NULL UNIQUE,
    issued_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cert_student FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cert_course  FOREIGN KEY (course_id)  REFERENCES courses(id)  ON DELETE CASCADE,
    UNIQUE KEY uq_cert_student_course (student_id, course_id),
    INDEX idx_cert_code    (cert_code),
    INDEX idx_cert_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- notifications
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED    NOT NULL,
    message    TEXT            NOT NULL,
    type       VARCHAR(50)     NOT NULL DEFAULT 'info',
    is_read    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user    (user_id),
    INDEX idx_notif_is_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- settings
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name   VARCHAR(100)    NOT NULL UNIQUE,
    value      TEXT            DEFAULT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_settings_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default admin user (password: Admin@123)
INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('Administrator', 'admin@quiz.com',
        '$2y$10$VA/dcSIpg.mxMP.g2QC4h.Z9OC5X7v5d2bkq8IYnDVmtELAcB2Cke',
        'admin', 1)
ON DUPLICATE KEY UPDATE id = id;

-- Sample categories
INSERT INTO categories (name, slug, description, icon) VALUES
  ('Mathematics',  'mathematics',  'Algebra, calculus, geometry and more',          'fa-square-root-variable'),
  ('Science',      'science',      'Physics, chemistry, biology and earth sciences', 'fa-flask'),
  ('History',      'history',      'World history, civilizations and timelines',    'fa-landmark'),
  ('Programming',  'programming',  'Coding, algorithms and software development',   'fa-code'),
  ('Languages',    'languages',    'English, Spanish, French and more',             'fa-language')
ON DUPLICATE KEY UPDATE id = id;

-- Default site settings
INSERT INTO settings (key_name, value) VALUES
  ('site_name',           'QuizMaster LMS'),
  ('site_description',    'The ultimate online learning and quiz platform'),
  ('site_email',          'admin@quiz.com'),
  ('site_logo',           ''),
  ('allow_registration',  '1'),
  ('default_role',        'student'),
  ('items_per_page',      '12'),
  ('enable_certificates', '1'),
  ('smtp_host',           ''),
  ('smtp_port',           '587'),
  ('smtp_user',           ''),
  ('smtp_pass',           ''),
  ('currency',            'USD'),
  ('currency_symbol',     '$'),
  ('maintenance_mode',    '0')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- -------------------------------------------------------
-- Sample instructor (password: Instructor@123)
-- -------------------------------------------------------
INSERT INTO users (name, email, password_hash, role, bio, is_active) VALUES
  ('Sarah Johnson', 'instructor@quiz.com',
   '$2y$10$fTjlPM7P9xXw5Y2AloCIzOvxnCsXu/h0boEWCLT1ZE/hj130Ti6qa',
   'instructor',
   'Senior software engineer and educator with over ten years of teaching experience.',
   1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  bio = VALUES(bio),
  is_active = VALUES(is_active);

SET @demo_instructor = (SELECT id FROM users WHERE email = 'instructor@quiz.com');
SET @cat_programming = (SELECT id FROM categories WHERE slug = 'programming');
SET @cat_math        = (SELECT id FROM categories WHERE slug = 'mathematics');
SET @cat_science     = (SELECT id FROM categories WHERE slug = 'science');
SET @cat_history     = (SELECT id FROM categories WHERE slug = 'history');
SET @cat_languages   = (SELECT id FROM categories WHERE slug = 'languages');

-- -------------------------------------------------------
-- Sample courses
-- -------------------------------------------------------
INSERT INTO courses (title, slug, description, instructor_id, category_id, status, price, created_at) VALUES
  ('Web Development Fundamentals', 'web-development-fundamentals',
   'Learn to build modern, responsive websites from scratch using HTML, CSS and JavaScript.',
   @demo_instructor, @cat_programming, 'published', 0.00, NOW()),
  ('Python for Beginners', 'python-for-beginners',
   'Master Python programming basics including variables, loops, functions and file handling.',
   @demo_instructor, @cat_programming, 'published', 0.00, NOW()),
  ('Algebra Essentials', 'algebra-essentials',
   'Build a strong foundation in algebra with equations, inequalities, functions and graphing.',
   @demo_instructor, @cat_math, 'published', 0.00, NOW()),
  ('Physics: Motion and Forces', 'physics-motion-and-forces',
   'Understand the laws of motion, forces, energy and momentum through clear practical examples.',
   @demo_instructor, @cat_science, 'published', 0.00, NOW()),
  ('World History: Ancient Civilizations', 'world-history-ancient-civilizations',
   'Explore the rise and fall of ancient Egypt, Greece, Rome and Mesopotamia.',
   @demo_instructor, @cat_history, 'published', 0.00, NOW()),
  ('English Grammar Masterclass', 'english-grammar-masterclass',
   'Improve your writing and speaking with clear, practical English grammar lessons.',
   @demo_instructor, @cat_languages, 'published', 0.00, NOW())
ON DUPLICATE KEY UPDATE
  title         = VALUES(title),
  description   = VALUES(description),
  instructor_id = VALUES(instructor_id),
  category_id   = VALUES(category_id),
  status        = VALUES(status),
  price         = VALUES(price);

-- -------------------------------------------------------
-- Sample lessons for the demo courses
-- -------------------------------------------------------
INSERT INTO lessons (course_id, title, content, video_url, duration_minutes, sort_order, created_at)
SELECT c.id, s.title, s.content, '', s.duration_minutes, s.sort_order, NOW()
FROM courses c
JOIN (
  SELECT 'web-development-fundamentals' AS slug, 'Introduction to HTML' AS title,
         'Learn how web pages are structured using elements, headings, links and images.' AS content,
         12 AS duration_minutes, 1 AS sort_order
  UNION ALL SELECT 'web-development-fundamentals', 'Styling with CSS',
         'Use colours, typography, spacing and layout to make pages look professional.', 14, 2
  UNION ALL SELECT 'web-development-fundamentals', 'JavaScript Basics',
         'Add interactivity with variables, functions, events and simple DOM updates.', 16, 3
  UNION ALL SELECT 'python-for-beginners', 'Setting Up Python',
         'Install Python and run your first program using the interactive shell.', 10, 1
  UNION ALL SELECT 'python-for-beginners', 'Variables and Data Types',
         'Work with numbers, strings, lists, dictionaries and booleans.', 15, 2
  UNION ALL SELECT 'python-for-beginners', 'Control Flow and Loops',
         'Make decisions with if statements and repeat work with for and while loops.', 18, 3
  UNION ALL SELECT 'algebra-essentials', 'Variables and Expressions',
         'Understand variables, constants and how to simplify algebraic expressions.', 12, 1
  UNION ALL SELECT 'algebra-essentials', 'Solving Linear Equations',
         'Solve one-step and two-step equations using inverse operations.', 14, 2
  UNION ALL SELECT 'algebra-essentials', 'Working with Inequalities',
         'Solve and graph inequalities on a number line.', 13, 3
  UNION ALL SELECT 'physics-motion-and-forces', 'Describing Motion',
         'Learn about distance, displacement, speed, velocity and acceleration.', 15, 1
  UNION ALL SELECT 'physics-motion-and-forces', 'Newton Laws of Motion',
         'Explore the three laws that explain how forces change motion.', 17, 2
  UNION ALL SELECT 'physics-motion-and-forces', 'Energy and Momentum',
         'Understand kinetic and potential energy and how momentum is conserved.', 16, 3
  UNION ALL SELECT 'world-history-ancient-civilizations', 'Mesopotamia and the First Cities',
         'Discover how farming, writing and trade shaped the first urban civilisations.', 14, 1
  UNION ALL SELECT 'world-history-ancient-civilizations', 'Ancient Egypt',
         'Study the Nile, pharaohs, pyramids and daily life in ancient Egypt.', 15, 2
  UNION ALL SELECT 'world-history-ancient-civilizations', 'Classical Greece and Rome',
         'Compare the political and cultural achievements of Greece and Rome.', 18, 3
  UNION ALL SELECT 'english-grammar-masterclass', 'Parts of Speech',
         'Identify nouns, verbs, adjectives, adverbs, pronouns and prepositions.', 11, 1
  UNION ALL SELECT 'english-grammar-masterclass', 'Sentence Structure',
         'Build clear sentences with subjects, predicates, clauses and phrases.', 13, 2
  UNION ALL SELECT 'english-grammar-masterclass', 'Punctuation and Style',
         'Use commas, apostrophes, colons and semicolons correctly.', 12, 3
) AS s ON s.slug = c.slug
WHERE NOT EXISTS (
  SELECT 1 FROM lessons l WHERE l.course_id = c.id AND l.title = s.title
);

-- -------------------------------------------------------
-- Rich lesson content and sample quizzes for the demo courses
-- -------------------------------------------------------

-- -------------------------------------------------------
-- Rich lesson information for the sample courses
-- -------------------------------------------------------
CREATE TEMPORARY TABLE tmp_lms_lesson_content (
    slug             VARCHAR(280) NOT NULL,
    title            VARCHAR(255) NOT NULL,
    content          LONGTEXT     NOT NULL,
    duration_minutes INT          NOT NULL
);

INSERT INTO tmp_lms_lesson_content (slug, title, content, duration_minutes) VALUES
('web-development-fundamentals', 'Introduction to HTML',
 '<h5>What is HTML?</h5><p>HTML is the language that gives a web page its structure. Headings, paragraphs, links, images and forms are all created with HTML elements.</p><h6>Core elements</h6><ul><li><code>&lt;h1&gt;</code> to <code>&lt;h6&gt;</code> create headings.</li><li><code>&lt;p&gt;</code> creates a paragraph.</li><li><code>&lt;a href="..."&gt;</code> creates a link.</li><li><code>&lt;img src="..."&gt;</code> displays an image.</li></ul><p>Practice by building a simple personal profile page.</p>', 12),
('web-development-fundamentals', 'Styling with CSS',
 '<h5>Making pages look good</h5><p>CSS controls presentation: colours, fonts, spacing, borders and layout. Styles can be written inline, in a style block or, preferably, in an external stylesheet.</p><ul><li>Use selectors to target elements.</li><li>Use the box model for padding, borders and margins.</li><li>Use Flexbox or Grid for responsive layouts.</li></ul><p>Keep colours consistent and test the page at different screen sizes.</p>', 14),
('web-development-fundamentals', 'JavaScript Basics',
 '<h5>Adding behaviour</h5><p>JavaScript lets a page respond to clicks, validate forms and update content without reloading.</p><ul><li>Variables store values.</li><li>Functions group reusable logic.</li><li>Event listeners react to user actions.</li></ul><p>Open the browser console and try changing the text of an element.</p>', 16),
('python-for-beginners', 'Setting Up Python',
 '<h5>Your first program</h5><p>Python is a readable, general-purpose language used for web development, automation, data science and more.</p><ul><li>Install Python from python.org.</li><li>Run <code>python --version</code> to confirm the installation.</li><li>Use <code>print("Hello, world!")</code> for your first program.</li></ul>', 10),
('python-for-beginners', 'Variables and Data Types',
 '<h5>Storing information</h5><p>Variables are names that refer to values. Python has several built-in data types.</p><ul><li><code>int</code> and <code>float</code> for numbers.</li><li><code>str</code> for text.</li><li><code>list</code> and <code>dict</code> for collections.</li><li><code>bool</code> for True and False.</li></ul><p>Try converting a number to text with <code>str()</code>.</p>', 15),
('python-for-beginners', 'Control Flow and Loops',
 '<h5>Making decisions and repeating work</h5><p><code>if</code>, <code>elif</code> and <code>else</code> let programs choose different paths. Loops repeat work without duplicating code.</p><ul><li>Use <code>for</code> to iterate over a sequence.</li><li>Use <code>while</code> when repetition depends on a condition.</li><li>Keep loop conditions clear to avoid infinite loops.</li></ul>', 18),
('algebra-essentials', 'Variables and Expressions',
 '<h5>Letters that stand for numbers</h5><p>In algebra, variables represent unknown or changing values. An expression combines variables, numbers and operations.</p><ul><li>In <code>3x + 2</code>, <code>x</code> is the variable.</li><li>Like terms can be combined: <code>2x + 3x = 5x</code>.</li><li>Substitute a value to evaluate an expression.</li></ul>', 12),
('algebra-essentials', 'Solving Linear Equations',
 '<h5>Keeping both sides balanced</h5><p>An equation states that two expressions are equal. To solve it, apply the same operation to both sides until the variable is alone.</p><ul><li>For <code>2x + 3 = 11</code>, subtract 3, then divide by 2.</li><li>Check the solution by substituting it back.</li><li>Use inverse operations to undo addition, subtraction, multiplication and division.</li></ul>', 14),
('algebra-essentials', 'Working with Inequalities',
 '<h5>Working with ranges</h5><p>Inequalities compare values using <code>&lt;</code>, <code>&lt;=</code>, <code>&gt;</code> and <code>&gt;=</code>. Solutions are ranges rather than single values.</p><ul><li>Solve inequalities like equations.</li><li>Reverse the sign when multiplying or dividing by a negative number.</li><li>Graph solutions on a number line.</li></ul>', 13),
('physics-motion-and-forces', 'Describing Motion',
 '<h5>How objects move</h5><p>Motion is described using distance, displacement, speed, velocity and acceleration.</p><ul><li>Speed is distance divided by time.</li><li>Velocity includes direction.</li><li>Acceleration is the rate of change of velocity.</li></ul><p>Always state the unit and direction when describing motion.</p>', 15),
('physics-motion-and-forces', 'Newton Laws of Motion',
 '<h5>Forces and motion</h5><p>Newton''s three laws explain how forces affect the motion of objects.</p><ul><li>An object stays at rest or moves uniformly unless a force acts.</li><li>Force equals mass times acceleration: <code>F = ma</code>.</li><li>Every action has an equal and opposite reaction.</li></ul>', 17),
('physics-motion-and-forces', 'Energy and Momentum',
 '<h5>Energy and collisions</h5><p>Energy can be transferred or transformed but is conserved. Momentum describes mass in motion.</p><ul><li>Kinetic energy depends on mass and speed.</li><li>Potential energy depends on position.</li><li>Momentum equals mass times velocity.</li></ul>', 16),
('world-history-ancient-civilizations', 'Mesopotamia and the First Cities',
 '<h5>The first cities</h5><p>Mesopotamia, between the Tigris and Euphrates rivers, is often called the cradle of civilisation.</p><ul><li>Farming produced surplus food.</li><li>Writing developed for records and trade.</li><li>City-states such as Ur and Babylon grew powerful.</li></ul>', 14),
('world-history-ancient-civilizations', 'Ancient Egypt',
 '<h5>Life along the Nile</h5><p>The Nile provided water, fertile soil and transport for ancient Egypt.</p><ul><li>Pharaohs ruled as political and religious leaders.</li><li>The pyramids served as royal tombs.</li><li>Hieroglyphs recorded language and beliefs.</li></ul>', 15),
('world-history-ancient-civilizations', 'Classical Greece and Rome',
 '<h5>Two influential civilisations</h5><p>Ancient Greece and Rome shaped politics, philosophy, art and law across the world.</p><ul><li>Greece developed democracy and philosophy.</li><li>Rome built roads, aqueducts and a vast legal system.</li><li>Roman culture absorbed and spread Greek ideas.</li></ul>', 18),
('english-grammar-masterclass', 'Parts of Speech',
 '<h5>The building blocks of sentences</h5><p>Words are grouped by the job they do in a sentence.</p><ul><li>Nouns name people, places or things.</li><li>Verbs show actions or states.</li><li>Adjectives describe nouns.</li><li>Adverbs describe verbs, adjectives or other adverbs.</li></ul>', 11),
('english-grammar-masterclass', 'Sentence Structure',
 '<h5>Subjects, verbs and clauses</h5><p>A complete sentence needs a subject and a verb that express a complete idea.</p><ul><li>A simple sentence has one independent clause.</li><li>Compound sentences join two independent clauses.</li><li>Complex sentences combine an independent and a dependent clause.</li></ul>', 13),
('english-grammar-masterclass', 'Punctuation and Style',
 '<h5>Clarity through punctuation</h5><p>Punctuation guides the reader through a sentence.</p><ul><li>Use commas for lists and pauses.</li><li>Use apostrophes for possession and contractions.</li><li>Use semicolons to connect related clauses.</li></ul>', 12);

UPDATE lessons l
JOIN courses c ON c.id = l.course_id
JOIN tmp_lms_lesson_content t ON t.slug = c.slug AND t.title = l.title
SET l.content = t.content,
    l.duration_minutes = t.duration_minutes;

DROP TEMPORARY TABLE tmp_lms_lesson_content;

-- -------------------------------------------------------
-- Sample quizzes (one per demo course)
-- -------------------------------------------------------
CREATE TEMPORARY TABLE tmp_lms_quizzes (
    slug             VARCHAR(280) NOT NULL,
    title            VARCHAR(255) NOT NULL,
    description      TEXT         NOT NULL,
    time_limit_minutes INT        NOT NULL,
    pass_score       INT          NOT NULL,
    max_attempts     INT          NOT NULL
);

INSERT INTO tmp_lms_quizzes (slug, title, description, time_limit_minutes, pass_score, max_attempts) VALUES
('web-development-fundamentals', 'Web Development Fundamentals Quiz',
 'Check your understanding of HTML, CSS and JavaScript basics.', 10, 60, 3),
('python-for-beginners', 'Python for Beginners Quiz',
 'Test your knowledge of Python syntax, data types and control flow.', 10, 60, 3),
('algebra-essentials', 'Algebra Essentials Quiz',
 'Practise equations, expressions and inequalities.', 10, 60, 3),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz',
 'Review motion, forces, energy and momentum.', 10, 60, 3),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz',
 'Check what you remember about early civilizations.', 10, 60, 3),
('english-grammar-masterclass', 'English Grammar Quiz',
 'Test your understanding of grammar and punctuation.', 10, 60, 3);

INSERT INTO quizzes (course_id, title, description, time_limit_minutes, pass_score, max_attempts, shuffle_questions, show_results, created_at)
SELECT c.id, t.title, t.description, t.time_limit_minutes, t.pass_score, t.max_attempts, 0, 1, NOW()
FROM tmp_lms_quizzes t
JOIN courses c ON c.slug = t.slug
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.course_id = c.id AND q.title = t.title
);

CREATE TEMPORARY TABLE tmp_lms_questions (
    slug          VARCHAR(280) NOT NULL,
    quiz_title    VARCHAR(255) NOT NULL,
    question_text TEXT         NOT NULL,
    q_type        VARCHAR(20)  NOT NULL,
    points        INT          NOT NULL,
    sort_order    INT          NOT NULL
);

INSERT INTO tmp_lms_questions (slug, quiz_title, question_text, q_type, points, sort_order) VALUES
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which HTML element creates the largest heading?', 'mcq', 1, 1),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which CSS property changes the text colour?', 'mcq', 1, 2),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'JavaScript runs in the browser.', 'true_false', 1, 3),
('python-for-beginners', 'Python for Beginners Quiz', 'Which keyword defines a function in Python?', 'mcq', 1, 1),
('python-for-beginners', 'Python for Beginners Quiz', 'Which of these is a Python list?', 'mcq', 1, 2),
('python-for-beginners', 'Python for Beginners Quiz', 'Python is case-sensitive.', 'true_false', 1, 3),
('algebra-essentials', 'Algebra Essentials Quiz', 'Solve 3x = 12.', 'mcq', 1, 1),
('algebra-essentials', 'Algebra Essentials Quiz', 'Simplify 2x + 3x.', 'mcq', 1, 2),
('algebra-essentials', 'Algebra Essentials Quiz', 'The graph of y = 2x + 1 is a straight line.', 'true_false', 1, 3),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'What is the SI unit of force?', 'mcq', 1, 1),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Which law is expressed by F = ma?', 'mcq', 1, 2),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Acceleration is the rate of change of velocity.', 'true_false', 1, 3),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which river was central to ancient Egypt?', 'mcq', 1, 1),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which civilization built the Great Pyramids?', 'mcq', 1, 2),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'The Roman Empire developed before ancient Greek civilization.', 'true_false', 1, 3),
('english-grammar-masterclass', 'English Grammar Quiz', 'In the sentence "The dog runs quickly", which word is the noun?', 'mcq', 1, 1),
('english-grammar-masterclass', 'English Grammar Quiz', 'Which punctuation mark ends a question?', 'mcq', 1, 2),
('english-grammar-masterclass', 'English Grammar Quiz', 'An adjective describes a noun.', 'true_false', 1, 3);

INSERT INTO questions (quiz_id, question_text, type, points, sort_order, created_at)
SELECT qz.id, t.question_text, t.q_type, t.points, t.sort_order, NOW()
FROM tmp_lms_questions t
JOIN courses c ON c.slug = t.slug
JOIN quizzes qz ON qz.course_id = c.id AND qz.title = t.quiz_title
WHERE NOT EXISTS (
    SELECT 1 FROM questions qs WHERE qs.quiz_id = qz.id AND qs.question_text = t.question_text
);

CREATE TEMPORARY TABLE tmp_lms_options (
    slug          VARCHAR(280) NOT NULL,
    quiz_title    VARCHAR(255) NOT NULL,
    question_text TEXT         NOT NULL,
    option_text   VARCHAR(500) NOT NULL,
    is_correct    TINYINT(1)   NOT NULL
);

INSERT INTO tmp_lms_options (slug, quiz_title, question_text, option_text, is_correct) VALUES
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which HTML element creates the largest heading?', 'h1', 1),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which HTML element creates the largest heading?', 'h2', 0),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which HTML element creates the largest heading?', 'header', 0),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which HTML element creates the largest heading?', 'title', 0),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which CSS property changes the text colour?', 'color', 1),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which CSS property changes the text colour?', 'background', 0),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which CSS property changes the text colour?', 'font-style', 0),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'Which CSS property changes the text colour?', 'border', 0),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'JavaScript runs in the browser.', 'True', 1),
('web-development-fundamentals', 'Web Development Fundamentals Quiz', 'JavaScript runs in the browser.', 'False', 0),
('python-for-beginners', 'Python for Beginners Quiz', 'Which keyword defines a function in Python?', 'def', 1),
('python-for-beginners', 'Python for Beginners Quiz', 'Which keyword defines a function in Python?', 'func', 0),
('python-for-beginners', 'Python for Beginners Quiz', 'Which keyword defines a function in Python?', 'function', 0),
('python-for-beginners', 'Python for Beginners Quiz', 'Which keyword defines a function in Python?', 'define', 0),
('python-for-beginners', 'Python for Beginners Quiz', 'Which of these is a Python list?', '[1, 2, 3]', 1),
('python-for-beginners', 'Python for Beginners Quiz', 'Which of these is a Python list?', '(1, 2, 3)', 0),
('python-for-beginners', 'Python for Beginners Quiz', 'Which of these is a Python list?', '{1, 2, 3}', 0),
('python-for-beginners', 'Python for Beginners Quiz', 'Which of these is a Python list?', '<1, 2, 3>', 0),
('python-for-beginners', 'Python for Beginners Quiz', 'Python is case-sensitive.', 'True', 1),
('python-for-beginners', 'Python for Beginners Quiz', 'Python is case-sensitive.', 'False', 0),
('algebra-essentials', 'Algebra Essentials Quiz', 'Solve 3x = 12.', '4', 1),
('algebra-essentials', 'Algebra Essentials Quiz', 'Solve 3x = 12.', '3', 0),
('algebra-essentials', 'Algebra Essentials Quiz', 'Solve 3x = 12.', '9', 0),
('algebra-essentials', 'Algebra Essentials Quiz', 'Solve 3x = 12.', '36', 0),
('algebra-essentials', 'Algebra Essentials Quiz', 'Simplify 2x + 3x.', '5x', 1),
('algebra-essentials', 'Algebra Essentials Quiz', 'Simplify 2x + 3x.', '6x', 0),
('algebra-essentials', 'Algebra Essentials Quiz', 'Simplify 2x + 3x.', 'x', 0),
('algebra-essentials', 'Algebra Essentials Quiz', 'Simplify 2x + 3x.', '5', 0),
('algebra-essentials', 'Algebra Essentials Quiz', 'The graph of y = 2x + 1 is a straight line.', 'True', 1),
('algebra-essentials', 'Algebra Essentials Quiz', 'The graph of y = 2x + 1 is a straight line.', 'False', 0),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'What is the SI unit of force?', 'Newton', 1),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'What is the SI unit of force?', 'Joule', 0),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'What is the SI unit of force?', 'Watt', 0),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'What is the SI unit of force?', 'Pascal', 0),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Which law is expressed by F = ma?', 'Newton''s second law', 1),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Which law is expressed by F = ma?', 'Newton''s first law', 0),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Which law is expressed by F = ma?', 'Newton''s third law', 0),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Which law is expressed by F = ma?', 'Law of gravitation', 0),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Acceleration is the rate of change of velocity.', 'True', 1),
('physics-motion-and-forces', 'Physics: Motion and Forces Quiz', 'Acceleration is the rate of change of velocity.', 'False', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which river was central to ancient Egypt?', 'Nile', 1),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which river was central to ancient Egypt?', 'Amazon', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which river was central to ancient Egypt?', 'Indus', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which river was central to ancient Egypt?', 'Danube', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which civilization built the Great Pyramids?', 'Ancient Egyptians', 1),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which civilization built the Great Pyramids?', 'Ancient Greeks', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which civilization built the Great Pyramids?', 'Romans', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'Which civilization built the Great Pyramids?', 'Persians', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'The Roman Empire developed before ancient Greek civilization.', 'True', 0),
('world-history-ancient-civilizations', 'Ancient Civilizations Quiz', 'The Roman Empire developed before ancient Greek civilization.', 'False', 1),
('english-grammar-masterclass', 'English Grammar Quiz', 'In the sentence "The dog runs quickly", which word is the noun?', 'dog', 1),
('english-grammar-masterclass', 'English Grammar Quiz', 'In the sentence "The dog runs quickly", which word is the noun?', 'runs', 0),
('english-grammar-masterclass', 'English Grammar Quiz', 'In the sentence "The dog runs quickly", which word is the noun?', 'quickly', 0),
('english-grammar-masterclass', 'English Grammar Quiz', 'In the sentence "The dog runs quickly", which word is the noun?', 'the', 0),
('english-grammar-masterclass', 'English Grammar Quiz', 'Which punctuation mark ends a question?', 'Question mark', 1),
('english-grammar-masterclass', 'English Grammar Quiz', 'Which punctuation mark ends a question?', 'Period', 0),
('english-grammar-masterclass', 'English Grammar Quiz', 'Which punctuation mark ends a question?', 'Comma', 0),
('english-grammar-masterclass', 'English Grammar Quiz', 'Which punctuation mark ends a question?', 'Exclamation mark', 0),
('english-grammar-masterclass', 'English Grammar Quiz', 'An adjective describes a noun.', 'True', 1),
('english-grammar-masterclass', 'English Grammar Quiz', 'An adjective describes a noun.', 'False', 0);

INSERT INTO options (question_id, option_text, is_correct)
SELECT qs.id, t.option_text, t.is_correct
FROM tmp_lms_options t
JOIN courses c ON c.slug = t.slug
JOIN quizzes qz ON qz.course_id = c.id AND qz.title = t.quiz_title
JOIN questions qs ON qs.quiz_id = qz.id AND qs.question_text = t.question_text
WHERE NOT EXISTS (
    SELECT 1 FROM options o WHERE o.question_id = qs.id AND o.option_text = t.option_text
);

DROP TEMPORARY TABLE tmp_lms_quizzes;
DROP TEMPORARY TABLE tmp_lms_questions;
DROP TEMPORARY TABLE tmp_lms_options;
