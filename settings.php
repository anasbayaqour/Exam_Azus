<?php
// settings.php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';
$user = [];
$notification_prefs = [
    'exam_notifications' => 0,
    'grade_notifications' => 0,
    'system_notifications' => 0
];

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Get user settings if they exist
    $stmt = $pdo->prepare("SELECT notification_prefs FROM user_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_settings && isset($user_settings['notification_prefs'])) {
        // Parse JSON notification preferences
        $saved_prefs = json_decode($user_settings['notification_prefs'], true);
        if (is_array($saved_prefs)) {
            $notification_prefs = array_merge($notification_prefs, $saved_prefs);
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle profile update
        if (isset($_POST['update_profile'])) {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            
            // Basic validation
            if (empty($first_name) || empty($last_name) || empty($email)) {
                throw new Exception("All fields are required");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            // Check if email is already taken by another user
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Email already in use by another account");
            }
            
            // Update user data
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$first_name, $last_name, $email, $_SESSION['user_id']]);
            
            // Update session variables
            $_SESSION['username'] = "$first_name $last_name";
            $_SESSION['email'] = $email;
            
            $success_message = "Profile updated successfully!";
        }
        
        // Handle password change
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password using password_verify
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            
            // Validate new password
            if (empty($new_password) || strlen($new_password) < 8) {
                throw new Exception("New password must be at least 8 characters long");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $success_message = "Password changed successfully!";
        }
        
        // Handle theme preference change
        if (isset($_POST['change_theme'])) {
            $theme = $_POST['theme'];
            $valid_themes = ['light', 'dark', 'system'];
            
            if (!in_array($theme, $valid_themes)) {
                throw new Exception("Invalid theme selection");
            }
            
            // Store theme preference in session
            $_SESSION['theme'] = $theme;
            
            // Check if we need to add a theme_preference column
            try {
                // First check if column exists
                $column_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'theme_preference'");
                $column_exists = ($column_check->rowCount() > 0);
                
                if (!$column_exists) {
                    // Add the column if it doesn't exist
                    $pdo->exec("ALTER TABLE users ADD COLUMN theme_preference VARCHAR(20) DEFAULT 'light'");
                }
                
                // Now update the preference
                $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE user_id = ?");
                $stmt->execute([$theme, $_SESSION['user_id']]);
            } catch (PDOException $e) {
                // If we can't modify the table, just save to session
                error_log("Couldn't save theme preference to database: " . $e->getMessage());
                // No need to throw exception, we already saved to session
            }
            
            $success_message = "Theme preference updated!";
        }
        
        // Handle notification preferences
        if (isset($_POST['update_notifications'])) {
            $exam_notifications = isset($_POST['exam_notifications']) ? 1 : 0;
            $grade_notifications = isset($_POST['grade_notifications']) ? 1 : 0;
            $system_notifications = isset($_POST['system_notifications']) ? 1 : 0;
            
            // Prepare notification preferences as JSON
            $notification_prefs = [
                'exam_notifications' => $exam_notifications,
                'grade_notifications' => $grade_notifications,
                'system_notifications' => $system_notifications
            ];
            
            $prefs_json = json_encode($notification_prefs);
            
            // Check if record already exists for this user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_settings WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->fetchColumn() > 0) {
                // Update existing preferences
                $stmt = $pdo->prepare("UPDATE user_settings SET notification_prefs = ? WHERE user_id = ?");
                $stmt->execute([$prefs_json, $_SESSION['user_id']]);
            } else {
                // Insert new preferences
                $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, notification_prefs) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $prefs_json]);
            }
            
            $success_message = "Notification preferences updated!";
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "A database error occurred. Please try again later.";
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_SESSION['theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ExamOnline</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            /* Light Theme */
            --primary-color: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
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
            --border-radius: 0.75rem;
            --border-radius-lg: 1rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            --transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --primary-color: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --primary-gradient: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
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
            min-height: 100vh;
            background-image: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.1) 0%, transparent 20%),
                              radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 20%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            animation: fadeInDown 0.6s both;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-block;
            position: relative;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 3px;
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.5s ease;
        }

        .page-title:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            animation: fadeIn 0.8s 0.2s both;
        }

        .settings-sidebar {
            background-color: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 2rem;
            transition: var(--transition-base);
            border: 1px solid var(--border-color);
        }

        .settings-sidebar:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1);
        }

        .settings-nav {
            list-style: none;
        }

        .settings-nav-item {
            margin-bottom: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .settings-nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition-base);
            position: relative;
            z-index: 1;
        }

        .settings-nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient);
            transform: scaleY(0);
            transform-origin: bottom;
            transition: transform 0.3s ease;
            border-radius: 5px 0 0 5px;
        }

        .settings-nav-link:hover, .settings-nav-link.active {
            background-color: var(--bg-tertiary);
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .settings-nav-link:hover::before, .settings-nav-link.active::before {
            transform: scaleY(1);
        }

        .settings-nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
            transition: var(--transition-base);
        }

        .settings-nav-link:hover i, .settings-nav-link.active i {
            transform: scale(1.2);
        }

        .settings-content {
            background-color: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 2rem;
            transition: var(--transition-base);
            border: 1px solid var(--border-color);
        }

        .settings-content:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1);
        }

        .settings-section {
            margin-bottom: 2.5rem;
            animation: fadeInUp 0.5s both;
        }

        .settings-section:nth-child(1) { animation-delay: 0.3s; }
        .settings-section:nth-child(2) { animation-delay: 0.4s; }
        .settings-section:nth-child(3) { animation-delay: 0.5s; }
        .settings-section:nth-child(4) { animation-delay: 0.6s; }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary-gradient);
            transition: width 0.5s ease;
        }

        .section-title:hover::after {
            width: 100px;
        }

        .section-title i {
            margin-right: 0.75rem;
            color: var(--primary-color);
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: var(--transition-base);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: var(--transition-base);
            font-size: 0.95rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            transform: translateY(-2px);
        }

        .form-control:hover {
            border-color: var(--primary-light);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition-base);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            background: var(--primary-gradient);
            z-index: -1;
            opacity: 0;
            transition: var(--transition-base);
        }

        .btn:hover::before {
            opacity: 1;
        }

        .btn i {
            margin-right: 0.5rem;
            transition: var(--transition-base);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3), 0 2px 4px -1px rgba(79, 70, 229, 0.1);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3), 0 4px 6px -2px rgba(79, 70, 229, 0.1);
        }

        .btn-primary:hover i {
            transform: translateX(3px);
        }

        .btn-secondary {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background-color: var(--border-color);
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            animation: fadeIn 0.5s, shake 0.5s;
            border-left: 4px solid transparent;
            box-shadow: var(--shadow);
            transition: var(--transition-base);
        }

        .alert:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .theme-options {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .theme-option {
            flex: 1;
            perspective: 1000px;
        }

        .theme-option input[type="radio"] {
            display: none;
        }

        .theme-option label {
            display: block;
            padding: 1.5rem 1rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            text-align: center;
            cursor: pointer;
            transition: var(--transition-base);
            transform-style: preserve-3d;
            position: relative;
            background-color: var(--bg-primary);
        }

        .theme-option label::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition-base);
            border-radius: var(--border-radius);
        }

        .theme-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
            transform: translateY(-5px) rotateX(10deg);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.3);
        }

        .theme-option input[type="radio"]:checked + label::before {
            opacity: 0.1;
        }

        .theme-option label:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .theme-option label i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            transition: var(--transition-base);
        }

        .theme-option input[type="radio"]:checked + label i {
            transform: scale(1.2);
        }

        .checkbox-group {
            margin-bottom: 1rem;
            position: relative;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            transition: var(--transition-base);
        }

        .checkbox-label:hover {
            background-color: var(--bg-tertiary);
            transform: translateX(5px);
        }

        .checkbox-input {
            margin-right: 0.75rem;
            width: 18px;
            height: 18px;
            appearance: none;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            outline: none;
            transition: var(--transition-base);
            position: relative;
            cursor: pointer;
        }

        .checkbox-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkbox-input:checked::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 0.7rem;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition-base);
        }

        .password-toggle-btn:hover {
            color: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
        }

        /* Floating animation for sidebar icons */
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        /* Custom animations */
        @keyframes gradientShift {
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

        /* Particle background effect */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: var(--primary-color);
            border-radius: 50%;
            opacity: 0.1;
            animation: floatParticle linear infinite;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(0) translateX(0);
                opacity: 0;
            }
            50% {
                opacity: 0.1;
            }
            100% {
                transform: translateY(-100vh) translateX(100px);
                opacity: 0;
            }
        }

        /* Glow effect for active elements */
        .glow {
            animation: glow 2s infinite alternate;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 5px rgba(99, 102, 241, 0.5);
            }
            to {
                box-shadow: 0 0 20px rgba(99, 102, 241, 0.8);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-sidebar {
                order: 2;
                position: static;
            }
            
            .settings-content {
                order: 1;
            }
            
            .theme-options {
                flex-direction: column;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <!-- Particle background -->
    <div class="particles" id="particles"></div>
    
    <div class="container">
        <div class="settings-header">
            <h1 class="page-title"><i class="fas fa-cog"></i> Settings</h1>
        </div>
        
        <?php if ($success_message): ?>
        <div class="alert alert-success animate__animated animate__bounceIn">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger animate__animated animate__shakeX">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <aside class="settings-sidebar">
                <ul class="settings-nav">
                    <li class="settings-nav-item">
                        <a href="#profile" class="settings-nav-link active">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#security" class="settings-nav-link">
                            <i class="fas fa-lock"></i> Security
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#appearance" class="settings-nav-link">
                            <i class="fas fa-palette"></i> Appearance
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#notifications" class="settings-nav-link">
                            <i class="fas fa-bell"></i> Notifications
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#privacy" class="settings-nav-link">
                            <i class="fas fa-shield-alt"></i> Privacy
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#dashboard" class="settings-nav-link">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </li>
                </ul>
            </aside>
            
            <main class="settings-content">
                <!-- Profile Section -->
                <section id="profile" class="settings-section">
                    <h2 class="section-title"><i class="fas fa-user-circle"></i> Profile Information</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </section>
                
                <!-- Security Section -->
                <section id="security" class="settings-section">
                    <h2 class="section-title"><i class="fas fa-shield-alt"></i> Security Settings</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="password-toggle">
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                                <button type="button" class="password-toggle-btn" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="password-toggle">
                                <input type="password" id="new_password" name="new_password" class="form-control" required>
                                <button type="button" class="password-toggle-btn" data-target="new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="password-toggle">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                <button type="button" class="password-toggle-btn" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </section>
                
                <!-- Appearance Section -->
                <section id="appearance" class="settings-section">
                    <h2 class="section-title"><i class="fas fa-paint-brush"></i> Appearance Settings</h2>
                    <form method="POST" action="">
                        <div class="theme-options">
                            <div class="theme-option">
                                <input type="radio" id="theme-light" name="theme" value="light" <?= (!isset($_SESSION['theme']) || $_SESSION['theme'] === 'light') ? 'checked' : '' ?>>
                                <label for="theme-light">
                                    <i class="fas fa-sun"></i>
                                    Light Mode
                                </label>
                            </div>
                            <div class="theme-option">
                                <input type="radio" id="theme-dark" name="theme" value="dark" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'dark') ? 'checked' : '' ?>>
                                <label for="theme-dark">
                                    <i class="fas fa-moon"></i>
                                    Dark Mode
                                </label>
                            </div>
                            <div class="theme-option">
                                <input type="radio" id="theme-system" name="theme" value="system" <?= (isset($_SESSION['theme']) && $_SESSION['theme'] === 'system') ? 'checked' : '' ?>>
                                <label for="theme-system">
                                    <i class="fas fa-laptop"></i>
                                    System Default
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="change_theme" class="btn btn-primary">
                                <i class="fas fa-palette"></i> Apply Theme
                            </button>
                        </div>
                    </form>
                </section>
                
                <!-- Notifications Section -->
                <section id="notifications" class="settings-section">
                    <h2 class="section-title"><i class="fas fa-bell"></i> Notification Preferences</h2>
                    <form method="POST" action="">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="exam_notifications" class="checkbox-input" <?= $notification_prefs['exam_notifications'] ? 'checked' : '' ?>>
                                Exam Notifications (Get notified about upcoming exams)
                            </label>
                        </div>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="grade_notifications" class="checkbox-input" <?= $notification_prefs['grade_notifications'] ? 'checked' : '' ?>>
                                Grade Notifications (Get notified when grades are published)
                            </label>
                        </div>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="system_notifications" class="checkbox-input" <?= $notification_prefs['system_notifications'] ? 'checked' : '' ?>>
                                System Notifications (Get notified about system updates and maintenance)
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="update_notifications" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </section>
                
                <!-- Privacy Section -->
                <section id="privacy" class="settings-section">
                    <h2 class="section-title"><i class="fas fa-user-shield"></i> Privacy Settings</h2>
                    <form method="POST" action="">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="profile_visibility" class="checkbox-input">
                                Make profile visible to other students
                            </label>
                        </div>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="show_grades" class="checkbox-input">
                                Show my grades in class rankings
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="update_privacy" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Privacy Settings
                            </button>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </div>
    
    <script>
        // Create particle background effect
        document.addEventListener('DOMContentLoaded', function() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size between 5px and 20px
                const size = Math.floor(Math.random() * 15) + 5;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                const posX = Math.floor(Math.random() * window.innerWidth);
                const posY = Math.floor(Math.random() * window.innerHeight);
                particle.style.left = `${posX}px`;
                particle.style.top = `${posY}px`;
                
                // Random animation duration between 20s and 40s
                const duration = Math.floor(Math.random() * 20) + 20;
                particle.style.animationDuration = `${duration}s`;
                
                // Random animation delay
                const delay = Math.floor(Math.random() * 10);
                particle.style.animationDelay = `${delay}s`;
                
                particlesContainer.appendChild(particle);
            }
            
            // Navigation links functionality
            const navLinks = document.querySelectorAll('.settings-nav-link');
            const sections = document.querySelectorAll('.settings-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Get the target section from the href attribute
                    const targetId = this.getAttribute('href').substring(1);
                    
                    // Hide all sections
                    sections.forEach(section => {
                        section.style.display = 'none';
                    });
                    
                    // Show the target section
                    document.getElementById(targetId).style.display = 'block';
                });
            });
            
            // Password toggle functionality
            const toggleButtons = document.querySelectorAll('.password-toggle-btn');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // Show only the first section by default
            sections.forEach((section, index) => {
                if (index > 0) {
                    section.style.display = 'none';
                }
            });
            
            // Back to dashboard link
            const dashboardLink = document.querySelector('a[href="#dashboard"]');
            dashboardLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'teacher.php';
            });
        });
    </script>
</body>
</html>