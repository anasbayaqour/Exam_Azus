<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once 'config.php';

// Handle user approval/rejection
if (isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $status = $_POST['status'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->execute([$status, $user_id]);
        $_SESSION['success_message'] = "✨ User status successfully updated!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "⚠️ Error: " . $e->getMessage();
    }
}

// Fetch statistics
$stats = [
    'pending_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
    'active_teachers' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'teacher' AND status = 'accepted'")->fetchColumn(),
    'active_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'student' AND status = 'accepted'")->fetchColumn(),
    'total_exams' => $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn()
];

// Fetch users with filters
$userType = isset($_GET['user_type']) ? $_GET['user_type'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

$query = "
    SELECT 
        u.*, 
        f.name AS filiere_name, 
        g.name AS group_name 
    FROM users u
    LEFT JOIN filieres f ON u.filiere_id = f.id
    LEFT JOIN student_groups g ON u.group_id = g.id
    WHERE 1=1
";
if ($userType !== 'all') $query .= " AND u.user_type = :user_type";
if ($status !== 'all') $query .= " AND u.status = :status";
$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
if ($userType !== 'all') $stmt->bindParam(':user_type', $userType);
if ($status !== 'all') $stmt->bindParam(':status', $status);
$stmt->execute();
$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="website icon" href="./Screenshot 2025-01-01 201746" />
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

body {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    min-height: 100vh;
    color: #fff;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.header {
    text-align: center;
    margin-bottom: 3rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    font-size: 3.5rem;
    font-weight: 800;
    background: linear-gradient(to right, #fff, #74b9ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: rgba(52, 152, 219, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    background: rgba(52, 152, 219, 0.3);
}

.filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.filter-select {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    cursor: pointer;
}

.filter-select option {
    background: #2c3e50;
    color: white;
}

.card {
    background: rgba(52, 152, 219, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 2rem;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

th {
    background: rgba(52, 152, 219, 0.2);
    font-weight: 600;
}

tr:hover {
    background: rgba(52, 152, 219, 0.1);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-pending {
    background: rgba(241, 196, 15, 0.2);
    color: #f1c40f;
}

.status-accepted {
    background: rgba(46, 204, 113, 0.2);
    color: #2ecc71;
}

.status-rejected {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    color: white;
}

.btn-accept {
    background: #2ecc71;
}

.btn-reject {
    background: #e74c3c;
}

.btn:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.alert-success {
    background: rgba(46, 204, 113, 0.2);
    color: #2ecc71;
    border: 1px solid rgba(46, 204, 113, 0.3);
}

.alert-error {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c;
    border: 1px solid rgba(231, 76, 60, 0.3);
}

.logout-btn {
    padding: 0.75rem 1.5rem;
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.logout-btn:hover {
    background: #c0392b;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }

    .header {
        flex-direction: column;
        gap: 1rem;
    }

    .header h1 {
        font-size: 2.5rem;
    }

    .filters {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <button onclick="window.location.href='logout.php'" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
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

        <div class="dashboard-grid">
            <div class="stat-card">
                <h3>Pending Users</h3>
                <div class="number"><?php echo $stats['pending_users']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Teachers</h3>
                <div class="number"><?php echo $stats['active_teachers']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Students</h3>
                <div class="number"><?php echo $stats['active_students']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Exams</h3>
                <div class="number"><?php echo $stats['total_exams']; ?></div>
            </div>
        </div>

        <div class="filters">
            <select class="filter-select" onchange="window.location.href='?user_type=' + this.value + '&status=<?php echo $status; ?>'">
                <option value="all" <?php echo $userType === 'all' ? 'selected' : ''; ?>>All Users</option>
                <option value="student" <?php echo $userType === 'student' ? 'selected' : ''; ?>>Students</option>
                <option value="teacher" <?php echo $userType === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
            </select>
            <select class="filter-select" onchange="window.location.href='?user_type=<?php echo $userType; ?>&status=' + this.value">
                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="accepted" <?php echo $status === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Field</th>
                            <th>Group</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst($user['user_type']); ?></td>
                                <td><?php echo $user['filiere_name'] ? htmlspecialchars($user['filiere_name']) : '-'; ?></td>
                                <td><?php echo $user['group_name'] ? htmlspecialchars($user['group_name']) : '-'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="status" value="accepted">
                                            <button type="submit" name="update_status" class="btn btn-accept">
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" name="update_status" class="btn btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="status" value="pending">
                                            <button type="submit" name="update_status" class="btn btn-accept">
                                                Reset to Pending
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>