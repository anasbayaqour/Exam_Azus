<?php
session_start();

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

setcookie('remember_me', '', time() - 3600, '/');
setcookie('user_preferences', '', time() - 3600, '/');

try {
    require_once 'config.php';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Unknown';
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (action_type, description, user_id) 
        VALUES ('logout', 'User logged out', ?)
    ");
    $stmt->execute([$user_id]);
} catch (PDOException $e) {
    error_log("Logout activity logging failed: " . $e->getMessage());
}

header('Location: Login.php?msg=logout_success');
exit();
?>
