<?php
// createxam page
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    $_SESSION['error_message'] = "You must be logged in as a teacher to create an exam.";
    header("Location: login.php");
    exit();
}

// Initialize variables
$filieres = [];
$groups = [];
$error_message = '';
$success_message = '';

// Fetch filieres from database
try {
    $stmt = $pdo->query("SELECT id, name FROM filieres ORDER BY name");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching filieres: " . $e->getMessage();
}

// Fetch groups from database
try {
    $stmt = $pdo->query("SELECT id, name FROM student_groups ORDER BY name");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching groups: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Validate and sanitize inputs
        $title = htmlspecialchars(trim($_POST['examTitle']));
        $description = htmlspecialchars(trim($_POST['examDescription']));
        $start_date = $_POST['examStartDate'];
        $end_date = $_POST['examEndDate'];
        $duration = (int)$_POST['examDuration'];
        $filiere_id = (int)$_POST['examFiliere'];
        $group_id = (int)$_POST['examGroup'];
        $teacher_id = $_SESSION['user_id'];
        $current_timestamp = date('Y-m-d H:i:s');
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Exam title is required";
        }
        
        if (strtotime($start_date) >= strtotime($end_date)) {
            $errors[] = "End date must be after start date";
        }
        
        if ($duration <= 0) {
            $errors[] = "Duration must be a positive number";
        }
        
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }

        // Insert exam details into the exams table
        $stmt = $pdo->prepare("
            INSERT INTO exams (
                title, 
                description, 
                start_date, 
                end_date, 
                duration, 
                teacher_id,
                filiere_id, 
                group_id, 
                created_at, 
                updated_at,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, 
            $description, 
            $start_date, 
            $end_date, 
            $duration, 
            $teacher_id,
            $filiere_id ?: null, 
            $group_id ?: null, 
            $current_timestamp, 
            $current_timestamp,
            'published'
        ]);

        $exam_id = $pdo->lastInsertId();

        // Process questions
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $questionId => $question) {
                $q_text = htmlspecialchars(trim($question['text']));
                $q_points = (int)$question['points'];
                $q_type = isset($question['answers']) && is_array($question['answers']) ? 'mcq' : 'open';

                // Validate question
                if (empty($q_text)) {
                    throw new Exception("Question text cannot be empty");
                }
                
                if ($q_points <= 0) {
                    throw new Exception("Question points must be a positive number");
                }

                // Handle file upload
                $file_path = null;
                if (isset($_FILES['questions']['name'][$questionId]['file']) && 
                    !empty($_FILES['questions']['name'][$questionId]['file']) &&
                    isset($_FILES['questions']['tmp_name'][$questionId]['file']) && 
                    !empty($_FILES['questions']['tmp_name'][$questionId]['file'])) {
                    
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_name = basename($_FILES['questions']['name'][$questionId]['file']);
                    $file_path = $upload_dir . uniqid() . '_' . $file_name;
                    
                    if (!move_uploaded_file($_FILES['questions']['tmp_name'][$questionId]['file'], $file_path)) {
                        throw new Exception("Failed to upload file for question");
                    }
                }

                // Insert question
                $stmt = $pdo->prepare("
                    INSERT INTO questions (
                        exam_id, 
                        question_text, 
                        points, 
                        question_type, 
                        type,
                        file_path
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$exam_id, $q_text, $q_points, $q_type, $q_type, $file_path]);

                $question_id = $pdo->lastInsertId();

                // Process MCQ options
                if ($q_type === 'mcq' && isset($question['answers']) && is_array($question['answers'])) {
                    $option_order = 1;
                    $has_correct_answer = false;
                    
                    foreach ($question['answers'] as $answer) {
                        $a_text = htmlspecialchars(trim($answer['text']));
                        $is_correct = isset($answer['correct']) && $answer['correct'] ? 1 : 0;
                        
                        if ($is_correct) {
                            $has_correct_answer = true;
                        }
                        
                        if (empty($a_text)) {
                            throw new Exception("Answer text cannot be empty");
                        }

                        // Insert MCQ option
                        $stmt = $pdo->prepare("
                            INSERT INTO question_options (
                                question_id, 
                                option_text, 
                                is_correct,
                                option_order
                            ) VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$question_id, $a_text, $is_correct, $option_order]);
                        $option_order++;
                    }
                    
                    if (!$has_correct_answer) {
                        throw new Exception("Multiple choice questions must have at least one correct answer");
                    }
                }
            }
        } else {
            throw new Exception("Exam must have at least one question");
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Exam created successfully!";
        header("Location: teacher.php");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error creating exam: ' . $e->getMessage();
        $_SESSION['debug_info'] = print_r($_POST, true);
        // Don't redirect, allow the form to display with the error
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
        :root {
            --primary: #7C5DFA;
            --primary-hover: #9277FF;
            --secondary: #252945;
            --success: #33D69F;
            --info: #7E88C3;
            --warning: #FF8F00;
            --danger: #EC5757;
            --dark: #0C0E16;
            --light: #F8F8FB;
            --bg-primary: #141625;
            --bg-secondary: #1E2139;
            --bg-card: #252945;
            --text-primary: #FFFFFF;
            --text-secondary: #DFE3FA;
            --border-color: #393A51;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .header {
            padding: 2.5rem 0;
            text-align: center;
            margin-bottom: 2.5rem;
            background: var(--bg-secondary);
            border-radius: 1rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .page-title {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            font-size: 2.5rem;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-weight: 400;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.2);
        }
        
        .card-title {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .form-control, .form-select {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--bg-secondary);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 93, 250, 0.25);
            color: var(--text-primary);
        }
        
        .form-control::placeholder {
            color: var(--info);
            opacity: 0.7;
        }
        
        .btn {
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            box-shadow: 0 4px 15px rgba(124, 93, 250, 0.35);
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(124, 93, 250, 0.5);
        }
        
        .btn-outline-primary {
            color: var(--text-primary);
            border: 2px solid var(--primary);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .btn-outline-secondary {
            color: var(--text-primary);
            border: 2px solid var(--text-secondary);
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-3px);
        }
        
        .btn-outline-success {
            color: var(--success);
            border: 2px solid var(--success);
            background: transparent;
        }
        
        .btn-outline-success:hover {
            background: var(--success);
            color: var(--dark);
            transform: translateY(-3px);
        }
        
        .btn-outline-info {
            color: var(--info);
            border: 2px solid var(--info);
            background: transparent;
        }
        
        .btn-outline-info:hover {
            background: var(--info);
            color: white;
            transform: translateY(-3px);
        }
        
        .btn-outline-danger {
            color: var(--danger);
            border: 2px solid var(--danger);
            background: transparent;
        }
        
        .btn-outline-danger:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-3px);
        }
        
        .question-card {
            position: relative;
            border-radius: 1rem;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .mcq-card {
            border-left: 6px solid var(--success);
        }
        
        .open-card {
            border-left: 6px solid var(--info);
        }
        
        .question-type-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .answer-option {
            background-color: var(--bg-secondary);
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .answer-option:hover {
            background-color: var(--secondary);
            transform: translateX(5px);
        }
        
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            margin-top: 0.2rem;
            cursor: pointer;
            background-color: var(--bg-secondary);
            border: 2px solid var(--info);
            border-radius: 0.25rem;
        }
        
        .form-check-input:checked {
            background-color: var(--success);
            border-color: var(--success);
        }
        
        .form-check-label {
            cursor: pointer;
            font-weight: 500;
            color: var(--text-primary);
            padding-left: 0.5rem;
        }
        
        .delete-btn {
            color: var(--danger);
            background: rgba(236, 87, 87, 0.1);
            border: none;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .delete-btn:hover {
            background-color: rgba(236, 87, 87, 0.3);
            transform: scale(1.05);
        }
        
        /* Custom group checkbox styles */
        .group-checkbox-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .group-checkbox-item {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .group-checkbox-item:hover {
            background-color: var(--secondary);
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .group-checkbox-item input {
            margin-right: 0.75rem;
        }
        
        .group-checkbox-item.selected {
            border: 2px solid var(--primary);
            background-color: rgba(124, 93, 250, 0.1);
        }
        
        /* Filière select styling */
        .filiere-select-container {
            position: relative;
        }
        
        .filiere-select {
            appearance: none;
            padding-right: 2.5rem;
        }
        
        .filiere-select-arrow {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--info);
            pointer-events: none;
        }
        
        .container {
            max-width: 1200px;
            padding: 2rem 1rem;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(124, 93, 250, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(124, 93, 250, 0); }
            100% { box-shadow: 0 0 0 0 rgba(124, 93, 250, 0); }
        }
        
        /* Alert Message */
        .alert {
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 2rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .alert-danger {
            background-color: rgba(236, 87, 87, 0.2);
            color: var(--danger);
        }
        
        .alert-success {
            background-color: rgba(51, 214, 159, 0.2);
            color: var(--success);
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        /* Date/time inputs */
        input[type="datetime-local"] {
            color-scheme: dark;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 8px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--info);
            border-radius: 8px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Add question buttons */
        .add-question-btn-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1.5rem;
            margin: 2.5rem 0;
        }
        
        .add-question-btn {
            padding: 1.25rem 2rem;
            border-radius: 1rem;
            font-size: 1rem;
            font-weight: 600;
            min-width: 240px;
            transition: all 0.4s ease;
        }
        
        .add-question-btn:hover {
            transform: translateY(-5px) scale(1.05);
        }
        
        /* Submit button area */
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .form-actions .btn {
            min-width: 180px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="page-title">Create New Exam</h1>
            <p class="page-subtitle">Design your perfect assessment with multiple question types</p>
        </header>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error!</strong>
                    <p class="mb-0"><?= $_SESSION['error_message'] ?></p>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            </div>
            <?php if (isset($_SESSION['debug_info'])): ?>
                <div class="card mb-4">
                    <h5>Debug Information:</h5>
                    <pre class="text-white"><?= $_SESSION['debug_info'] ?></pre>
                    <?php unset($_SESSION['debug_info']); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Success!</strong>
                    <p class="mb-0"><?= $_SESSION['success_message'] ?></p>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <form id="examForm" class="needs-validation" method="POST" action="" enctype="multipart/form-data" novalidate>
            <!-- Exam Details Card -->
            <div class="card fade-in">
                <h4 class="card-title">
                    <i class="fas fa-clipboard-list me-2"></i>Exam Details
                </h4>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examTitle" class="form-label">
                                <i class="fas fa-heading me-2"></i>Exam Title
                            </label>
                            <input type="text" id="examTitle" name="examTitle" class="form-control" 
                                   placeholder="Enter exam title" required>
                            <div class="invalid-feedback">Please provide an exam title.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examDuration" class="form-label">
                                <i class="fas fa-clock me-2"></i>Duration (minutes)
                            </label>
                            <input type="number" id="examDuration" name="examDuration" class="form-control" 
                                   placeholder="Duration in minutes" required min="1">
                            <div class="invalid-feedback">Please provide a valid duration.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label for="examDescription" class="form-label">
                                <i class="fas fa-align-left me-2"></i>Description
                            </label>
                            <textarea id="examDescription" name="examDescription" class="form-control" 
                                      rows="3" placeholder="Enter exam description" required></textarea>
                            <div class="invalid-feedback">Please provide a description.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examFiliere" class="form-label">
                                <i class="fas fa-graduation-cap me-2"></i>Filière
                            </label>
                            <div class="filiere-select-container">
                                <select id="examFiliere" name="examFiliere" class="form-select filiere-select" required>
                                    <option value="" disabled selected>Select Filière</option>
                                    <?php foreach ($filieres as $filiere): ?>
                                        <option value="<?= htmlspecialchars($filiere['id']) ?>">
                                            <?= htmlspecialchars($filiere['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down filiere-select-arrow"></i>
                            </div>
                            <div class="invalid-feedback">Please select a filière.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examGroup" class="form-label">
                                <i class="fas fa-users me-2"></i>Student Group
                            </label>
                            <div class="filiere-select-container">
                                <select id="examGroup" name="examGroup" class="form-select filiere-select" required>
                                    <option value="" disabled selected>Select Group</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= htmlspecialchars($group['id']) ?>">
                                            <?= htmlspecialchars($group['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down filiere-select-arrow"></i>
                            </div>
                            <div class="invalid-feedback">Please select a group.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examStartDate" class="form-label">
                                <i class="fas fa-calendar-plus me-2"></i>Start Date & Time
                            </label>
                            <input type="datetime-local" id="examStartDate" name="examStartDate" 
                                   class="form-control" required>
                            <div class="invalid-feedback">Please provide a start date and time.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examEndDate" class="form-label">
                                <i class="fas fa-calendar-minus me-2"></i>End Date & Time
                            </label>
                            <input type="datetime-local" id="examEndDate" name="examEndDate" 
                                   class="form-control" required>
                            <div class="invalid-feedback">Please provide an end date and time.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions Container -->
            <div id="questionsContainer"></div>

            <!-- Add Question Buttons -->
            <div class="add-question-btn-container">
                <button type="button" class="btn btn-outline-success add-question-btn pulse" onclick="addQuestion('mcq')">
                    <i class="fas fa-plus-circle me-2"></i>Add Multiple Choice
                </button>
                <button type="button" class="btn btn-outline-info add-question-btn pulse" onclick="addQuestion('open')">
                    <i class="fas fa-pen me-2"></i>Add Open Question
                </button>
            </div>

            <!-- Submit Buttons -->
            <div class="form-actions">
                <a href="teacher.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>Create Exam
                </button>
            </div>
        </form>
    </div>

    <!-- Bootstrap 5 Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for handling questions -->
    <script>
        // Initialize question counter
        let questionCounter = 0;
        
        // Form validation
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Function to add a new question
        function addQuestion(type) {
            questionCounter++;
            
            const questionsContainer = document.getElementById('questionsContainer');
            const questionCard = document.createElement('div');
            questionCard.className = `question-card ${type}-question fade-in`;
            questionCard.id = `question-${questionCounter}`;
            
            const questionBadgeText = type === 'mcq' ? 'Multiple Choice' : 'Open Answer';
            const questionBadgeClass = type === 'mcq' ? 'mcq-badge' : 'open-badge';
            
            let questionHTML = `
                <div class="question-header">
                    <span class="question-type-badge ${questionBadgeClass}">${questionBadgeText}</span>
                    <span class="question-points">
                        <label for="questions[${questionCounter}][points]" class="form-label mb-0">Points:</label>
                        <input type="number" id="questions[${questionCounter}][points]" 
                               name="questions[${questionCounter}][points]" class="form-control form-control-sm d-inline-block" 
                               style="width: 70px;" value="1" min="1" required>
                    </span>
                </div>
                <button type="button" class="remove-question-btn" onclick="removeQuestion(${questionCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="mb-3">
                    <label for="questions[${questionCounter}][text]" class="form-label">Question Text</label>
                    <textarea id="questions[${questionCounter}][text]" name="questions[${questionCounter}][text]" 
                              class="form-control" rows="2" required placeholder="Enter your question here..."></textarea>
                    <div class="invalid-feedback">Please provide a question text.</div>
                </div>
                <div class="mb-3">
                    <label for="questions[${questionCounter}][file]" class="form-label">
                        <i class="fas fa-paperclip me-2"></i>Attachment (Optional)
                    </label>
                    <input type="file" id="questions[${questionCounter}][file]" 
                           name="questions[${questionCounter}][file]" class="form-control">
                </div>
            `;
            
            if (type === 'mcq') {
                questionHTML += `
                    <div class="answer-options" id="answer-options-${questionCounter}">
                        <h5 class="mb-3">Answer Options</h5>
                    </div>
                    <div class="add-answer-btn" onclick="addAnswerOption(${questionCounter})">
                        <i class="fas fa-plus me-2"></i>Add Answer Option
                    </div>
                `;
            }
            
            questionCard.innerHTML = questionHTML;
            questionsContainer.appendChild(questionCard);
            
            // If MCQ, add the first two answer options automatically
            if (type === 'mcq') {
                addAnswerOption(questionCounter);
                addAnswerOption(questionCounter);
            }
        }
        
        // Function to remove a question
        function removeQuestion(id) {
            const questionCard = document.getElementById(`question-${id}`);
            questionCard.classList.add('fade-out');
            
            setTimeout(() => {
                questionCard.remove();
            }, 300);
        }
        
        // Function to add an answer option to MCQ
        function addAnswerOption(questionId) {
            const answerOptionsContainer = document.getElementById(`answer-options-${questionId}`);
            const optionId = Date.now(); // Use timestamp to ensure unique IDs
            
            const answerOption = document.createElement('div');
            answerOption.className = 'answer-option';
            answerOption.id = `answer-option-${optionId}`;
            
            answerOption.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" 
                               id="questions[${questionId}][answers][${optionId}][correct]" 
                               name="questions[${questionId}][answers][${optionId}][correct]" value="1">
                        <label class="form-check-label" for="questions[${questionId}][answers][${optionId}][correct]">
                            Correct
                        </label>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <input type="text" class="form-control" 
                               id="questions[${questionId}][answers][${optionId}][text]" 
                               name="questions[${questionId}][answers][${optionId}][text]" 
                               placeholder="Enter answer option" required>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" 
                            onclick="removeAnswerOption(${optionId})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            answerOptionsContainer.appendChild(answerOption);
        }
        
        // Function to remove an answer option
        function removeAnswerOption(optionId) {
            const answerOption = document.getElementById(`answer-option-${optionId}`);
            answerOption.remove();
        }
        
        // Set minimum date for datepickers to current date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const yyyy = today.getFullYear();
            let mm = today.getMonth() + 1;
            let dd = today.getDate();
            let hh = today.getHours();
            let min = today.getMinutes();
            
            mm = mm < 10 ? '0' + mm : mm;
            dd = dd < 10 ? '0' + dd : dd;
            hh = hh < 10 ? '0' + hh : hh;
            min = min < 10 ? '0' + min : min;
            
            const formattedDate = `${yyyy}-${mm}-${dd}T${hh}:${min}`;
            
            document.getElementById('examStartDate').min = formattedDate;
            document.getElementById('examEndDate').min = formattedDate;
            
            // Auto-set end date to be one day later than start date when start date changes
            document.getElementById('examStartDate').addEventListener('change', function() {
                const startDate = new Date(this.value);
                const endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 1);
                
                const endYear = endDate.getFullYear();
                let endMonth = endDate.getMonth() + 1;
                let endDay = endDate.getDate();
                let endHours = endDate.getHours();
                let endMinutes = endDate.getMinutes();
                
                endMonth = endMonth < 10 ? '0' + endMonth : endMonth;
                endDay = endDay < 10 ? '0' + endDay : endDay;
                endHours = endHours < 10 ? '0' + endHours : endHours;
                endMinutes = endMinutes < 10 ? '0' + endMinutes : endMinutes;
                
                const formattedEndDate = `${endYear}-${endMonth}-${endDay}T${endHours}:${endMinutes}`;
                document.getElementById('examEndDate').value = formattedEndDate;
            });
            
            // Add at least one question when the page loads
            addQuestion('mcq');
        });
        
        // Validate form before submission
        document.getElementById('examForm').addEventListener('submit', function(event) {
            // Check if there are any questions added
            const questionsContainer = document.getElementById('questionsContainer');
            if (questionsContainer.children.length === 0) {
                event.preventDefault();
                alert('Please add at least one question to the exam.');
                return false;
            }
            
            // Check if end date is after start date
            const startDate = new Date(document.getElementById('examStartDate').value);
            const endDate = new Date(document.getElementById('examEndDate').value);
            
            if (endDate <= startDate) {
                event.preventDefault();
                alert('End date must be after start date.');
                return false;
            }
            
            // Check if MCQ questions have at least one answer option
            const mcqQuestions = document.querySelectorAll('.mcq-question');
            for (let i = 0; i < mcqQuestions.length; i++) {
                const questionId = mcqQuestions[i].id.split('-')[1];
                const answerOptions = document.querySelectorAll(`#answer-options-${questionId} .answer-option`);
                
                if (answerOptions.length < 2) {
                    event.preventDefault();
                    alert('Multiple choice questions must have at least 2 answer options.');
                    return false;
                }
                
                // Check if at least one option is marked as correct
                let hasCorrectAnswer = false;
                for (let j = 0; j < answerOptions.length; j++) {
                    const optionId = answerOptions[j].id.split('-')[2];
                    const isCorrect = document.querySelector(`#questions\\[${questionId}\\]\\[answers\\]\\[${optionId}\\]\\[correct\\]`);
                    
                    if (isCorrect && isCorrect.checked) {
                        hasCorrectAnswer = true;
                        break;
                    }
                }
                
                if (!hasCorrectAnswer) {
                    event.preventDefault();
                    alert('Multiple choice questions must have at least one correct answer.');
                    return false;
                }
            }
            
            return true;
        });
        
        // Confirm before leaving page if there are unsaved changes
        window.addEventListener('beforeunload', function(e) {
            const questionsContainer = document.getElementById('questionsContainer');
            if (questionsContainer.children.length > 0) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    </script>
</body>
</html>