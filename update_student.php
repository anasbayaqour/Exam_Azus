<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}
require_once 'config.php';

$data = $_POST;
$stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, 
                       fillier = ?, group_column = ? WHERE user_id = ? AND status = 'accepted'");
$stmt->execute([
    $data['first_name'],
    $data['last_name'],
    $data['email'],
    $data['fillier'],
    $data['group_column'],
    $data['user_id']
]);

echo json_encode(['success' => $stmt->rowCount() > 0]);
?>