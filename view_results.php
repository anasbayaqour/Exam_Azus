<?php
require_once 'config.php';
session_start();

// Authentication and access control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$student_id = 0;
$error_message = '';
$exam = null;
$student = null;
$results = null;
$questions = [];
$is_teacher_view = false;

try {
    // Validate database connection
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // Determine viewing context (teacher or student)
    if ($_SESSION['user_type'] === 'teacher') {
        // Teacher viewing a specific student's results
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        
        if ($student_id <= 0) {
            throw new Exception("Invalid student selected");
        }
        
        // Verify teacher's permission to view this exam
        $stmt = $pdo->prepare("
            SELECT e.id, e.title, e.description 
            FROM exams e
            WHERE e.id = ? AND e.teacher_id = ?
        ");
        $stmt->execute([$exam_id, $_SESSION['user_id']]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            throw new Exception("Exam not found or you don't have permission to view it");
        }
        
        $is_teacher_view = true;
    } else {
        // Student viewing their own results
        $student_id = $_SESSION['user_id'];
        
        // Get exam information for the student
        $stmt = $pdo->prepare("
            SELECT e.id, e.title, e.description 
            FROM exams e
            JOIN exam_results er ON e.id = er.exam_id
            WHERE e.id = ? AND er.student_id = ?
        ");
        $stmt->execute([$exam_id, $student_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            throw new Exception("Exam results not found");
        }
    }

    // Fetch student information
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name 
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student information not found");
    }

    // Fetch comprehensive exam results
    // FIXED: Changed query to use proper table names based on the schema
    $stmt = $pdo->prepare("
        SELECT 
            er.score,
            er.submitted_at,
            er.corrected_at,
            ea.total_score,
            ea.final_score,
            ea.graded_at,
            u.first_name as grader_first_name,
            u.last_name as grader_last_name
        FROM exam_results er
        LEFT JOIN exam_attempts ea ON er.student_id = ea.student_id AND er.exam_id = ea.exam_id
        LEFT JOIN users u ON ea.graded_by = u.user_id
        WHERE er.exam_id = ? AND er.student_id = ?
    ");
    $stmt->execute([$exam_id, $student_id]);
    $results = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$results) {
        throw new Exception("No results found for this exam");
    }

    // Fetch questions and student answers with comprehensive details
    // FIXED: Corrected SQL query to reference the right tables and columns
    $stmt = $pdo->prepare("
        SELECT 
            q.id,
            q.question_text,
            q.question_type,
            q.points as max_points,
            sa.answer_text,
            sa.selected_option_id,
            COALESCE(sa.points_earned, 0) as points_earned,
            COALESCE(sa.feedback, '') as feedback,
            COALESCE(sa.is_correct, FALSE) as is_correct,
            qo.option_text as selected_option_text,
            (
                SELECT GROUP_CONCAT(option_text SEPARATOR '|')
                FROM question_options 
                WHERE question_id = q.id AND is_correct = TRUE
            ) as correct_options
        FROM questions q
        LEFT JOIN exam_attempts ea ON q.exam_id = ea.exam_id
        LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ea.id
        LEFT JOIN question_options qo ON sa.selected_option_id = qo.id
        WHERE q.exam_id = ? AND ea.student_id = ?
        ORDER BY q.id
    ");
    $stmt->execute([$exam_id, $student_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark notification as read for student
    if ($_SESSION['user_type'] === 'student') {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? 
            AND notification_type = 'grade_available' 
            AND reference_id = ?
        ");
        $stmt->execute([$student_id, $exam_id]);
    }

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error in view_results.php: " . $e->getMessage());
    $error_message = $e->getMessage();
}

// Calculate total possible points
$total_possible_points = array_reduce($questions, function($carry, $q) {
    return $carry + $q['max_points'];
}, 0);

// Ensure we have a valid score
$final_score = isset($results['score']) ? round($results['score'], 2) : 0;
$total_score = isset($results['total_score']) ? round($results['total_score'], 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - <?= htmlspecialchars($exam['title'] ?? 'Unknown Exam') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Color Palette */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2ecc71;
        --background-light: #f4f6f9;
        --text-color: #2c3e50;
        --accent-color: #9b59b6;
        --success-color: #27ae60;
        --danger-color: #e74c3c;
        --warning-color: #f39c12;
        --gradient-primary: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        --gradient-success: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        --gradient-danger: linear-gradient(135deg, #ff5f6d 0%, #ffc371 100%);
    }

    /* Global Styles */
    body {
        background-color: var(--background-light);
        font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
        color: var(--text-color);
        line-height: 1.6;
    }

    .container {
        background-color: white;
        border-radius: 15px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        padding: 2.5rem;
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

    /* Result Card Styling */
    .result-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .result-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: var(--gradient-primary);
    }

    .result-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .result-card .card-header {
        background: rgba(52, 152, 219, 0.1);
        border-bottom: none;
    }

    /* Question Card Styling */
    .question-card {
        border-radius: 12px;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .question-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .correct-answer {
        border-left: 5px solid var(--success-color);
        background: linear-gradient(to right, rgba(46, 204, 113, 0.05), transparent);
    }

    .incorrect-answer {
        border-left: 5px solid var(--danger-color);
        background: linear-gradient(to right, rgba(231, 76, 60, 0.05), transparent);
    }

    /* Icons Styling */
    .fas, .far {
        margin-right: 10px;
        transition: all 0.3s ease;
        color: var(--primary-color);
    }

    .fas:hover, .far:hover {
        transform: scale(1.2) rotate(5deg);
        color: var(--accent-color);
    }

    /* Score Styling */
    .text-success {
        background: var(--gradient-success);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: bold;
    }

    .text-danger {
        background: var(--gradient-danger);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: bold;
    }

    /* Badges */
    .points-badge {
        background: var(--gradient-primary);
        transform: scale(1);
        transition: transform 0.2s ease;
    }

    .points-badge:hover {
        transform: scale(1.1);
    }

    /* Feedback Section */
    .feedback-section {
        background: rgba(52, 152, 219, 0.05);
        border-left: 4px solid var(--primary-color);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .feedback-section:hover {
        background: rgba(52, 152, 219, 0.1);
    }

    /* Buttons */
    .btn-primary {
        background: var(--gradient-primary);
        border: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(120deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: all 0.6s;
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .question-card {
        animation: fadeInUp 0.5s ease-out;
        animation-fill-mode: backwards;
    }

    .question-card:nth-child(2n) {
        animation-delay: 0.2s;
    }

    .question-card:nth-child(3n) {
        animation-delay: 0.4s;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .container {
            padding: 1rem;
            margin-top: 0;
        }
    }
</style>
</head>
<body>
    <div class="container py-4">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>
                    <i class="fas fa-poll"></i>
                    Exam Results: <?= htmlspecialchars($exam['title']) ?>
                </h1>
                
                <?php if ($is_teacher_view): ?>
                    <a href="pending_submissions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Submissions
                    </a>
                <?php else: ?>
                    <a href="student_dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                <?php endif; ?>
            </div>

            <div class="card result-card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-user-graduate"></i>
                        Student: <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>Final Score:</strong></p>
                            <h2 class="<?= ($final_score >= 50 ? 'text-success' : 'text-danger') ?>">
                                <?= $final_score ?>%
                            </h2>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Points Earned:</strong></p>
                            <h4>
                                <?= $total_score ?> / <?= $total_possible_points ?>
                            </h4>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Graded On:</strong></p>
                            <p>
                                <?php 
                                $graded_date = $results['graded_at'] ?? $results['corrected_at'] ?? null;
                                echo $graded_date 
                                    ? htmlspecialchars(date('F j, Y g:i a', strtotime($graded_date))) 
                                    : 'Not graded yet';
                                ?>
                            </p>
                            <?php if (!empty($results['grader_first_name'])): ?>
                                <p>
                                    <strong>Graded By:</strong> 
                                    <?= htmlspecialchars($results['grader_first_name'] . ' ' . $results['grader_last_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($exam['description'])): ?>
                        <div class="mt-3">
                            <p><strong>Exam Description:</strong></p>
                            <p><?= nl2br(htmlspecialchars($exam['description'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="mb-3">
                <i class="fas fa-question-circle"></i>
                Questions and Answers
            </h3>

            <?php foreach ($questions as $question): ?>
                <div class="card question-card <?= ($question['is_correct'] ? 'correct-answer' : 'incorrect-answer') ?>">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 d-flex justify-content-between align-items-center">
                            Question <?= htmlspecialchars($question['id']) ?>
                            <span class="badge bg-primary points-badge">
                                <?= htmlspecialchars($question['points_earned']) ?> / 
                                <?= htmlspecialchars($question['max_points']) ?> pts
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="question-text mb-3">
                            <p><strong>Question:</strong></p>
                            <p><?= nl2br(htmlspecialchars($question['question_text'])) ?></p>
                        </div>
                        
                        <div class="student-answer mb-3">
                            <p><strong>Your Answer:</strong></p>
                            <?php if ($question['question_type'] === 'mcq'): ?>
                                <p><?= htmlspecialchars($question['selected_option_text'] ?: 'No answer provided') ?></p>
                            <?php else: ?>
                                <p><?= nl2br(htmlspecialchars($question['answer_text'] ?: 'No answer provided')) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($question['question_type'] === 'mcq' && !empty($question['correct_options'])): ?>
                            <div class="correct-answers mb-3">
                                <p><strong>Correct Answer(s):</strong></p>
                                <ul>
                                    <?php foreach (explode('|', $question['correct_options']) as $correct_option): ?>
                                        <?php if (!empty($correct_option)): ?>
                                            <li><?= htmlspecialchars($correct_option) ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($question['feedback'])): ?>
                            <div class="feedback-section p-3 mt-3 mb-0">
                                <p class="mb-1"><strong>Teacher Feedback:</strong></p>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($question['feedback'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($is_teacher_view): ?>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="correction.php?exam_id=<?= $exam_id ?>&student_id=<?= $student_id ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Correction
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>