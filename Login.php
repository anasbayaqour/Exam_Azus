<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Modified this part to handle both hashed and plain text passwords
        // For hashed passwords, use password_verify
        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            // Removed duplicate line
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            switch ($user['user_type']) {
                case 'admin':
                    header("Location: admin.php");
                    break;
                case 'teacher':
                    header("Location: teacher.php");
                    break;
                case 'student':
                    header("Location: student.php");
                    break;
            }
            exit();
        } else {
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamOnline</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
    <style>
        :root {
            --primary: #4f46e5;
            --secondary: #7c3aed;
            --accent: #06b6d4;
            --success: #10b981;
            --background: #0f172a;
            --text: #f8fafc;
            --card-bg: rgba(30, 41, 59, 0.7);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .animated-background {
            position: fixed;
            width: 100vw;
            height: 100vh;
            top: 0;
            left: 0;
            z-index: -1;
            background: linear-gradient(45deg, #0f172a, #1e293b);
            overflow: hidden;
        }

        .animated-background::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--primary) 0%, transparent 20%),
                        radial-gradient(circle at 80% 20%, var(--secondary) 0%, transparent 20%),
                        radial-gradient(circle at 20% 80%, var(--accent) 0%, transparent 20%);
            animation: backgroundAnimation 15s linear infinite;
            opacity: 0.1;
        }

        @keyframes backgroundAnimation {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 1.5rem 5%;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .logo i {
            color: var(--primary);
            transform: scale(1);
            transition: transform 0.3s ease;
        }

        .logo:hover i {
            transform: scale(1.2) rotate(360deg);
        }

        .main-content {
            padding-top: 100px;
            min-height: 100vh;
        }

        .hero {
            padding: 4rem 5%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 100px);
        }

        .hero-container {
            max-width: 1400px;
            width: 100%;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text {
            animation: slideInLeft 1s ease;
        }

        .hero-text h1 {
            font-size: 4rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, var(--text), var(--accent));
            -webkit-background-clip: text;
            color: transparent;
            animation: gradientText 3s linear infinite;
        }

        @keyframes gradientText {
            0% { filter: hue-rotate(0deg); }
            100% { filter: hue-rotate(360deg); }
        }

        .hero-text p {
            font-size: 1.2rem;
            color: #94a3b8;
            margin-bottom: 2rem;
            max-width: 600px;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeInUp 1s ease;
            transform: translateZ(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px) translateZ(0);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        }

        .login-card h2 {
            font-size: 1.8rem;
            margin-bottom: 2rem;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .login-card h2 i {
            color: var(--primary);
            animation: bounceIcon 2s infinite;
        }

        @keyframes bounceIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            background: rgba(255, 255, 255, 0.1);
        }

        .form-group i {
            position: absolute;
            right: 1rem;
            top: 2.7rem;
            color: #94a3b8;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: var(--text);
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .features {
            padding: 4rem 5%;
            background: rgba(30, 41, 59, 0.5);
        }

        .features-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .feature-card i {
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .feature-card:hover i {
            transform: scale(1.2);
        }

        .feature-card h3 {
            color: var(--text);
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #94a3b8;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-message {
            background: rgba(220, 38, 38, 0.1);
            color: #ef4444;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(220, 38, 38, 0.2);
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .hero-text h1 {
                font-size: 3rem;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .hero-text h1 {
                font-size: 2.5rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
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
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        .bt {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            color: rgb(238, 237, 241);
            background-color: #4f46e5;
            border: 2px solid rgb(252, 253, 255);
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: none;
        }

        .bt:hover {
            color: white;
            background-color: rgba(79, 44, 204, 0.67);
            border-color: rgb(202, 210, 219);
            transform: scale(1.05);
        }

        .bt:active {
            transform: scale(1);
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .footer {
            background: rgb(0, 1, 8);
            color: white;
            padding: 40px 20px;
            font-family: 'Poppins', sans-serif;
        }

        .footer-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            gap: 20px;
        }

        .footer-section {
            flex: 1;
            min-width: 200px;
            margin-bottom: 20px;
        }

        .footer-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #fff;
        }

        .footer-section p {
            font-size: 14px;
            line-height: 1.6;
            color: #ddd;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section ul li a {
            color: #ddd;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: #2575fc;
        }

        .footer-section ul li i {
            margin-right: 10px;
            color: #2575fc;
        }

        .social-icons {
            display: flex;
            gap: 10px;
        }

        .social-icon {
            color: #fff;
            background: #2575fc;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .social-icon:hover {
            background: #1a5bbf;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 14px;
            color: #ddd;
        }

        @media (max-width: 768px) {
            .footer-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .footer-section {
                margin-bottom: 30px;
            }

            .social-icons {
                justify-content: center;
            }
        }
        
        /* Style for error messages */
        .error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .theme-toggle-btn {
    background: transparent;
    color: var(--text);
    border: 1px solid var(--text);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-right: 1rem;
    transition: all 0.3s ease;
}

.theme-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}


        
    </style>
</head>
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
                <a href="#features" class="btn">Get Started</a>
            </div>

            
        </div>
    </nav>

    <main class="main-content">
        <section class="hero">
            <div class="hero-container">
                <div class="hero-text">
                    <h1 id="features">Master Your Future with Online Exams</h1>
                    <p>Experience the next generation of online examination. Advanced features, real-time results, and a seamless learning experience designed for modern education.</p>
                </div>

                <div class="login-card">
                    <h2><i class="fas fa-user-graduate"></i> Login</h2>
                    <?php if (!empty($error)): ?>
                        <div class="error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                            <i class="fas fa-lock"></i>
                        </div>
                        <button type="submit" class="btn">
                            Sign In <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                    <div style="text-align: center; margin-top: 10px;">
                        <a href="register.php" class="bt">Create Account</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="features" id="">
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-clock"></i>
                    <h3>Smart Timing</h3>
                    <p>Advanced timing system with auto-submission and flexible duration settings.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure Platform</h3>
                    <p>Enhanced security measures to maintain examination integrity.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Real-time Analytics</h3>
                    <p>Instant results and detailed performance analysis.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Mobile Ready</h3>
                    <p>Access your exams on any device, anywhere, anytime.</p>
                </div>
                <div class="feature-card">
                    <i class="fa-solid fa-gears" style="color: #0fb6cc;"></i>
                    <h3>Entre faste</h3>
                    <p>Easily access your exams anytime, on any device, wherever life takes you.</p>
                </div>
                <div class="feature-card">
                    <i class="fa-solid fa-magnifying-glass" style="color: #0fb6cc;"></i>
                    <h3>Learn Anywhere</h3>
                    <p>Access your exams effortlessly on any device.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h3>About ExamOnline</h3>
                <p>ExamOnline is a cutting-edge platform designed to revolutionize online examinations. We provide advanced features, real-time analytics, and a seamless experience for both students and teachers.</p>
            </div>
            <div class="footer-section">
                <h3>Contact Us</h3>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> imouzzer kander , hay alla ouichou</li>
                    <li><i class="fas fa-phone"></i> 0635363958</li>
                    <li><i class="fas fa-envelope"></i> anasbayaqour@gmail.com</li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Follow Us</h3>
                <div class="social-icons">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2023 ExamOnline. All rights reserved.</p>
        </div>
    </footer>

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

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        const background = document.querySelector('.animated-background');
        document.addEventListener('mousemove', function(e) {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            background.style.transform = `translate(${x * 10}px, ${y * 10}px)`;
        });
      
    </script>
</body>
</html>