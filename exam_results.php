<?php
// exam_results.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'teacher') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    // Get exam details
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        throw new Exception("Exam not found");
    }

    // Get all students who took this exam
    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, u.username, u.email, 
               r.score, r.total_questions, r.correct_answers,
               r.start_time, r.end_time, r.status
        FROM student_results r
        JOIN student s ON r.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE r.exam_id = ?
        ORDER BY r.score DESC
    ");
    $stmt->execute([$exam_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $total_students = count($results);
    $average_score = $total_students > 0 ? 
        array_sum(array_column($results, 'score')) / $total_students : 0;
    $highest_score = $total_students > 0 ? 
        max(array_column($results, 'score')) : 0;
    $lowest_score = $total_students > 0 ? 
        min(array_column($results, 'score')) : 0;

    // Get question statistics
    $stmt = $pdo->prepare("
        SELECT q.id, q.question_text, 
               COUNT(a.id) AS attempt_count,
               SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count
        FROM exam_questions eq
        JOIN questions q ON eq.question_id = q.id
        LEFT JOIN student_answers a ON a.question_id = q.id AND a.exam_id = eq.exam_id
        WHERE eq.exam_id = ?
        GROUP BY q.id
        ORDER BY eq.question_order
    ");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - <?= htmlspecialchars($exam['title'] ?? 'Exam') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
    :root {
        --primary-color: #4f46e5;
        --primary-light: #6366f1;
        --primary-dark: #4338ca;
        --secondary-color: #10b981;
        --danger-color: #ef4444;
        --warning-color: #f59e0b;
        --info-color: #3b82f6;
        
        --text-primary: #111827;
        --text-secondary: #6b7280;
        --text-muted: #9ca3af;
        
        --bg-primary: #ffffff;
        --bg-secondary: #f9fafb;
        --bg-tertiary: #f3f4f6;
        
        --border-color: #e5e7eb;
        --border-radius: 0.5rem;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        
        --transition: all 0.2s ease;
    }

    [data-theme="dark"] {
        --primary-color: #6366f1;
        --primary-light: #818cf8;
        --primary-dark: #4f46e5;
        --secondary-color: #10b981;
        --danger-color: #ef4444;
        --warning-color: #f59e0b;
        --info-color: #3b82f6;
        
        --text-primary: #f9fafb;
        --text-secondary: #e5e7eb;
        --text-muted: #9ca3af;
        
        --bg-primary: #1f2937;
        --bg-secondary: #111827;
        --bg-tertiary: #374151;
        
        --border-color: #4b5563;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        line-height: 1.5;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background-color: var(--bg-tertiary);
        color: var(--text-primary);
        border-radius: var(--border-radius);
        text-decoration: none;
        transition: var(--transition);
    }

    .back-btn:hover {
        background-color: var(--border-color);
    }

    .back-btn i {
        margin-right: 0.5rem;
    }

    .exam-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
    }

    .exam-description {
        color: var(--text-secondary);
        margin-bottom: 2rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background-color: var(--bg-primary);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow);
    }

    .stat-title {
        font-size: 0.875rem;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
    }

    .stat-value.high {
        color: var(--secondary-color);
    }

    .stat-value.medium {
        color: var(--warning-color);
    }

    .stat-value.low {
        color: var(--danger-color);
    }

    .chart-container {
        background-color: var(--bg-primary);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow);
    }

    .chart-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .results-table {
        width: 100%;
        border-collapse: collapse;
        background-color: var(--bg-primary);
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
    }

    .results-table th,
    .results-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .results-table th {
        background-color: var(--bg-tertiary);
        font-weight: 600;
    }

    .results-table tr:last-child td {
        border-bottom: none;
    }

    .results-table tr:hover {
        background-color: var(--bg-tertiary);
    }

    .score-cell {
        font-weight: 600;
    }

    .score-high {
        color: var(--secondary-color);
    }

    .score-medium {
        color: var(--warning-color);
    }

    .score-low {
        color: var(--danger-color);
    }

    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-completed {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--secondary-color);
    }

    .status-in-progress {
        background-color: rgba(59, 130, 246, 0.1);
        color: var(--info-color);
    }

    .status-failed {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .view-btn {
        display: inline-flex;
        align-items: center;
        padding: 0.375rem 0.75rem;
        background-color: var(--primary-color);
        color: white;
        border-radius: var(--border-radius);
        text-decoration: none;
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .view-btn:hover {
        background-color: var(--primary-dark);
    }

    .view-btn i {
        margin-right: 0.375rem;
    }

    .questions-section {
        background-color: var(--bg-primary);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow);
    }

    .question-item {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .question-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .question-text {
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .question-stats {
        display: flex;
        align-items: center;
        font-size: 0.875rem;
        color: var(--text-muted);
    }

    .question-stats span {
        margin-right: 1rem;
    }

    .progress-bar {
        height: 6px;
        background-color: var(--bg-tertiary);
        border-radius: 3px;
        margin-top: 0.5rem;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background-color: var(--primary-color);
        border-radius: 3px;
    }

    .empty-state {
        background-color: var(--bg-primary);
        border-radius: var(--border-radius);
        padding: 3rem;
        text-align: center;
        box-shadow: var(--shadow);
    }

    .empty-state-icon {
        font-size: 3rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .empty-state h3 {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }

    .export-btns {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .export-btn {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background-color: var(--bg-tertiary);
        color: var(--text-primary);
        border-radius: var(--border-radius);
        text-decoration: none;
        transition: var(--transition);
    }

    .export-btn:hover {
        background-color: var(--border-color);
    }

    .export-btn i {
        margin-right: 0.5rem;
    }

    @media (max-width: 768px) {
        .container {
            padding: 1rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .results-table {
            display: block;
            overflow-x: auto;
        }
        
        .export-btns {
            flex-direction: column;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="teacher.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3>Error Loading Exam Results</h3>
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php else: ?>
            <h1 class="exam-title"><?= htmlspecialchars($exam['title']) ?></h1>
            <p class="exam-description"><?= htmlspecialchars($exam['description']) ?></p>

            <div class="export-btns">
                <a href="export_results.php?id=<?= $exam_id ?>&format=csv" class="export-btn">
                    <i class="fas fa-file-csv"></i> Export as CSV
                </a>
                <a href="export_results.php?id=<?= $exam_id ?>&format=pdf" class="export-btn">
                    <i class="fas fa-file-pdf"></i> Export as PDF
                </a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Students</div>
                    <div class="stat-value"><?= $total_students ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Average Score</div>
                    <div class="stat-value <?= $average_score >= 70 ? 'high' : ($average_score >= 50 ? 'medium' : 'low') ?>">
                        <?= number_format($average_score, 1) ?>%
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Highest Score</div>
                    <div class="stat-value high"><?= number_format($highest_score, 1) ?>%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Lowest Score</div>
                    <div class="stat-value low"><?= number_format($lowest_score, 1) ?>%</div>
                </div>
            </div>

            <div class="chart-container">
                <h2 class="chart-title">Score Distribution</h2>
                <canvas id="scoreChart"></canvas>
            </div>

            <h2>Student Results</h2>
            <?php if ($total_students > 0): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Score</th>
                            <th>Correct Answers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?= htmlspecialchars($result['username']) ?></td>
                                <td><?= htmlspecialchars($result['email']) ?></td>
                                <td class="score-cell <?= $result['score'] >= 70 ? 'score-high' : ($result['score'] >= 50 ? 'score-medium' : 'score-low') ?>">
                                    <?= number_format($result['score'], 1) ?>%
                                </td>
                                <td><?= $result['correct_answers'] ?> / <?= $result['total_questions'] ?></td>
                                <td>
                                    <span class="status-badge <?= 
                                        $result['status'] == 'completed' ? 'status-completed' : 
                                        ($result['status'] == 'in_progress' ? 'status-in-progress' : 'status-failed') 
                                    ?>">
                                        <?= ucfirst(str_replace('_', ' ', $result['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="student_result.php?exam_id=<?= $exam_id ?>&student_id=<?= $result['user_id'] ?>" class="view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3>No Results Available</h3>
                    <p>No students have completed this exam yet.</p>
                </div>
            <?php endif; ?>

            <h2>Question Statistics</h2>
            <div class="questions-section">
                <?php if (!empty($questions)): ?>
                    <?php foreach ($questions as $question): ?>
                        <div class="question-item">
                            <h3 class="question-text"><?= htmlspecialchars($question['question_text']) ?></h3>
                            <div class="question-stats">
                                <span>Attempts: <?= $question['attempt_count'] ?></span>
                                <span>Correct: <?= $question['correct_count'] ?> (<?= 
                                    $question['attempt_count'] > 0 ? 
                                    number_format(($question['correct_count'] / $question['attempt_count']) * 100, 1) : 0
                                ?>%)</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= 
                                    $question['attempt_count'] > 0 ? 
                                    ($question['correct_count'] / $question['attempt_count']) * 100 : 0
                                ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h3>No Questions Found</h3>
                        <p>This exam doesn't have any questions yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($total_students > 0): ?>
        // Score distribution chart
        const scoreData = {
            labels: ['0-20%', '21-40%', '41-60%', '61-80%', '81-100%'],
            datasets: [{
                label: 'Number of Students',
                data: [
                    <?= count(array_filter($results, fn($r) => $r['score'] <= 20)) ?>,
                    <?= count(array_filter($results, fn($r) => $r['score'] > 20 && $r['score'] <= 40)) ?>,
                    <?= count(array_filter($results, fn($r) => $r['score'] > 40 && $r['score'] <= 60)) ?>,
                    <?= count(array_filter($results, fn($r) => $r['score'] > 60 && $r['score'] <= 80)) ?>,
                    <?= count(array_filter($results, fn($r) => $r['score'] > 80)) ?>
                ],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(234, 179, 8, 0.7)',
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)'
                ],
                borderColor: [
                    'rgba(239, 68, 68, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(234, 179, 8, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)'
                ],
                borderWidth: 1
            }]
        };

        const scoreCtx = document.getElementById('scoreChart').getContext('2d');
        new Chart(scoreCtx, {
            type: 'bar',
            data: scoreData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.y} students`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Theme switcher (if you want to include it)
        const themeBtn = document.createElement('button');
        themeBtn.innerHTML = '<i class="fas fa-moon"></i>';
        themeBtn.style.position = 'fixed';
        themeBtn.style.bottom = '20px';
        themeBtn.style.right = '20px';
        themeBtn.style.width = '50px';
        themeBtn.style.height = '50px';
        themeBtn.style.borderRadius = '50%';
        themeBtn.style.backgroundColor = 'var(--primary-color)';
        themeBtn.style.color = 'white';
        themeBtn.style.border = 'none';
        themeBtn.style.cursor = 'pointer';
        themeBtn.style.boxShadow = 'var(--shadow-md)';
        themeBtn.style.zIndex = '1000';
        themeBtn.style.display = 'flex';
        themeBtn.style.alignItems = 'center';
        themeBtn.style.justifyContent = 'center';
        themeBtn.style.fontSize = '1.25rem';
        
        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            if (currentTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'light');
                themeBtn.innerHTML = '<i class="fas fa-moon"></i>';
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                themeBtn.innerHTML = '<i class="fas fa-sun"></i>';
                localStorage.setItem('theme', 'dark');
            }
        });
        
        document.body.appendChild(themeBtn);
        
        // Set initial theme
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);
        themeBtn.innerHTML = currentTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    });
    </script>
</body>
</html>