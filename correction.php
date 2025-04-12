<?php
// Include configuration and start session
require_once 'config.php';
session_start();


$exam_id = 0;
$student_id = 0;
$error_message = '';
$success_message = '';
$exam = ['title' => 'Unknown Exam', 'id' => 0];
$student = ['first_name' => 'Unknown', 'last_name' => 'Student', 'user_id' => 0];
$questions = [];

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    // Redirect unauthorized users
    header("Location: login.php");
    exit();
}

// Validate required parameters
if (!isset($_GET['exam_id']) || !isset($_GET['student_id'])) {
    $_SESSION['error'] = "Exam ID and Student ID are required";
    header("Location: pending_submissions.php");
    exit();
}

// Sanitize input parameters
$exam_id = (int)$_GET['exam_id'];
$student_id = (int)$_GET['student_id'];

try {
    // Check database connection
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // Fetch student information
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, user_id 
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student not found");
    }

    // Verify the exam exists and belongs to the teacher
    $stmt = $pdo->prepare("
        SELECT e.id, e.title 
        FROM exams e
        WHERE e.id = ? AND e.teacher_id = ?
    ");
    $stmt->execute([$exam_id, $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        throw new Exception("Exam not found or you don't have permission to correct it");
    }

    // Find the exam attempt for this student and exam
    $stmt = $pdo->prepare("
        SELECT id FROM exam_attempts 
        WHERE student_id = ? AND exam_id = ?
    ");
    $stmt->execute([$student_id, $exam_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        throw new Exception("No exam attempt found for this student");
    }
    $attempt_id = $attempt['id'];

    // Fetch open-ended questions and student answers
    $stmt = $pdo->prepare("
        SELECT 
            q.id, 
            q.question_text, 
            q.points, 
            sa.answer_text, 
            COALESCE(sa.points_earned, 0) as points_earned,
            COALESCE(sa.feedback, '') as feedback, 
            sa.id as answer_id,
            q.question_type
        FROM questions q
        LEFT JOIN student_answers sa ON q.id = sa.question_id 
        LEFT JOIN exam_attempts ea ON sa.attempt_id = ea.id
        WHERE ea.student_id = ? 
        AND q.exam_id = ? 
        AND q.question_type IN ('open', 'essay', 'short_answer')
        ORDER BY q.id
    ");
    $stmt->execute([$student_id, $exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_correction'])) {
        // Start a database transaction
        $pdo->beginTransaction();
        $manual_points = 0;

        // Process each question's score
        foreach ($_POST['scores'] as $answer_id => $score_data) {
            $answer_id = (int)$answer_id;
            $points = (float)$score_data['points'];
            $feedback = trim($score_data['feedback'] ?? '');
            
            // Validate points are within question limits
            $question_points = (float)$score_data['max_points'];
            if ($points < 0 || $points > $question_points) {
                throw new Exception("Invalid points for question");
            }
            
            $manual_points += $points;

            // Update student answer with points and feedback
            $stmt = $pdo->prepare("
                UPDATE student_answers 
                SET 
                    points_earned = :points, 
                    feedback = :feedback
                WHERE id = :answer_id
            ");
            $stmt->execute([
                ':points' => $points, 
                ':feedback' => $feedback, 
                ':answer_id' => $answer_id
            ]);
        }


        // Calculate total score
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(q.points), 0) as total_points,
                COALESCE(SUM(
                    CASE 
                        WHEN q.question_type = 'mcq' AND qo.is_correct = 1 THEN q.points
                        WHEN q.question_type IN ('open', 'essay', 'short_answer') THEN COALESCE(sa.points_earned, 0)
                        ELSE 0
                    END
                ), 0) as earned_points
            FROM questions q
            LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ?
            LEFT JOIN question_options qo ON q.id = qo.question_id AND sa.selected_option_id = qo.id
            WHERE q.exam_id = ?
        ");
        $stmt->execute([$attempt_id, $exam_id]);
        $score_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_points = (float)$score_data['total_points'];
        $earned_points = (float)$score_data['earned_points'];
        $score = $total_points > 0 ? round(($earned_points / $total_points) * 100, 1) : 0;

        // Update exam attempt status
        $stmt = $pdo->prepare("
            UPDATE exam_attempts
            SET status = 'graded', 
                total_score = ?,
                final_score = ?,
                graded_by = ?,
                graded_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$earned_points, $score, $_SESSION['user_id'], $attempt_id]);

        // Insert or update exam results
        $stmt = $pdo->prepare("
            INSERT INTO exam_results 
            (student_id, exam_id, score, submitted_at, corrected_at, status) 
            VALUES (?, ?, ?, NOW(), NOW(), 'complete')
            ON DUPLICATE KEY UPDATE 
            score = ?, 
            corrected_at = NOW(), 
            status = 'complete'
        ");
        $stmt->execute([
            $student_id, $exam_id, $score, 
            $score
        ]);

        // Create notification for the student
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, 
                title, 
                message, 
                notification_type, 
                reference_id
            ) VALUES (
                :student_id, 
                :title, 
                :message, 
                'grade_available', 
                :exam_id
            )
        ");
        
        $notification_title = "Exam Graded: " . htmlspecialchars($exam['title']);
        $notification_message = sprintf(
            "Your exam '%s' has been graded. You scored %.1f%%.", 
            htmlspecialchars($exam['title']), 
            $score
        );
        
        $stmt->execute([
            ':student_id' => $student_id,
            ':title' => $notification_title,
            ':message' => $notification_message,
            ':exam_id' => $exam_id
        ]);

        $pdo->commit();

        // Redirect after successful submission
        $_SESSION['success'] = "Exam correction submitted successfully";
        header("Location: view_results.php?exam_id=$exam_id&student_id=$student_id");
        exit();
    }

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error and set error message
    error_log("Error in correction.php: " . $e->getMessage());
    $error_message = "An error occurred: " . $e->getMessage();
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Correction - <?= htmlspecialchars($exam['title'] ?? 'Unknown Exam') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2ecc71;
        --background-light: #f4f6f9;
        --text-color: #2c3e50;
        --accent-color: #9b59b6;
        --success-color: #27ae60;
        --danger-color: #e74c3c;
        --gradient-primary: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        --gradient-secondary: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    body {
        background-color: var(--background-light);
        font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
        color: var(--text-color);
        line-height: 1.6;
    }

    /* Container Styling */
    .container {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
        padding: 2rem;
        margin-top: 2rem;
    }

    /* Header Styling */
    .d-flex h1 {
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .d-flex h1:hover {
        transform: scale(1.02);
    }

    /* Card Styling */
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    }

    .card-header {
        background: var(--gradient-secondary);
        color: white;
        border-bottom: none;
        border-radius: 12px 12px 0 0;
    }

    .question-card {
        border-left: 5px solid var(--primary-color);
        transition: all 0.3s ease;
    }

    .question-card:hover {
        border-left-width: 8px;
    }

    /* Icons Styling */
    .fas, .far {
        margin-right: 10px;
        transition: color 0.3s ease;
    }

    .card-body .fas, .card-body .far {
        color: var(--primary-color);
    }

    .btn .fas, .btn .far {
        transition: transform 0.2s ease;
    }

    .btn:hover .fas, .btn:hover .far {
        transform: scale(1.2) rotate(5deg);
    }

    /* Input Styling */
    .form-control, .points-input {
        border-radius: 8px;
        border-color: rgba(45, 145, 225, 0.2);
        transition: all 0.3s ease;
    }

    .form-control:focus, .points-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }

    .points-input {
        font-weight: bold;
        color: var(--accent-color);
    }

    .max-points {
        background-color: rgba(52, 152, 219, 0.1);
        color: var(--primary-color);
    }

    /* Badges and Tags */
    .badge {
        background: var(--gradient-primary);
        transition: transform 0.2s ease;
    }

    .badge:hover {
        transform: scale(1.05);
    }

    /* Buttons */
    .btn-primary {
        background: var(--gradient-primary);
        border: none;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
    }

    /* Alerts */
    .alert-danger {
        background-color: rgba(231, 76, 60, 0.1);
        border: 1px solid var(--danger-color);
        color: var(--danger-color);
    }

    .alert-info {
        background-color: rgba(52, 152, 219, 0.1);
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .question-card {
        animation: fadeIn 0.5s ease-out;
        animation-fill-mode: backwards;
    }

    .question-card:nth-child(2n) {
        animation-delay: 0.2s;
    }

    .question-card:nth-child(3n) {
        animation-delay: 0.4s;
    }
</style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>
                <i class="fas fa-check-circle text-primary"></i>
                Correcting: <?= htmlspecialchars($exam['title'] ?? 'Unknown Exam') ?>
            </h1>
            <a href="pending_submissions.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Submissions
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-user-graduate"></i>
                    Student: <?= htmlspecialchars(($student['first_name'] ?? 'Unknown') . ' ' . ($student['last_name'] ?? 'Student')) ?>
                </h5>
                <p class="card-text">
                    <i class="fas fa-calendar-alt"></i>
                    Exam ID: <?= htmlspecialchars($exam_id ?? 'N/A') ?>
                </p>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php
                // More detailed explanation for why no questions appear
                $stmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM questions WHERE exam_id = ?) as total_questions,
                        (SELECT COUNT(*) FROM questions WHERE exam_id = ? AND question_type IN ('open', 'essay', 'short_answer')) as open_questions
                ");
                $stmt->execute([$exam_id, $exam_id]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($counts['total_questions'] == 0) {
                    echo "This exam contains no questions at all.";
                } elseif ($counts['open_questions'] == 0) {
                    echo "This exam only contains multiple-choice questions (no open-ended questions to correct).";
                } else {
                    echo "No answers found for the open-ended questions in this exam.";
                }
                ?>
            </div>
        <?php else: ?>
            <form method="POST" class="correction-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                
                <?php foreach ($questions as $question): ?>
                    <div class="card question-card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                Question <?= htmlspecialchars($question['id'] ?? '') ?> 
                                <span class="badge bg-primary float-end">
                                    <?= htmlspecialchars($question['points'] ?? 0) ?> pts
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="question-text mb-3">
                                <?= nl2br(htmlspecialchars($question['question_text'] ?? '')) ?>
                            </div>
                            
                            <div class="student-answer">
                                <h6 class="text-muted">Student Answer:</h6>
                                <div class="answer-text">
                                    <?= nl2br(htmlspecialchars($question['answer_text'] ?? '')) ?>
                                </div>
                            </div>
                            
                            <div class="correction-section mt-3">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Points Awarded:</label>
                                        <div class="input-group">
                                            <input type="number" 
                                                   name="scores[<?= htmlspecialchars($question['answer_id'] ?? '') ?>][points]" 
                                                   class="form-control points-input"
                                                   min="0" 
                                                   max="<?= htmlspecialchars($question['points'] ?? 0) ?>" 
                                                   step="0.5"
                                                   value="<?= htmlspecialchars($question['points_earned'] ?? 0) ?>" 
                                                   required>
                                            <span class="input-group-text max-points">
                                                / <?= htmlspecialchars($question['points'] ?? 0) ?> pts
                                            </span>
                                        </div>
                                        <input type="hidden" 
                                               name="scores[<?= htmlspecialchars($question['answer_id'] ?? '') ?>][max_points]" 
                                               value="<?= htmlspecialchars($question['points'] ?? 0) ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Feedback:</label>
                                        <textarea name="scores[<?= htmlspecialchars($question['answer_id'] ?? '') ?>][feedback]" 
                                                  class="form-control" 
                                                  rows="3"><?= htmlspecialchars($question['feedback'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" name="submit_correction" class="btn btn-primary">
                        <i class="fas fa-save"></i> Submit Corrections
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validate points before submission
            document.querySelector('.correction-form')?.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[type="number"]');
                let valid = true;
                
                inputs.forEach(input => {
                    const max = parseFloat(input.max);
                    const value = parseFloat(input.value);
                    
                    if (isNaN(value) || value < 0 || value > max) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please enter valid points for all questions (between 0 and max points)');
                    return false;
                }
                
                return true;
            });

            // Real-time validation as user types
            document.querySelectorAll('.points-input').forEach(input => {
                input.addEventListener('input', function() {
                    const max = parseFloat(this.max);
                    const value = parseFloat(this.value);
                    
                    if (isNaN(value) || value < 0 || value > max) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            });
        });
    </script>
</body>
</html>