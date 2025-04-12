<?php
// filepath: c:\laragon\www\anas-mehdi-Exams\Project_exaam_online\exam_online\students.php
session_start();
require_once 'config.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

try {
    // Fetch all students
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email
        FROM users
        WHERE user_type = 'student'
        ORDER BY first_name ASC
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error in students.php: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - ExamOnline</title>
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

        .student-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .student-card h3 {
            margin: 0 0 0.5rem;
        }

        .student-card p {
            margin: 0.25rem 0;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .btn-primary {
            background: #4f46e5;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #3730a3;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Students</h1>

        <?php if (!empty($students)): ?>
            <?php foreach ($students as $student): ?>
                <div class="student-card">
                    <h3><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h3>
                    <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
                    <div class="actions">
                        <a href="student_results.php?student_id=<?= $student['user_id'] ?>" class="btn btn-primary">View Results</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No students found.</p>
        <?php endif; ?>
    </div>
</body>
</html>