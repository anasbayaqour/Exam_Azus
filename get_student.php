<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}
require_once 'config.php';

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, fillier, group_column 
                       FROM users WHERE user_id = ? AND status = 'accepted'");
$stmt->execute([$id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    echo json_encode($student);
} else {
    echo json_encode(['error' => 'Student not found']);
}
?>