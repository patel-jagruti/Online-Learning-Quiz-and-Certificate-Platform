<?php
require_once __DIR__.'/../../config/config.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();
$userId = $_SESSION['user_id'];
session_write_close();

// Accept JSON or form-encoded
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$attemptId = (int)($input['attempt_id'] ?? 0);
$quizId    = (int)($input['quiz_id'] ?? 0);
$answers   = $input['answers'] ?? [];

if (!$attemptId || !$quizId) {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

// Validate attempt belongs to this user
$attemptStmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id=? AND student_id=? AND status='in_progress'");
$attemptStmt->execute([$attemptId, $userId]);
$attempt = $attemptStmt->fetch();
if (!$attempt) {
    echo json_encode(['success'=>false,'message'=>'Invalid or already submitted attempt.']); exit;
}

// Fetch quiz
$quiz = $db->prepare("SELECT * FROM quizzes WHERE id=?");
$quiz->execute([$quizId]);
$quiz = $quiz->fetch();
if (!$quiz) {
    echo json_encode(['success'=>false,'message'=>'Quiz not found.']); exit;
}

// Fetch all questions with correct options
$qStmt = $db->prepare("SELECT q.*, GROUP_CONCAT(o.id,'::',o.option_text,'::',o.is_correct ORDER BY o.id SEPARATOR '||') as options_raw FROM questions q LEFT JOIN options o ON o.question_id=q.id WHERE q.quiz_id=? GROUP BY q.id");
$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();

$totalPoints = 0;
$earnedPoints = 0;
$correctCount = 0;

$db->beginTransaction();
try {
    // Delete old answers if any (re-submit protection)
    $db->prepare("DELETE FROM attempt_answers WHERE attempt_id=?")->execute([$attemptId]);
    $insStmt = $db->prepare("INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, text_answer, is_correct) VALUES (?,?,?,?,?)");

    foreach ($questions as $q) {
        $qId = $q['id'];
        $totalPoints += $q['points'];
        $givenAnswer = $answers[$qId] ?? null;

        $selectedOptionId = null;
        $textAnswer = null;
        $isCorrect = 0;

        if ($q['type'] === 'short_answer') {
            $textAnswer = trim($givenAnswer ?? '');
            // Basic match (case-insensitive against correct option text if set)
            $optParts = $q['options_raw'] ? explode('||', $q['options_raw']) : [];
            foreach ($optParts as $op) {
                [$oid, $otxt, $ocorrect] = explode('::', $op, 3);
                if ($ocorrect && strtolower($textAnswer) === strtolower(trim($otxt))) {
                    $isCorrect = 1; break;
                }
            }
        } else {
            if ($givenAnswer !== null) {
                // For true_false, givenAnswer may be 'true'/'false' string or option id
                $optParts = $q['options_raw'] ? explode('||', $q['options_raw']) : [];
                foreach ($optParts as $op) {
                    [$oid, $otxt, $ocorrect] = explode('::', $op, 3);
                    if ($oid == $givenAnswer || strtolower($otxt) === strtolower($givenAnswer)) {
                        $selectedOptionId = $oid;
                        $isCorrect = (int)$ocorrect;
                        break;
                    }
                }
            }
        }

        if ($isCorrect) {
            $earnedPoints += $q['points'];
            $correctCount++;
        }

        $insStmt->execute([$attemptId, $qId, $selectedOptionId ?: null, $textAnswer, $isCorrect]);
    }

    // Calculate score
    $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
    $passed = $score >= $quiz['pass_score'];

    // Time taken
    $timeTaken = time() - strtotime($attempt['started_at']);

    // Update attempt
    $db->prepare("UPDATE quiz_attempts SET score=?, submitted_at=NOW(), status='completed', time_taken_seconds=? WHERE id=?")
       ->execute([$score, $timeTaken, $attemptId]);

    // Certificate check: if passed and course-based and all lessons done
    if ($passed && $quiz['course_id']) {
        $progress = calculateProgress($userId, $quiz['course_id']);
        if ($progress >= 100) {
            $certCheck = $db->prepare("SELECT id FROM certificates WHERE student_id=? AND course_id=?");
            $certCheck->execute([$userId, $quiz['course_id']]);
            if (!$certCheck->fetch()) {
                $certCode = generateCertCode();
                $db->prepare("INSERT INTO certificates (student_id, course_id, cert_code, issued_at) VALUES (?,?,?,NOW())")
                   ->execute([$userId, $quiz['course_id'], $certCode]);
            }
        }
    }

    $db->commit();

    echo json_encode([
        'success'      => true,
        'score'        => $score,
        'passed'       => $passed,
        'correct'      => $correctCount,
        'total'        => count($questions),
        'redirect_url' => APP_URL.'/student/result.php?attempt='.$attemptId
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}
