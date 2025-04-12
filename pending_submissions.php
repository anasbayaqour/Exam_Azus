<?php
session_start();

require_once 'config.php';

// Check if user is logged in as a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Initialize variables
$pending_submissions = [];
$error_message = '';
$success_message = '';

try {
    // Check database connection
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // Updated query to include additional information and handle potential NULL values
    $stmt = $pdo->prepare("
        SELECT 
            er.id as result_id,
            e.id as exam_id,
            e.title as exam_title,
            u.user_id as student_id,
            u.first_name,
            u.last_name,
            u.email,
            er.submitted_at,
            (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) as total_questions,
            (SELECT AVG(CASE 
                WHEN difficulty = 'easy' THEN 1 
                WHEN difficulty = 'medium' THEN 2 
                WHEN difficulty = 'hard' THEN 3 
                ELSE 2 
            END) FROM questions q WHERE q.exam_id = e.id) as avg_difficulty
        FROM exam_results er
        JOIN exams e ON er.exam_id = e.id
        JOIN users u ON er.student_id = u.user_id
        WHERE er.status = 'pending'
        AND e.teacher_id = ?
        ORDER BY er.submitted_at ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check for success messages from other pages
    if (isset($_SESSION['success'])) {
        $success_message = $_SESSION['success'];
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        $error_message = $_SESSION['error'];
        unset($_SESSION['error']);
    }

} catch (Exception $e) {
    error_log("Error in pending_submissions.php: " . $e->getMessage());
    $error_message = "An error occurred while loading submissions";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Exam Submissions | Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
    /* Color Palette */
    --primary: #4361ee;
    --primary-dark: #3a56d4;
    --secondary: #3f37c9;
    --accent: #4cc9f0;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --light: #f8f9fa;
    --dark: #212529;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    
    /* Typography */
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    
    /* Layout */
    --border-radius-sm: 4px;
    --border-radius: 8px;
    --border-radius-lg: 12px;
    --border-radius-xl: 16px;
    --box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --box-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    
    /* Transitions */
    --transition-fast: 0.15s ease;
    --transition: 0.3s ease;
    --transition-slow: 0.5s ease;
}

/* Base Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: var(--font-family);
    background: linear-gradient(135deg, #f6f9ff 0%, #f1f5fe 100%);
    color: var(--gray-800);
    min-height: 100vh;
    line-height: 1.6;
}

/* Dashboard Container */
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: var(--box-shadow-lg);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    transform: rotate(30deg);
}

.header-content h1 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.header-icon {
    font-size: 1.8rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.header-actions {
    z-index: 2;
}

.btn-return {
    padding: 0.6rem 1.2rem;
    border-radius: var(--border-radius);
    border: 2px solid white;
    font-weight: 500;
    transition: var(--transition);
}

.btn-return:hover {
    background-color: white;
    color: var(--primary);
    transform: translateY(-3px);
}

/* Content Wrapper */
.content-wrapper {
    background-color: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    animation: slideUpFade 0.6s ease-out;
}

@keyframes slideUpFade {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 5rem 2rem;
    text-align: center;
}

.empty-icon-container {
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 2rem;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

.empty-icon {
    font-size: 3rem;
    color: var(--gray-500);
}

.empty-state h2 {
    font-size: 1.8rem;
    margin-bottom: 1rem;
    color: var(--gray-700);
}

.empty-state p {
    font-size: 1.1rem;
    color: var(--gray-600);
    max-width: 400px;
    margin-bottom: 2rem;
}

/* Table Container */
.table-container {
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
}

.table-counter {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
    font-weight: 500;
    color: var(--gray-700);
}

.counter-badge {
    background-color: var(--primary);
    color: white;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.table-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-container {
    position: relative;
}

.search-input {
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    transition: var(--transition-fast);
    width: 250px;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
}

.filter-container select {
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
    padding: 0.5rem;
    cursor: pointer;
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
}

.submissions-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.submissions-table thead th {
    background-color: var(--gray-100);
    padding: 1rem 1.5rem;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 2px solid var(--gray-200);
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
}

.submissions-table tbody tr {
    transition: var(--transition-fast);
    animation: fadeIn 0.5s ease-out;
    animation-fill-mode: both;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.submissions-table tbody tr:nth-child(even) {
    background-color: var(--gray-50);
}

.submissions-table tbody tr:hover {
    background-color: rgba(67, 97, 238, 0.05);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 5;
    position: relative;
}

.submissions-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
}

/* Table Cell Styles */
.td-student {
    min-width: 250px;
}

.student-profile {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.avatar-container {
    position: relative;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    transition: var(--transition);
}

.submission-row:hover .avatar {
    transform: scale(1.1);
}

.student-details {
    display: flex;
    flex-direction: column;
}

.student-name {
    font-weight: 500;
    color: var(--gray-800);
}

.student-email {
    font-size: 0.85rem;
    color: var(--gray-600);
}

.td-exam {
    min-width: 180px;
}

.exam-title {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.status-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: var(--border-radius-sm);
    font-size: 0.75rem;
    font-weight: 600;
    background-color: var(--warning);
    color: var(--gray-800);
}

.td-questions {
    text-align: center;
}

.questions-count {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-weight: 500;
}

.icon-questions {
    color: var(--primary);
}

.td-difficulty {
    min-width: 150px;
}

.difficulty-indicator {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-weight: 500;
    font-size: 0.9rem;
}

.difficulty-bar {
    height: 6px;
    background-color: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
}

.difficulty-progress {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.difficulty-easy .difficulty-progress {
    background-color: #4ade80;
}

.difficulty-medium .difficulty-progress {
    background-color: #facc15;
}

.difficulty-hard .difficulty-progress {
    background-color: #ef4444;
}

.td-date {
    min-width: 150px;
}

.submission-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.icon-calendar {
    color: var(--primary);
}

.submission-time-ago {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-top: 0.25rem;
}

.td-actions {
    text-align: right;
}

.btn-correction {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    border-radius: var(--border-radius);
    padding: 0.6rem 1.2rem;
    color: white;
    font-weight: 500;
    transition: var(--transition);
    text-decoration: none;
}

.btn-correction:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 15px -3px rgba(67, 97, 238, 0.3);
}

/* Animation Delays for Rows */
.submissions-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
.submissions-table tbody tr:nth-child(2) { animation-delay: 0.2s; }
.submissions-table tbody tr:nth-child(3) { animation-delay: 0.3s; }
.submissions-table tbody tr:nth-child(4) { animation-delay: 0.4s; }
.submissions-table tbody tr:nth-child(5) { animation-delay: 0.5s; }
.submissions-table tbody tr:nth-child(6) { animation-delay: 0.6s; }
.submissions-table tbody tr:nth-child(7) { animation-delay: 0.7s; }
.submissions-table tbody tr:nth-child(8) { animation-delay: 0.8s; }
.submissions-table tbody tr:nth-child(9) { animation-delay: 0.9s; }
.submissions-table tbody tr:nth-child(10) { animation-delay: 1s; }

/* Responsive Styles */
@media (max-width: 992px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        align-self: flex-start;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .table-actions {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-container {
        width: 100%;
    }
    
    .search-input {
        width: 100%;
    }
    
    .filter-container {
        width: 100%;
    }
    
    .filter-container select {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .student-email {
        display: none;
    }
    
    .submission-time-ago {
        display: none;
    }
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-tasks me-3 header-icon"></i>Pending Submissions</h1>
                <p class="text-light">Review and grade student exam submissions</p>
            </div>
            <div class="header-actions">
                <a href="teacher.php" class="btn btn-outline-light btn-return">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-wrapper">
            <?php if (empty($pending_submissions)): ?>
                <div class="empty-state">
                    <div class="empty-icon-container">
                        <i class="fas fa-inbox empty-icon"></i>
                    </div>
                    <h2>No Pending Submissions</h2>
                    <p>All exams have been reviewed or no submissions are waiting.</p>
                    <a href="create-exam.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Create New Exam
                    </a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-counter">
                            <span class="counter-badge"><?= count($pending_submissions) ?></span>
                            <span>Submissions Awaiting Review</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-container">
                                <input type="text" id="searchInput" placeholder="Search submissions..." class="search-input">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <div class="filter-container">
                                <select id="sortSelect" class="form-select form-select-sm">
                                    <option value="date-desc">Newest First</option>
                                    <option value="date-asc" selected>Oldest First</option>
                                    <option value="name-asc">Student Name (A-Z)</option>
                                    <option value="name-desc">Student Name (Z-A)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table submissions-table">
                            <thead>
                                <tr>
                                    <th class="th-student">Student</th>
                                    <th class="th-exam">Exam</th>
                                    <th class="th-questions">Questions</th>
                                    <th class="th-difficulty">Difficulty</th>
                                    <th class="th-date">Submitted</th>
                                    <th class="th-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_submissions as $index => $submission): ?>
                                    <tr class="submission-row" data-id="<?= htmlspecialchars($submission['result_id']) ?>">
                                        <td class="td-student">
                                            <div class="student-profile">
                                                <div class="avatar-container">
                                                    <div class="avatar">
                                                        <?= htmlspecialchars(strtoupper(substr($submission['first_name'], 0, 1) . substr($submission['last_name'], 0, 1))) ?>
                                                    </div>
                                                </div>
                                                <div class="student-details">
                                                    <div class="student-name"><?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?></div>
                                                    <div class="student-email"><?= htmlspecialchars($submission['email'] ?? 'N/A') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="td-exam">
                                            <div class="exam-title"><?= htmlspecialchars($submission['exam_title']) ?></div>
                                            <span class="status-badge">Pending</span>
                                        </td>
                                        <td class="td-questions">
                                            <div class="questions-count">
                                                <i class="fas fa-list-check icon-questions"></i>
                                                <?= htmlspecialchars($submission['total_questions'] ?? '0') ?>
                                            </div>
                                        </td>
                                        <td class="td-difficulty">
                                            <?php
                                            $difficulty = $submission['avg_difficulty'] ?? 2;
                                            $difficultyText = $difficulty < 1.5 ? 'Easy' : ($difficulty < 2.5 ? 'Medium' : 'Hard');
                                            $difficultyClass = 'difficulty-' . strtolower($difficultyText);
                                            ?>
                                            <div class="difficulty-indicator <?= $difficultyClass ?>">
                                                <?= htmlspecialchars($difficultyText) ?>
                                                <div class="difficulty-bar">
                                                    <div class="difficulty-progress" style="width: <?= min(100, ($difficulty / 3) * 100) ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="td-date">
                                            <div class="submission-time">
                                                <i class="fas fa-calendar-alt icon-calendar"></i>
                                                <time datetime="<?= htmlspecialchars($submission['submitted_at']) ?>">
                                                    <?= htmlspecialchars(date('M j, Y', strtotime($submission['submitted_at']))) ?>
                                                </time>
                                            </div>
                                            <div class="submission-time-ago">
                                                <?php
                                                $submittedDate = new DateTime($submission['submitted_at']);
                                                $now = new DateTime();
                                                $interval = $submittedDate->diff($now);
                                                
                                                if ($interval->d > 0) {
                                                    echo $interval->d . ' days ago';
                                                } elseif ($interval->h > 0) {
                                                    echo $interval->h . ' hours ago';
                                                } else {
                                                    echo $interval->i . ' minutes ago';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="td-actions">
                                            <a href="correction.php?exam_id=<?= htmlspecialchars($submission['exam_id']) ?>&student_id=<?= htmlspecialchars($submission['student_id']) ?>" 
                                               class="btn btn-primary btn-correction">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Grade</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/submissions.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    const submissionRows = document.querySelectorAll('.submission-row');
    const tableBody = document.querySelector('.submissions-table tbody');
    
    // Hover effects with sound (optional - commented out by default)
    function playHoverSound() {
        // Uncomment to enable sound (very subtle click)
        /*
        const audio = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBIAAAABAAEARKwAAIhYAQACABAAAABkYXRhAgAAAAEA');
        audio.volume = 0.05;
        audio.play().catch(e => console.log('Audio play prevented: Requires user interaction first'));
        */
    }
    
    // Add hover effects to rows
    submissionRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            playHoverSound();
            highlightRow(this);
        });
        
        row.addEventListener('mouseleave', function() {
            resetRowHighlight(this);
        });
        
        // Add click effect
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on the action button
            if (!e.target.closest('.btn-correction')) {
                // Add ripple effect
                createRipple(e, this);
            }
        });
    });
    
    // Ripple effect function
    function createRipple(event, element) {
        const circle = document.createElement('div');
        const diameter = Math.max(element.clientWidth, element.clientHeight);
        
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - element.getBoundingClientRect().left - diameter / 2}px`;
        circle.style.top = `${event.clientY - element.getBoundingClientRect().top - diameter / 2}px`;
        circle.classList.add('ripple');
        
        const ripple = element.querySelector('.ripple');
        if (ripple) {
            ripple.remove();
        }
        
        element.appendChild(circle);
        
        // Remove after animation completes
        setTimeout(() => {
            circle.remove();
        }, 600);
    }
    
    // Add CSS for ripple effect
    const style = document.createElement('style');
    style.textContent = `
        .submission-row { position: relative; overflow: hidden; }
        .ripple {
            position: absolute;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Row highlight/unhighlight functions
    function highlightRow(row) {
        row.style.transform = 'translateY(-2px)';
        row.style.boxShadow = '0 4px 12px rgba(67, 97, 238, 0.15)';
        row.style.zIndex = '10';
        
        // Highlight the action button
        const actionBtn = row.querySelector('.btn-correction');
        if (actionBtn) {
            actionBtn.style.transform = 'translateY(-3px)';
            actionBtn.style.boxShadow = '0 10px 15px -3px rgba(67, 97, 238, 0.3)';
        }
        
        // Slightly pulse the avatar
        const avatar = row.querySelector('.avatar');
        if (avatar) {
        avatar.style.transform = 'scale(1.1)';
    }
}

function resetRowHighlight(row) {
    row.style.transform = '';
    row.style.boxShadow = '';
    row.style.zIndex = '';
    
    // Reset the action button
    const actionBtn = row.querySelector('.btn-correction');
    if (actionBtn) {
        actionBtn.style.transform = '';
        actionBtn.style.boxShadow = '';
    }
    
    // Reset the avatar
    const avatar = row.querySelector('.avatar');
    if (avatar) {
        avatar.style.transform = '';
    }
}

// Search functionality
searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    filterRows(searchTerm);
});

function filterRows(searchTerm) {
    submissionRows.forEach(row => {
        const studentName = row.querySelector('.student-name').innerText.toLowerCase();
        const studentEmail = row.querySelector('.student-email')?.innerText.toLowerCase() || '';
        const examTitle = row.querySelector('.exam-title').innerText.toLowerCase();
        
        if (studentName.includes(searchTerm) || 
            studentEmail.includes(searchTerm) || 
            examTitle.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Sorting functionality
sortSelect.addEventListener('change', function() {
    sortRows(this.value);
});

function sortRows(sortOption) {
    const rowsArray = Array.from(submissionRows);
    
    rowsArray.sort((a, b) => {
        switch(sortOption) {
            case 'date-asc':
                return compareDates(a, b, true);
            case 'date-desc':
                return compareDates(a, b, false);
            case 'name-asc':
                return compareNames(a, b, true);
            case 'name-desc':
                return compareNames(a, b, false);
            default:
                return 0;
        }
    });
    
    // Clear table and append sorted rows
    rowsArray.forEach(row => tableBody.appendChild(row));
}

function compareDates(a, b, ascending) {
    const dateA = new Date(a.querySelector('time').getAttribute('datetime'));
    const dateB = new Date(b.querySelector('time').getAttribute('datetime'));
    return ascending ? dateA - dateB : dateB - dateA;
}

function compareNames(a, b, ascending) {
    const nameA = a.querySelector('.student-name').innerText.toLowerCase();
    const nameB = b.querySelector('.student-name').innerText.toLowerCase();
    
    if (ascending) {
        return nameA.localeCompare(nameB);
    } else {
        return nameB.localeCompare(nameA);
    }
}

// Initialize sort on page load
sortRows(sortSelect.value);

// Add animation delay to rows
submissionRows.forEach((row, index) => {
    row.style.animationDelay = `${0.1 * (index + 1)}s`;
});

// Focus search on keyboard shortcut (ctrl + f or cmd + f)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        searchInput.focus();
    }
});

// Optional: Add tooltips to action buttons
const actionButtons = document.querySelectorAll('.btn-correction');
actionButtons.forEach(btn => {
    btn.setAttribute('title', 'Review and grade this submission');
});

// Optional: Add visual feedback when clicking the Grade button
actionButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Loading...</span>';
    });
});

// Handle server errors - display fallback message if needed
window.addEventListener('error', function(e) {
    if (e.target.tagName === 'IMG') {
        // Replace broken images with initials instead
        const row = e.target.closest('.submission-row');
        if (row) {
            const studentName = row.querySelector('.student-name').innerText;
            const initials = studentName.split(' ').map(name => name[0]).join('').substring(0, 2).toUpperCase();
            e.target.style.display = 'none';
            
            const avatarElement = row.querySelector('.avatar');
            if (avatarElement) {
                avatarElement.innerText = initials;
            }
        }
    }
});

// Notify if there are many submissions
if (submissionRows.length > 10) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-info alert-dismissible fade show mt-3';
    alertDiv.innerHTML = `
        <i class="fas fa-info-circle me-2"></i>
        You have ${submissionRows.length} submissions awaiting review. Consider prioritizing older submissions first.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.querySelector('.content-wrapper').appendChild(alertDiv);
}
});
    </script>
</body>
</html>
    