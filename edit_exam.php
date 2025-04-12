<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config.php';

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$organized_questions = [];

if ($exam_id <= 0) {
    $_SESSION['error_message'] = "Invalid exam ID.";
    header("Location: teacher.php");
    exit();
}

try {
    // Fetch exam details
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $_SESSION['error_message'] = "Exam not found.";
        header("Location: teacher.php");
        exit();
    }

    // Verify the current user is the teacher who created this exam
    if ($_SESSION['user_id'] != $exam['teacher_id'] && $_SESSION['user_type'] != 'admin') {
        $_SESSION['error_message'] = "You don't have permission to edit this exam.";
        header("Location: teacher.php");
        exit();
    }

    // Fetch filieres and groups for dropdowns
    $filieres = $pdo->query("SELECT id, name FROM filieres")->fetchAll(PDO::FETCH_ASSOC);
    $groups = $pdo->query("SELECT id, name FROM student_groups")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch questions and answers
    $stmt = $pdo->prepare("
        SELECT 
            q.id AS question_id,
            q.question_text,
            q.points,
            q.question_type,
            q.image_path,
            q.file_path,
            o.id AS option_id,
            o.option_text,
            o.is_correct,
            o.option_order
        FROM questions q
        LEFT JOIN question_options o ON q.id = o.question_id
        WHERE q.exam_id = ?
        ORDER BY q.id, o.option_order
    ");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize questions and answers
    foreach ($questions as $row) {
        $question_id = $row['question_id'];
        if (!isset($organized_questions[$question_id])) {
            $organized_questions[$question_id] = [
                'text' => $row['question_text'],
                'points' => $row['points'],
                'type' => $row['question_type'],
                'image_path' => $row['image_path'],
                'file_path' => $row['file_path'],
                'answers' => []
            ];
        }
        if ($row['option_id']) {
            $organized_questions[$question_id]['answers'][] = [
                'id' => $row['option_id'],
                'text' => $row['option_text'],
                'correct' => (bool)$row['is_correct'],
                'order' => $row['option_order']
            ];
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        
        try {
            // Validate and sanitize input
            $title = filter_input(INPUT_POST, 'examTitle', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'examDescription', FILTER_SANITIZE_STRING);
            $duration = filter_input(INPUT_POST, 'examDuration', FILTER_VALIDATE_INT);
            $filiere_id = filter_input(INPUT_POST, 'examFiliere', FILTER_VALIDATE_INT);
            $group_id = filter_input(INPUT_POST, 'examGroup', FILTER_VALIDATE_INT);
            $status = filter_input(INPUT_POST, 'examStatus', FILTER_SANITIZE_STRING);
            $is_filiere_wide = isset($_POST['is_filiere_wide']) ? 1 : 0;

            // Keep original dates
            $start_date = $exam['start_date'];
            $end_date = $exam['end_date'];

            // Validate required fields
            if (empty($title)) {
                throw new Exception('Exam title is required.');
            }
            if ($duration <= 0) {
                throw new Exception('Duration must be a positive number.');
            }

            // Update exam details (keeping original dates)
            $stmt = $pdo->prepare("
                UPDATE exams 
                SET title = ?, description = ?, start_date = ?, end_date = ?, duration = ?, 
                    filiere_id = ?, group_id = ?, status = ?, is_filiere_wide = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, 
                $description, 
                $start_date, 
                $end_date, 
                $duration, 
                $filiere_id, 
                $group_id, 
                $status,
                $is_filiere_wide,
                $exam_id
            ]);

            // Handle questions
            if (isset($_POST['questions'])) {
                $posted_questions = $_POST['questions'];
                $posted_question_ids = array_filter(array_keys($posted_questions), 'is_numeric');
                
                // Delete questions not present in the POST data
                if (!empty($posted_question_ids)) {
                    $placeholders = implode(',', array_fill(0, count($posted_question_ids), '?'));
                    
                    // Delete options for questions to be deleted
                    $stmt = $pdo->prepare("
                        DELETE FROM question_options 
                        WHERE question_id IN (
                            SELECT id FROM questions 
                            WHERE exam_id = ? 
                            AND id NOT IN ($placeholders)
                        )
                    ");
                    $params = array_merge([$exam_id], $posted_question_ids);
                    $stmt->execute($params);
                    
                    // Delete the questions themselves
                    $stmt = $pdo->prepare("
                        DELETE FROM questions 
                        WHERE exam_id = ? 
                        AND id NOT IN ($placeholders)
                    ");
                    $stmt->execute($params);
                } else {
                    // No questions in POST, delete all questions for this exam
                    $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE exam_id = ?)");
                    $stmt->execute([$exam_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM questions WHERE exam_id = ?");
                    $stmt->execute([$exam_id]);
                }

                // Process each question
                foreach ($posted_questions as $question_id => $question) {
                    $q_text = filter_var($question['text'], FILTER_SANITIZE_STRING);
                    $q_points = filter_var($question['points'], FILTER_VALIDATE_FLOAT);
                    $q_type = isset($question['answers']) && is_array($question['answers']) ? 'mcq' : 'open';

                    if (empty($q_text)) {
                        throw new Exception('Question text cannot be empty.');
                    }
                    if ($q_points <= 0) {
                        throw new Exception('Points must be a positive number.');
                    }

                    if (is_numeric($question_id) && $question_id > 0) {
                        // Update existing question
                        $stmt = $pdo->prepare("
                            UPDATE questions 
                            SET question_text = ?, points = ?, question_type = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$q_text, $q_points, $q_type, $question_id]);
                    } else {
                        // Insert new question
                        $stmt = $pdo->prepare("
                            INSERT INTO questions (exam_id, question_text, points, question_type)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$exam_id, $q_text, $q_points, $q_type]);
                        $question_id = $pdo->lastInsertId();
                    }

                    // Handle file uploads for question
                    if (!empty($_FILES['questions']['tmp_name'][$question_id]['file'])) {
                        $file = $_FILES['questions']['tmp_name'][$question_id]['file'];
                        $file_name = $_FILES['questions']['name'][$question_id]['file'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                        
                        if (in_array($file_ext, $allowed_ext)) {
                            $new_file_name = uniqid() . '_' . $file_name;
                            $upload_path = 'uploads/' . $new_file_name;
                            
                            if (move_uploaded_file($file, $upload_path)) {
                                $stmt = $pdo->prepare("UPDATE questions SET file_path = ? WHERE id = ?");
                                $stmt->execute([$upload_path, $question_id]);
                            }
                        }
                    }

                    // Handle MCQ answers
                    if ($q_type === 'mcq' && isset($question['answers'])) {
                        // Delete existing answers first
                        $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
                        $stmt->execute([$question_id]);

                        $option_order = 1;
                        $has_correct_answer = false;
                        
                        foreach ($question['answers'] as $answer) {
                            $a_text = filter_var($answer['text'], FILTER_SANITIZE_STRING);
                            $is_correct = !empty($answer['correct']) ? 1 : 0;
                            
                            if (empty($a_text)) {
                                throw new Exception('Answer text cannot be empty.');
                            }
                            
                            if ($is_correct) {
                                $has_correct_answer = true;
                            }

                            $stmt = $pdo->prepare("
                                INSERT INTO question_options (question_id, option_text, is_correct, option_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$question_id, $a_text, $is_correct, $option_order]);
                            $option_order++;
                        }
                        
                        if (!$has_correct_answer) {
                            throw new Exception('Each MCQ question must have at least one correct answer.');
                        }
                    }
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Exam updated successfully!";
            header("Location: teacher.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Error updating exam: ' . $e->getMessage();
            header("Location: edit_exam.php?id=$exam_id");
            exit();
        }
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header("Location: teacher.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            <h1 class="page-title">Edit Exam</h1>
            <p class="page-subtitle">Update your assessment questions and details</p>
        </header>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error!</strong>
                    <p class="mb-0"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Success!</strong>
                    <p class="mb-0"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
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
                                   placeholder="Enter exam title" value="<?= htmlspecialchars($exam['title']) ?>" required>
                            <div class="invalid-feedback">Please provide an exam title.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examDuration" class="form-label">
                                <i class="fas fa-clock me-2"></i>Duration (minutes)
                            </label>
                            <input type="number" id="examDuration" name="examDuration" class="form-control" 
                                   placeholder="Duration in minutes" value="<?= htmlspecialchars($exam['duration']) ?>" required min="1">
                            <div class="invalid-feedback">Please provide a valid duration.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label for="examDescription" class="form-label">
                                <i class="fas fa-align-left me-2"></i>Description
                            </label>
                            <textarea id="examDescription" name="examDescription" class="form-control" 
                                      rows="3" placeholder="Enter exam description" required><?= htmlspecialchars($exam['description']) ?></textarea>
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
                                    <option value="" disabled>Select Filière</option>
                                    <?php foreach ($filieres as $filiere): ?>
                                        <option value="<?= htmlspecialchars($filiere['id']) ?>" <?= $exam['filiere_id'] == $filiere['id'] ? 'selected' : '' ?>>
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
                                    <option value="" disabled>Select Group</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= htmlspecialchars($group['id']) ?>" <?= $exam['group_id'] == $group['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down filiere-select-arrow"></i>
                            </div>
                            <div class="invalid-feedback">Please select a group.</div>
                        </div>
                    </div>
                    
                    <!-- Display dates as read-only -->
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-calendar-plus me-2"></i>Start Date & Time
                            </label>
                            <input type="hidden" name="examStartDate" value="<?= htmlspecialchars($exam['start_date']) ?>">
                            <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($exam['start_date'])) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-calendar-minus me-2"></i>End Date & Time
                            </label>
                            <input type="hidden" name="examEndDate" value="<?= htmlspecialchars($exam['end_date']) ?>">
                            <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($exam['end_date'])) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="examStatus" class="form-label">
                                <i class="fas fa-info-circle me-2"></i>Status
                            </label>
                            <select id="examStatus" name="examStatus" class="form-select" required>
                                <option value="draft" <?= $exam['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= $exam['status'] == 'published' ? 'selected' : '' ?>>Published</option>
                                <option value="in_progress" <?= $exam['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= $exam['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="archived" <?= $exam['status'] == 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_filiere_wide" 
                                       name="is_filiere_wide" <?= $exam['is_filiere_wide'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_filiere_wide">
                                    Apply to all groups in this filière
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions Container -->
            <div id="questionsContainer">
                <?php foreach ($organized_questions as $question_id => $question): ?>
                    <div class="card question-card fade-in <?= $question['type'] === 'mcq' ? 'mcq-card' : 'open-card' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">
                                    <span class="question-type-badge <?= $question['type'] === 'mcq' ? 'bg-success' : 'bg-info' ?>">
                                        <i class="fas fa-<?= $question['type'] === 'mcq' ? 'list-ul' : 'pen' ?>"></i>
                                        <?= $question['type'] === 'mcq' ? 'Multiple Choice Question' : 'Open Question' ?>
                                    </span>
                                </h5>
                                <button type="button" class="btn btn-outline-danger delete-btn" onclick="deleteQuestion(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <div class="mb-4">
                                <label for="questionText_<?= $question_id ?>" class="form-label">
                                    <i class="fas fa-question-circle me-2"></i>Question Text
                                </label>
                                <textarea id="questionText_<?= $question_id ?>" name="questions[<?= $question_id ?>][text]" 
                                          class="form-control" rows="3" placeholder="Enter your question here..." required><?= htmlspecialchars($question['text']) ?></textarea>
                                <div class="invalid-feedback">Please provide a question text.</div>
                            </div>
                            
                            <input type="hidden" name="questions[<?= $question_id ?>][type]" value="<?= $question['type'] ?>">
                            
                            <div class="mb-4">
                                <label for="questionPoints_<?= $question_id ?>" class="form-label">
                                    <i class="fas fa-star me-2"></i>Points
                                </label>
                                <input type="number" id="questionPoints_<?= $question_id ?>" 
                                       name="questions[<?= $question_id ?>][points]" class="form-control" 
                                       placeholder="Enter points value" value="<?= htmlspecialchars($question['points']) ?>" required min="1" step="0.5">
                                <div class="invalid-feedback">Please provide valid points (minimum 1).</div>
                            </div>
                            
                            <?php if ($question['type'] === 'mcq'): ?>
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-list-ul me-2"></i>Options (Check the correct answer)
                                    </label>
                                    <div id="optionsContainer_<?= $question_id ?>" class="mt-3">
                                        <?php foreach ($question['answers'] as $index => $answer): ?>
                                            <div class="answer-option">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="d-flex align-items-center flex-grow-1">
                                                        <input type="checkbox" id="optionCorrect_<?= $question_id ?>_<?= $index ?>" 
                                                               name="questions[<?= $question_id ?>][answers][<?= $index ?>][correct]" 
                                                               class="form-check-input" <?= $answer['correct'] ? 'checked' : '' ?>>
                                                        <input type="text" id="optionText_<?= $question_id ?>_<?= $index ?>" 
                                                               name="questions[<?= $question_id ?>][answers][<?= $index ?>][text]" 
                                                               class="form-control ms-3" placeholder="Enter option text" 
                                                               value="<?= htmlspecialchars($answer['text']) ?>" required>
                                                    </div>
                                                    <button type="button" class="btn btn-outline-danger delete-btn" onclick="deleteOption(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-3" 
                                            onclick="addOption(<?= $question_id ?>)">
                                        <i class="fas fa-plus me-2"></i>Add Option
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <label for="questionFile_<?= $question_id ?>" class="form-label">
                                    <i class="fas fa-file-upload me-2"></i>Attach File (Optional)
                                </label>
                                <?php if (!empty($question['file_path'])): ?>
                                    <div class="mb-2">
                                        <small>Current file: <?= basename($question['file_path']) ?></small>
                                        <a href="<?= htmlspecialchars($question['file_path']) ?>" target="_blank" class="ms-2">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" id="questionFile_<?= $question_id ?>" 
                                       name="questions[<?= $question_id ?>][file]" class="form-control">
                                <small class="text-info d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Supported formats: JPG, PNG, PDF, DOC, DOCX
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

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
                    <i class="fas fa-save me-2"></i>Update Exam
                </button>
            </div>
        </form>
    </div>

    <!-- Bootstrap 5 Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Make group checkboxes more interactive
        document.addEventListener('DOMContentLoaded', function() {
            // Remove date validation since we're not editing dates anymore
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
            const questionIndex = Date.now(); // Use timestamp as temporary ID for new questions
            
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
            const optionIndex = Date.now(); // Use timestamp as temporary ID for new options

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
            }, 300);
        }

        // Function to validate MCQ questions have at least one correct answer
        function validateMCQAnswers() {
            const questionsContainer = document.getElementById('questionsContainer');
            const mcqCards = questionsContainer.querySelectorAll('.mcq-card');
            
            for (let i = 0; i < mcqCards.length; i++) {
                const card = mcqCards[i];
                const questionId = card.querySelector('textarea').id.split('_')[1];
                const hasCorrectAnswer = card.querySelector(input[name^="questions[${questionId}][answers]"][name$="[correct]"]:checked);
                
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
    </script>
</body>
</html>