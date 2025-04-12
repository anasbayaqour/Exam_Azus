<?php
// filepath: c:\laragon\www\anas-mehdi-Exams\Project_exaam_online\exam_online\exams.php
session_start();
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Determine user type (student or teacher)
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

try {
    if ($user_type === 'student') {
        // Fetch available exams for students
        $stmt = $pdo->prepare("
            SELECT id, title, description, start_date, end_date, duration
            FROM exams
            WHERE NOW() BETWEEN start_date AND end_date
            ORDER BY start_date ASC
        ");
        $stmt->execute();
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_type === 'teacher') {
        // Fetch exams created by the teacher
        $stmt = $pdo->prepare("
            SELECT id, title, description, start_date, end_date, duration
            FROM exams
            WHERE teacher_id = ?
            ORDER BY start_date ASC
        ");
        $stmt->execute([$user_id]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new Exception("Invalid user type.");
    }
} catch (Exception $e) {
    error_log("Error in exams.php: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams - ExamOnline</title>
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

        .exam-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .exam-card h3 {
            margin: 0 0 0.5rem;
        }

        .exam-card p {
            margin: 0.25rem 0;
        }

        .exam-card .actions {
            margin-top: 1rem;
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
        <h1>Available Exams</h1>

        <?php if (!empty($exams)): ?>
            <?php foreach ($exams as $exam): ?>
                <div class="exam-card">
                    <h3><?= htmlspecialchars($exam['title']) ?></h3>
                    <p><?= htmlspecialchars($exam['description']) ?></p>
                    <p><strong>Start Date:</strong> <?= htmlspecialchars($exam['start_date']) ?></p>
                    <p><strong>End Date:</strong> <?= htmlspecialchars($exam['end_date']) ?></p>
                    <p><strong>Duration:</strong> <?= htmlspecialchars($exam['duration']) ?> minutes</p>
                    <div class="actions">
                        <?php if ($user_type === 'student'): ?>
                            <a href="take_exam.php?id=<?= $exam['id'] ?>" class="btn btn-primary">Take Exam</a>
                        <?php elseif ($user_type === 'teacher'): ?>
                            <a href="correction.php?exam_id=<?= $exam['id'] ?>" class="btn btn-secondary">View Submissions</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No exams available at the moment.</p>
        <?php endif; ?>
    </div>
</body>
</html>