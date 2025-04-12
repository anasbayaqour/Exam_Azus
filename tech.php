<?php
require_once 'config.php';

session_start();

// Redirect if the user is not logged in or is not a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Handle student acceptance or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $action = $_POST['action'];
    
    if ($action === 'accept' || $action === 'reject') {
        $status = $action === 'accept' ? 'accepted' : 'rejected';
        try {
            // Update the student's status in the database
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$status, $student_id]);
            
            // Set a session message based on the action
            $_SESSION['message'] = $action === 'accept' ? 
                "Student has been successfully accepted." : 
                "Student has been rejected.";
            $_SESSION['message_type'] = $action === 'accept' ? 'success' : 'warning';
        } catch (PDOException $e) {
            // Handle database errors
            $_SESSION['message'] = "Error processing request.";
            $_SESSION['message_type'] = 'error';
        }
        
        // Redirect back to the teacher dashboard
        header("Location: tech.php");
        exit();
    }
}

// Fetch all pending students with their filiere names
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.filiere_id, u.group_id, u.status, u.created_at, f.name AS filiere_name
        FROM users u
        LEFT JOIN filieres f ON u.filiere_id = f.id
        WHERE u.status = 'pending'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $pendingStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle database errors
    $_SESSION['message'] = "Error fetching pending students.";
    $_SESSION['message_type'] = 'error';
    $pendingStudents = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - ExamOnline</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="teacher-dashboard.css">
</head>
<style>
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --secondary: #0ea5e9;
    --accent: #06b6d4;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --background: #0f172a;
    --surface: #1e293b;
    --text: #f8fafc;
    --text-secondary: #94a3b8;
    --card-bg: rgba(30, 41, 59, 0.8);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

body {
    background: var(--background);
    color: var(--text);
    line-height: 1.6;
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
}

.animated-background {
    position: fixed;
    width: 100vw;
    height: 100vh;
    top: 0;
    left: 0;
    z-index: -1;
    background: linear-gradient(135deg, var(--background), var(--surface));
    overflow: hidden;
}

.animated-background::before {
    content: '';
    position: absolute;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(14, 165, 233, 0.15) 0%, transparent 30%);
    animation: backgroundAnimation 20s ease infinite;
    opacity: 0.5;
}

@keyframes backgroundAnimation {
    0% {
        transform: translate(-25%, -25%) rotate(0deg);
    }
    50% {
        transform: translate(-20%, -20%) rotate(180deg);
    }
    100% {
        transform: translate(-25%, -25%) rotate(360deg);
    }
}

.navbar {
    position: fixed;
    top: 0;
    width: 100%;
    padding: 1rem 5%;
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(10px);
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.nav-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-links {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.main-content {
    padding-top: 5rem;
    min-height: 100vh;
    width: 100%;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 5%;
    width: 100%;
}

.dashboard-header {
    margin-bottom: 2rem;
    text-align: center;
}

.dashboard-header h1 {
    font-size: clamp(1.5rem, 5vw, 2.5rem);
    margin-bottom: 0.5rem;
    background: linear-gradient(to right, var(--text), var(--accent));
    -webkit-background-clip: text;
    color: transparent;
}

.dashboard-header p {
    color: var(--text-secondary);
    font-size: clamp(0.875rem, 2vw, 1rem);
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
    width: 100%;
}

.student-card {
    background: var(--card-bg);
    border-radius: 1rem;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.student-info {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.student-avatar {
    width: 60px;
    height: 60px;
    min-width: 60px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary);
}

.student-details {
    flex: 1;
    min-width: 180px;
}

.student-details h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    word-break: break-word;
}

.student-details p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    word-break: break-word;
}

.student-details p i {
    min-width: 16px;
}

.student-actions {
    display: flex;
    gap: 1rem;
    width: 100%;
}

.student-actions form {
    width: 100%;
    display: flex;
    gap: 1rem;
}

.btn, .btn-secondary, .btn-accept, .btn-reject {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    white-space: nowrap;
}

.btn {
    background: var(--primary);
    color: var(--text);
}

.btn-secondary {
    background: rgba(99, 102, 241, 0.1);
    color: var(--text);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.btn-accept {
    background: var(--success);
    color: white;
    flex: 1;
}

.btn-reject {
    background: var(--error);
    color: white;
    flex: 1;
}

.btn:hover, .btn-secondary:hover, .btn-accept:hover, .btn-reject:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    color: var(--success);
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.2);
    color: var(--warning);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: var(--error);
}

.no-students {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    background: var(--card-bg);
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.no-students i {
    font-size: 3rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.no-students h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.no-students p {
    color: var(--text-secondary);
}

.loading {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--background);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.5s ease;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    border-top-color: var(--primary);
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Media Queries */
@media (max-width: 1200px) {
    .container {
        padding: 2rem 3%;
    }

    .students-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
    }
}

@media (max-width: 992px) {
    .nav-content {
        padding: 0.5rem 0;
    }
    
    .dashboard-header h1 {
        font-size: 1.75rem;
    }

    .student-card {
        padding: 1.2rem;
    }
}

@media (max-width: 768px) {
    .navbar {
        padding: 0.75rem 4%;
    }
    
    .nav-content {
        justify-content: center;
        text-align: center;
    }
    
    .nav-links {
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
    }
    
    .logo {
        font-size: 1.2rem;
    }
    
    .main-content {
        padding-top: 7rem;
    }
    
    .student-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .student-avatar {
        margin-bottom: 0.5rem;
    }
    
    .student-details p {
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .container {
        padding: 1.5rem 4%;
    }
    
    .nav-links .btn, .nav-links .btn-secondary {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
    
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .student-card {
        padding: 1rem;
    }
    
    .student-actions form {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .student-actions button {
        width: 100%;
    }
}

@media (max-width: 400px) {
    .navbar {
        padding: 0.75rem 3%;
    }
    
    .logo {
        font-size: 1.1rem;
    }
    
    .nav-links {
        gap: 0.5rem;
    }
    
    .nav-links .btn, .nav-links .btn-secondary {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.25rem;
    }
    
    .dashboard-header p {
        font-size: 0.8rem;
    }
}
</style>
<body>
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <div class="animated-background"></div>

    <nav class="navbar">
        <div class="nav-content">
            <a href="#" class="logo">
                <i class="fas fa-graduation-cap"></i>
                ExamOnline
            </a>
            <div class="nav-links">
                <a href="teacher.php" class="btn-secondary">Routeur</a>
                <a href="allstudent.php" class="btn-secondary">View students</a>
                <a href="logout.php" class="btn">Logout</a>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <section class="dashboard-section">
            <div class="container">
                <div class="dashboard-header">
                    <h1><i class="fas fa-user-graduate"></i> Pending Students</h1>
                    <p>Review and manage student registration requests</p>
                </div>

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                        <?= $_SESSION['message'] ?>
                    </div>
                    <?php 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                <?php endif; ?>

                <div class="students-grid">
                    <?php if (!empty($pendingStudents)): ?>
                        <?php foreach ($pendingStudents as $student): ?>
                            <div class="student-card">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="student-details">
                                        <h3><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h3>
                                        <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($student['email']) ?></p>
                                        <p><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($student['filiere_name'] ?? 'Not specified') ?></p>
                                        <p><i class="fas fa-users"></i> Group: <?= htmlspecialchars($student['group_id'] ?? 'Not assigned') ?></p>
                                        <p><i class="fas fa-clock"></i> Registered: <?= date('F j, Y', strtotime($student['created_at'])) ?></p>
                                    </div>
                                </div>
                                <div class="student-actions">
                                    <form method="POST" action="">
                                        <input type="hidden" name="student_id" value="<?= $student['user_id'] ?>">
                                        <button type="submit" name="action" value="accept" class="btn-accept">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn-reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-students">
                            <i class="fas fa-users-slash"></i>
                            <h3>No Pending Requests</h3>
                            <p>There are currently no pending student registration requests.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        window.addEventListener('load', function() {
            const loader = document.getElementById('loading');
            setTimeout(() => {
                loader.style.opacity = '0';
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 500);
            }, 1000);
        });
    </script>
</body>
</html>