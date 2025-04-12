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
 /* Enhanced Color Palette with more options */
:root {
    --primary-color: #3498db;
    --primary-dark: #2980b9;
    --secondary-color: #2ecc71;
    --secondary-dark: #27ae60;
    --background-light: #f4f6f9;
    --background-gradient: linear-gradient(120deg, #f6f9fc 0%, #edf2f7 100%);
    --text-color: #2c3e50;
    --accent-color: #9b59b6;
    --success-color: #27ae60;
    --danger-color: #e74c3c;
    --warning-color: #f39c12;
    --info-color: #3498db;
    --gradient-primary: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    --gradient-success: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --gradient-danger: linear-gradient(135deg, #ff5f6d 0%, #ffc371 100%);
    --gradient-warning: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
    --shadow-soft: 0 10px 30px rgba(0, 0, 0, 0.08);
    --shadow-medium: 0 15px 35px rgba(0, 0, 0, 0.1);
    --shadow-hard: 0 20px 40px rgba(0, 0, 0, 0.15);
    --border-radius: 15px;
}

/* Global Styles Enhancement */
body {
    background: var(--background-gradient);
    font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
    color: var(--text-color);
    line-height: 1.6;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    right: 0;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(52, 152, 219, 0.1) 0%, rgba(52, 152, 219, 0) 70%);
    z-index: -1;
    border-radius: 50%;
    transform: translate(30%, -30%);
    animation: pulse 15s infinite alternate;
}

body::after {
    content: '';
    position: fixed;
    bottom: 0;
    left: 0;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(46, 204, 113, 0.1) 0%, rgba(46, 204, 113, 0) 70%);
    z-index: -1;
    border-radius: 50%;
    transform: translate(-30%, 30%);
    animation: pulse 20s infinite alternate-reverse;
}

@keyframes pulse {
    0% { transform: translate(-30%, 30%) scale(1); }
    50% { transform: translate(-25%, 25%) scale(1.05); }
    100% { transform: translate(-30%, 30%) scale(1); }
}

.container {
    background-color: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-medium);
    padding: 2.5rem;
    margin-top: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    z-index: 1;
    transition: all 0.5s ease;
}

.container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.05) 0%, rgba(46, 204, 113, 0.05) 100%);
    z-index: -1;
}

/* Enhanced Header Styling */
.d-flex h1 {
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
    transition: all 0.3s ease;
    position: relative;
    padding-bottom: 10px;
}

.d-flex h1::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 80px;
    height: 4px;
    background: var(--gradient-primary);
    border-radius: 2px;
    transition: width 0.3s ease;
}

.d-flex h1:hover {
    transform: scale(1.02);
}

.d-flex h1:hover::after {
    width: 120px;
}

/* Enhanced Result Card Styling */
.result-card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
    transition: all 0.5s ease;
    overflow: hidden;
    position: relative;
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
    box-shadow: var(--shadow-hard);
}

.result-card .card-header {
    background: rgba(52, 152, 219, 0.08);
    border-bottom: none;
    position: relative;
    overflow: hidden;
}

.result-card .card-header::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.result-card:hover .card-header::after {
    transform: translateX(100%);
}

/* Enhanced Question Card Styling */
.question-card {
    border-radius: 12px;
    margin-bottom: 1.5rem;
    transition: all 0.4s ease;
    border: none;
    box-shadow: var(--shadow-soft);
    position: relative;
    overflow: hidden;
    transform: perspective(1000px) rotateX(0deg);
}

.question-card:hover {
    transform: perspective(1000px) rotateX(2deg) translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.correct-answer {
    border-left: 5px solid var(--success-color);
    background: linear-gradient(to right, rgba(46, 204, 113, 0.08), rgba(46, 204, 113, 0.02));
}

.incorrect-answer {
    border-left: 5px solid var(--danger-color);
    background: linear-gradient(to right, rgba(231, 76, 60, 0.08), rgba(231, 76, 60, 0.02));
}

.correct-answer::before,
.incorrect-answer::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 50px;
    height: 50px;
    border-radius: 0 0 0 50px;
    background: var(--success-color);
    opacity: 0.1;
    z-index: 0;
    transition: all 0.3s ease;
}

.incorrect-answer::before {
    background: var(--danger-color);
}

.correct-answer:hover::before,
.incorrect-answer:hover::before {
    width: 70px;
    height: 70px;
    opacity: 0.15;
}

/* Enhanced Icons Styling */
.fas, .far {
    margin-right: 10px;
    transition: all 0.4s ease;
    color: var(--primary-color);
    transform-origin: center;
}

.fas:hover, .far:hover {
    transform: scale(1.2) rotate(10deg);
    color: var(--accent-color);
    text-shadow: 0 0 10px rgba(155, 89, 182, 0.3);
}

/* Enhanced Score Styling */
.text-success {
    background: var(--gradient-success);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: bold;
    position: relative;
    display: inline-block;
}

.text-success::after {
    content: attr(data-content);
    position: absolute;
    top: 0;
    left: 0;
    color: transparent;
    -webkit-text-stroke: 2px rgba(46, 204, 113, 0.2);
    z-index: -1;
}

.text-danger {
    background: var(--gradient-danger);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: bold;
    position: relative;
    display: inline-block;
}

.text-danger::after {
    content: attr(data-content);
    position: absolute;
    top: 0;
    left: 0;
    color: transparent;
    -webkit-text-stroke: 2px rgba(231, 76, 60, 0.2);
    z-index: -1;
}

/* Enhanced Badges */
.points-badge {
    background: var(--gradient-primary);
    transform: scale(1);
    transition: all 0.3s ease;
    border-radius: 20px;
    padding: 0.5rem 0.75rem;
    box-shadow: 0 4px 10px rgba(106, 17, 203, 0.2);
}

.points-badge:hover {
    transform: scale(1.1) rotate(2deg);
    box-shadow: 0 6px 15px rgba(106, 17, 203, 0.3);
}

/* Enhanced Feedback Section */
.feedback-section {
    background: rgba(52, 152, 219, 0.05);
    border-left: 4px solid var(--primary-color);
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.feedback-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(120deg, transparent, rgba(52, 152, 219, 0.05), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.feedback-section:hover {
    background: rgba(52, 152, 219, 0.1);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.1);
}

.feedback-section:hover::before {
    transform: translateX(100%);
}

/* Enhanced Buttons */
.btn-primary {
    background: var(--gradient-primary);
    border: none;
    border-radius: 50px;
    padding: 0.6rem 1.5rem;
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(106, 17, 203, 0.25);
}

.btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255,255,255,0.4), transparent);
    transition: all 0.6s;
}

.btn-primary:hover::before {
    left: 100%;
}

.btn-primary:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 7px 20px rgba(106, 17, 203, 0.4);
}

.btn-outline-secondary {
    border-radius: 50px;
    padding: 0.6rem 1.5rem;
    transition: all 0.3s ease;
    border-color: var(--primary-color);
    color: var(--primary-color);
    position: relative;
    overflow: hidden;
    z-index: 1;
}

.btn-outline-secondary::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
    transition: width 0.3s ease;
    z-index: -1;
}

.btn-outline-secondary:hover {
    color: white;
    border-color: var(--primary-color);
    background-color: transparent;
}

.btn-outline-secondary:hover::before {
    width: 100%;
}

/* Enhanced Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

.result-card {
    animation: fadeInUp 0.6s ease-out, float 6s ease-in-out infinite;
    animation-delay: 0s, 1s;
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

.question-card:nth-child(4n) {
    animation-delay: 0.6s;
}

/* Progress Bar for Score Visualization */
.score-bar-container {
    height: 12px;
    background-color: rgba(52, 152, 219, 0.1);
    border-radius: 6px;
    margin-top: 10px;
    overflow: hidden;
    position: relative;
}

.score-bar {
    height: 100%;
    border-radius: 6px;
    background: var(--gradient-primary);
    width: 0;
    transition: width 1.5s cubic-bezier(0.19, 1, 0.22, 1);
    position: relative;
    overflow: hidden;
}

.score-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, 
        rgba(255,255,255,0) 0%, 
        rgba(255,255,255,0.4) 50%, 
        rgba(255,255,255,0) 100%);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Highlight Effects */
.highlight-text {
    display: inline-block;
    position: relative;
    z-index: 1;
}

.highlight-text::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 30%;
    background-color: rgba(46, 204, 113, 0.2);
    z-index: -1;
    transform: rotate(-1deg);
    transition: height 0.3s ease;
}

.highlight-text:hover::after {
    height: 50%;
}

/* Fixed Navigation Button */
.floating-nav {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--gradient-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 20px rgba(106, 17, 203, 0.3);
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 100;
}

.floating-nav:hover {
    transform: scale(1.1) rotate(10deg);
    box-shadow: 0 8px 25px rgba(106, 17, 203, 0.4);
}

.floating-nav i {
    color: white;
    font-size: 24px;
    margin-right: 0;
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 10px;
}

::-webkit-scrollbar-track {
    background: rgba(52, 152, 219, 0.05);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(var(--primary-color), var(--accent-color));
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(var(--accent-color), var(--primary-color));
}

/* Enhanced Responsive Adjustments */
@media (max-width: 992px) {
    .container {
        padding: 1.5rem;
    }
    
    .floating-nav {
        width: 50px;
        height: 50px;
        bottom: 20px;
        right: 20px;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
        margin-top: 1rem;
        border-radius: 10px;
    }
    
    .points-badge {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
    }
    
    body::before, body::after {
        opacity: 0.5;
    }
}

@media (max-width: 576px) {
    .d-flex h1 {
        font-size: 1.8rem;
    }
    
    .floating-nav {
        width: 45px;
        height: 45px;
        bottom: 15px;
        right: 15px;
    }
    
    .floating-nav i {
        font-size: 18px;
    }
}

/* Print-friendly Styles */
@media print {
    body, .container {
        background: white !important;
        box-shadow: none !important;
    }
    
    .floating-nav {
        display: none !important;
    }
    
    .question-card, .result-card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
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
                    <a href="student.php" class="btn btn-outline-secondary">
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
                    <a href="student.php?exam_id=<?= $exam_id ?>&student_id=<?= $student_id ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Correction
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>