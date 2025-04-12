<?php
session_start();
require_once 'config.php';

// Check if the user is logged in and is an admin or teacher
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'teacher')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    // Fetch total number of students
    $stmt = $pdo->query("SELECT COUNT(*) AS total_students FROM users WHERE user_type = 'student'");
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];

    // Fetch total number of exams
    $stmt = $pdo->query("SELECT COUNT(*) AS total_exams FROM exams WHERE status = 'published'");
    $total_exams = $stmt->fetch(PDO::FETCH_ASSOC)['total_exams'];

    // Fetch average score across all exams
    $stmt = $pdo->query("
        SELECT AVG(ea.total_score) AS average_score
        FROM exam_attempts ea
        WHERE ea.is_completed = 1
    ");
    $average_score = round($stmt->fetch(PDO::FETCH_ASSOC)['average_score'], 2);

    // Fetch performance data for chart
    $stmt = $pdo->query("
        SELECT e.title AS exam_title, AVG(ea.total_score) AS average_score
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE ea.is_completed = 1
        GROUP BY e.id
        ORDER BY e.title ASC
    ");
    $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while fetching analytics data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - ExamOnline</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f3f4f6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #4f46e5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card h2 {
            font-size: 2rem;
            margin: 0;
            color: #4f46e5;
        }

        .stat-card p {
            margin: 10px 0 0;
            color: #6b7280;
        }

        canvas {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Analytics Dashboard</h1>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php else: ?>
            <!-- Stats Section -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h2><?= htmlspecialchars($total_students) ?></h2>
                    <p>Total Students</p>
                </div>
                <div class="stat-card">
                    <h2><?= htmlspecialchars($total_exams) ?></h2>
                    <p>Total Exams</p>
                </div>
                <div class="stat-card">
                    <h2><?= htmlspecialchars($average_score) ?>%</h2>
                    <p>Average Score</p>
                </div>
            </div>

            <!-- Performance Chart -->
            <h2>Exam Performance</h2>
            <canvas id="performanceChart"></canvas>
        <?php endif; ?>
    </div>

    <script>
        // Performance Chart Data
        const performanceData = <?= json_encode($performance_data) ?>;

        const labels = performanceData.map(data => data.exam_title);
        const scores = performanceData.map(data => data.average_score);

        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Average Score (%)',
                    data: scores,
                    backgroundColor: 'rgba(79, 70, 229, 0.7)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    </script>
</body>
</html>