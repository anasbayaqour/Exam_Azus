<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the filiere_id based on the name selected from the dropdown
        $filiere_stmt = $pdo->prepare("SELECT id FROM filieres WHERE name = :filiere_name");
        $filiere_stmt->execute(['filiere_name' => $_POST['filiere']]);
        $filiere = $filiere_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$filiere) {
            throw new PDOException("Invalid filiere selected");
        }
        
        // Get the group_id based on the name selected from the dropdown
        $group_stmt = $pdo->prepare("SELECT id FROM student_groups WHERE name = :group_name");
        $group_stmt->execute(['group_name' => $_POST['group_column']]);
        $group = $group_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            throw new PDOException("Invalid group selected");
        }
        
        // Now insert the user with proper foreign keys
        $stmt = $pdo->prepare("
            INSERT INTO users (
                first_name, last_name, email, password, 
                user_type, filiere_id, group_id, status
            ) VALUES (
                :first_name, :last_name, :email, :password, 
                'student', :filiere_id, :group_id, 'pending'
            )
        ");
        
        $stmt->execute([
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'],
            'password' => $_POST['password'], // Consider hashing this password for security
            'filiere_id' => $filiere['id'],
            'group_id' => $group['id']
        ]);
        
        $_SESSION['success_message'] = "✨ Student successfully added!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "⚠️ Error: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch filieres for the dropdown
$filieres_query = $pdo->query("SELECT id, name FROM filieres ORDER BY name");
$filieres = $filieres_query->fetchAll(PDO::FETCH_ASSOC);

// Fetch groups for the dropdown
$groups_query = $pdo->query("SELECT id, name FROM student_groups ORDER BY name");
$groups = $groups_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(135deg, rgb(8, 43, 71) 0%, rgb(14, 13, 99) 100%);
            min-height: 100vh;
            color: #fff;
        }

        .custom-gradient {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(10px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            animation: fadeScale 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #fff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            animation: slideIn 0.5s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
        }

        .form-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            padding: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: fadeScale 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            color: #fff;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #fff;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.1);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.5rem;
            padding-right: 3rem;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.25rem 2.5rem;
            background: #fff;
            color: #6366f1;
            border: none;
            border-radius: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-routeur {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
            text-decoration: none; /* Remove link decoration */
        }

        .btn-routeur:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.2);
        }

        .btn-routeur:active {
            transform: translateY(0);
        }

        @keyframes fadeScale {
            0% { 
                opacity: 0;
                transform: scale(0.95);
            }
            100% { 
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-card {
                padding: 1.5rem;
            }

            .header h1 {
                font-size: 2.5rem;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .btn-routeur {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Add New Student</h1>
            <p>Enter student details to create a new account</p>
            <!-- Routeur Button -->
            <button onclick="window.location.href='teacher.php'" class="btn-routeur">
                <i class="fas fa-arrow-left"></i>
                Routeur
            </button>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               placeholder="Last Name" required>
                    </div>
                    <div class="form-group">
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="Email Address" required>
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <select class="form-control" id="filiere" name="filiere" required>
                            <option value="">Select Field of Study</option>
                            <?php foreach ($filieres as $filiere): ?>
                                <option value="<?php echo htmlspecialchars($filiere['name']); ?>">
                                    <?php echo htmlspecialchars($filiere['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select class="form-control" id="group_column" name="group_column" required>
                            <option value="">Select Group</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo htmlspecialchars($group['name']); ?>">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    Add Student
                </button>
            </form>
        </div>
    </div>
</body>
</html>