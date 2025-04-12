<?php
session_start();
date_default_timezone_set('Africa/Casablanca');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$exam = [];
$questions = [];
$remaining_time = 0;

try {
    // Validate exam ID
    if (!$exam_id) {
        throw new Exception("Invalid exam ID.");
    }

    // Check database connection
    if (!$pdo) {
        throw new Exception("Database connection failed.");
    }

    // Get exam details and verify availability
    $stmt = $pdo->prepare("
        SELECT e.*, 
               CASE 
                   WHEN ea.id IS NULL THEN 'Not Started'
                   WHEN ea.is_completed = 0 THEN 'In Progress'
                   ELSE 'Completed'
               END as attempt_status
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = :student_id
        WHERE e.id = :exam_id
        AND e.status = 'published'
        AND e.start_date <= NOW()
        AND e.end_date >= NOW()
    ");
    $stmt->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        throw new Exception("Exam not available or time window has passed.");
    }

    // Check if exam is already completed
    if ($exam['attempt_status'] === 'Completed') {
        header("Location: student.php#results");
        exit();
    }

    // Calculate remaining time in seconds
    $now = new DateTime();
    $end_time = new DateTime($exam['end_date']);
    $remaining_time = $end_time->getTimestamp() - $now->getTimestamp();

    // Don't allow more time than exam duration
    $exam_duration = $exam['duration'] * 60; // Convert minutes to seconds
    $remaining_time = min($remaining_time, $exam_duration);

    // Get exam questions with their options
    $stmt = $pdo->prepare("
        SELECT q.*, o.id as option_id, o.option_text, o.is_correct
        FROM questions q
        LEFT JOIN question_options o ON q.id = o.question_id
        WHERE q.exam_id = :exam_id
        ORDER BY q.id, o.option_order
    ");
    $stmt->execute(['exam_id' => $exam_id]);
    $raw_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize questions with their options
    $questions = [];
    foreach ($raw_questions as $row) {
        $question_id = $row['id'];
        if (!isset($questions[$question_id])) {
            $questions[$question_id] = [
                'id' => $row['id'],
                'question_text' => $row['question_text'],
                'type' => $row['question_type'],
                'points' => $row['points'],
                'options' => []
            ];
        }
        if ($row['option_id']) {
            $questions[$question_id]['options'][] = [
                'id' => $row['option_id'],
                'text' => $row['option_text'],
                'is_correct' => $row['is_correct']
            ];
        }
    }

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
        $pdo->beginTransaction();

        // Record exam attempt
        if ($exam['attempt_status'] === 'Not Started') {
            $stmt = $pdo->prepare("
                INSERT INTO exam_attempts 
                (student_id, exam_id, start_time, is_completed) 
                VALUES (?, ?, NOW(), 1)
            ");
            $stmt->execute([$student_id, $exam_id]);
            $attempt_id = $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("
                UPDATE exam_attempts 
                SET is_completed = 1, submit_time = NOW() 
                WHERE student_id = ? AND exam_id = ?
            ");
            $stmt->execute([$student_id, $exam_id]);
            
            // Get existing attempt ID
            $stmt = $pdo->prepare("SELECT id FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
            $stmt->execute([$student_id, $exam_id]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            $attempt_id = $attempt['id'];
        }

        // Process answers
        $total_points = 0;
        $mcq_points = 0;

        foreach ($questions as $question) {
            $question_id = $question['id'];
            
            if ($question['type'] === 'mcq') {
                // Process MCQ answers
                $option_id = isset($POST['answer'.$question_id]) ? (int)$POST['answer'.$question_id] : 0;
                
                // Find the selected option
                $selected_option = null;
                foreach ($question['options'] as $option) {
                    if ($option['id'] == $option_id) {
                        $selected_option = $option;
                        break;
                    }
                }
                
                // Calculate points
                $points = ($selected_option && $selected_option['is_correct']) ? $question['points'] : 0;
                $mcq_points += $points;
                
                // Record student answer - FIXED: Added correct columns and matching parameters
                $stmt = $pdo->prepare("
                    INSERT INTO student_answers 
                    (attempt_id, question_id, selected_option_id, answer_text, is_correct) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $attempt_id,
                    $question_id,
                    $option_id,
                    $selected_option ? $selected_option['text'] : '',
                    ($selected_option && $selected_option['is_correct']) ? 1 : 0
                ]);
            } else {
                // Process open-ended answers
                $answer_text = isset($POST['answer'.$question_id]) ? trim($POST['answer'.$question_id]) : '';
                $answer_language = isset($POST['language'.$question_id]) ? trim($POST['language'.$question_id]) : 'text';
                
                // Record student answer (points will be assigned later by teacher)
                // FIXED: Make sure the answer_language column exists or modify this query
                $stmt = $pdo->prepare("
                    INSERT INTO student_answers 
                    (attempt_id, question_id, answer_text, selected_option_id, is_correct) 
                    VALUES (?, ?, ?, NULL, 0)
                ");
                $stmt->execute([$attempt_id, $question_id, $answer_text]);
                
                // Optional: If you've added the answer_language column, use this query instead:
                /*
                $stmt = $pdo->prepare("
                    INSERT INTO student_answers 
                    (attempt_id, question_id, answer_text, answer_language, selected_option_id, is_correct) 
                    VALUES (?, ?, ?, ?, NULL, 0)
                ");
                $stmt->execute([$attempt_id, $question_id, $answer_text, $answer_language]);
                */
            }
        }

        // Calculate total points (only MCQ for now, open-ended will be added after correction)
        $total_points = $mcq_points;

        // Update exam attempt with total score
        $stmt = $pdo->prepare("
            UPDATE exam_attempts 
            SET total_score = ? 
            WHERE id = ?
        ");
        $stmt->execute([$total_points, $attempt_id]);

        // Record exam results with pending status for open-ended questions
        $stmt = $pdo->prepare("
            INSERT INTO exam_results 
            (student_id, exam_id, submitted_at, status, score) 
            VALUES (?, ?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE submitted_at = NOW(), status = VALUES(status), score = VALUES(score)
        ");
        
        // If there are open-ended questions, status is pending, otherwise complete
        $has_open_questions = count(array_filter($questions, function($q) { return $q['type'] === 'open'; })) > 0;
        $status = $has_open_questions ? 'pending' : 'complete';
        
        $stmt->execute([$student_id, $exam_id, $status, $total_points]);

        $pdo->commit();

        $success_message = "Exam submitted successfully!" . ($has_open_questions ? " Your results will be available after correction." : "");
        header("Refresh: 3; url=student.php#results");
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in take-exam.php: " . $e->getMessage());
    $error_message = "An error occurred: " . $e->getMessage();
}

// Pass data to view
$view_data = [
    'exam' => $exam,
    'questions' => $questions,
    'remaining_time' => $remaining_time,
    'error_message' => $error_message,
    'success_message' => $success_message
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - ExamOnline</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/take-exam.css">
    <style>
/* Base Styles */
:root {
    --primary-color: #4361ee;
    --primary-light: #4895ef;
    --primary-dark: #3a0ca3;
    --secondary-color: #f72585;
    --success-color: #4cc9f0;
    --warning-color: #fca311;
    --danger-color: #e63946;
    --light-color: #f8f9fa;
    --dark-color: #212529;
    --grey-color: #6c757d;
    --border-radius: 8px;
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: #f5f7fb;
    color: var(--dark-color);
    line-height: 1.6;
}

.app-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Navigation */
.top-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    margin-bottom: 30px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.logo {
    display: flex;
    align-items: center;
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-color);
}

.logo i {
    margin-right: 10px;
    font-size: 28px;
}

.home-link {
    display: flex;
    align-items: center;
    color: var(--grey-color);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.home-link:hover {
    color: var(--primary-color);
}

.home-link i {
    margin-right: 8px;
}

/* Main Container */
.main-container {
    padding: 20px;
    border-radius: var(--border-radius);
    background-color: white;
    box-shadow: var(--box-shadow);
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.alert i {
    margin-right: 10px;
    font-size: 18px;
}

.alert-error {
    background-color: rgba(230, 57, 70, 0.1);
    color: var(--danger-color);
    border-left: 4px solid var(--danger-color);
}

.alert-success {
    background-color: rgba(76, 201, 240, 0.1);
    color: var(--success-color);
    border-left: 4px solid var(--success-color);
}

/* Exam Header */
.exam-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.exam-title h1 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--primary-dark);
}

.subject-tag {
    display: inline-block;
    background-color: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

.timer-container {
    margin-top: 10px;
}

.timer {
    display: flex;
    align-items: center;
    background-color: rgba(247, 37, 133, 0.1);
    color: var(--secondary-color);
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 18px;
    font-weight: 600;
}

.timer i {
    margin-right: 10px;
}

/* Progress Bar */
.exam-progress {
    margin-bottom: 30px;
}

.progress-bar {
    height: 8px;
    background-color: rgba(67, 97, 238, 0.1);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress {
    height: 100%;
    background: linear-gradient(to right, var(--primary-light), var(--primary-dark));
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-text {
    text-align: right;
    font-size: 14px;
    color: var(--grey-color);
}

/* Exam Info Card */
.exam-info-card {
    background-color: #f8f9fa;
    border-radius: var(--border-radius);
    margin-bottom: 30px;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.card-header {
    background-color: var(--primary-color);
    color: white;
    padding: 15px 20px;
}

.card-header h2 {
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.card-header h2 i {
    margin-right: 10px;
}

.card-content {
    padding: 20px;
}

.card-content p {
    margin-bottom: 20px;
}

.exam-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.metadata-item {
    display: flex;
    align-items: center;
}

.metadata-item i {
    margin-right: 10px;
    color: var(--primary-color);
}

/* Question Cards */
.questions-container {
    position: relative;
}

.question-card {
    background-color: white;
    border-radius: var(--border-radius);
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 30px;
    margin-bottom: 30px;
    display: none;
}

.question-card.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.question-number {
    display: inline-block;
    background-color: var(--primary-color);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
}

.question-text {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 20px;
    line-height: 1.6;
}

.question-points {
    font-size: 14px;
    color: var(--grey-color);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.question-points i {
    color: var(--warning-color);
    margin-right: 5px;
}

/* Answer Options */
.answer-container {
    margin-bottom: 30px;
}

.answer-option {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: var(--border-radius);
    border: 1px solid rgba(0, 0, 0, 0.1);
    margin-bottom: 10px;
    cursor: pointer;
    transition: var(--transition);
}

.answer-option:hover {
    background-color: rgba(67, 97, 238, 0.05);
    border-color: var(--primary-light);
}

.answer-option.selected {
    background-color: rgba(67, 97, 238, 0.1);
    border-color: var(--primary-color);
}

.option-input {
    display: none;
}

.option-text {
    position: relative;
    padding-left: 30px;
}

.option-text:before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border: 2px solid var(--grey-color);
    border-radius: 50%;
    transition: var(--transition);
}

.option-input:checked + .option-text:before {
    border-color: var(--primary-color);
    background-color: var(--primary-color);
}

.option-input:checked + .option-text:after {
    content: '';
    position: absolute;
    left: 7px;
    top: 4px;
    width: 6px;
    height: 12px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Code Editor Container */
.code-editor-container {
    margin-bottom: 20px;
}

.language-selector {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.language-selector label {
    margin-right: 10px;
    font-weight: 500;
}

.language-selector select {
    padding: 8px 15px;
    border-radius: var(--border-radius);
    border: 1px solid rgba(0, 0, 0, 0.1);
    background-color: white;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    transition: var(--transition);
}

.language-selector select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

/* Monaco Editor Container */
.monaco-editor-container {
    height: 300px;
    border: 1px solid #ddd;
    border-radius: var(--border-radius);
    overflow: hidden;
}

/* Navigation Buttons */
.question-navigation {
    display: flex;
    justify-content: space-between;
}

.btn {
    padding: 12px 24px;
    border-radius: 30px;
    border: none;
    font-family: 'Poppins', sans-serif;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
}

.btn i {
    margin-right: 8px;
    font-size: 18px;
}

.btn i:last-child {
    margin-right: 0;
    margin-left: 8px;
}

.btn-prev {
    background-color: rgba(108, 117, 125, 0.1);
    color: var(--grey-color);
}

.btn-prev:hover {
    background-color: rgba(108, 117, 125, 0.2);
}

.btn-next, 
.btn-review {
    background-color: var(--primary-color);
    color: white;
}

.btn-next:hover, 
.btn-review:hover {
    background-color: var(--primary-dark);
    transform: translateY(-2px);
}

/* Review Section */
.review-section {
    display: none;
    padding: 30px;
    background-color: white;
    border-radius: var(--border-radius);
    border: 1px solid rgba(0, 0, 0, 0.1);
    animation: fadeIn 0.5s ease;
}

.review-section.active {
    display: block;
}

.review-section h2 {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 30px;
    color: var(--primary-dark);
    text-align: center;
}

.questions-overview {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    margin-bottom: 30px;
}

.question-bubble {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border: 1px solid rgba(0, 0, 0, 0.1);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.question-bubble:hover {
    background-color: rgba(67, 97, 238, 0.1);
    border-color: var(--primary-color);
}

.question-bubble.answered {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.question-bubble.current {
    border: 2px solid var(--primary-color);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.3);
}

.legend {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-bottom: 30px;
}

.legend-item {
    display: flex;
    align-items: center;
}

.bullet {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.bullet.answered {
    background-color: var(--primary-color);
}

.bullet.unanswered {
    background-color: #f8f9fa;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.btn-submit {
    background-color: var(--secondary-color);
    color: white;
    margin: 0 auto;
    display: flex;
    padding: 14px 40px;
    font-size: 18px;
    transition: var(--transition);
}

.btn-submit:hover {
    background-color: #d61c75;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(247, 37, 133, 0.3);
}

.warning-text {
    text-align: center;
    color: var(--warning-color);
    margin-top: 20px;
    font-size: 14px;
}

/* Loading Animation */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    transition: opacity 0.3s ease;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 5px solid rgba(67, 97, 238, 0.1);
    border-radius: 50%;
    border-top-color: var(--primary-color);
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Animation for Timer */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.timer.warning {
    background-color: rgba(252, 163, 17, 0.1);
    color: var(--warning-color);
    animation: pulse 1s infinite;
}

.timer.danger {
    background-color: rgba(230, 57, 70, 0.1);
    color: var(--danger-color);
    animation: pulse 0.5s infinite;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .exam-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .timer-container {
        margin-top: 15px;
    }
    
    .exam-metadata {
        flex-direction: column;
        gap: 10px;
    }
    
    .question-navigation {
        flex-direction: column;
        gap: 15px;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .question-card {
        padding: 20px;
    }
    
    .legend {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
}
</style>


</head>
<body>
    <div class="app-container">
        <nav class="top-nav">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>ExamOnline</span>
            </div>
            <a href="student.php" class="home-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </nav>
        
        <div class="main-container">
            <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message) && !empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($exam)): ?>
                <div class="exam-header">
                    <div class="exam-title">
                        <h1><?= htmlspecialchars($exam['title']) ?></h1>
                        <span class="subject-tag"><?= htmlspecialchars($exam['subject'] ?? 'Exam') ?></span>
                    </div>
                    <div class="timer-container">
                        <div class="timer" id="examTimer" data-time="<?= $remaining_time ?>">
                            <i class="fas fa-clock"></i>
                            <span id="timerDisplay">00:00:00</span>
                        </div>
                    </div>
                </div>

                <div class="exam-progress">
                    <div class="progress-bar">
                        <div class="progress" id="progressBar" style="width: 0%;"></div>
                    </div>
                    <div class="progress-text"><span id="currentQuestionNum">0</span> of <span id="totalQuestions"><?= count($questions) ?></span> questions answered</div>
                </div>

                <div class="exam-info-card">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Exam Information</h2>
                    </div>
                    <div class="card-content">
                        <p><?= htmlspecialchars($exam['description'] ?? 'Complete all questions within the time limit.') ?></p>
                        <div class="exam-metadata">
                            <div class="metadata-item">
                                <i class="fas fa-question-circle"></i>
                                <span><?= count($questions) ?> Questions</span>
                            </div>
                            <div class="metadata-item">
                                <i class="fas fa-hourglass-half"></i>
                                <span><?= htmlspecialchars($exam['duration'] ?? '0') ?> Minutes</span>
                            </div>
                            <div class="metadata-item">
                                <i class="fas fa-trophy"></i>
                                <span>Total Points: <?= array_sum(array_column($questions, 'points')) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="examForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $exam_id) ?>">
                    <div class="questions-container">
                        <?php $questionNum = 1; ?>
                        <?php foreach ($questions as $question): ?>
                            <div class="question-card <?= $questionNum === 1 ? 'active' : '' ?>" id="question<?= $questionNum ?>">
                                <span class="question-number">Question <?= $questionNum ?></span>
                                <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>
                                <div class="question-points">
                                    <i class="fas fa-star"></i> <?= htmlspecialchars($question['points']) ?> points
                                </div>
                                
                                <div class="answer-container">
                                    <?php if ($question['type'] === 'mcq'): ?>
                                        <?php foreach ($question['options'] as $option): ?>
                                            <label class="answer-option">
                                                <input type="radio" name="answer_<?= $question['id'] ?>" value="<?= $option['id'] ?>" class="option-input">
                                                <span class="option-text"><?= htmlspecialchars($option['text']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php elseif ($question['type'] === 'open'): ?>
                                        <div class="code-editor-container">
                                            <div class="language-selector">
                                                <label for="language_<?= $question['id'] ?>">Language:</label>
                                                <select name="language_<?= $question['id'] ?>" id="language_<?= $question['id'] ?>" class="language-select">
                                                    <option value="text">Plain Text</option>
                                                    <option value="python">Python</option>
                                                    <option value="javascript">JavaScript</option>
                                                    <option value="java">Java</option>
                                                    <option value="cpp">C++</option>
                                                    <option value="php">PHP</option>
                                                    <option value="sql">SQL</option>
                                                </select>
                                            </div>
                                            <div class="monaco-editor-container" id="editor_<?= $question['id'] ?>"></div>
                                            <textarea name="answer_<?= $question['id'] ?>" id="hidden_textarea_<?= $question['id'] ?>" style="display:none;"></textarea>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="question-navigation">
                                    <?php if ($questionNum > 1): ?>
                                        <button type="button" class="btn btn-prev" onclick="showQuestion(<?= $questionNum-1 ?>)">
                                            <i class="fas fa-arrow-left"></i> Previous
                                        </button>
                                    <?php else: ?>
                                        <div></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($questionNum < count($questions)): ?>
                                        <button type="button" class="btn btn-next" onclick="showQuestion(<?= $questionNum+1 ?>)">
                                            Next <i class="fas fa-arrow-right"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-review" onclick="showReview()">
                                            Review & Submit <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $questionNum++; ?>
                        <?php endforeach; ?>
                        
                        <div class="review-section" id="reviewSection">
                            <h2>Review Your Answers</h2>
                            <div class="questions-overview">
                                <?php for ($i = 1; $i <= count($questions); $i++): ?>
                                    <div class="question-bubble" id="bubble<?= $i ?>" onclick="showQuestion(<?= $i ?>)"><?= $i ?></div>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="legend">
                                <div class="legend-item">
                                    <span class="bullet answered"></span>
                                    <span>Answered</span>
                                </div>
                                <div class="legend-item">
                                    <span class="bullet unanswered"></span>
                                    <span>Unanswered</span>
                                </div>
                            </div>
                            
                            <button type="submit" name="submit_exam" class="btn btn-submit">
                                <i class="fas fa-paper-plane"></i> Submit Exam
                            </button>
                            
                            <p class="warning-text">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Once submitted, you cannot change your answers.
                            </p>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="spinner"></div>
    </div>

    <!-- Monaco Editor Loader -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>
    <script>
        // Initialize variables
        let currentQuestion = 1;
        const totalQuestions = <?= count($questions) ?>;
        const answeredQuestions = new Set();
        const editors = {}; // Store Monaco editor instances
        
        // Timer functionality
        function startTimer(duration) {
            let timer = duration;
            const timerDisplay = document.getElementById('timerDisplay');
            const timerElement = document.getElementById('examTimer');
            
            const timerInterval = setInterval(function() {
                const hours = Math.floor(timer / 3600);
                const minutes = Math.floor((timer % 3600) / 60);
                const seconds = timer % 60;
                
                timerDisplay.textContent = ${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')};
                
                if (timer <= 300) { // 5 minutes remaining
                    timerElement.classList.add('warning');
                }
                
                if (timer <= 60) { // 1 minute remaining
                    timerElement.classList.remove('warning');
                    timerElement.classList.add('danger');
                }
                
                if (--timer < 0) {
                    clearInterval(timerInterval);
                    timerDisplay.textContent = "00:00:00";
                    alert("Time's up! Your exam will be submitted automatically.");
                    document.getElementById('examForm').submit();
                }
            }, 1000);
        }
        
        // Question navigation
        function showQuestion(questionNum) {
            // Hide all question cards
            document.querySelectorAll('.question-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Show the selected question card
            document.getElementById(question${questionNum}).classList.add('active');
            
            // Hide review section if it's visible
            document.getElementById('reviewSection').classList.remove('active');
            
            // Update current question
            currentQuestion = questionNum;
            
            // Update question bubbles in review section
            updateQuestionBubbles();
            
            // Resize Monaco editor when shown
            if (editors[editor_${questionNum}]) {
                setTimeout(() => {
                    editors[editor_${questionNum}].layout();
                }, 100);
            }
        }
        
        // Show review section
        function showReview() {
            // Hide all question cards
            document.querySelectorAll('.question-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Show review section
            document.getElementById('reviewSection').classList.add('active');
            
            // Update question bubbles
            updateQuestionBubbles();
        }
        
        // Update question bubbles in review section
        function updateQuestionBubbles() {
            // Mark current question
            document.querySelectorAll('.question-bubble').forEach(bubble => {
                bubble.classList.remove('current');
            });
            
            const currentBubble = document.getElementById(bubble${currentQuestion});
            if (currentBubble) {
                currentBubble.classList.add('current');
            }
            
            // Update progress bar
            updateProgress();
        }
        
        // Update progress information
        function updateProgress() {
            const answeredCount = answeredQuestions.size;
            document.getElementById('currentQuestionNum').textContent = answeredCount;
            document.getElementById('progressBar').style.width = ${(answeredCount / totalQuestions) * 100}%;
        }
        
        // Track answered questions
        function trackAnswers() {
            // For MCQ questions
            document.querySelectorAll('input[type="checkbox"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const questionId = this.name.split('_')[1];
                    const questionNum = Array.from(document.querySelectorAll('.question-card')).findIndex(
                        card => card.querySelector(input[name="answer_${questionId}"])
                    ) + 1;
                    
                    answeredQuestions.add(questionNum);
                    document.getElementById(bubble${questionNum}).classList.add('answered');
                    updateProgress();
                });
            });
            
            // For open-ended questions (handled by Monaco editor content change)
        }
        
        // Initialize Monaco Editors
        function initializeMonacoEditors() {
            // Configure Monaco loader
            require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.40.0/min/vs' }});
            
            // Load Monaco editor
            require(['vs/editor/editor.main'], function() {
                // Get all code editor containers
                const editorContainers = document.querySelectorAll('.monaco-editor-container');
                
                editorContainers.forEach(container => {
                    const questionId = container.id.split('_')[1];
                    const textarea = document.getElementById(hidden_textarea_${questionId});
                    const languageSelect = document.getElementById(language_${questionId});
                    
                    // Create editor
                    const editor = monaco.editor.create(container, {
                        value: textarea.value,
                        language: getMonacoLanguage(languageSelect.value),
                        theme: 'vs-dark',
                        automaticLayout: true,
                        minimap: { enabled: false },
                        scrollBeyondLastLine: false,
                        fontSize: 14,
                        lineNumbers: 'on',
                        roundedSelection: true,
                        autoIndent: 'full',
                        tabSize: 4
                    });
                    
                    // Store editor instance
                    editors[container.id] = editor;
                    
                    // Sync editor content with hidden textarea
                    editor.onDidChangeModelContent(() => {
                        textarea.value = editor.getValue();
                        
                        // Mark question as answered if there's content
                        const questionNum = Array.from(document.querySelectorAll('.question-card')).findIndex(
                            card => card.querySelector(textarea[name="answer_${questionId}"])
                        ) + 1;
                        
                        if (editor.getValue().trim() !== '') {
                            answeredQuestions.add(questionNum);
                            document.getElementById(bubble${questionNum}).classList.add('answered');
                        } else {
                            answeredQuestions.delete(questionNum);
                            document.getElementById(bubble${questionNum}).classList.remove('answered');
                        }
                        
                        updateProgress();
                    });
                    
                    // Handle language changes
                    languageSelect.addEventListener('change', function() {
                        monaco.editor.setModelLanguage(editor.getModel(), getMonacoLanguage(this.value));
                    });
                });
            });
        }
        
        // Map our language names to Monaco's language IDs
        function getMonacoLanguage(lang) {
            const map = {
                'python': 'python',
                'javascript': 'javascript',
                'java': 'java',
                'cpp': 'cpp',
                'php': 'php',
                'sql': 'sql',
                'text': 'plaintext'
            };
            return map[lang] || 'plaintext';
        }
        
        // Form submission handling
        document.getElementById('examForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Create confetti effect for successful submission
            if (answeredQuestions.size === totalQuestions) {
                createConfetti();
            }
        });
        
        // Confetti animation
        function createConfetti() {
            const colors = ['#4361ee', '#4895ef', '#3a0ca3', '#f72585', '#4cc9f0'];
            
            for (let i = 0; i < 100; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = ${Math.random() * 100}vw;
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = ${Math.random() * 3}s;
                
                document.body.appendChild(confetti);
                
                // Remove after animation completes
                setTimeout(() => {
                    confetti.remove();
                }, 3000);
            }
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Start timer
            const remainingTime = parseInt(document.getElementById('examTimer').getAttribute('data-time'));
            startTimer(remainingTime);
            
            // Track answered questions
            trackAnswers();
            
            // Initialize Monaco editors
            initializeMonacoEditors();
        });
    </script>
</body>
</html>