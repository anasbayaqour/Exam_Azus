<?php
// Student Dashboard
session_start();
require_once 'config.php';
date_default_timezone_set('Africa/Casablanca');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_type'] !== 'student') {
    header("Location: unauthorized.php");
    exit();
}

// Initialize variables
$student = [
    'first_name' => 'Unknown',
    'last_name' => 'User',
    'email' => '',
    'filiere_id' => null,
    'group_id' => null,
    'filiere_name' => 'Unknown',
    'group_name' => 'Unknown'
];
$exams = [];
$results = [];
$error_message = '';

try {
    // Retrieve student information
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email, u.filiere_id, u.group_id, 
               f.name as filiere_name, g.name as group_name
        FROM users u
        LEFT JOIN filieres f ON u.filiere_id = f.id
        LEFT JOIN student_groups g ON u.group_id = g.id
        WHERE u.user_id = :user_id AND u.user_type = 'student'
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $error_message = "Could not retrieve your profile information. Please contact support.";
    }
    
    // Retrieve available exams only if student has group_id and filiere_id
    if ($student['group_id'] && $student['filiere_id']) {
        $stmt = $pdo->prepare("
            SELECT e.*, 
                   CASE 
                       WHEN ea.id IS NULL THEN 'Not Started'
                       WHEN ea.is_completed = 0 THEN 'In Progress'
                       ELSE 'Completed'
                   END as attempt_status
            FROM exams e
            LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = :student_id
            WHERE (e.group_id = :group_id OR e.group_id IS NULL OR e.group_id = 0)
            AND (e.filiere_id = :filiere_id OR e.filiere_id IS NULL OR e.filiere_id = 0)
            AND e.end_date > NOW()
            AND e.status = 'published'
            ORDER BY e.start_date ASC
        ");
        $stmt->execute([
            'student_id' => $_SESSION['user_id'],
            'group_id' => $student['group_id'],
            'filiere_id' => $student['filiere_id']
        ]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get student's exam results
    $stmt = $pdo->prepare("
        SELECT e.title as exam_name, 
               er.score, 
               er.submitted_at,
               er.status as correction_status,
               er.exam_id,
               CASE 
                   WHEN er.status = 'pending' THEN 'Pending Correction'
                   WHEN er.status = 'complete' THEN CONCAT(ROUND((er.score / 100) * 20, 1), '/20')
                   ELSE 'N/A'
               END as display_score
        FROM exam_results er
        JOIN exams e ON er.exam_id = e.id
        WHERE er.student_id = :student_id
        ORDER BY er.submitted_at DESC
    ");
    $stmt->execute(['student_id' => $_SESSION['user_id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "An error occurred while retrieving your data. Please try again later.";
}

// HTML starts here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo htmlspecialchars($student['first_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/student.css">
    <style>
:root {
    /* Modern Color Scheme */
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #818cf8;
    --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    --secondary: #0ea5e9;
    --accent: #06b6d4;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    
    /* Dark Theme (Default) */
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --text-tertiary: #64748b;
    --card-bg: rgba(30, 41, 59, 0.8);
    --card-border: rgba(255, 255, 255, 0.05);
    
    /* Layout */
    --sidebar-width: 280px;
    --header-height: 70px;
    --border-radius: 16px;
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --transition-speed: 0.3s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    line-height: 1.6;
    min-height: 100vh;
    transition: background-color var(--transition-speed) ease;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: var(--bg-tertiary);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--bg-secondary);
    border-right: 1px solid var(--card-border);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transition: transform var(--transition-speed) ease, width var(--transition-speed) ease;
    overflow-y: auto;
    overflow-x: hidden;
}

.sidebar-header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--card-border);
}

.logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: color var(--transition-speed) ease;
}

.logo i {
    color: var(--primary);
}

.sidebar-close {
    display: none;
    cursor: pointer;
    font-size: 1.25rem;
    color: var(--text-secondary);
    transition: color var(--transition-speed) ease;
}

.sidebar-close:hover {
    color: var(--text-primary);
}

.nav-menu {
    list-style: none;
    padding: 1.5rem 0;
    flex-grow: 1;
}

.nav-item {
    margin: 0.5rem 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all var(--transition-speed) ease;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    margin: 0 0.75rem;
    position: relative;
    overflow: hidden;
}

.nav-link:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: var(--primary-gradient);
    border-radius: 0 3px 3px 0;
    transform: translateX(-4px);
    transition: transform var(--transition-speed) ease;
}

.nav-link:hover, .nav-link.active {
    background: rgba(99, 102, 241, 0.1);
    color: var(--text-primary);
}

.nav-link.active:before {
    transform: translateX(0);
}

.nav-link i {
    font-size: 1.25rem;
    width: 1.5rem;
    text-align: center;
    transition: transform var(--transition-speed) ease;
}

.nav-link:hover i, .nav-link.active i {
    color: var(--primary);
    transform: scale(1.1);
}

.sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--card-border);
}

/* Header */
.header {
    height: var(--header-height);
    display: flex;
    align-items: center;
    padding: 0 2rem;
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(10px);
    position: fixed;
    top: 0;
    right: 0;
    left: var(--sidebar-width);
    z-index: 999;
    border-bottom: 1px solid var(--card-border);
    transition: left var(--transition-speed) ease;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

#pageTitle {
    font-size: 1.5rem;
    font-weight: 600;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: gradientAnimation 10s ease infinite;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.controls-group {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.menu-toggle {
    display: none;
    cursor: pointer;
    font-size: 1.25rem;
    color: var(--text-secondary);
    transition: color var(--transition-speed) ease;
}

.menu-toggle:hover {
    color: var(--text-primary);
}

.theme-toggle-btn {
    background: transparent;
    border: 1px solid var(--card-border);
    color: var(--text-secondary);
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-speed) ease;
}

.theme-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    transform: rotate(15deg);
}

.user-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
}

.user-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.3);
}

.user-details {
    display: flex;
    flex-direction: column;
}

/* Main Content */
.main-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.main-content {
    margin-left: var(--sidebar-width);
    padding-top: var(--header-height);
    transition: margin-left var(--transition-speed) ease;
    min-height: 100vh;
}

.container {
    padding: 2rem;
}

/* Dashboard Components */
.dashboard-header {
    margin-bottom: 2.5rem;
}

.dashboard-header h1 {
    font-size: 2.25rem;
    margin-bottom: 0.5rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    position: relative;
    display: inline-block;
}

.dashboard-header h1::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -8px;
    width: 50px;
    height: 4px;
    background: var(--primary-gradient);
    border-radius: 3px;
}

.dashboard-header p {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.alert {
    padding: 1.25rem;
    border-radius: var(--border-radius);
    margin-bottom: 1.5rem;
    background: var(--bg-secondary);
    border-left: 4px solid var(--warning);
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn var(--transition-speed) ease;
}

.alert-error {
    border-left-color: var(--error);
}

.alert i {
    font-size: 1.5rem;
    color: var(--warning);
}

.alert-error i {
    color: var(--error);
}

.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    padding: 1.75rem;
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
    backdrop-filter: blur(10px);
    animation: fadeIn 0.6s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.stat-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.stat-card-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
}

.stat-card-icon {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.5rem;
    transition: all var(--transition-speed) ease;
}

.stat-card:hover .stat-card-icon {
    background: var(--primary);
    color: white;
    transform: rotate(10deg);
}

.stat-card-value {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-card p {
    color: var(--text-secondary);
}

.student-info, .info-card {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    padding: 1.75rem;
    margin-bottom: 2.5rem;
    backdrop-filter: blur(10px);
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    animation: fadeIn 0.6s ease;
}

.student-info h2, .info-card h2 {
    margin-bottom: 1.5rem;
    position: relative;
    display: inline-block;
    font-size: 1.5rem;
}

.student-info h2::after, .info-card h2::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -8px;
    width: 30px;
    height: 3px;
    background: var(--primary-gradient);
    border-radius: 3px;
}

.student-info p, .info-card p {
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.student-info p strong, .info-card p strong {
    color: var(--primary-light);
}

.exams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.exam-card {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    padding: 1.75rem;
    backdrop-filter: blur(10px);
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    transition: all var(--transition-speed) ease;
    position: relative;
    overflow: hidden;
    animation: fadeIn 0.6s ease;
}

.exam-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform var(--transition-speed) ease;
}

.exam-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.exam-card:hover::before {
    transform: scaleX(1);
}

.exam-card h3 {
    font-size: 1.25rem;
    margin-bottom: 1rem;
    color: var(--primary-light);
}

.exam-card p {
    margin-bottom: 0.75rem;
}

.upcoming-exam {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--card-border);
}

.upcoming-exam:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.upcoming-exam h3 {
    color: var(--primary-light);
    margin-bottom: 0.5rem;
}

/* Buttons */
.btn {
    padding: 0.875rem 1.75rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all var(--transition-speed) ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    position: relative;
    overflow: hidden;
    z-index: 1;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(-100%);
    transition: transform var(--transition-speed) ease;
    z-index: -1;
}

.btn:hover::before {
    transform: translateX(0);
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);
}

.btn-primary:hover {
    box-shadow: 0 10px 15px rgba(99, 102, 241, 0.3);
    transform: translateY(-2px);
}

.btn-full {
    width: 100%;
}

.btn[disabled] {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn[disabled]:hover {
    transform: none;
    box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);
}

.btn[disabled]::before {
    display: none;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
    border-radius: var(--border-radius);
    background: var(--card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table th,
.results-table td {
    padding: 1.25rem 1.5rem;
    text-align: left;
}

.results-table th {
    color: var(--primary-light);
    font-weight: 600;
    background: rgba(30, 41, 59, 0.5);
    position: relative;
}

.results-table th:not(:last-child)::after {
    content: '';
    position: absolute;
    right: 0;
    top: 25%;
    height: 50%;
    width: 1px;
    background: var(--card-border);
}

.results-table tbody tr {
    border-bottom: 1px solid var(--card-border);
    transition: background-color var(--transition-speed) ease;
}

.results-table tbody tr:last-child {
    border-bottom: none;
}

.results-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.success-badge {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.pending-badge {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

/* Forms */
.update-form {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    padding: 2rem;
    max-width: 600px;
    margin: 0 auto;
    backdrop-filter: blur(10px);
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
}

.form-group {
    margin-bottom: 1.75rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.75rem;
    color: var(--text-primary);
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 0.875rem 1rem;
    border-radius: 0.75rem;
    border: 2px solid var(--bg-tertiary);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: all var(--transition-speed) ease;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

/* Page Transitions */
.page {
    display: none;
    animation: fadeIn 0.5s ease;
}

.page.active {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes gradientAnimation {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Light Theme */
/* Light Theme */
body.light-theme {
    --bg-primary: #f8fafc;
    --bg-secondary: #ffffff;
    --bg-tertiary: #e2e8f0;
    --text-primary: #0f172a;
    --text-secondary: #334155;
    --text-tertiary: #64748b;
    --card-bg: rgba(255, 255, 255, 0.8);
    --card-border: rgba(0, 0, 0, 0.1);
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
}

/* Default Dark Theme Variables */
:root {
    /* Modern Color Scheme */
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #818cf8;
    --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    --secondary: #0ea5e9;
    --accent: #06b6d4;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    
    /* Dark Theme (Default) */
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --text-tertiary: #64748b;
    --card-bg: rgba(30, 41, 59, 0.8);
    --card-border: rgba(255, 255, 255, 0.05);
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    
    /* Layout */
    --sidebar-width: 280px;
    --header-height: 70px;
    --border-radius: 16px;
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --transition-speed: 0.3s;
}
/* Responsive Design */
@media (max-width: 1200px) {
    :root {
        --sidebar-width: 240px;
    }
}

@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
        box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .header {
        left: 0;
    }
    
    .menu-toggle {
        display: block;
    }
    
    .card-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}

@media (max-width: 768px) {
    .container {
        padding: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.75rem;
    }
    
    .stat-card-value {
        font-size: 2rem;
    }
    
    .exams-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .container {
        padding: 1rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.5rem;
    }
    
    .stat-card-value {
        font-size: 1.75rem;
    }
    
    .table-responsive {
        margin: 0 -1rem;
        width: calc(100% + 2rem);
        border-radius: 0;
    }
    
    .update-form {
        padding: 1.5rem;
    }
    
    .user-details {
        display: none;
    }
}

/* Notification styles */
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.notification {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    padding: 1rem;
    margin-bottom: 10px;
    box-shadow: var(--card-shadow);
    border-left: 4px solid var(--primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 300px;
    max-width: 100%;
    animation: slideInRight 0.3s ease forwards;
    backdrop-filter: blur(10px);
}

.notification.success {
    border-left-color: var(--success);
}

.notification.warning {
    border-left-color: var(--warning);
}

.notification.error {
    border-left-color: var(--error);
}

.notification-icon {
    font-size: 1.5rem;
    color: var(--primary);
}

.notification.success .notification-icon {
    color: var(--success);
}

.notification.warning .notification-icon {
    color: var(--warning);
}

.notification.error .notification-icon {
    color: var(--error);
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.notification-close {
    color: var(--text-secondary);
    cursor: pointer;
    transition: color var(--transition-speed) ease;
}

.notification-close:hover {
    color: var(--text-primary);
}

.notification.hide {
    animation: slideOutRight 0.3s ease forwards;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

/* Exam timer styles */
.exam-timer {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-top: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--warning);
    animation: pulse 2s infinite ease-in-out;
}

.exam-timer.danger {
    color: var(--error);
    animation: pulse 1s infinite ease-in-out;
}

@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
    100% {
        opacity: 1;
    }
}

/* Loading animation */
.loader {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100px;
}

.loader-dot {
    width: 12px;
    height: 12px;
    margin: 0 5px;
    background-color: var(--primary);
    border-radius: 50%;
    display: inline-block;
    animation: bounce 1.4s infinite ease-in-out both;
}

.loader-dot:nth-child(1) {
    animation-delay: -0.32s;
}

.loader-dot:nth-child(2) {
    animation-delay: -0.16s;
}

@keyframes bounce {
    0%, 80%, 100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}
    </style>

</head>
<body>
    <!-- Notification container -->
    <div class="notification-container" id="notificationContainer">
        <?php if ($error_message): ?>
            <div class="notification error">
                <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="notification-content">
                    <div class="notification-title">Error</div>
                    <div class="notification-message"><?php echo htmlspecialchars($error_message); ?></div>
                </div>
                <div class="notification-close"><i class="fas fa-times"></i></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <i class="fas fa-graduation-cap"></i>
                ExamOnline
            </a>
            <div class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="#home" class="nav-link active" data-page="home">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#exams" class="nav-link" data-page="exams">
                    <i class="fas fa-book"></i>
                    <span>Available Exams</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#results" class="nav-link" data-page="results">
                    <i class="fas fa-chart-bar"></i>
                    <span>Results</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#profile" class="nav-link" data-page="profile">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="logout.php" class="btn btn-primary btn-full">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Header -->
    <header class="header" id="header">
        <div class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="header-content">
            <h2 id="pageTitle">Dashboard</h2>
            <div class="controls-group">
                <button class="theme-toggle-btn" id="themeToggle">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1); ?>
                    </div>
                    <div class="user-details">
                        <span><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                        <small>Student</small>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-wrapper">
        <main class="main-content">
            <!-- Home/Dashboard Page -->
            <section id="home" class="page active">
                <div class="container">
                    <div class="dashboard-header">
                        <h1>Student Dashboard</h1>
                        <p>Welcome back, <?php echo htmlspecialchars($student['first_name']); ?>! Here's your academic progress overview.</p>
                    </div>

                    <?php if (count($exams) > 0): ?>
                        <div class="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Upcoming Exam!</strong>
                                <p>You have <?php echo count($exams); ?> upcoming exam(s). Make sure you're prepared!</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card-grid">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <h3>Completed Exams</h3>
                                <div class="stat-card-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-card-value">
                                <?php echo count(array_filter($results, fn($r) => $r['correction_status'] === 'complete')); ?>
                            </div>
                            <p>Out of <?php echo count($results); ?> total exams</p>
                        </div>

                        <div class="stat-card">
                            <div class="stat-card-header">
                                <h3>Average Score</h3>
                                <div class="stat-card-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                            <div class="stat-card-value">
                                <?php 
                                $completed = array_filter($results, fn($r) => $r['correction_status'] === 'complete' && is_numeric($r['score']));
                                $avg = count($completed) > 0 ? array_sum(array_column($completed, 'score')) / count($completed) : 0;
                                echo round($avg, 1) . '%';
                                ?>
                            </div>
                            <p>Across all subjects</p>
                        </div>

                        <div class="stat-card">
                            <div class="stat-card-header">
                                <h3>Upcoming Exams</h3>
                                <div class="stat-card-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo count($exams); ?></div>
                            <p>In the next 30 days</p>
                        </div>
                    </div>

                    <div class="student-info">
                        <h2>Your Information</h2>
                        <p><i class="fas fa-user"></i> <strong>Name:</strong> <?php echo htmlspecialchars($student['first_name']) . ' ' . htmlspecialchars($student['last_name']); ?></p>
                        <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                        <p><i class="fas fa-graduation-cap"></i> <strong>Program:</strong> <?php echo htmlspecialchars($student['filiere_name']); ?></p>
                        <p><i class="fas fa-users"></i> <strong>Group:</strong> <?php echo htmlspecialchars($student['group_name']); ?></p>
                    </div>

                    <?php if (count($exams) > 0): ?>
                        <div class="info-card">
                            <h2>Upcoming Exams</h2>
                            <?php foreach ($exams as $exam): ?>
                                <div class="upcoming-exam">
                                    <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <p><i class="fas fa-calendar"></i> <strong>Date:</strong> <?php echo date('F j, Y', strtotime($exam['start_date'])); ?></p>
                                    <p><i class="fas fa-clock"></i> <strong>Time:</strong> <?php echo date('g:i A', strtotime($exam['start_date'])) . ' - ' . date('g:i A', strtotime($exam['end_date'])); ?></p>
                                    <p><i class="fas fa-hourglass"></i> <strong>Duration:</strong> <?php echo floor($exam['duration'] / 60) . 'h ' . ($exam['duration'] % 60) . 'm'; ?></p>
                                    <p><i class="fas fa-clipboard-check"></i> <strong>Status:</strong> 
                                        <span class="badge <?php echo $exam['attempt_status'] === 'Completed' ? 'success-badge' : 'pending-badge'; ?>">
                                            <?php echo htmlspecialchars($exam['attempt_status']); ?>
                                        </span>
                                    </p>
                                    <a href="take_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-<?php echo $exam['attempt_status'] === 'Not Started' ? 'play' : 'info-circle'; ?>"></i>
                                        <?php echo $exam['attempt_status'] === 'Not Started' ? 'Start Exam' : 'View Details'; ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Exams Page -->
            <section id="exams" class="page">
                <div class="container">
                    <div class="dashboard-header">
                        <h1>Available Exams</h1>
                        <p>Upcoming exams you can register for or take if already enrolled.</p>
                    </div>

                    <?php if (count($exams) > 0): ?>
                        <div class="exams-grid">
                            <?php foreach ($exams as $exam): ?>
                                <div class="exam-card">
                                    <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <p><i class="fas fa-calendar"></i> <strong>Date:</strong> <?php echo date('F j, Y', strtotime($exam['start_date'])); ?></p>
                                    <p><i class="fas fa-clock"></i> <strong>Duration:</strong> <?php echo floor($exam['duration'] / 60) . 'h ' . ($exam['duration'] % 60) . 'm'; ?></p>
                                    <p><i class="fas fa-question-circle"></i> <strong>Type:</strong> <?php echo htmlspecialchars($exam['status'] ?? 'Standard'); ?></p>
                                    <p><i class="fas fa-clipboard-check"></i> <strong>Status:</strong> 
                                        <span class="badge <?php echo $exam['attempt_status'] === 'Completed' ? 'success-badge' : 'pending-badge'; ?>">
                                            <?php echo htmlspecialchars($exam['attempt_status']); ?>
                                        </span>
                                    </p>
                                    <div class="exam-timer">
                                        <i class="fas fa-hourglass-half"></i>
                                        <?php
                                        $now = new DateTime();
                                        $start = new DateTime($exam['start_date']);
                                        $interval = $now->diff($start);
                                        echo $interval->format('%a days %h hours remaining');
                                        ?>
                                    </div>
                                    <a href="<?php echo $exam['attempt_status'] === 'Not Started' ? 'take_exam.php?id='.$exam['id'] : 'message_Exam.php'; ?>" class="btn btn-primary btn-full" style="margin-top: 1rem;">
                                    <i class="fas fa-<?php echo $exam['attempt_status'] === 'Not Started' ? 'play' : 'info-circle'; ?>"></i>
                                    <?php echo $exam['attempt_status'] === 'Not Started' ? 'Start Exam' : 'View Details'; ?>
                                </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>No Exams Available</strong>
                                <p>There are currently no exams scheduled for your program. Please check back later.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Results Page -->
            <section id="results" class="page">
                <div class="container">
                    <div class="dashboard-header">
                        <h1>Exam Results</h1>
                        <p>View and analyze your past exam performances.</p>
                    </div>

                    <?php if (count($results) > 0): ?>
                        <div class="table-responsive">
                            <table class="results-table">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Date</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                                            <td><?php echo date('F j, Y', strtotime($result['submitted_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($result['display_score']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $result['correction_status'] === 'complete' ? 'success-badge' : 'pending-badge'; ?>">
                                                    <?php echo $result['correction_status'] === 'complete' ? 'Completed' : 'Pending'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view_results12.php?exam_id=<?php echo $result['exam_id']; ?>" class="btn btn-primary">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>No Results Available</strong>
                                <p>You haven't completed any exams yet. Your results will appear here after you complete exams.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Profile Page -->
            <section id="profile" class="page">
                <div class="container">
                    <div class="dashboard-header">
                        <h1>User Profile</h1>
                        <p>Manage your personal information and settings.</p>
                    </div>

                    <div class="update-form">
                        <form id="profileForm" action="update_profile.php" method="POST">
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password to confirm changes">
                            </div>
                            <div class="form-group">
                                <label for="newPassword">New Password (leave blank to keep current)</label>
                                <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password if you want to change">
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password">
                            </div>
                            <button type="submit" class="btn btn-primary btn-full">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="js/student.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Navigation functionality
        const navLinks = document.querySelectorAll('.nav-link');
        const pages = document.querySelectorAll('.page');
        const pageTitle = document.getElementById('pageTitle');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetPage = this.getAttribute('data-page');
                
                // Update active link
                navLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                // Show target page, hide others
                pages.forEach(page => {
                    if (page.id === targetPage) {
                        page.classList.add('active');
                        pageTitle.textContent = this.querySelector('span').textContent;
                    } else {
                        page.classList.remove('active');
                    }
                });
                
                // Close sidebar on mobile after navigation
                if (window.innerWidth < 768) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            });
        });
        
        // Mobile menu toggle
// Theme toggle
const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');

// Check for saved theme preference
const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'light') {
    document.body.classList.add('light-theme');
    themeIcon.classList.remove('fa-moon');
    themeIcon.classList.add('fa-sun');
}

themeToggle.addEventListener('click', function() {
    document.body.classList.toggle('light-theme');
    
    if (document.body.classList.contains('light-theme')) {
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
        localStorage.setItem('theme', 'light');
    } else {
        themeIcon.classList.remove('fa-sun');
        themeIcon.classList.add('fa-moon');
        localStorage.setItem('theme', 'dark');
    }
});
        
        // Form validation
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('newPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                if (newPassword !== '' && newPassword !== confirmPassword) {
                    e.preventDefault();
                    
                    // Show error notification
                    const notification = document.createElement('div');
                    notification.className = 'notification error';
                    notification.innerHTML = `
                        <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="notification-content">
                            <div class="notification-title">Error</div>
                            <div class="notification-message">New passwords do not match!</div>
                        </div>
                        <div class="notification-close"><i class="fas fa-times"></i></div>
                    `;
                    
                    const container = document.getElementById('notificationContainer');
                    container.appendChild(notification);
                    
                    // Auto-remove after 5 seconds
                    setTimeout(() => {
                        notification.remove();
                    }, 5000);
                    
                    // Make close button work
                    notification.querySelector('.notification-close').addEventListener('click', function() {
                        notification.remove();
                    });
                }
            });
        }
        
        // Make notification close buttons work
        document.querySelectorAll('.notification-close').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.notification').remove();
            });
        });
    });
    </script>
</body>
</html>