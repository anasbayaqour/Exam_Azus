<?php
// profile.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$user = null;
$error_message = '';
$success_message = '';

try {
    // Fetch user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error_message = "User not found in the database.";
        error_log("User not found for user_id: " . $user_id);
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        $errors = [];
        
        // Validate email
        if (empty($email)) {
            $errors[] = "Email address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        // Password validation - only check if user wants to change password
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors[] = "Current password is required to set a new password.";
            } else {
                // Check if password is hashed in database
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    // If password is stored in plain text (for migration purposes)
                    if ($current_password === $user['password']) {
                        // Migrate to hashed password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    } else {
                        $errors[] = "Current password is incorrect.";
                    }
                } elseif (!password_verify($current_password, $user['password'])) {
                    $errors[] = "Current password is incorrect.";
                }
            }
            
            // Check password match
            if ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            }
            
            // Check password strength
            if (strlen($new_password) < 8) {
                $errors[] = "Password must be at least 8 characters long.";
            }
        }
        
        if (empty($errors)) {
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Update user data
                if (!empty($new_password)) {
                    // If we're changing password and it was plain text before
                    if (!isset($hashed_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                    $stmt = $pdo->prepare("UPDATE users SET first_name = :name, email = :email, password = :password, updated_at = NOW() WHERE user_id = :user_id");
                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'password' => $hashed_password,
                        'user_id' => $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET first_name = :name, email = :email, updated_at = NOW() WHERE user_id = :user_id");
                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'user_id' => $user_id
                    ]);
                }
                
                // Commit transaction
                $pdo->commit();
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $success_message = "Your profile has been updated successfully.";
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Profile update error: " . $e->getMessage());
                $error_message = "A system error occurred. Please try again later.";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "A system error occurred. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - ExamOnline</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            /* Base Colors */
            --primary: #4F46E5;
            --primary-light: #6366F1;
            --primary-dark: #4338CA;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
            
            /* Neutral Colors */
            --bg-dark: #0F172A;
            --bg-card: #1E293B;
            --card-hover: #243552;
            --border: #334155;
            --border-light: #475569;
            
            /* Text Colors */
            --text-primary: #F8FAFC;
            --text-secondary: #94A3B8;
            --text-tertiary: #64748B;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            /* Animations */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: 300ms cubic-bezier(0.68, -0.55, 0.27, 1.55);
            
            /* Spacing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
            
            /* Border Radius */
            --radius-sm: 0.25rem;
            --radius: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-full: 9999px;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html, body {
            height: 100%;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: var(--text-primary);
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(79, 70, 229, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 75% 75%, rgba(16, 185, 129, 0.1) 0%, transparent 40%);
            background-attachment: fixed;
        }
        
        .app-container {
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .content-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-xl) var(--spacing-md);
        }
        
        .container {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .card {
            background-color: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid var(--border);
            position: relative;
            transition: transform var(--transition), box-shadow var(--transition);
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg), 0 20px 25px -5px rgba(79, 70, 229, 0.1);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--info) 50%, var(--success) 100%);
        }
        
        .card-header {
            padding: var(--spacing-xl);
            border-bottom: 1px solid var(--border);
            background-color: rgba(255, 255, 255, 0.02);
            position: relative;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(99, 102, 241, 0.3) 25%, 
                rgba(99, 102, 241, 0.6) 50%, 
                rgba(99, 102, 241, 0.3) 75%, 
                transparent 100%);
        }
        
        .card-body {
            padding: var(--spacing-xl);
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
            letter-spacing: -0.025em;
            display: flex;
            align-items: center;
        }
        
        .page-title-icon {
            margin-right: var(--spacing-md);
            color: var(--primary-light);
            background: rgba(99, 102, 241, 0.1);
            border-radius: var(--radius-full);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 400;
            margin-left: calc(40px + var(--spacing-md));
        }
        
        /* Form Styles */
        .form-section {
            margin-bottom: var(--spacing-xl);
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        
        .form-section:nth-child(1) {
            animation-delay: 0.1s;
        }
        
        .form-section:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
        }
        
        .section-title-icon {
            margin-right: var(--spacing-md);
            color: var(--primary-light);
            font-size: 1.25rem;
        }
        
        .form-grid {
            display: grid;
            gap: var(--spacing-lg);
        }
        
        .form-group {
            margin-bottom: var(--spacing-lg);
            position: relative;
            overflow: hidden;
        }
        
        .form-label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.875rem;
            transition: color var(--transition-fast);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            padding-left: 2.5rem;
            font-size: 1rem;
            line-height: 1.5;
            color: var(--text-primary);
            background-color: rgba(255, 255, 255, 0.05);
            background-clip: padding-box;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
        }
        
        .form-control:focus {
            border-color: var(--primary-light);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
        }
        
        .form-control::placeholder {
            color: var(--text-tertiary);
            opacity: 0.7;
        }
        
        .form-text {
            display: block;
            margin-top: var(--spacing-xs);
            font-size: 0.75rem;
            color: var(--text-tertiary);
            transition: color var(--transition-fast);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            transition: all var(--transition-fast);
        }
        
        .form-control:focus + .input-icon,
        .form-control:not(:placeholder-shown) + .input-icon {
            color: var(--primary-light);
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
            transition: all var(--transition-fast);
            z-index: 2;
        }
        
        .password-toggle:focus {
            outline: none;
            color: var(--primary-light);
        }
        
        .password-toggle:hover {
            color: var(--primary-light);
            transform: translateY(-50%) scale(1.1);
        }
        
        /* Alert Styles */
        .alert {
            padding: var(--spacing-md) var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            border-radius: var(--radius);
            border-left: 4px solid transparent;
            display: flex;
            align-items: flex-start;
            animation: slideInDown 0.5s ease-out forwards;
            transform-origin: top center;
            box-shadow: var(--shadow-md);
        }
        
        .alert-icon {
            margin-right: var(--spacing-md);
            font-size: 1.25rem;
            line-height: 1.5;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-left-color: var(--success);
            color: #34D399;
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-left-color: var(--danger);
            color: #F87171;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            border: 1px solid transparent;
            border-radius: var(--radius);
            transition: all var(--transition-bounce);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            width: 100px;
            height: 100px;
            margin-top: -50px;
            margin-left: -50px;
            top: 50%;
            left: 50%;
            transform: scale(0);
            transition: transform 0.5s;
            z-index: -1;
        }
        
        .btn:active::after {
            transform: scale(3);
        }
        
        .btn-primary {
            color: #fff;
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.5);
        }
        
        .btn-primary:active {
            transform: translateY(1px);
        }
        
        .btn-lg {
            padding: 0.875rem 1.75rem;
            font-size: 1rem;
        }
        
        .btn-block {
            display: flex;
            width: 100%;
        }
        
        .btn-icon {
            margin-right: var(--spacing-sm);
            transition: transform var(--transition);
        }
        
        .btn:hover .btn-icon {
            transform: translateX(-2px) scale(1.1);
        }
        
        /* Navigation */
        .nav-bar {
            padding: var(--spacing-md) var(--spacing-lg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow);
        }
        
        .nav-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all var(--transition-fast);
        }
        
        .nav-brand:hover {
            color: var(--primary-light);
            transform: scale(1.05);
        }
        
        .nav-brand-icon {
            margin-right: var(--spacing-sm);
            color: var(--primary-light);
        }
        
        .nav-items {
            display: flex;
            gap: var(--spacing-md);
        }
        
        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius);
            transition: all var(--transition);
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover {
            color: var(--text-primary);
            background-color: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .nav-link-icon {
            margin-right: var(--spacing-sm);
            transition: transform var(--transition);
        }
        
        .nav-link:hover .nav-link-icon {
            transform: translateX(-2px);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-secondary);
            text-decoration: none;
            margin-top: var(--spacing-lg);
            transition: all var(--transition);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius);
        }
        
        .back-link:hover {
            color: var(--text-primary);
            background-color: rgba(255, 255, 255, 0.05);
            transform: translateX(-4px);
        }
        
        .back-link-icon {
            margin-right: var(--spacing-sm);
            transition: transform var(--transition);
        }
        
        .back-link:hover .back-link-icon {
            transform: translateX(-4px);
        }
        
        /* Footer */
        .footer {
            padding: var(--spacing-lg);
            text-align: center;
            color: var(--text-tertiary);
            font-size: 0.875rem;
            background-color: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(5px);
            border-top: 1px solid var(--border);
        }
        
        .footer-heart {
            color: var(--danger);
            display: inline-block;
            animation: heartBeat 1.5s infinite;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-header, .card-body {
                padding: var(--spacing-lg);
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .page-subtitle {
                margin-left: 0;
                margin-top: var(--spacing-xs);
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes heartBeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.3); }
            28% { transform: scale(1); }
            42% { transform: scale(1.3); }
            70% { transform: scale(1); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .animate-pulse {
            animation: pulse 2s infinite;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .slide-in-left {
            animation: slideInLeft 0.5s ease-out forwards;
        }
        
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        /* Input focus effects */
        .input-focus-effect {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary);
            transition: width var(--transition);
        }
        
        .form-control:focus ~ .input-focus-effect {
            width: 100%;
        }
        
        /* Form validation styles */
        .form-control.is-valid {
            border-color: var(--success);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%2310B981' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23EF4444' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23EF4444' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="nav-bar">
            <a href="teacher.php" class="nav-brand">
                <i class="fas fa-graduation-cap nav-brand-icon"></i>
                ExamOnline
            </a>
            <div class="nav-items">
                <a href="teacher.php" class="nav-link">
                    <i class="fas fa-tachometer-alt nav-link-icon"></i> Dashboard
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt nav-link-icon"></i> Logout
                </a>
            </div>
        </nav>
        
        <div class="content-wrapper">
            <div class="container fade-in">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success animate_animated animate_fadeInDown">
                        <div class="alert-icon">
                            <i class="fas fa-check-circle fa-bounce"></i>
                        </div>
                        <div class="alert-content">
                            <?php echo $success_message; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger animate_animated animate_fadeInDown">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-circle fa-shake"></i>
                        </div>
                        <div class="alert-content">
                            <?php echo $error_message; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h1 class="page-title">
                            <div class="page-title-icon animate_animated animatepulse animate_infinite">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            Profile Settings
                        </h1>
                        <p class="page-subtitle">Manage your account details and preferences</p>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($user): ?>
                            <form method="POST" action="profile.php" id="profileForm">
                                <div class="form-section">
                                    <h2 class="section-title">
                                        <i class="fas fa-id-card section-title-icon"></i>
                                        Personal Information
                                    </h2>
                                    <div class="form-group">
                                        <label for="name" class="form-label">Full Name</label>
                                        <div class="input-group">
                                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" placeholder="Enter your full name" required>
                                            <i class="fas fa-user input-icon"></i>
                                            <div class="input-focus-effect"></div>
                                        </div>
                                        <small class="form-text">
                                            This name will be displayed on your profile and communications
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Enter your email address" required>
                                            <i class="fas fa-envelope input-icon"></i>
                                            <div class="input-focus-effect"></div>
                                        </div>
                                        <small class="form-text">
                                            We'll never share your email with anyone else
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h2 class="section-title">
                                        <i class="fas fa-lock section-title-icon"></i>
                                        Change Password
                                    </h2>
                                    <div class="form-group">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter your current password">
                                            <i class="fas fa-key input-icon"></i>
                                            <button type="button" class="password-toggle" data-target="current_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="input-focus-effect"></div>
                                        </div>
                                        <small class="form-text">
                                            Required only if you want to change your password
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter your new password">
                                            <i class="fas fa-lock input-icon"></i>
                                            <button type="button" class="password-toggle" data-target="new_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="input-focus-effect"></div>
                                        </div>
                                        <small class="form-text">
                                            Must be at least 8 characters long
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your new password">
                                            <i class="fas fa-lock input-icon"></i>
                                            <button type="button" class="password-toggle" data-target="confirm_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="input-focus-effect"></div>
                                        </div>
                                        <small class="form-text">
                                            Please re-enter your new password
                                        </small>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg btn-block">
                                    <i class="fas fa-save btn-icon"></i>
                                    Save Changes
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <div class="alert-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="alert-content">
                                    User information could not be loaded. Please try logging in again.
                                </div>
                            </div>
                            <a href="login.php" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt btn-icon"></i>
                                Back to Login
                            </a>
                        <?php endif; ?>
                        
                        <a href="teacher.php" class="back-link">
                            <i class="fas fa-arrow-left back-link-icon"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="footer">
            <p>© <?php echo date('Y'); ?> ExamOnline. All rights reserved. Created with <span class="footer-heart">&hearts;</span> for education.</p>
        </footer>
    </div>
    
    <script>
        // Password toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.password-toggle');
            
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
                    
                    // Focus the input after toggling
                    passwordInput.focus();
                });
            });
            
            // Form validation
            const profileForm = document.getElementById('profileForm');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const currentPasswordInput = document.getElementById('current_password');
            
            profileForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous validation states
                newPasswordInput.classList.remove('is-valid', 'is-invalid');
                confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
                currentPasswordInput.classList.remove('is-valid', 'is-invalid');
                
                // Validate passwords only if the user is trying to change it
                if (newPasswordInput.value || confirmPasswordInput.value) {
                    // Check if current password is provided
                    if (!currentPasswordInput.value) {
                        currentPasswordInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        currentPasswordInput.classList.add('is-valid');
                    }
                    
                    // Check new password length
                    if (newPasswordInput.value.length < 8) {
                        newPasswordInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        newPasswordInput.classList.add('is-valid');
                    }
                    
                    // Check if passwords match
                    if (newPasswordInput.value !== confirmPasswordInput.value) {
                        confirmPasswordInput.classList.add('is-invalid');
                        isValid = false;
                    } else if (confirmPasswordInput.value) {
                        confirmPasswordInput.classList.add('is-valid');
                    }
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
            
            // Input focus animation
            const formControls = document.querySelectorAll('.form-control');
            
            formControls.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('input-focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('input-focused');
                });
                
                // Initialize state for pre-filled inputs
                if (input.value) {
                    input.classList.add('has-value');
                }
                
                input.addEventListener('input', function() {
                    if (this.value) {
                        this.classList.add('has-value');
                    } else {
                        this.classList.remove('has-value');
                    }
                });
            });
            
            // Alert auto-close
            const alerts = document.querySelectorAll('.alert');
            
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('animate__fadeOutUp');
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>