<?php
// change_exam_status.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

$examId = $_GET['id'] ?? 0;
$newStatus = $_POST['status'] ?? '';

if (!in_array($newStatus, ['draft', 'published', 'archived'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE exams SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $examId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>