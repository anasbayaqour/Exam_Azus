<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id']) && isset($_SESSION['user_id'])) {
    try {
        $pdo->beginTransaction();

        $exam_id = (int)$_POST['exam_id'];
        $student_id = (int)$_SESSION['user_id'];
        $attempt_id = $_SESSION['exam_attempt_id'] ?? null;

        if (!$attempt_id) {
            throw new Exception("No active exam attempt found. Please start the exam first.");
        }

        // Verify the exam attempt exists and belongs to this student
        $stmt = $pdo->prepare("SELECT id FROM exam_attempts WHERE id = ? AND student_id = ? AND exam_id = ?");
        $stmt->execute([$attempt_id, $student_id, $exam_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid exam attempt.");
        }

        // Fetch all questions for this exam
        $stmt = $pdo->prepare("
            SELECT q.id, q.points, q.question_type 
            FROM questions q 
            WHERE q.exam_id = ?
        ");
        $stmt->execute([$exam_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($questions)) {
            throw new Exception("No questions found for this exam.");
        }

        $total_points = 0;
        $earned_points = 0;

        // Delete any existing answers for this attempt (prevents duplicate submissions)
        $stmt = $pdo->prepare("DELETE FROM student_answers WHERE attempt_id = ?");
        $stmt->execute([$attempt_id]);

        // Process each question
        foreach ($questions as $question) {
            $question_id = $question['id'];
            $question_type = $question['question_type'];
            $points = (float)$question['points'];
            $total_points += $points;

            if (!isset($_POST['answer_'.$question_id])) {
                continue; // Skip if no answer provided
            }

            $answer = $_POST['answer_'.$question_id];
            $score = 0;
            $is_correct = 0;
            $selected_option_id = null;
            $answer_text = null;

            if ($question_type === 'mcq') {
                // For MCQ questions, check if the selected answer is correct
                $selected_option_id = (int)$answer;
                
                $stmt = $pdo->prepare("
                    SELECT 1 FROM question_options 
                    WHERE id = ? AND question_id = ? AND is_correct = 1
                ");
                $stmt->execute([$selected_option_id, $question_id]);
                
                $is_correct = $stmt->fetch() ? 1 : 0;
                $score = $is_correct ? $points : 0;
                $earned_points += $score;
            } 
            elseif ($question_type === 'open') {
                // For open-ended questions, save answer for manual grading
                $answer_text = trim($answer);
                // Initially set score to 0 (teacher will correct later)
                $score = 0;
            }

            // Insert the student's answer
            $stmt = $pdo->prepare("
                INSERT INTO student_answers (
                    attempt_id, 
                    question_id, 
                    selected_option_id, 
                    text_answer, 
                    is_correct, 
                    score,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $attempt_id,
                $question_id,
                $selected_option_id,
                $answer_text,
                $is_correct,
                $score
            ]);
        }

        // Calculate final score (only counting automatically graded MCQs)
        $final_score = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0;

        // Update exam attempt as completed
        $stmt = $pdo->prepare("
            UPDATE exam_attempts 
            SET 
                submit_time = NOW(),
                is_completed = 1,
                total_score = ?,
                status = IF(? > 0, 'submitted', 'pending')
            WHERE id = ?
        ");
        $stmt->execute([$final_score, $final_score, $attempt_id]);

        $pdo->commit();

        // Clear the exam attempt from session
        unset($_SESSION['exam_attempt_id']);

        // Redirect to confirmation page
        header("Location: exam-submission-confirmation.php?exam_id=".$exam_id);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Log the error and show user-friendly message
        error_log("Exam submission error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to submit exam: " . $e->getMessage();
        header("Location: take-exam.php?exam_id=".$exam_id);
        exit();
    }
} else {
    // Invalid request
    $_SESSION['error'] = "Invalid request";
    header("Location: student-dashboard.php");
    exit();
}