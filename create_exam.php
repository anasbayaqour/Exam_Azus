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
                filiere_id, 
                group_id, 
                teacher_id,
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
            $filiere_id ?: null, 
            $group_id ?: null, 
            $teacher_id,
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
                        file_path
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$exam_id, $q_text, $q_points, $q_type, $file_path]);

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
        header("Location: create_exam.php");
        exit();
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
    
    <script>
        // Make group checkboxes more interactive
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to current date/time
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            const minDateTime = ${year}-${month}-${day}T${hours}:${minutes};

            document.getElementById('examStartDate').setAttribute('min', minDateTime);
            document.getElementById('examEndDate').setAttribute('min', minDateTime);
        });

        // Form validation
        (function() {
            'use strict';

            // Fetch all forms we want to apply validation to
            var forms = document.querySelectorAll('.needs-validation');

            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    // Check for questions
                    const questionsContainer = document.getElementById('questionsContainer');
                    if (questionsContainer.children.length === 0) {
                        event.preventDefault();
                        event.stopPropagation();
                        alert('Please add at least one question.');
                        return;
                    }

                    // General form validation
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    // Validate MCQ answers
                    if (!validateMCQAnswers()) {
                        event.preventDefault();
                        event.stopPropagation();
                        return;
                    }

                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Function to add a new question
        function addQuestion(type) {
            const questionsContainer = document.getElementById('questionsContainer');
            const questionIndex = questionsContainer.children.length;

            const questionCard = document.createElement('div');
            questionCard.className = card question-card fade-in ${type === 'mcq' ? 'mcq-card' : 'open-card'};
            questionCard.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <span class="question-type-badge ${type === 'mcq' ? 'bg-success' : 'bg-info'}">
                                <i class="fas fa-${type === 'mcq' ? 'list-ul' : 'pen'}"></i>
                                ${type === 'mcq' ? 'Multiple Choice Question' : 'Open Question'}
                            </span>
                        </h5>
                        <button type="button" class="btn btn-outline-danger delete-btn" onclick="deleteQuestion(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4">
                        <label for="questionText_${questionIndex}" class="form-label">
                            <i class="fas fa-question-circle me-2"></i>Question Text
                        </label>
                        <textarea id="questionText_${questionIndex}" name="questions[${questionIndex}][text]" 
                                  class="form-control" rows="3" placeholder="Enter your question here..." required></textarea>
                        <div class="invalid-feedback">Please provide a question text.</div>
                    </div>
                    
                    <input type="hidden" name="questions[${questionIndex}][type]" value="${type}">
                    
                    <div class="mb-4">
                        <label for="questionPoints_${questionIndex}" class="form-label">
                            <i class="fas fa-star me-2"></i>Points
                        </label>
                        <input type="number" id="questionPoints_${questionIndex}" 
                               name="questions[${questionIndex}][points]" class="form-control" 
                               placeholder="Enter points value" required min="1" step="0.5">
                        <div class="invalid-feedback">Please provide valid points (minimum 1).</div>
                    </div>
                    
                    ${type === 'mcq' ? `
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-list-ul me-2"></i>Options (Check the correct answer)
                            </label>
                            <div id="optionsContainer_${questionIndex}" class="mt-3"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-3" 
                                    onclick="addOption(${questionIndex})">
                                <i class="fas fa-plus me-2"></i>Add Option
                            </button>
                        </div>
                    ` : ''}
                    
                    <div class="mb-4">
                        <label for="questionFile_${questionIndex}" class="form-label">
                            <i class="fas fa-file-upload me-2"></i>Attach File (Optional)
                        </label>
                        <input type="file" id="questionFile_${questionIndex}" 
                               name="questions[${questionIndex}][file]" class="form-control">
                        <small class="text-info d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Supported formats: JPG, PNG, PDF, DOC, DOCX
                        </small>
                    </div>
                </div>
            `;

            questionsContainer.appendChild(questionCard);
            
            // Automatically add two options for MCQ questions
            if (type === 'mcq') {
                addOption(questionIndex);
                addOption(questionIndex);
            }
        }

        // Function to add a new option to a MCQ question
        function addOption(questionIndex) {
            const optionsContainer = document.getElementById(optionsContainer_${questionIndex});
            const optionIndex = optionsContainer.children.length;

            const optionDiv = document.createElement('div');
            optionDiv.className = 'answer-option';
            optionDiv.innerHTML = `
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center flex-grow-1">
                        <input type="checkbox" id="optionCorrect_${questionIndex}_${optionIndex}" 
                               name="questions[${questionIndex}][answers][${optionIndex}][correct]" 
                               class="form-check-input">
                        <input type="text" id="optionText_${questionIndex}_${optionIndex}" 
                               name="questions[${questionIndex}][answers][${optionIndex}][text]" 
                               class="form-control ms-3" placeholder="Enter option text" required>
                    </div>
                    <button type="button" class="btn btn-outline-danger delete-btn" onclick="deleteOption(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            optionsContainer.appendChild(optionDiv);
        }

        // Function to delete a question
        function deleteQuestion(button) {
            const questionCard = button.closest('.question-card');
            
            // Add a fade-out animation
            questionCard.style.opacity = '0';
            questionCard.style.transform = 'translateY(20px)';
            questionCard.style.transition = 'all 0.3s ease';
            
            // Remove the element after animation completes
            setTimeout(() => {
                questionCard.remove();
                renumberQuestions();
            }, 300);
        }

        // Function to delete an option
        function deleteOption(button) {
            const optionDiv = button.closest('.answer-option');
            
            // Add a fade-out animation
            optionDiv.style.opacity = '0';
            optionDiv.style.transform = 'translateX(20px)';
            optionDiv.style.transition = 'all 0.3s ease';
            
            // Remove the element after animation completes
            setTimeout(() => {
                const optionsContainer = optionDiv.parentElement;
                optionDiv.remove();
                
                // If there are less than 2 options remaining, add options to make it at least 2
                if (optionsContainer.children.length < 2) {
                    const questionIndex = optionsContainer.id.split('_')[1];
                    while (optionsContainer.children.length < 2) {
                        addOption(questionIndex);
                    }
                }
                
                renumberOptions(optionsContainer);
            }, 300);
        }

        // Function to renumber questions after deletion
        function renumberQuestions() {
            const questionsContainer = document.getElementById('questionsContainer');
            const questionCards = questionsContainer.querySelectorAll('.question-card');
            
            questionCards.forEach((card, index) => {
                // Update question index in all form elements
                const oldIndex = card.querySelector('[name^="questions["]').name.match(/questions\[(\d+)\]/)[1];
                
                // Update all elements with the question index
                const elements = card.querySelectorAll([name*="questions[${oldIndex}]"]);
                elements.forEach(element => {
                    element.name = element.name.replace(questions[${oldIndex}], questions[${index}]);
                });
                
                // Update IDs that contain the question index
                const idElements = card.querySelectorAll([id*="_${oldIndex}_"], [id$="_${oldIndex}"]);
                idElements.forEach(element => {
                    if (element.id.endsWith(_${oldIndex})) {
                        element.id = element.id.replace(_${oldIndex}, _${index});
                    } else {
                        element.id = element.id.replace(_${oldIndex}_, _${index}_);
                    }
                });
                
                // Update optionsContainer ID if exists
                const optionsContainer = card.querySelector([id^="optionsContainer_"]);
                if (optionsContainer) {
                    optionsContainer.id = optionsContainer_${index};
                    
                    // Also update the add option button's onclick attribute
                    const addOptionBtn = card.querySelector('button[onclick^="addOption"]');
                    if (addOptionBtn) {
                        addOptionBtn.setAttribute('onclick', addOption(${index}));
                    }
                }
            });
        }

        // Function to renumber options within a question after deletion
        function renumberOptions(optionsContainer) {
            const options = optionsContainer.querySelectorAll('.answer-option');
            const questionIndex = optionsContainer.id.split('_')[1];
            
            options.forEach((option, index) => {
                const inputs = option.querySelectorAll('input');
                
                inputs.forEach(input => {
                    const oldOptionIndex = input.name.match(/answers\[(\d+)\]/)[1];
                    input.name = input.name.replace(answers[${oldOptionIndex}], answers[${index}]);
                    
                    // Update IDs as well
                    if (input.id.includes(_${questionIndex}_)) {
                        input.id = input.id.replace(_${questionIndex}_${oldOptionIndex}, _${questionIndex}_${index});
                    }
                });
            });
        }

        // Function to validate MCQ questions have at least one correct answer
        function validateMCQAnswers() {
            const questionsContainer = document.getElementById('questionsContainer');
            const mcqCards = questionsContainer.querySelectorAll('.mcq-card');
            
            for (let i = 0; i < mcqCards.length; i++) {
                const card = mcqCards[i];
                const questionIndex = i;
                const hasCorrectAnswer = card.querySelector(input[name^="questions[${questionIndex}][answers]"][name$="[correct]"]:checked);
                
                if (!hasCorrectAnswer) {
                    alert('Each multiple choice question must have at least one correct answer selected.');
                    return false;
                }
                
                // Also check that there are at least 2 options
                const options = card.querySelectorAll('.answer-option');
                if (options.length < 2) {
                    alert('Each multiple choice question must have at least 2 options.');
                    return false;
                }
            }
            
            return true;
        }

        // Validate that end date is after start date
        document.getElementById('examEndDate').addEventListener('change', function() {
            const startDate = new Date(document.getElementById('examStartDate').value);
            const endDate = new Date(this.value);
            
            if (endDate <= startDate) {
                alert('End date must be after start date.');
                this.value = '';
            }
        });

        document.getElementById('examStartDate').addEventListener('change', function() {
            const endDateInput = document.getElementById('examEndDate');
            if (endDateInput.value) {
                const startDate = new Date(this.value);
                const endDate = new Date(endDateInput.value);
                
                if (endDate <= startDate) {
                    alert('End date must be after start date.');
                    endDateInput.value = '';
                }
            }
        });

        // Initialize the form with at least one question
        window.addEventListener('DOMContentLoaded', function() {
            // Optionally add a default question if needed
            // addQuestion('mcq');
        });
    </script>
</body>
</html>