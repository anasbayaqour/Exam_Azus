<?php
// filepath: c:\laragon\www\anas-mehdi-Exams\Project_exaam_online\exam_online\student_results.php
session_start();
require_once 'config.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Get the student ID from the query parameter
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    die("Invalid student ID.");
}

try {
    // Fetch student information
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? AND user_type = 'student'");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        die("Student not found.");
    }

    // Fetch exam results for the student
    $stmt = $pdo->prepare("
        SELECT e.title, ea.total_score, ea.submit_time, e.start_date, e.end_date
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.student_id = ? AND ea.is_completed = 1
        ORDER BY ea.submit_time DESC
    ");
    $stmt->execute([$student_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error in student_results.php: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results - ExamOnline</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .student-info {
            margin-bottom: 2rem;
        }

        .result-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .result-card h3 {
            margin: 0 0 0.5rem;
        }

        .result-card p {
            margin: 0.25rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="student-info">
            <h1>Results for <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h1>
            <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
        </div>

        <?php if (!empty($results)): ?>
            <?php foreach ($results as $result): ?>
                <div class="result-card">
                    <h3><?= htmlspecialchars($result['title']) ?></h3>
                    <p><strong>Score:</strong> <?= htmlspecialchars($result['total_score']) ?>%</p>
                    <p><strong>Submitted On:</strong> <?= htmlspecialchars($result['submit_time']) ?></p>
                    <p><strong>Exam Period:</strong> <?= htmlspecialchars($result['start_date']) ?> to <?= htmlspecialchars($result['end_date']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No results found for this student.</p>
        <?php endif; ?>
    </div>
</body>
</html>