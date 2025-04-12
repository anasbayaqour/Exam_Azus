<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Taken</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #6b85f1;
            --secondary: #3f37c9;
            --accent: #4cc9f0;
            --accent-light: #7fd7f4;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4caf50;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
            position: relative;
        }
        
        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 40px rgba(255, 255, 255, 0.2);
            animation: float 15s infinite linear;
            opacity: 0;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) scale(0.5);
                opacity: 0;
            }
            10% {
                opacity: 0.8;
            }
            100% {
                transform: translateY(-100px) scale(1.2);
                opacity: 0;
            }
        }
        
        .message-container {
            text-align: center;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(8px);
            max-width: 500px;
            width: 90%;
            animation: fadeInUp 0.8s ease-out both;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transform-style: preserve-3d;
            perspective: 1000px;
            position: relative;
            overflow: hidden;
        }
        
        .message-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom right,
                rgba(67, 97, 238, 0.05) 0%,
                rgba(76, 201, 240, 0.05) 50%,
                transparent 100%
            );
            transform: rotate(30deg);
            z-index: -1;
            animation: shine 8s infinite;
        }
        
        @keyframes shine {
            0% {
                transform: rotate(30deg) translate(-20%, -20%);
            }
            50% {
                transform: rotate(30deg) translate(20%, 20%);
            }
            100% {
                transform: rotate(30deg) translate(-20%, -20%);
            }
        }
        
        .icon-container {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
            animation: pulse 2s infinite ease-in-out, fadeInUp 0.8s ease-out both;
            color: white;
            font-size: 3rem;
        }
        
        h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 1rem;
            font-weight: 700;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }
        
        p {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        
        .btn-dashboard {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            padding: 0.85rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.4s ease;
            border: none;
            box-shadow: 0 4px 20px rgba(67, 97, 238, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
            z-index: 1;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }
        
        .btn-dashboard::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
            z-index: -1;
        }
        
        .btn-dashboard:hover {
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.5);
        }
        
        .btn-dashboard:hover::before {
            left: 100%;
        }
        
        .btn-dashboard i {
            transition: transform 0.3s ease;
        }
        
        .btn-dashboard:hover i {
            transform: translateX(5px);
        }
        
        /* Confetti effect */
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: var(--primary);
            opacity: 0;
            animation: confettiFall 5s linear forwards;
        }
        
        @keyframes confettiFall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 15px 35px rgba(67, 97, 238, 0.4);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
            }
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .message-container {
                padding: 2rem 1.5rem;
            }
            h1 {
                font-size: 1.8rem;
            }
            .icon-container {
                width: 80px;
                height: 80px;
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background animation -->
    <div class="background-animation" id="background"></div>
    
    <div class="message-container">
        <div class="icon-container">
            <i class="fas fa-check"></i>
        </div>
        <h1>Exam Submitted Successfully</h1>
        <p>Your answers have been recorded and submitted. You'll be able to view your results once they're available.</p>
        <a href="student.php" class="btn btn-dashboard">
        Take Your Result <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create floating bubbles
            const background = document.getElementById('background');
            const colors = ['rgba(67, 97, 238, 0.3)', 'rgba(76, 201, 240, 0.3)', 'rgba(63, 55, 201, 0.3)'];
            
            for (let i = 0; i < 20; i++) {
                createBubble();
            }
            
            function createBubble() {
                const bubble = document.createElement('div');
                bubble.classList.add('bubble');
                
                // Random properties
                const size = Math.random() * 100 + 50;
                const posX = Math.random() * 100;
                const duration = Math.random() * 15 + 10;
                const delay = Math.random() * 15;
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                bubble.style.cssText = `
                    width: ${size}px;
                    height: ${size}px;
                    left: ${posX}%;
                    background: ${color};
                    animation-duration: ${duration}s;
                    animation-delay: ${delay}s;
                `;
                
                background.appendChild(bubble);
            }
            
            // Create occasional confetti
            setInterval(() => {
                createConfetti();
            }, 300);
            
            function createConfetti() {
                const confetti = document.createElement('div');
                confetti.classList.add('confetti');
                
                // Random properties
                const colors = ['#4361ee', '#3f37c9', '#4cc9f0', '#f72585', '#7209b7'];
                const left = Math.random() * 100;
                const animationDuration = Math.random() * 3 + 2;
                const size = Math.random() * 10 + 5;
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                confetti.style.cssText = `
                    left: ${left}%;
                    background-color: ${color};
                    width: ${size}px;
                    height: ${size}px;
                    animation-duration: ${animationDuration}s;
                    border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
                `;
                
                document.body.appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => {
                    confetti.remove();
                }, animationDuration * 1000);
            }
            
            // Add 3D tilt effect
            const messageContainer = document.querySelector('.message-container');
            
            messageContainer.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
                messageContainer.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });
            
            messageContainer.addEventListener('mouseenter', () => {
                messageContainer.style.transition = 'transform 0.1s ease';
            });
            
            messageContainer.addEventListener('mouseleave', () => {
                messageContainer.style.transition = 'transform 0.5s ease';
                messageContainer.style.transform = 'rotateY(0deg) rotateX(0deg)';
            });
        });
    </script>
</body>
</html>