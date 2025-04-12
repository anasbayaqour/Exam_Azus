<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND status = 'accepted'");
$stmt->execute([$data['user_id']]);

echo json_encode(['success' => $stmt->rowCount() > 0]);
?>